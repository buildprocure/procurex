<?php
declare(strict_types=1);

namespace App\Modules\Buyer\Returns;

use App\Core\Config;
use App\Core\Mailer;

/**
 * Emails for each step of a return. Mailer::send() never throws, so a mail
 * problem can never roll back or block a return/refund.
 */
class ReturnNotifier
{
    public static function requested(array $return): void
    {
        self::send(
            $return['supplier_email'] ?? '',
            "Return request #{$return['id']} for invoice {$return['invoice_number']}",
            "{$return['buyer_name']} has requested a return of \$" . self::money($return['refund_total'])
                . " worth of goods on invoice {$return['invoice_number']}.",
            $return
        );
    }

    public static function decided(array $return, bool $approved, ?string $comment): void
    {
        $verb = $approved ? 'approved' : 'rejected';
        self::send(
            $return['buyer_contact_email'] ?? '',
            "Your return request #{$return['id']} was {$verb}",
            "{$return['supplier_name']} {$verb} your return request for invoice {$return['invoice_number']}."
                . ($comment ? ' Comment: ' . $comment : ''),
            $return
        );
    }

    public static function refunded(array $return): void
    {
        $body = "A refund of \$" . self::money($return['refund_total'])
            . " for return #{$return['id']} (invoice {$return['invoice_number']}) has been issued to the original payment method.";
        self::send($return['buyer_contact_email'] ?? '', "Refund issued for return #{$return['id']}", $body, $return);
        self::send($return['supplier_email'] ?? '', "Refund issued for return #{$return['id']}", $body, $return);
    }

    public static function refundFailed(array $return, string $message): void
    {
        self::send(
            $return['supplier_email'] ?? '',
            "Refund failed for return #{$return['id']}",
            "The Stripe refund for return #{$return['id']} (invoice {$return['invoice_number']}) failed: {$message}. "
                . "Open the return and retry the refund.",
            $return
        );
    }

    private static function send(string $to, string $subject, string $message, array $return): void
    {
        if ($to === '') {
            return;
        }

        $link = Config::appUrl() . '/Returns/view.php?id=' . (int) $return['id'];

        $html = '<p>' . htmlspecialchars($message, ENT_QUOTES, 'UTF-8') . '</p>'
            . '<p><a href="' . htmlspecialchars($link, ENT_QUOTES, 'UTF-8') . '">View return</a></p>';
        $text = $message . "\n" . $link;

        Mailer::send($to, $subject, $html, $text, 'return_refund', 'return_request', (int) $return['id']);
    }

    private static function money($amount): string
    {
        return number_format((float) $amount, 2);
    }
}
