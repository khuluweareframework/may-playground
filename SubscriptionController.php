<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Stripe\Stripe;
use Stripe\PaymentIntent;
use App\Http\Requests\SubscriptionPaymentRequest;

class SubscriptionController extends Controller
{
    /**
     * Handle subscription payment with split charges.
     *
     * @param  \App\Http\Requests\SubscriptionPaymentRequest  $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function subscribe(SubscriptionPaymentRequest $request)
    {
        // Set your Stripe API key
        Stripe::setApiKey(env('STRIPE_SECRET_KEY'));

        // Create a PaymentIntent
        $paymentIntent = PaymentIntent::create([
            'amount' => $request->input('amount'),
            'currency' => 'usd', // Change to your currency if needed
            'payment_method_types' => ['card'],
            'application_fee_amount' => $request->input('amount') * 0.3, // 30% for platform
            'transfer_data' => [
                'destination' => [
                    // Account ID of the receiver with 70% share
                    $request->input('receiver_account_id_70') => $request->input('amount') * 0.7,
                ],
            ],
        ]);

        // Return response
        return response()->json(['client_secret' => $paymentIntent->client_secret]);
    }
