<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Stripe\Stripe;
use Stripe\Charge;

class PaymentController extends Controller
{
    /**
     * Process the payment.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function processPayment(Request $request)
    {
        // Validate the form data
        $request->validate([
            'amount' => 'required|numeric|min:0.01', // Validate that amount is numeric and greater than 0
            'card_number' => 'required|string', // Validate that card number is provided
            'expiry_month' => 'required|numeric|min:1|max:12', // Validate that expiry month is numeric and between 1 and 12
            'expiry_year' => 'required|numeric|min:' . date('Y') . '|max:' . (date('Y') + 10), // Validate that expiry year is numeric and not older than current year and not more than 10 years from now
            'cvc' => 'required|string', // Validate that cvc is provided
        ]);

        // Set your Stripe API key
        Stripe::setApiKey(env('STRIPE_SECRET'));

        try {
            // Create a charge
            Charge::create([
                'amount' => $request->amount * 100, // Convert amount to cents
                'currency' => 'usd', // You can change the currency according to your needs
                'source' => $request->stripeToken,
                'description' => 'Payment for products/services',
            ]);

            // Payment successful, you can do further processing here
            return redirect()->back()->with('success', 'Payment successful!');
        } catch (\Exception $e) {
            // Payment failed
            return redirect()->back()->with('error', $e->getMessage());
        }
    }
}
