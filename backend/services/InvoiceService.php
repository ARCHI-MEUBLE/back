<?php
/**
 * Service de génération de factures PDF
 * Génère des factures au format PDF pour les commandes payées
 */

require_once __DIR__ . '/../../vendor/fpdf/fpdf.php';

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

        // Nom du fichier PDF
        $suffix = '';
        if (($order['payment_strategy'] ?? '') === 'deposit') {
            $suffix = ($order['balance_payment_status'] ?? '') === 'paid' ? '_solde' : '_acompte';
        }
        $filename = "facture_{$invoiceNumber}{$suffix}.pdf";

        // Utiliser /data/invoices pour Railway (volume persistant)
        $invoicesDir = file_exists('/data') ? '/data/invoices' : __DIR__ . '/../../invoices';
        $filepath = $invoicesDir . '/' . $filename;

        // Créer le dossier invoices s'il n'existe pas
        if (!file_exists($invoicesDir)) {
            mkdir($invoicesDir, 0777, true);
        }

        // Générer le PDF avec FPDF
        try {
            $this->generatePDFWithFPDF($order, $customer, $items, $samples, $invoiceNumber, $invoiceDate, $filepath);
            error_log("PDF generated successfully with FPDF: {$filepath}");
        } catch (Exception $e) {
            error_log("FPDF generation failed: " . $e->getMessage());
            // Fallback: générer quand même le HTML pour que l'utilisateur puisse voir quelque chose
        }

        // Toujours générer le HTML en backup
        $html = $this->generateInvoiceHTML($order, $customer, $items, $samples, $invoiceNumber, $invoiceDate);
        file_put_contents(str_replace('.pdf', '.html', $filepath), $html);

        return [
            'filename' => $filename,
            'filepath' => $filepath,
            'invoice_number' => $invoiceNumber
        ];
    }

    /**
     * Génère le PDF en utilisant la librairie FPDF
     */
    private function generatePDFWithFPDF($order, $customer, $items, $samples, $invoiceNumber, $invoiceDate, $filepath) {
        $pdf = new FPDF();
        $pdf->AddPage();
        $pdf->SetAutoPageBreak(true, 15);
        
        // Logo / Nom entreprise
        $pdf->SetFont('Arial', 'B', 24);
        $pdf->SetTextColor(217, 119, 6); // #d97706
        $pdf->Cell(100, 15, utf8_decode($this->companyName), 0, 0);
        
        // Titre Facture
        $pdf->SetFont('Arial', 'B', 32);
        $title = 'FACTURE';
        if (($order['payment_strategy'] ?? '') === 'deposit') {
            if (($order['balance_payment_status'] ?? '') === 'paid') {
                $title = 'FACTURE SOLDE';
            } else {
                $title = 'FACTURE ACOMPTE';
            }
        }
        $pdf->Cell(90, 15, $title, 0, 1, 'R');
        
        // Infos Entreprise (Gauche)
        $pdf->SetFont('Arial', '', 10);
        $pdf->SetTextColor(51, 51, 51);
        $startX = $pdf->GetX();
        $startY = $pdf->GetY();
        
        $pdf->Cell(100, 5, utf8_decode($this->companyAddress), 0, 1);
        $pdf->Cell(100, 5, utf8_decode('Tél: ' . $this->companyPhone), 0, 1);
        $pdf->Cell(100, 5, utf8_decode('Email: ' . $this->companyEmail), 0, 1);
        $pdf->Cell(100, 5, utf8_decode('SIRET: ' . $this->companySiret), 0, 1);
        $pdf->Cell(100, 5, utf8_decode('N° TVA: ' . $this->companyTVA), 0, 1);
        
        // Infos Facture (Droite)
        $pdf->SetY($startY);
        $pdf->Cell(190, 5, utf8_decode('N°: ' . $invoiceNumber), 0, 1, 'R');
        $pdf->SetX($startX);
        $pdf->SetY($startY + 5);
        $pdf->Cell(190, 5, utf8_decode('Date: ' . $invoiceDate), 0, 1, 'R');
        $pdf->SetX($startX);
        $pdf->SetY($startY + 10);
        $pdf->Cell(190, 5, utf8_decode('Commande: ' . $order['order_number']), 0, 1, 'R');

        // Type de paiement
        $pdf->SetX($startX);
        $pdf->SetY($startY + 15);
        $paymentTypeLabel = 'Paiement Integral';
        if (($order['payment_strategy'] ?? '') === 'deposit') {
            if (($order['balance_payment_status'] ?? '') === 'paid') {
                $paymentTypeLabel = 'Solde de commande';
            } else {
                $paymentTypeLabel = 'Acompte de commande (' . ($order['deposit_percentage'] ?? 0) . '%)';
            }
        }
        $pdf->SetFont('Arial', 'B', 10);
        $pdf->Cell(190, 5, utf8_decode($paymentTypeLabel), 0, 1, 'R');
        
        $pdf->Ln(10);
        $pdf->SetDrawColor(217, 119, 6);
        $pdf->Line(10, $pdf->GetY(), 200, $pdf->GetY());
        $pdf->Ln(5);
        
        // Bloc Client et Livraison
        $yBeforeBlocks = $pdf->GetY();
        
        // Facturé à
        $pdf->SetFont('Arial', 'B', 12);
        $pdf->Cell(95, 7, utf8_decode('FACTURÉ À'), 0, 1);
        $pdf->SetFont('Arial', '', 10);
        $pdf->Cell(95, 5, utf8_decode($customer['first_name'] . ' ' . $customer['last_name']), 0, 1);
        $pdf->Cell(95, 5, utf8_decode($customer['email']), 0, 1);
        $pdf->Cell(95, 5, utf8_decode($customer['phone']), 0, 1);
        $pdf->MultiCell(90, 5, utf8_decode($order['billing_address']), 0, 'L');
        
        $yAfterBilling = $pdf->GetY();
        
        // Livraison (à droite)
        $pdf->SetY($yBeforeBlocks);
        $pdf->SetX(110);
        $pdf->SetFont('Arial', 'B', 12);
        $pdf->Cell(90, 7, utf8_decode('ADRESSE DE LIVRAISON'), 0, 1);
        $pdf->SetX(110);
        $pdf->SetFont('Arial', '', 10);
        $pdf->Cell(90, 5, utf8_decode($customer['first_name'] . ' ' . $customer['last_name']), 0, 1);
        $pdf->SetX(110);
        $pdf->MultiCell(90, 5, utf8_decode($order['shipping_address'] ?? $order['billing_address']), 0, 'L');
        
        $yAfterShipping = $pdf->GetY();
        $pdf->SetY(max($yAfterBilling, $yAfterShipping) + 10);
        
        // Tableau des articles
        $pdf->SetFont('Arial', 'B', 10);
        $pdf->SetFillColor(249, 250, 251);
        $pdf->Cell(100, 10, utf8_decode('Désignation'), 1, 0, 'L', true);
        $pdf->Cell(20, 10, utf8_decode('Qté'), 1, 0, 'C', true);
        $pdf->Cell(35, 10, utf8_decode('Prix HT'), 1, 0, 'R', true);
        $pdf->Cell(35, 10, utf8_decode('Prix TTC'), 1, 1, 'R', true);
        
        $pdf->SetFont('Arial', '', 10);
        foreach ($items as $item) {
            // Récupérer le nom depuis config_data si disponible
            $itemName = 'Meuble personnalisé';
            if (isset($item['config_data'])) {
                $config = is_string($item['config_data']) ? json_decode($item['config_data'], true) : $item['config_data'];
                if (isset($config['name']) && !empty($config['name'])) {
                    $itemName = $config['name'];
                } else if (isset($item['prompt'])) {
                    $itemName = $item['prompt'];
                }
            } else if (isset($item['prompt'])) {
                $itemName = $item['prompt'];
            }

            $itemPrice = $item['total_price'] ?? ($item['unit_price'] * $item['quantity']);
            $itemPriceHT = $itemPrice / 1.20;
            
            // Calculer la hauteur nécessaire pour le nom (MultiCell)
            $x = $pdf->GetX();
            $y = $pdf->GetY();
            $pdf->MultiCell(100, 7, utf8_decode($itemName), 'LBR', 'L');
            $newY = $pdf->GetY();
            $h = $newY - $y;
            
            $pdf->SetXY($x + 100, $y);
            $pdf->Cell(20, $h, $item['quantity'], 'BR', 0, 'C');
            $pdf->Cell(35, $h, number_format($itemPriceHT, 2, ',', ' ') . ' ' . chr(128), 'BR', 0, 'R');
            $pdf->Cell(35, $h, number_format($itemPrice, 2, ',', ' ') . ' ' . chr(128), 'BR', 1, 'R');
        }
        
        // Échantillons
        foreach ($samples as $sample) {
            $sampleName = "Échantillon: " . ($sample['sample_name'] ?? 'Échantillon') . " - " . ($sample['material'] ?? '');
            
            $pdf->SetFillColor(240, 253, 244);
            $pdf->Cell(100, 8, utf8_decode($sampleName), 'LBR', 0, 'L', true);
            $pdf->Cell(20, 8, $sample['quantity'], 'BR', 0, 'C', true);
            $pdf->Cell(35, 8, 'GRATUIT', 'BR', 0, 'R', true);
            $pdf->Cell(35, 8, '0,00 ' . chr(128), 'BR', 1, 'R', true);
        }
        
        $pdf->Ln(5);
        
        // Totaux
        $totalHT = $order['total_amount'] / 1.20;
        $tva = $order['total_amount'] - $totalHT;
        
        $pdf->SetX(130);
        $pdf->SetFont('Arial', 'B', 10);
        $pdf->Cell(35, 8, utf8_decode('Total HT:'), 0, 0, 'R');
        $pdf->SetFont('Arial', '', 10);
        $pdf->Cell(25, 8, number_format($totalHT, 2, ',', ' ') . ' ' . chr(128), 0, 1, 'R');
        
        $pdf->SetX(130);
        $pdf->SetFont('Arial', 'B', 10);
        $pdf->Cell(35, 8, utf8_decode('TVA (20%):'), 0, 0, 'R');
        $pdf->SetFont('Arial', '', 10);
        $pdf->Cell(25, 8, number_format($tva, 2, ',', ' ') . ' ' . chr(128), 0, 1, 'R');
        
        $pdf->SetX(130);
        $pdf->SetFillColor(254, 243, 199);
        $pdf->SetFont('Arial', 'B', 12);
        $pdf->Cell(35, 10, utf8_decode('Total TTC:'), 0, 0, 'R', true);
        $pdf->Cell(25, 10, number_format($order['total_amount'], 2, ',', ' ') . ' ' . chr(128), 0, 1, 'R', true);

        // Détail Acompte / Solde si applicable
        if (($order['payment_strategy'] ?? '') === 'deposit') {
            $currentPaidLabel = (($order['balance_payment_status'] ?? '') === 'paid') ? 'Solde payé :' : 'Acompte payé :';
            $currentPaidAmount = (($order['balance_payment_status'] ?? '') === 'paid') ? $order['remaining_amount'] : $order['deposit_amount'];

            $pdf->SetX(110);
            $pdf->SetFont('Arial', 'B', 11);
            $pdf->SetFillColor(217, 249, 225); // Vert clair
            $pdf->Cell(55, 10, utf8_decode($currentPaidLabel), 0, 0, 'R', true);
            $pdf->Cell(25, 10, number_format($currentPaidAmount, 2, ',', ' ') . ' ' . chr(128), 0, 1, 'R', true);

            if (($order['balance_payment_status'] ?? '') !== 'paid') {
                $pdf->SetX(130);
                $pdf->SetFont('Arial', 'B', 10);
                $pdf->SetTextColor(217, 30, 30); // Rouge pour le solde dû
                $pdf->Cell(35, 8, utf8_decode('Reste à percevoir:'), 0, 0, 'R');
                $pdf->SetFont('Arial', '', 10);
                $pdf->Cell(25, 8, number_format($order['remaining_amount'], 2, ',', ' ') . ' ' . chr(128), 0, 1, 'R');
                $pdf->SetTextColor(51, 51, 51); // Reset couleur
            }
        }
        
        // Infos Paiement
        $pdf->Ln(10);
        $pdf->SetFillColor(254, 243, 199);
        $pdf->SetFont('Arial', 'B', 10);
        $paymentDate = ($order['confirmed_at'] ? date('d/m/Y', strtotime($order['confirmed_at'])) : date('d/m/Y', strtotime($order['created_at'])));
        $pdf->Cell(190, 10, utf8_decode('Paiement effectué par ' . ucfirst($order['payment_method']) . ' le ' . $paymentDate), 0, 1, 'L', true);
        
        // Footer
        $pdf->SetY(-30);
        $pdf->SetFont('Arial', '', 8);
        $pdf->SetTextColor(102, 102, 102);
        $pdf->Cell(190, 4, utf8_decode('Merci pour votre confiance !'), 0, 1, 'C');
        $pdf->Cell(190, 4, utf8_decode($this->companyName . ' - ' . $this->companyAddress), 0, 1, 'C');
        $pdf->Cell(190, 4, utf8_decode('SIRET: ' . $this->companySiret . ' | N° TVA: ' . $this->companyTVA), 0, 1, 'C');
        
        $pdf->Output('F', $filepath);
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

        $title = 'FACTURE';
        $paymentDetailHTML = '';
        if (($order['payment_strategy'] ?? '') === 'deposit') {
            if (($order['balance_payment_status'] ?? '') === 'paid') {
                $title = 'FACTURE SOLDE';
                $paymentDetailHTML = "
                    <div style='text-align: right; margin-top: 10px; padding: 10px; background-color: #f0fdf4; border-radius: 4px;'>
                        <strong>Solde payé : " . number_format($order['remaining_amount'], 2, ',', ' ') . " €</strong>
                    </div>";
            } else {
                $title = 'FACTURE ACOMPTE';
                $paymentDetailHTML = "
                    <div style='text-align: right; margin-top: 10px; padding: 10px; background-color: #f0fdf4; border-radius: 4px;'>
                        <strong>Acompte payé (" . ($order['deposit_percentage'] ?? 0) . "%) : " . number_format($order['deposit_amount'], 2, ',', ' ') . " €</strong><br>
                        <span style='color: #ef4444;'>Reste à percevoir : " . number_format($order['remaining_amount'], 2, ',', ' ') . " €</span>
                    </div>";
            }
        }

        $itemsHTML = '';

        // Ajouter les configurations
        foreach ($items as $item) {
            // Récupérer le nom depuis config_data si disponible
            $itemName = 'Meuble personnalisé';
            if (isset($item['config_data'])) {
                $config = is_string($item['config_data']) ? json_decode($item['config_data'], true) : $item['config_data'];
                if (isset($config['name']) && !empty($config['name'])) {
                    $itemName = $config['name'];
                } else if (isset($item['prompt'])) {
                    $itemName = $item['prompt'];
                }
            } else if (isset($item['prompt'])) {
                $itemName = $item['prompt'];
            }

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
                    <div class='invoice-title'>{$title}</div>
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
                " . (($order['payment_strategy'] ?? '') === 'deposit' ? "
                <tr>
                    <td style='padding: 8px; text-align: right;'><strong>Acompte payé:</strong></td>
                    <td style='padding: 8px; text-align: right;'>" . number_format($order['deposit_amount'], 2, ',', ' ') . " €</td>
                </tr>
                " . (($order['balance_payment_status'] ?? '') !== 'paid' ? "
                <tr>
                    <td style='padding: 8px; text-align: right; color: #ef4444;'><strong>Reste à payer:</strong></td>
                    <td style='padding: 8px; text-align: right; color: #ef4444;'><strong>" . number_format($order['remaining_amount'], 2, ',', ' ') . " €</strong></td>
                </tr>
                " : "
                <tr>
                    <td style='padding: 8px; text-align: right;'><strong>Solde payé:</strong></td>
                    <td style='padding: 8px; text-align: right;'>" . number_format($order['remaining_amount'], 2, ',', ' ') . " €</td>
                </tr>
                ") : "") . "
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
        return "http://127.0.0.1:8000/backend/invoices/{$filename}";
    }
}
