<?php
/**
 * Service de génération de factures PDF
 * Génère des factures au format PDF pour les commandes payées
 */

class InvoiceService {
    private $companyName = 'ArchiMeuble';
    private $companyAddress = '30 Rue Henri Regnault, 59000 Lille, France';
    private $companyPhone = '06 01 06 28 67';
    private $companyEmail = 'pro.archimeuble@gmail.com';
    private $companySiret = '123 456 789 00012';
    private $companyTVA = 'FR 12 123456789';

    /**
     * Génère une facture PDF pour une commande
     */
    public function generateInvoice($order, $customer, $items, $samples = []) {
        $invoiceNumber = $this->getInvoiceNumber($order);
        $invoiceDate = date('d/m/Y', strtotime($order['created_at']));

        // Générer le HTML de la facture
        $html = $this->generateInvoiceHTML($order, $customer, $items, $samples, $invoiceNumber, $invoiceDate);

        // Nom du fichier PDF
        $filename = "facture_{$invoiceNumber}.pdf";

        // Utiliser /data/invoices pour Railway (volume persistant)
        $invoicesDir = file_exists('/data') ? '/data/invoices' : __DIR__ . '/../../invoices';
        $filepath = $invoicesDir . '/' . $filename;

        // Créer le dossier invoices s'il n'existe pas
        if (!file_exists($invoicesDir)) {
            mkdir($invoicesDir, 0777, true);
        }

        // Générer le PDF (méthode simplifiée avec wkhtmltopdf ou DomPDF)
        $this->htmlToPdf($html, $filepath);

        return [
            'filename' => $filename,
            'filepath' => $filepath,
            'invoice_number' => $invoiceNumber
        ];
    }

    /**
     * Génère le numéro de facture basé sur l'ID de commande
     */
    private function getInvoiceNumber($order) {
        $year = date('Y', strtotime($order['created_at']));
        return "FAC-{$year}-" . str_pad($order['id'], 6, '0', STR_PAD_LEFT);
    }

    /**
     * Génère le HTML de la facture
     */
    private function generateInvoiceHTML($order, $customer, $items, $samples, $invoiceNumber, $invoiceDate) {
        $totalHT = $order['total_amount'] / 1.20; // Montant HT (TVA 20%)
        $tva = $order['total_amount'] - $totalHT;

        $itemsHTML = '';

        // Ajouter les configurations
        foreach ($items as $item) {
            // Utiliser les bons noms de colonnes de order_items
            $itemName = $item['prompt'] ?? 'Meuble personnalisé';
            $itemPrice = $item['total_price'] ?? ($item['unit_price'] * $item['quantity']);
            $itemPriceHT = $itemPrice / 1.20;

            $itemsHTML .= "
                <tr>
                    <td style='padding: 10px; border-bottom: 1px solid #ddd;'>{$itemName}</td>
                    <td style='padding: 10px; border-bottom: 1px solid #ddd; text-align: center;'>{$item['quantity']}</td>
                    <td style='padding: 10px; border-bottom: 1px solid #ddd; text-align: right;'>" . number_format($itemPriceHT, 2, ',', ' ') . " €</td>
                    <td style='padding: 10px; border-bottom: 1px solid #ddd; text-align: right;'>" . number_format($itemPrice, 2, ',', ' ') . " €</td>
                </tr>
            ";
        }

        // Ajouter les échantillons gratuits
        foreach ($samples as $sample) {
            $sampleName = "Échantillon: " . ($sample['sample_name'] ?? 'Échantillon') . " - " . ($sample['material'] ?? '');

            $itemsHTML .= "
                <tr style='background-color: #f0fdf4;'>
                    <td style='padding: 10px; border-bottom: 1px solid #ddd;'>{$sampleName}</td>
                    <td style='padding: 10px; border-bottom: 1px solid #ddd; text-align: center;'>{$sample['quantity']}</td>
                    <td style='padding: 10px; border-bottom: 1px solid #ddd; text-align: right; color: #16a34a; font-weight: bold;'>GRATUIT</td>
                    <td style='padding: 10px; border-bottom: 1px solid #ddd; text-align: right; color: #16a34a; font-weight: bold;'>0,00 €</td>
                </tr>
            ";
        }

        return "
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset='UTF-8'>
            <style>
                body {
                    font-family: Arial, sans-serif;
                    color: #333;
                    line-height: 1.6;
                }
                .header {
                    display: flex;
                    justify-content: space-between;
                    margin-bottom: 25px;
                    padding-bottom: 15px;
                    border-bottom: 3px solid #d97706;
                }
                .company-info {
                    flex: 1;
                }
                .invoice-info {
                    text-align: right;
                }
                .invoice-title {
                    font-size: 32px;
                    font-weight: bold;
                    color: #d97706;
                    margin-bottom: 10px;
                }
                .section-title {
                    font-size: 16px;
                    font-weight: bold;
                    margin-top: 20px;
                    margin-bottom: 8px;
                    color: #111;
                }
                .info-box {
                    background-color: #f9fafb;
                    padding: 12px;
                    border-radius: 8px;
                    margin-bottom: 15px;
                }
                table {
                    width: 100%;
                    border-collapse: collapse;
                    margin-top: 20px;
                }
                th {
                    background-color: #f9fafb;
                    padding: 12px;
                    text-align: left;
                    font-weight: bold;
                    border-bottom: 2px solid #ddd;
                }
                .total-row {
                    font-weight: bold;
                    background-color: #fef3c7;
                }
                .footer {
                    margin-top: 30px;
                    padding-top: 15px;
                    border-top: 1px solid #ddd;
                    text-align: center;
                    font-size: 11px;
                    color: #666;
                    line-height: 1.4;
                }
            </style>
        </head>
        <body>
            <div class='header'>
                <div class='company-info'>
                    <h1 style='margin: 0; color: #d97706;'>{$this->companyName}</h1>
                    <p style='margin: 5px 0;'>{$this->companyAddress}</p>
                    <p style='margin: 5px 0;'>Tél: {$this->companyPhone}</p>
                    <p style='margin: 5px 0;'>Email: {$this->companyEmail}</p>
                    <p style='margin: 5px 0;'>SIRET: {$this->companySiret}</p>
                    <p style='margin: 5px 0;'>N° TVA: {$this->companyTVA}</p>
                </div>
                <div class='invoice-info'>
                    <div class='invoice-title'>FACTURE</div>
                    <p style='margin: 5px 0;'><strong>N°:</strong> {$invoiceNumber}</p>
                    <p style='margin: 5px 0;'><strong>Date:</strong> {$invoiceDate}</p>
                    <p style='margin: 5px 0;'><strong>Commande:</strong> {$order['order_number']}</p>
                </div>
            </div>

            <div class='section-title'>FACTURÉ À</div>
            <div class='info-box'>
                <p style='margin: 5px 0;'><strong>{$customer['first_name']} {$customer['last_name']}</strong></p>
                <p style='margin: 5px 0;'>{$customer['email']}</p>
                <p style='margin: 5px 0;'>{$customer['phone']}</p>
                <p style='margin: 5px 0;'>{$order['billing_address']}</p>
            </div>

            <div class='section-title'>ADRESSE DE LIVRAISON</div>
            <div class='info-box'>
                <p style='margin: 5px 0;'>{$customer['first_name']} {$customer['last_name']}</p>
                <p style='margin: 5px 0;'>{$order['shipping_address']}</p>
            </div>

            <div class='section-title'>DÉTAILS DE LA COMMANDE</div>
            <table>
                <thead>
                    <tr>
                        <th style='width: 50%;'>Désignation</th>
                        <th style='width: 10%; text-align: center;'>Qté</th>
                        <th style='width: 20%; text-align: right;'>Prix HT</th>
                        <th style='width: 20%; text-align: right;'>Prix TTC</th>
                    </tr>
                </thead>
                <tbody>
                    {$itemsHTML}
                </tbody>
            </table>

            <table style='margin-top: 30px; width: 40%; margin-left: auto;'>
                <tr>
                    <td style='padding: 8px; text-align: right;'><strong>Total HT:</strong></td>
                    <td style='padding: 8px; text-align: right;'>" . number_format($totalHT, 2, ',', ' ') . " €</td>
                </tr>
                <tr>
                    <td style='padding: 8px; text-align: right;'><strong>TVA (20%):</strong></td>
                    <td style='padding: 8px; text-align: right;'>" . number_format($tva, 2, ',', ' ') . " €</td>
                </tr>
                <tr class='total-row'>
                    <td style='padding: 12px; text-align: right; font-size: 18px;'><strong>Total TTC:</strong></td>
                    <td style='padding: 12px; text-align: right; font-size: 18px;'><strong>" . number_format($order['total_amount'], 2, ',', ' ') . " €</strong></td>
                </tr>
            </table>

            <div style='margin-top: 20px; padding: 12px; background-color: #fef3c7; border-radius: 8px;'>
                <p style='margin: 0;'><strong>Paiement effectué par:</strong> " . ucfirst($order['payment_method']) . "</p>
                <p style='margin: 5px 0 0 0;'><strong>Statut:</strong> Payé le " . ($order['confirmed_at'] ? date('d/m/Y', strtotime($order['confirmed_at'])) : date('d/m/Y', strtotime($order['created_at']))) . "</p>
            </div>

            <div class='footer'>
                <p style='margin: 0 0 5px 0;'>Merci pour votre confiance !</p>
                <p style='margin: 0 0 5px 0;'>{$this->companyName} - {$this->companyAddress}</p>
                <p style='margin: 0;'>SIRET: {$this->companySiret} | N° TVA: {$this->companyTVA}</p>
            </div>
        </body>
        </html>
        ";
    }

    /**
     * Convertit HTML en PDF avec FPDF (fallback si wkhtmltopdf non disponible)
     */
    private function htmlToPdf($html, $filepath) {
        // Toujours sauvegarder le HTML pour backup
        $htmlFilepath = str_replace('.pdf', '.html', $filepath);
        file_put_contents($htmlFilepath, $html);

        // Utiliser wkhtmltopdf si disponible
        if (function_exists('exec') && $this->commandExists('wkhtmltopdf')) {
            $tempHtml = tempnam(sys_get_temp_dir(), 'invoice_') . '.html';
            file_put_contents($tempHtml, $html);

            // Options wkhtmltopdf pour un meilleur rendu
            $cmd = sprintf(
                'wkhtmltopdf --quiet --page-size A4 --margin-top 8mm --margin-bottom 8mm --margin-left 10mm --margin-right 10mm %s %s 2>&1',
                escapeshellarg($tempHtml),
                escapeshellarg($filepath)
            );

            exec($cmd, $output, $return_var);
            @unlink($tempHtml);

            if ($return_var === 0 && file_exists($filepath) && filesize($filepath) > 100) {
                error_log("PDF generated successfully with wkhtmltopdf: {$filepath}");
                return true;
            } else {
                error_log("wkhtmltopdf failed or not available: " . implode("\n", $output));
            }
        }

        // Fallback: Générer le PDF avec la version HTML
        // L'utilisateur pourra toujours télécharger le fichier HTML qui sera servi
        error_log("PDF generation: serving HTML file as fallback");

        // Ne pas créer de PDF vide - laisser le HTML être servi par invoice.php
        return true;
    }

    /**
     * Vérifie si une commande existe sur le système
     */
    private function commandExists($command) {
        $return = shell_exec(sprintf("which %s 2>/dev/null", escapeshellarg($command)));
        return !empty($return);
    }

    /**
     * Retourne le chemin public vers une facture
     */
    public function getInvoiceUrl($filename) {
        return "http://localhost:8000/backend/invoices/{$filename}";
    }
}
