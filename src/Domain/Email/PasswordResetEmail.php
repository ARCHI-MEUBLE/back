<?php

declare(strict_types=1);

namespace App\Domain\Email;

final class PasswordResetEmail
{
    public static function subject(): string
    {
        return 'Réinitialisation de votre mot de passe - ArchiMeuble';
    }

    public static function html(string $name, string $resetUrl): string
    {
        $year = date('Y');
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
                .button { display: inline-block; padding: 16px 32px; background-color: #1A1917; color: #FFFFFF !important; text-decoration: none; font-weight: 600; margin-top: 30px; }
                .footer { text-align: center; margin-top: 40px; font-size: 12px; color: #706F6C; }
            </style>
        </head>
        <body>
            <div class='container'>
                <div class='header'>
                    <span class='logo'>ArchiMeuble</span>
                </div>
                <div class='content'>
                    <h2 style='margin-top: 0;'>Bonjour {$name},</h2>
                    <p>Vous avez demandé la réinitialisation de votre mot de passe pour votre compte ArchiMeuble.</p>
                    <p>Cliquez sur le bouton ci-dessous pour choisir un nouveau mot de passe. Ce lien est valable pendant 1 heure.</p>
                    <div style='text-align: center;'>
                        <a href='{$resetUrl}' class='button'>Réinitialiser mon mot de passe</a>
                    </div>
                    <p style='margin-top: 30px; font-size: 14px; color: #706F6C;'>
                        Si vous n'avez pas demandé cette réinitialisation, vous pouvez ignorer cet email en toute sécurité.
                        Votre mot de passe restera inchangé.
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
}
