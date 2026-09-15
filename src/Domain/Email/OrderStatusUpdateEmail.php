<?php

declare(strict_types=1);

namespace App\Domain\Email;

final class OrderStatusUpdateEmail
{
    private const LABELS = [
        'pending' => 'En attente de validation', 'confirmed' => 'Confirmée - En attente de paiement', 'paid' => 'Payée',
        'in_production' => 'En cours de fabrication', 'shipped' => 'Expédiée', 'delivered' => 'Livrée',
        'cancelled' => 'Annulée', 'refunded' => 'Remboursée',
    ];

    private const COLORS = [
        'pending' => ['bg' => '#FEF3C7', 'text' => '#92400E'], 'confirmed' => ['bg' => '#DBEAFE', 'text' => '#1E40AF'],
        'paid' => ['bg' => '#D1FAE5', 'text' => '#065F46'], 'in_production' => ['bg' => '#E9D5FF', 'text' => '#6B21A8'],
        'shipped' => ['bg' => '#CFFAFE', 'text' => '#0E7490'], 'delivered' => ['bg' => '#D1FAE5', 'text' => '#065F46'],
        'cancelled' => ['bg' => '#FEE2E2', 'text' => '#B91C1C'], 'refunded' => ['bg' => '#F3F4F6', 'text' => '#374151'],
    ];

    private const MESSAGES = [
        'pending' => "Votre commande est en attente de validation par notre équipe. Vous recevrez un email dès qu'elle sera validée.",
        'confirmed' => 'Votre commande a été validée ! Vous pouvez maintenant procéder au paiement.',
        'paid' => 'Votre paiement a bien été reçu. Merci pour votre confiance ! Votre commande va être traitée.',
        'in_production' => 'Bonne nouvelle ! Votre commande est maintenant en cours de préparation.',
        'shipped' => 'Votre commande a été expédiée ! Vous recevrez bientôt les informations de livraison.',
        'delivered' => 'Votre commande a été livrée. Nous espérons que vous êtes satisfait de votre achat !',
        'cancelled' => "Votre commande a été annulée. Si vous avez des questions, n'hésitez pas à nous contacter.",
        'refunded' => 'Le remboursement de votre commande a été effectué. Le montant sera crédité sous quelques jours.',
    ];

    public static function subject(string $orderNumber): string
    {
        return "Mise à jour de votre commande #{$orderNumber} - ArchiMeuble";
    }

    public static function label(string $status): string
    {
        return self::LABELS[$status] ?? $status;
    }

    public static function html(string $name, string $orderNumber, string $status, string $frontendUrl): string
    {
        $year = date('Y');
        $statusLabel = self::label($status);
        $colors = self::COLORS[$status] ?? ['bg' => '#F3F4F6', 'text' => '#374151'];
        $message = self::MESSAGES[$status] ?? 'Le statut de votre commande a été mis à jour.';
        $actionButton = '';
        if ($status === 'confirmed') {
            $actionButton = self::actionButton($frontendUrl, 'Voir ma commande');
        } elseif (in_array($status, ['paid', 'in_production', 'shipped', 'delivered'], true)) {
            $actionButton = self::actionButton($frontendUrl, 'Suivre ma commande');
        }
        return "
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset='utf-8'>
            <style>
                body { font-family: 'Helvetica Neue', Helvetica, Arial, sans-serif; line-height: 1.6; color: #1A1917; margin: 0; padding: 0; }
                .container { max-width: 600px; margin: 0 auto; padding: 40px 20px; }
                .header { text-align: center; margin-bottom: 40px; }
                .logo { font-size: 24px; font-weight: bold; text-decoration: none; color: #1A1917; }
                .content { background-color: #FAFAF9; padding: 40px; border: 1px solid #E8E6E3; }
                .footer { text-align: center; margin-top: 40px; font-size: 12px; color: #706F6C; }
            </style>
        </head>
        <body>
            <div class='container'>
                <div class='header'>
                    <span class='logo'>ArchiMeuble</span>
                </div>
                <div class='content'>
                    <div style='display: inline-block; padding: 6px 16px; background-color: {$colors['bg']}; color: {$colors['text']}; font-size: 12px; font-weight: bold; text-transform: uppercase; margin-bottom: 20px;'>
                        {$statusLabel}
                    </div>
                    <h2 style='margin-top: 0;'>Bonjour {$name},</h2>
                    <p>Le statut de votre commande <strong>#{$orderNumber}</strong> a été mis à jour.</p>
                    <p>{$message}</p>
                    {$actionButton}
                    <p style='margin-top: 30px; font-size: 14px; color: #706F6C;'>
                        Si vous avez des questions, n'hésitez pas à nous contacter à <a href='mailto:pro.archimeuble@gmail.com' style='color: #8B7355;'>pro.archimeuble@gmail.com</a>
                    </p>
                    <p style='margin-top: 20px; font-size: 14px; color: #706F6C;'>
                        Cordialement,<br>
                        L'équipe ArchiMeuble
                    </p>
                </div>
                <div class='footer'>
                    <p>&copy; {$year} ArchiMeuble. Tous droits réservés.</p>
                </div>
            </div>
        </body>
        </html>
        ";
    }

    private static function actionButton(string $frontendUrl, string $label): string
    {
        return "
                <div style='text-align: center; margin: 30px 0;'>
                    <a href='{$frontendUrl}/account?section=orders' style='display: inline-block; padding: 14px 32px; background-color: #1A1917; color: #ffffff; text-decoration: none; font-weight: bold;'>
                        {$label}
                    </a>
                </div>
            ";
    }
}
