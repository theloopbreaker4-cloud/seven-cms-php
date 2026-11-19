<?php
/** SevenCMS — github.com/theloopbreaker4-cloud/seven-cms-php */

defined('_SEVEN') or die('No direct script access allowed');

/**
 * CustomTheme — palettes created in the admin, no build step needed.
 *
 * A theme stores three colours per mode (primary, bg, text); the full set of
 * CSS variables is derived from them and emitted as an inline <style> for
 * [data-palette="custom-<slug>"]. Both light and dark modes are mandatory.
 * Stored as JSON in the `custom_themes` setting.
 */
class CustomTheme
{
    public const PREFIX = 'custom-';
    private const SETTING = 'custom_themes';

    /** @return array<string, array{name:string, light:array, dark:array}> keyed by palette slug */
    public static function all(): array
    {
        $raw = Setting::get(self::SETTING);
        $all = is_array($raw) ? $raw : (json_decode((string)$raw, true) ?: []);
        return array_filter($all, fn($t) => is_array($t) && isset($t['light'], $t['dark']));
    }

    public static function exists(string $slug): bool
    {
        return isset(self::all()[$slug]);
    }

    /**
     * Validate and save. Returns [slug, null] or [null, error].
     * @param array{primary:string,bg:string,text:string} $light
     * @param array{primary:string,bg:string,text:string} $dark
     */
    public static function save(string $name, array $light, array $dark): array
    {
        $name = trim(strip_tags($name));
        if ($name === '' || mb_strlen($name) > 40) return [null, 'Name is required (max 40 characters).'];

        foreach (['light' => $light, 'dark' => $dark] as $mode => $c) {
            foreach (['primary', 'bg', 'text'] as $k) {
                if (!preg_match('/^#[0-9a-f]{6}$/i', (string)($c[$k] ?? ''))) return [null, "Pick a valid {$mode} {$k} colour."];
            }
            if (self::contrast($c['text'], $c['bg']) < 4.5) {
                return [null, ucfirst($mode) . ' mode: text on background is too low-contrast (needs 4.5:1).'];
            }
        }

        $base = strtolower(trim((string)preg_replace('/[^a-z0-9]+/i', '-', $name), '-')) ?: 'theme';
        $slug = self::PREFIX . $base;
        $all  = self::all();
        for ($i = 2; isset($all[$slug]) && $all[$slug]['name'] !== $name; $i++) $slug = self::PREFIX . $base . '-' . $i;

        $pick = fn(array $c) => ['primary' => strtolower($c['primary']), 'bg' => strtolower($c['bg']), 'text' => strtolower($c['text'])];
        $all[$slug] = ['name' => $name, 'light' => $pick($light), 'dark' => $pick($dark)];
        Setting::set(self::SETTING, $all);
        return [$slug, null];
    }

    public static function delete(string $slug): void
    {
        $all = self::all();
        unset($all[$slug]);
        Setting::set(self::SETTING, $all);
        if (Setting::get('theme_palette') === $slug) Setting::set('theme_palette', 'default');
    }

    /** CSS for the given palettes (all custom ones when $only is null). */
    public static function css(?array $only = null): string
    {
        $css = '';
        foreach (self::all() as $slug => $t) {
            if ($only !== null && !in_array($slug, $only, true)) continue;
            $sel = '[data-palette="' . $slug . '"]';
            $css .= $sel . ',' . $sel . '[data-theme="light"]{' . self::vars($t['light'], false) . '}';
            $css .= $sel . '[data-theme="dark"]{' . self::vars($t['dark'], true) . '}';
        }
        return $css;
    }

    private static function vars(array $c, bool $dark): string
    {
        [$p, $bg, $tx] = [$c['primary'], $c['bg'], $c['text']];
        $hover = $dark ? self::mix($p, '#ffffff', 0.15) : self::mix($p, '#000000', 0.15);
        $second = self::mix($p, $tx, 0.35);
        $v = [
            '--bg-primary'     => $bg,
            '--bg-secondary'   => self::mix($bg, $tx, 0.04),
            '--bg-tertiary'    => self::mix($bg, $tx, 0.08),
            '--text-primary'   => $tx,
            '--text-secondary' => self::mix($tx, $bg, 0.25),
            '--text-tertiary'  => self::mix($tx, $bg, 0.45),
            '--border-color'   => self::mix($bg, $tx, 0.16),
            '--primary'        => $p,
            '--primary-hover'  => $hover,
            '--secondary'      => $second,
            '--secondary-hover'=> $dark ? self::mix($second, '#ffffff', 0.15) : self::mix($second, '#000000', 0.15),
            '--primary-gradient'       => "linear-gradient(135deg, {$p} 0%, {$second} 100%)",
            '--primary-gradient-hover' => "linear-gradient(135deg, {$hover} 0%, {$second} 100%)",
            '--sidebar-bg'     => self::mix($bg, $tx, 0.05),
            '--sidebar-border' => self::mix($bg, $tx, 0.14),
            '--header-bg'      => self::mix($bg, $tx, 0.03),
            '--success' => '#16a34a', '--warning' => '#d97706', '--danger' => '#dc2626', '--info' => '#0284c7',
            '--glow-a' => self::rgba($p, 0.14), '--glow-b' => self::rgba($second, 0.10),
            '--orb-a'  => self::rgba($p, 0.16), '--orb-b'  => self::rgba($second, 0.11),
        ];
        $out = '';
        foreach ($v as $k => $val) $out .= $k . ':' . $val . ';';
        return $out;
    }

    private static function rgb(string $hex): array
    {
        return array_map('hexdec', str_split(ltrim($hex, '#'), 2));
    }

    /** $a mixed toward $b by $t (0..1). */
    private static function mix(string $a, string $b, float $t): string
    {
        [$r1, $g1, $b1] = self::rgb($a);
        [$r2, $g2, $b2] = self::rgb($b);
        return sprintf('#%02x%02x%02x', round($r1 + ($r2 - $r1) * $t), round($g1 + ($g2 - $g1) * $t), round($b1 + ($b2 - $b1) * $t));
    }

    private static function rgba(string $hex, float $alpha): string
    {
        [$r, $g, $b] = self::rgb($hex);
        return "rgba({$r},{$g},{$b},{$alpha})";
    }

    /** WCAG 2.x contrast ratio. */
    public static function contrast(string $a, string $b): float
    {
        $lum = function (string $hex): float {
            $c = array_map(function ($v) {
                $v /= 255;
                return $v <= 0.03928 ? $v / 12.92 : (($v + 0.055) / 1.055) ** 2.4;
            }, self::rgb($hex));
            return 0.2126 * $c[0] + 0.7152 * $c[1] + 0.0722 * $c[2];
        };
        [$l1, $l2] = [$lum($a), $lum($b)];
        return (max($l1, $l2) + 0.05) / (min($l1, $l2) + 0.05);
    }
}
