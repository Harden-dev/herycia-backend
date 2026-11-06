<?php

namespace App\Http\Controllers\API\V1\Payment;

use App\Actions\Subscription\HandleGeniusPayCallbackAction;
use App\Enums\PaymentTransactionStatus;
use App\Http\Controllers\Controller;
use App\Repositories\Contracts\PaymentTransactionRepositoryInterface;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

/**
 * Retour navigateur après paiement GeniusPay (success_url et error_url pointent ici,
 * avec notre référence interne en query). Une transaction en attente est revérifiée via
 * l'API, de façon synchrone mais courte (timeout réduit, sans nouvel essai, limitée par
 * référence) ; les autres états répondent selon la base. À défaut de confirmation immédiate,
 * le webhook ou la réconciliation finalisent et le front reçoit « pending ».
 */
class GeniusPayCallbackController extends Controller
{
    public function __construct(
        private HandleGeniusPayCallbackAction $handleGeniusPayCallbackAction,
        private PaymentTransactionRepositoryInterface $paymentTransactionRepository,
    ) {}

    public function __invoke(): Response
    {
        $reference = request()->query('reference');

        // Référence validée avant toute requête BDD ou écriture dans les logs.
        if (! is_string($reference) || ! HandleGeniusPayCallbackAction::isValidReference($reference)) {
            return $this->redirectToFrontend('payment=failed');
        }

        $success = $this->handleGeniusPayCallbackAction->executeFromBrowser($reference);

        return $this->redirectToFrontend(
            'payment='.$this->outcome($success, $reference).'&reference='.urlencode($reference),
        );
    }

    private function redirectToFrontend(string $query): Response
    {
        $frontendUrl = rtrim((string) config('salono.frontend_url'), '/');

        // Jamais de redirection relative (elle resterait sur le domaine de l'API).
        if (! str_starts_with($frontendUrl, 'http://') && ! str_starts_with($frontendUrl, 'https://')) {
            Log::critical('GeniusPay callback: salono.frontend_url (FRONTEND_URL) absent ou invalide.');

            return response(
                'Votre paiement est en cours de traitement. Vous pouvez revenir sur l\'application Salono.',
                Response::HTTP_OK,
                ['Content-Type' => 'text/plain; charset=UTF-8'],
            );
        }

        return redirect()->away($frontendUrl.'/settings?'.$query);
    }

    /**
     * success | pending | failed. « pending » : paiement pas encore confirmé (vérification
     * indisponible, paiement en cours, ou en vérification manuelle) ; il sera finalisé par le
     * webhook, la réconciliation planifiée ou l'équipe.
     */
    private function outcome(bool $success, string $reference): string
    {
        if ($success) {
            return 'success';
        }

        $status = $this->paymentTransactionRepository->findByReference($reference)?->status;

        return in_array($status, [PaymentTransactionStatus::Pending, PaymentTransactionStatus::NeedsReview], true)
            ? 'pending'
            : 'failed';
    }
}
