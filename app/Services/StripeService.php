<?php

namespace App\Services;

use App\Traits\ConsumesExternalServices;
use Illuminate\Http\Request;

class StripeService
{
    use ConsumesExternalServices;

    protected $baseUri;

    protected $key;

    protected $secret;

    protected $plans;

    public function __construct()
    {
        $this->baseUri = config('services.stripe.base_uri');
        $this->key = config('services.stripe.key');
        $this->secret = config('services.stripe.secret');
        $this->plans = config('services.stripe.plans');
    }

    public function resolveAuthorization(&$queryParams, &$formParams, &$headers)
    {
        $headers['Authorization'] = $this->resolveAccessToken();
    }

    public function decodeResponse($response)
    {
        return json_decode($response);
    }

    public function resolveAccessToken()
    {
        return "Bearer {$this->secret}";
    }

    public function handlePayment(Request $request)
    {
        $request->validate([
            'payment_method' => 'required',
        ]);

        $intent = $this->createIntent($request->value, $request->currency, $request->payment_method);

        // dd($intent);

        session()->put('paymentIntentId', $intent->id);
        session()->put('paymentMethod', $request->payment_method);

        return redirect()->route(('approval'));
    }

    public function handleApproval()
    {
        if (session()->has('paymentIntentId') && session()->has('paymentMethod')) {
            $paymentIntentId = session()->get('paymentIntentId');
            $paymentMethod = session()->get('paymentMethod');

            $confirmation = $this->confirmPayment($paymentIntentId, $paymentMethod);

            // dd($confirmation);

            if ($confirmation->status === 'requires_action') {
                $clientSecret = $confirmation->client_secret;

                return view('stripe.3d-secure')->with([
                    'clientSecret' => $clientSecret,
                ]);
            }

            if ($confirmation->status === 'succeeded') {
                // $name = $confirmation->charges->data[0]->billing_details->name;
                $currency = strtoupper($confirmation->currency);
                $amount = $confirmation->amount / $this->resolveFactor(($currency));

                // return redirect()
                //     ->route('home')
                //     ->withSuccess(['payment' => "Thanks, {$name}. We received your {$amount}{$currency} payment."]);
                return redirect()
                    ->route('home')
                    ->withSuccess(['payment' => "Thanks. We received your {$amount}{$currency} payment."]);
            }
        }

        return redirect()
            ->route('home')
            ->withErrors('We were unable to confirm your payment. Try again, please');
    }

    public function handleSubscription(Request $request)
    {
        $customer = $this->createCustomer(
            $request->user()->name,
            $request->user()->email,
            $request->payment_method,
        );

        $subscription = $this->createSubscription(
            $customer->id,
            $request->payment_method,
            $this->plans[$request->plan],
        );

        if ($subscription->status == 'active') {
            session()->put('subscriptionId', $subscription->id);

            return redirect()->route(
                'subscribe.approval',
                [
                    'plan' => $request->plan,
                    'subscription_id' => $subscription->id,
                ],
            );
        }

        $paymentIntent = $subscription->latest_invoice->payment_intent;

        if ($paymentIntent->status === 'requires_action') {
            $clientSecret = $paymentIntent->client_secret;

            session()->put('subscriptionId', $subscription->id);

            return view('stripe.3d-secure-subscription')->with([
                'clientSecret' => $clientSecret,
                'plan' => $request->plan,
                'paymentMethod' => $request->payment_method,
                'subscriptionId' => $subscription->id,
            ]);
        }

        return redirect()->route('subscribe.show')
            ->withErrors('We were unable to activate your subscription. Try again, please.');
    }

    public function validateSubscription(Request $request)
    {
        if (session()->has('subscriptionId')) {
            $subscriptionId = session()->get('subscriptionId');

            session()->forget('subscriptionId');

            return $request->subscription_id == $subscriptionId;
        }

        return false;
    }

    public function createIntent($value, $currency, $paymentMethod)
    {
        return $this->makeRequest(
            'POST',
            '/v1/payment_intents',
            [],
            [
                'amount' => round($value * $this->resolveFactor($currency)),
                'currency' => strtolower($currency),
                'payment_method' => $paymentMethod,
                'confirmation_method' => 'manual',
            ],
        );
    }

    public function confirmPayment($paymentIntentId, $paymentMethod)
    {
        return $this->makeRequest(
            'POST',
            "/v1/payment_intents/{$paymentIntentId}/confirm",
            [],
            [
                'payment_method' => $paymentMethod,
                'return_url' => route('processing'),
            ],
        );
    }

    public function getPaymentIntent($paymentIntentId)
    {
        return $this->makeRequest(
            'GET',
            "/v1/payment_intents/{$paymentIntentId}",
        );
    }

    public function createCustomer($name, $email, $paymentMethod)
    {
        return $this->makeRequest(
            'POST',
            '/v1/customers',
            [],
            [
                'name' => $name,
                'email' => $email,
                'payment_method' => $paymentMethod,
            ],
        );
    }

    public function createSubscription($customerId, $paymentMethod, $priceId)
    {
        return $this->makeRequest(
            'POST',
            '/v1/subscriptions',
            [],
            [
                'customer' => $customerId,
                'items' => [
                    ['price' => $priceId],
                ],
                'default_payment_method' => $paymentMethod,
                'expand' => ['latest_invoice.payment_intent'],
            ],
        );
    }

    public function resolveFactor($currency)
    {
        $zeroDecimalCurrencies = ['JPY'];

        if (in_array(strtoupper($currency), $zeroDecimalCurrencies)) {
            return 1;
        }

        return 100;
    }
}
