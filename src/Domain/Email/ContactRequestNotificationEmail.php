<?php

declare(strict_types=1);

namespace App\Domain\Email;

final class ContactRequestNotificationEmail
{
    public static function subject(string $name, string $subject): string
    {
        $subjectLabel = $subject !== '' ? $subject : 'Demande de contact';
        return "Nouveau message de contact - {$name} ({$subjectLabel})";
    }

    public static function html(string $name, string $email, string $phone, string $company, string $subject, string $message, string $dashboardUrl): string
    {
        $year = date('Y');
        $companyHtml = $company !== '' ? "<br>{$company}" : '';
        $subjectHtml = $subject !== ''
            ? "<p style='margin: 0 0 8px 0; color: #A8A7A3; font-size: 10px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.1em;'>Sujet</p><p style='margin: 0 0 24px 0;'>{$subject}</p>"
            : '';
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
                    <h2 style='margin-top: 0;'>Nouveau message de contact</h2>
                    <p style='margin: 0 0 8px 0; color: #A8A7A3; font-size: 10px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.1em;'>Contact</p>
                    <p style='margin: 0 0 24px 0;'>{$name}{$companyHtml}<br>
                        <a href='mailto:{$email}' style='color: #1A1917;'>{$email}</a><br>
                        <a href='tel:{$phone}' style='color: #1A1917;'>{$phone}</a>
                    </p>
                    {$subjectHtml}
                    <p style='margin: 0 0 8px 0; color: #A8A7A3; font-size: 10px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.1em;'>Message</p>
                    <p style='margin: 0; white-space: pre-wrap;'>{$message}</p>
                    <div style='text-align: center;'>
                        <a href='{$dashboardUrl}' class='button'>Voir dans le tableau de bord</a>
                    </div>
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
