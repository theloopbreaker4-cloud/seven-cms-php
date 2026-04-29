<?php
/** SevenCMS — github.com/theloopbreaker4-cloud/seven-cms-php */

defined('_SEVEN') or die('No direct script access allowed');

/**
 * EcomMail — outbound transactional emails.
 *
 * Uses PHP's mail() by default. Plugins can override by binding their own
 * service in the container ('ecom.mailer') and Listeners on ecom.mail.* events.
 *
 * Each method renders a template under modules/Ecom/emails/{template}.html.
 */
class EcomMail
{
    public static function orderPlaced(Order $order): void
    {
        self::send($order->email, "Your order {$order->number}", 'order_placed', ['order' => $order]);
    }

    public static function orderPaid(Order $order): void
    {
        self::send($order->email, "Payment received — order {$order->number}", 'order_paid', ['order' => $order]);
    }

    public static function orderShipped(Order $order, ?string $tracking = null): void
    {
        self::send($order->email, "Your order is on the way — {$order->number}", 'order_shipped', [
            'order' => $order, 'tracking' => $tracking,
        ]);
    }

    public static function orderCancelled(Order $order): void
    {
        self::send($order->email, "Order cancelled — {$order->number}", 'order_cancelled', ['order' => $order]);
    }

    public static function digitalDelivered(Order $order, Product $product, array $tokens): void
    {
        self::send($order->email, "Your download is ready — {$order->number}", 'digital_delivered', [
            'order' => $order, 'product' => $product, 'tokens' => $tokens,
        ]);
    }

    public static function subscriptionRenewed(Subscription $sub, Order $invoice): void
    {
        $row = DB::findOne('ecom_customers', ' id = :id ', [':id' => $sub->customerId]);
        if (!$row) return;
        self::send($row['email'], "Subscription renewed", 'subscription_renewed', [
            'subscription' => $sub, 'order' => $invoice,
        ]);
    }

    public static function subscriptionCancelled(Subscription $sub): void
    {
        $row = DB::findOne('ecom_customers', ' id = :id ', [':id' => $sub->customerId]);
        if (!$row) return;
        self::send($row['email'], "Subscription cancelled", 'subscription_cancelled', ['subscription' => $sub]);
    }

    // ──────────────────────────────────────────────────────────────────

    private static function send(string $to, string $subject, string $template, array $vars): void
    {
        if ($to === '') return;

        // Plugin override hook
        Event::dispatch('ecom.mail.before', ['to' => $to, 'subject' => $subject, 'template' => $template, 'vars' => $vars]);

        $body = self::render($template, $vars);

        // Legacy override: a custom 'ecom.mailer' service still bypasses the queue.
        if (class_exists('Container') && Container::has('ecom.mailer')) {
            $mailer = Container::get('ecom.mailer');
            if (is_object($mailer) && method_exists($mailer, 'send')) {
                $from = (string)Env::get('MAIL_FROM', 'no-reply@' . ($_SERVER['HTTP_HOST'] ?? 'localhost'));
                $headers = ['MIME-Version: 1.0', 'Content-Type: text/html; charset=utf-8', 'From: ' . $from];
                $mailer->send($to, $subject, $body, $headers);
                return;
            }
        }

        // Default path — go through the queue. Worker (`bin/sev mail:send`)
        // delivers asynchronously, so requests don't block on SMTP.
        if (class_exists('Mailer')) {
            Mailer::queue($to, $subject, $body);
            return;
        }

        // Fallback for early bootstraps where Mailer class isn't loaded yet.
        $from = (string)Env::get('MAIL_FROM', 'no-reply@' . ($_SERVER['HTTP_HOST'] ?? 'localhost'));
        @mail($to, $subject, $body, "MIME-Version: 1.0\r\nContent-Type: text/html; charset=utf-8\r\nFrom: {$from}");
    }

    private static function render(string $template, array $vars): string
    {
        $path = ROOT_DIR . "/modules/Ecom/emails/{$template}.html";
        if (!is_file($path)) {
            return '<p>' . htmlspecialchars($template) . '</p>';
        }
        extract($vars, EXTR_SKIP);
        ob_start();
        include $path;
        return (string)ob_get_clean();
    }
}
