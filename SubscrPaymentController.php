<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Stripe\Stripe;
use Stripe\Customer;
use Stripe\Subscription;

class PaymentController extends Controller
{
    /**
     * Process the recurring payment.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function processRecurringPayment(Request $request)
    {
        // Validate the form data
        $request->validate([
            'plan' => 'required|string', // Validate that plan is provided
            'card_number' => 'required|string', // Validate that card number is provided
            'expiry_month' => 'required|numeric|min:1|max:12', // Validate that expiry month is numeric and between 1 and 12
            'expiry_year' => 'required|numeric|min:' . date('Y') . '|max:' . (date('Y') + 10), // Validate that expiry year is numeric and not older than current year and not more than 10 years from now
            'cvc' => 'required|string', // Validate that cvc is provided
        ]);

        // Set your Stripe API key
        Stripe::setApiKey(env('STRIPE_SECRET'));

        try {
            // Create a new customer in Stripe
            $customer = Customer::create([
                'email' => $request->user()->email, // Assuming you have an authenticated user
                'source' => $request->stripeToken,
            ]);

            // Subscribe the customer to a plan
            $subscription = Subscription::create([
                'customer' => $customer->id,
                'items' => [
                    [
                        'plan' => $request->plan,
                    ],
                ],
            ]);

            // Payment successful, you can do further processing here
            return redirect()->back()->with('success', 'Subscription successful!');
        } catch (\Exception $e) {
            // Subscription failed
            return redirect()->back()->with('error', $e->getMessage());
        }
    }
}
