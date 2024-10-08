<?php

namespace App\Http\Controllers;

use App\Resolvers\PaymentPlatformResolver;
use App\Services\PayPalService;
use Illuminate\Http\Request;

class PaymentController extends Controller
{
    protected $paymentPlatformResolver;

    public function __construct(PaymentPlatformResolver $paymentPlatformResolver)
    {
        $this->middleware('auth');

        $this->paymentPlatformResolver = $paymentPlatformResolver;
    }

    public function pay(Request $request)
    {
        $rules = [
            'value' => ['required', 'numeric', 'min:5'],
            'currency' => ['required', 'exists:currencies,iso'],
            'payment_platform' => ['required', 'exists:payment_platforms,id'],
        ];

        // dd($request->all());

        $request->validate($rules);

        $paymentPlatform = $this->paymentPlatformResolver
            ->resolveService($request->payment_platform);

        session()->put('paymentPlatformId', $request->payment_platform);

        if ($request->user()->hasActiveSubscription()) {
            $request->value = round($request->value * 0.9, 2);
        }

        // $paymentPlatform = resolve(PayPalService::class);

        return $paymentPlatform->handlePayment($request);
    }

    public function approval()
    {
        // $paymentPlatform = resolve(PayPalService::class);

        if (session()->has('paymentPlatformId')) {
            $paymentPlatform = $this->paymentPlatformResolver
                ->resolveService(session()->get('paymentPlatformId'));

            return $paymentPlatform->handleApproval();
        }

        return redirect()
            ->route('home')
            ->withErrors('We cannot retrieve your payment platform. Try again please.');
    }

    public function cancelled()
    {
        return redirect()
            ->route('home')
            ->withErrors('You cancelled the payment');
    }

    public function processing()
    {
        if (session()->has('paymentPlatformId') && session()->has('paymentIntentId')) {
            $paymentPlatform = $this->paymentPlatformResolver
                ->resolveService(session()->get('paymentPlatformId'));

            $paymentIntentId = session()->get('paymentIntentId');

            $paymentIntent = $paymentPlatform->getPaymentIntent($paymentIntentId);

            if ($paymentIntent->status === 'requires_payment_method') {
                return redirect()->route('cancelled'); // Failed payment
            }

            return redirect()->route('approval');
        }

        return redirect()
            ->route('home')
            ->withErrors('We cannot process your payment. Try again please.');
    }
}
