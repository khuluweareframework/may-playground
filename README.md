# Setting Up Subscription Payments with Stripe in Laravel

This guide will help you set up subscription payments with Stripe in a Laravel application, complete with controllers, validations, and step-by-step instructions.

## Step 1: Set Up Laravel Project

Create a new Laravel project using Composer:

```bash
composer create-project --prefer-dist laravel/laravel stripe-subscriptions
```

## Step 2: Install Cashier Package

Install the Laravel Cashier package for Stripe integration:

```bash
composer require laravel/cashier
```

## Step 3: Configure Stripe Account

Create a Stripe account if you haven't already. Obtain your Stripe API keys from the Dashboard.

## Step 4: Configure Stripe API Keys

Update your `.env` file with your Stripe API keys:

```dotenv
STRIPE_KEY=your-stripe-public-key
STRIPE_SECRET=your-stripe-secret-key
```

## Step 5: Create User Model

Create a User model with a `stripe_id` attribute to store the user's Stripe customer ID:

```bash
php artisan make:model User -m
```

Update the migration file (`database/migrations/create_users_table.php`) to include the `stripe_id` field.

## Step 6: Add Stripe Customer ID to User Model

Use the `Billable` trait provided by Cashier in your `User` model (`app/Models/User.php`) and specify the `stripe_id` attribute.

```php
use Laravel\Cashier\Billable;

class User extends Authenticatable
{
    use Billable;

    protected $fillable = [
        'name', 'email', 'password',
    ];

    protected $hidden = [
        'password', 'remember_token',
    ];

    // Other methods...
}
```

## Step 7: Create Subscription Controller

Generate a controller to handle subscription payments:

```bash
php artisan make:controller SubscriptionController
```

Implement a method in the `SubscriptionController` to handle subscription creation.

```php


<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Stripe\Stripe;
use Stripe\Subscription;

class SubscriptionController extends Controller
{
    /**
     * Handle subscription creation.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function subscribe(Request $request)
    {
        try {
            // Set your Stripe API key
            Stripe::setApiKey(env('STRIPE_SECRET'));

            // Create the subscription
            $subscription = Subscription::create([
                'customer' => $request->user()->stripe_id,
                'items' => [
                    [
                        'price' => env('STRIPE_SUBSCRIPTION_PRICE_ID'), // Price ID for subscription
                    ],
                ],
            ]);

            // Return success response
            return response()->json(['subscription' => $subscription]);
        } catch (\Exception $e) {
            // Return error response
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }
}



## Step 8: Set Up Webhook Controller

Generate a controller to handle Stripe webhook events:

```bash
php artisan make:controller StripeWebhookController
```

Implement the `handleWebhook` method in the `StripeWebhookController` to process incoming events.

```php




<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Laravel\Cashier\Http\Controllers\WebhookController as CashierController;
use Stripe\Stripe;

class StripeWebhookController extends CashierController
{
    /**
     * Handle Stripe webhook events.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Symfony\Component\HttpFoundation\Response
     */
    public function handleWebhook(Request $request)
    {
        // Verify the signature of the incoming webhook event
        $payload = $request->getContent();
        $sigHeader = $request->header('Stripe-Signature');
        $event = null;

        try {
            $event = \Stripe\Webhook::constructEvent(
                $payload, $sigHeader, config('services.stripe.webhook_secret')
            );
        } catch (\UnexpectedValueException $e) {
            // Invalid payload
            return response('Invalid payload', 400);
        } catch (\Stripe\Exception\SignatureVerificationException $e) {
            // Invalid signature
            return response('Invalid signature', 400);
        }

        // Handle the event
        return $this->handleStripeWebhook($event);
    }
}




## Step 9: Configure Webhook Endpoint in Stripe Dashboard

In the Stripe Dashboard, navigate to "Developers" > "Webhooks". Add the URL of your webhook endpoint (`https://your-app-url/stripe/webhook`) and select the events to receive notifications for.

## Step 10: Protect Webhook Endpoint

Protect your webhook endpoint by validating incoming requests. Add the `webhook` middleware provided by Cashier.

```php
protected $routeMiddleware = [
    // Other middleware entries...
    'webhook' => \Laravel\Cashier\Http\Middleware\WebhookSignature::class,
];
```

Then, apply the middleware to your webhook route.

```
Route::post('/stripe/webhook', 'StripeWebhookController@handleWebhook')->middleware('webhook');
```


## Step 11: Implement Subscription Button in Frontend

Implement a subscription button in your frontend to allow users to subscribe. This could be a simple HTML form or a JavaScript-powered button.

```
<form action="/subscribe" method="POST">
    @csrf
    <button type="submit">Subscribe</button>
</form>
```
And here's an example of a JavaScript-powered button for subscription:

```
<button id="subscribeButton">Subscribe</button>

<script>
    document.getElementById('subscribeButton').addEventListener('click', function() {
        fetch('/subscribe', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
            },
            body: JSON.stringify({})
        }).then(function(response) {
            // Handle response
            console.log(response);
        }).catch(function(error) {
            // Handle error
            console.error(error);
        });
    });
</script>

```


These examples demonstrate how you can implement a subscription button in your frontend using either a simple HTML form or a JavaScript-powered button. Adjust the action URL and form fields as necessary based on your application's routes and requirements.

## Step 12: Test Subscription Flow

Test the subscription flow by clicking the subscription button, verifying that the subscription is created successfully, and confirming that the webhook events are received and processed properly.

That's it! You've successfully set up subscription payments with Stripe in your Laravel application, including handling webhook events for real-time updates.




