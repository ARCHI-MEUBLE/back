<?php

declare(strict_types=1);

namespace App\Console;

use App\Config\Settings;
use App\Db\Connection;
use App\Domain\Payment\InstallmentRepository;
use App\Infrastructure\Stripe\StripeGateway;
use Stripe\Exception\CardException;
use Throwable;

final class CronInstallmentsCommand
{
    public function __construct(private readonly Settings $settings) {}

    public function run(): int
    {
        $installments = new InstallmentRepository(Connection::fromUrl($this->settings->databaseUrl));
        $stripe = new StripeGateway($this->settings->stripe);
        $this->log('========== DÉBUT DU TRAITEMENT DES MENSUALITÉS ==========');
        try {
            $stripe->ensureConfigured();
        } catch (Throwable $e) {
            $this->log('ERREUR CRITIQUE: ' . $e->getMessage());
            return 1;
        }
        $pending = $installments->pending();
        $this->log('Mensualités en attente trouvées: ' . count($pending));
        foreach ($pending as $installment) {
            $this->process($installments, $stripe, $installment);
        }
        if ($pending === []) {
            $this->log("Aucune mensualité à traiter aujourd'hui");
        }
        $this->log('========== FIN DU TRAITEMENT ==========');
        return 0;
    }

    private function process(InstallmentRepository $installments, StripeGateway $stripe, array $installment): void
    {
        $this->log("Traitement de la mensualité #{$installment['id']} - Commande {$installment['order_number']} - {$installment['installment_number']}/3");
        try {
            $intent = $stripe->createPaymentIntent([
                'amount' => (int) ($installment['amount'] * 100),
                'currency' => 'eur',
                'customer' => $installment['stripe_customer_id'],
                'payment_method_types' => ['card'],
                'off_session' => true,
                'confirm' => true,
                'description' => "Mensualité {$installment['installment_number']}/3 - Commande {$installment['order_number']}",
                'metadata' => ['order_id' => $installment['order_id'], 'installment_id' => $installment['id'], 'installment_number' => $installment['installment_number']],
            ]);
            if ($intent->status === 'succeeded') {
                $installments->markPaid((int) $installment['id'], $intent->id);
                $this->log("Mensualité #{$installment['id']} payée avec succès (PI: {$intent->id})");
            } else {
                $this->log("Paiement en attente pour mensualité #{$installment['id']} - Statut: {$intent->status}");
            }
        } catch (CardException $e) {
            $installments->markFailed((int) $installment['id']);
            $this->log("Échec mensualité #{$installment['id']}: " . $e->getMessage());
        } catch (Throwable $e) {
            $this->log("Erreur mensualité #{$installment['id']}: " . $e->getMessage());
        }
    }

    private function log(string $message): void
    {
        fwrite(STDOUT, '[' . date('Y-m-d H:i:s') . "] {$message}\n");
    }
}
