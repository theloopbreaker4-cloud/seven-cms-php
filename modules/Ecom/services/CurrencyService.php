<?php

defined('_SEVEN') or die('No direct script access allowed');

/**
 * CurrencyService — multi-currency support for Ecom.
 *
 * Conversion direction: prices are stored in the shop's base currency
 * (`ecom.currency` setting / `ecom_currencies.is_base = 1`). Display +
 * checkout convert to the active currency at render time using the most
 * recent rate in `ecom_fx_rates`.
 *
 * Active currency resolution at request time:
 *   1. `?currency=XXX` query string (and we set a cookie)
 *   2. `seven_currency` cookie
 *   3. logged-in customer's `preferred_currency`
 *   4. shop default (base)
 */
class CurrencyService
{
    private const COOKIE = 'seven_currency';

    public static function isEnabled(): bool
    {
        return Setting::get('ecom.multi_currency_enabled', '0') === '1';
    }

    public static function base(): string
    {
        $row = DB::findOne('ecom_currencies', ' is_base = 1 ');
        if ($row) return strtoupper($row['code']);
        return strtoupper((string)Setting::get('ecom.currency', 'USD'));
    }

    /** @return array<int,array{code:string,label:?string,is_base:int}> */
    public static function enabled(): array
    {
        return DB::getAll('SELECT code, label, is_base FROM ecom_currencies WHERE is_enabled = 1 ORDER BY is_base DESC, code ASC') ?: [];
    }

    /** Currently active currency for this request. Always returns a valid code. */
    public static function active(): string
    {
        if (!self::isEnabled()) return self::base();

        $allowed = array_map(fn($r) => $r['code'], self::enabled());
        if (!$allowed) return self::base();

        $pick = $_GET['currency'] ?? $_COOKIE[self::COOKIE] ?? null;
        if (is_string($pick) && in_array(strtoupper($pick), $allowed, true)) {
            $pick = strtoupper($pick);
            // Persist for 30 days
            if (!headers_sent()) {
                @setcookie(self::COOKIE, $pick, [
                    'expires' => time() + 86400 * 30,
                    'path'    => '/',
                    'samesite'=> 'Lax',
                ]);
            }
            return $pick;
        }
        return self::base();
    }

    /**
     * Convert a minor-unit amount from `$from` to `$to`.
     * Returns the converted amount in `$to` minor units (rounded).
     */
    public static function convert(int $minor, string $from, string $to): int
    {
        $from = strtoupper($from);
        $to   = strtoupper($to);
        if ($from === $to) return $minor;

        $rate = self::rate($from, $to);
        if ($rate === null) return $minor; // graceful fallback — no conversion

        $fromDecimals = Money::decimals($from);
        $toDecimals   = Money::decimals($to);

        $major = $minor / (10 ** $fromDecimals);
        $converted = $major * $rate;
        return (int)round($converted * (10 ** $toDecimals));
    }

    /** Format a base-currency amount in the active currency. */
    public static function display(int $minorBase, string $locale = 'en'): string
    {
        $base = self::base();
        $act  = self::active();
        if ($base === $act) return Money::format($minorBase, $base, $locale);
        $converted = self::convert($minorBase, $base, $act);
        return Money::format($converted, $act, $locale);
    }

    /**
     * Latest rate from $from to $to. Tries direct, then via base, then inverse.
     * Returns null if no usable rate exists.
     */
    public static function rate(string $from, string $to): ?float
    {
        $direct = DB::getCell(
            'SELECT rate FROM ecom_fx_rates WHERE base_code = :f AND quote_code = :t ORDER BY fetched_at DESC LIMIT 1',
            [':f' => $from, ':t' => $to]
        );
        if ($direct !== false && $direct !== null) return (float)$direct;

        // Try via base.
        $base = self::base();
        if ($from !== $base && $to !== $base) {
            $r1 = DB::getCell(
                'SELECT rate FROM ecom_fx_rates WHERE base_code = :f AND quote_code = :b ORDER BY fetched_at DESC LIMIT 1',
                [':f' => $from, ':b' => $base]
            );
            $r2 = DB::getCell(
                'SELECT rate FROM ecom_fx_rates WHERE base_code = :b AND quote_code = :t ORDER BY fetched_at DESC LIMIT 1',
                [':b' => $base, ':t' => $to]
            );
            if ($r1 && $r2) return (float)$r1 * (float)$r2;
        }

        // Inverse.
        $inv = DB::getCell(
            'SELECT rate FROM ecom_fx_rates WHERE base_code = :t AND quote_code = :f ORDER BY fetched_at DESC LIMIT 1',
            [':t' => $to, ':f' => $from]
        );
        if ($inv !== false && $inv !== null && (float)$inv > 0) return 1.0 / (float)$inv;

        return null;
    }

    /** Manually set a rate. Source = "manual" — overrides automatic refresh. */
    public static function setRate(string $from, string $to, float $rate): void
    {
        DB::execute(
            'INSERT INTO ecom_fx_rates (base_code, quote_code, rate, source, fetched_at)
             VALUES (:f, :t, :r, "manual", NOW())',
            [':f' => strtoupper($from), ':t' => strtoupper($to), ':r' => $rate]
        );
    }

    /**
     * Daily refresh from a free public source (exchangerate.host).
     * Provider can be replaced by binding 'ecom.fx.provider' in the container.
     * Called by the cron job `ecom.fx.refresh`.
     */
    public static function refreshRates(): void
    {
        $base   = self::base();
        $codes  = array_map(fn($r) => $r['code'], self::enabled());
        if (count($codes) <= 1) return;

        $quotes = array_filter($codes, fn($c) => $c !== $base);
        if (!$quotes) return;

        // Allow plugin override
        if (class_exists('Container') && Container::has('ecom.fx.provider')) {
            $svc = Container::get('ecom.fx.provider');
            if (is_object($svc) && method_exists($svc, 'fetch')) {
                $rates = $svc->fetch($base, $quotes);
                if (is_array($rates)) self::storeRates($base, $rates, get_class($svc));
                return;
            }
        }

        // Default: exchangerate.host (no API key required).
        $url = 'https://api.exchangerate.host/latest?base=' . urlencode($base) . '&symbols=' . urlencode(implode(',', $quotes));
        $ctx = stream_context_create(['http' => ['timeout' => 8]]);
        $body = @file_get_contents($url, false, $ctx);
        if (!$body) {
            Logger::channel('ecom')->warning('FX fetch failed', ['url' => $url]);
            return;
        }
        $data = json_decode($body, true);
        if (!is_array($data) || empty($data['rates'])) return;

        self::storeRates($base, $data['rates'], 'exchangerate.host');
    }

    private static function storeRates(string $base, array $rates, string $source): void
    {
        $now = date('Y-m-d H:i:s');
        foreach ($rates as $code => $rate) {
            if (!is_numeric($rate) || (float)$rate <= 0) continue;
            DB::execute(
                'INSERT INTO ecom_fx_rates (base_code, quote_code, rate, source, fetched_at)
                 VALUES (:b, :q, :r, :s, :t)',
                [':b' => strtoupper($base), ':q' => strtoupper($code), ':r' => (float)$rate, ':s' => $source, ':t' => $now]
            );
        }
        Logger::channel('ecom')->info('FX rates updated', ['source' => $source, 'count' => count($rates)]);
    }
}
