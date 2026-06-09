<?php

namespace App\Http\Controllers\API\V1\Payment;

use App\Actions\Subscription\HandlePaystackCallbackAction;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;

class PaystackCallbackController extends Controller
{
    public function __construct(
        private HandlePaystackCallbackAction $handlePaystackCallbackAction,
    ) {}

    public function __invoke(): RedirectResponse
    {
        $reference = request()->query('reference');
        $success = $this->handlePaystackCallbackAction->execute(
            is_string($reference) ? $reference : null,
        );

        $frontendUrl = rtrim(config('salono.frontend_url'), '/');
        $query = $success ? 'payment=success' : 'payment=failed';

        if (is_string($reference) && $reference !== '') {
            $query .= '&reference='.urlencode($reference);
        }

        return redirect()->away($frontendUrl.'/settings?'.$query);
    }
}
