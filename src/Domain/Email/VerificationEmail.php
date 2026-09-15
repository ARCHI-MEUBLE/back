<?php

declare(strict_types=1);

namespace App\Domain\Email;

final class VerificationEmail
{
    public static function subject(): string
    {
        return 'Vérifiez votre adresse email - ArchiMeuble';
    }

    public static function html(string $name, string $code): string
    {
        $year = date('Y');
        $formattedCode = substr($code, 0, 3) . ' ' . substr($code, 3, 3);
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
                .code-box { background-color: #1A1917; padding: 24px 40px; text-align: center; margin: 30px 0; }
                .code { font-size: 36px; font-weight: bold; color: #FFFFFF; letter-spacing: 8px; font-family: 'Courier New', monospace; }
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
                    <p>Merci de vous être inscrit sur ArchiMeuble !</p>
                    <p>Pour activer votre compte, veuillez entrer le code de vérification ci-dessous sur notre site :</p>

                    <div class='code-box'>
                        <span class='code'>{$formattedCode}</span>
                    </div>

                    <p style='text-align: center; font-size: 14px; color: #706F6C;'>
                        Ce code est valable pendant <strong>15 minutes</strong>.
                    </p>

                    <p style='margin-top: 30px; font-size: 14px; color: #706F6C;'>
                        Si vous n'avez pas créé de compte sur ArchiMeuble, vous pouvez ignorer cet email en toute sécurité.
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
