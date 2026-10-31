<?php

namespace App\Http\Controllers\API\V1\Payment;

use App\Actions\Subscription\HandlePaystackCallbackAction;
use App\Enums\PaymentTransactionStatus;
use App\Http\Controllers\Controller;
use App\Repositories\Contracts\PaymentTransactionRepositoryInterface;
use Illuminate\Http\RedirectResponse;

class PaystackCallbackController extends Controller
{
    public function __construct(
        private HandlePaystackCallbackAction $handlePaystackCallbackAction,
        private PaymentTransactionRepositoryInterface $paymentTransactionRepository,
    ) {}

    public function __invoke(): RedirectResponse
    {
        $reference = request()->query('reference');
        $success = $this->handlePaystackCallbackAction->execute(
            is_string($reference) ? $reference : null,
        );

        $frontendUrl = rtrim(config('salono.frontend_url'), '/');
        $query = 'payment='.$this->outcome($success, is_string($reference) ? $reference : null);

        if (is_string($reference) && $reference !== '') {
            $query .= '&reference='.urlencode($reference);
        }

        return redirect()->away($frontendUrl.'/settings?'.$query);
    }

    /**
     * success | pending | failed. « pending » : paiement pas encore confirmé par Paystack
     * (vérification indisponible, paiement en cours) ; il sera activé par le webhook
     * ou par la réconciliation planifiée.
     */
    private function outcome(bool $success, ?string $reference): string
    {
        if ($success) {
            return 'success';
        }

        $transaction = $reference !== null && $reference !== ''
            ? $this->paymentTransactionRepository->findByReference($reference)
            : null;

        return $transaction?->status === PaymentTransactionStatus::Pending ? 'pending' : 'failed';
    }
}
