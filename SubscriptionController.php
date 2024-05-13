I will be using:  

composer require stripe/stripe-php

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
}






<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class SubscriptionPaymentRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    public function authorize()
    {
        return true; // You may need to adjust this based on your authentication logic
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array
     */
    public function rules()
    {
        return [
            'amount' => 'required|numeric|min:0.01', // Validate amount
            'receiver_account_id_70' => 'required', // Validate receiver's account ID
            // Add more validation rules as needed
        ];
    }
}




-----------------  TESTS 

    <?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Tests\TestCase;
use Stripe\PaymentIntent;
use Stripe\Stripe;

class SubscriptionControllerTest extends TestCase
{
    /**
     * A basic feature test example.
     *
     * @return void
     */
    public function testSubscribe()
    {
        // Mock Stripe API call
        $mockPaymentIntent = PaymentIntent::constructFrom([
            'id' => 'mock_payment_intent_id',
            'client_secret' => 'mock_client_secret'
        ]);
        Stripe::shouldReceive('setApiKey');
        Stripe::shouldReceive('PaymentIntent::create')->andReturn($mockPaymentIntent);

        // Send subscription payment request
        $response = $this->postJson('/subscribe', [
            'amount' => 1000, // Amount in cents
            'receiver_account_id_70' => 'mock_receiver_account_id'
        ]);

        // Assert response
        $response->assertStatus(200)
            ->assertJson([
                'client_secret' => 'mock_client_secret'
            ]);
    }

    /**
     * Test subscription payment validation.
     *
     * @return void
     */
    public function testSubscribeValidation()
    {
        $response = $this->postJson('/subscribe', []);

        // Assert validation errors
        $response->assertStatus(422)
            ->assertJsonValidationErrors(['amount', 'receiver_account_id_70']);
    }
}

This test case covers two scenarios:

testSubscribe: This method sends a POST request to the /subscribe endpoint with valid input data and asserts that the response is successful (status code 200) and contains the expected client_secret key in the JSON response.

testSubscribeValidation: This method sends a POST request to the /subscribe endpoint with invalid input data and asserts that the response status code is 422 (unprocessable entity) and contains validation errors for the amount and receiver_account_id_70 fields.

Make sure to adjust the test assertions according to your actual implementation and validation rules. Additionally, you may need to mock other dependencies or modify the test setup based on your project's requirements.



    ----------------------------

    ============================


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
            Stripe::setApiKey(env('STRIPE_SECRET_KEY'));

            // Create the subscription
            $subscription = Subscription::create([
                'customer' => $request->user()->stripe_id, // assuming user model has stripe_id
                'items' => [
                    [
                        'price' => env('STRIPE_SUBSCRIPTION_PRICE_ID'), // Price ID for £30 subscription
                    ],
                ],
                'payment_behavior' => 'default_incomplete', // Keep invoices open for manual payment
                'expand' => ['latest_invoice.payment_intent'],
            ]);

            // Return success response
            return response()->json(['subscription' => $subscription]);
        } catch (\Exception $e) {
            // Return error response
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }
}


In this controller:

We handle the subscription creation process using the Stripe PHP library.
The user's Stripe customer ID is assumed to be stored in the stripe_id attribute of the User model. Adjust this according to your actual setup.
We specify the price ID for the £30 subscription.
The subscription is set to default_incomplete payment behavior to keep invoices open for manual payment.
In case of any exceptions during the subscription creation, we return a JSON response with an error message and status code 500.
Don't forget to replace env('STRIPE_SECRET_KEY') and env('STRIPE_SUBSCRIPTION_PRICE_ID') with your actual Stripe secret key and price ID for the £30 subscription, respectively.

Also, ensure that you have configured your Stripe webhook to handle events like successful payments, failed payments, etc., to update your application accordingly.


    ==================  Webhooks

    Certainly! Configuring a Stripe webhook allows your application to receive real-time notifications about events that occur in your Stripe account, such as successful payments, failed payments, subscription cancellations, etc. This is crucial for keeping your application's data in sync with Stripe.

Here's a step-by-step guide to setting up a Stripe webhook in your Laravel application:

Create a Webhook Endpoint:

Create a route in your Laravel routes file (web.php) to handle incoming webhook events. This route will point to a controller method responsible for processing the events.

    ``` 
    Route::post('/stripe/webhook', 'StripeWebhookController@handleWebhook');
    ```
    Generate Webhook Controller:

Create a controller to handle Stripe webhook events. You can generate this controller using Laravel's artisan command:

```
php artisan make:controller StripeWebhookController
    ```

Handle Webhook Events:

In the StripeWebhookController, implement the handleWebhook method to process incoming events.

    <?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Laravel\Cashier\Http\Controllers\WebhookController as CashierController;

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


In this controller:

We verify the signature of the incoming webhook event using the webhook secret stored in your .env file.
We then delegate the event handling to Cashier's handleStripeWebhook method, which processes the event based on its type.
Configure Webhook Endpoint in Stripe Dashboard:

Go to your Stripe Dashboard and navigate to "Developers" > "Webhooks". Click on "Add endpoint" and enter the URL of your webhook endpoint (/stripe/webhook in this case). Select the events you want to receive notifications for, such as successful payments, failed payments, etc. Stripe will send a test webhook to verify the endpoint.

Protect Webhook Endpoint:

For security reasons, you may want to add additional protection to your webhook endpoint by validating the incoming requests. One way to do this is by using the webhook middleware provided by Cashier.

In your Kernel.php file, add the webhook middleware to the $routeMiddleware array:

protected $routeMiddleware = [
    // Other middleware entries...
    'webhook' => \Laravel\Cashier\Http\Middleware\WebhookSignature::class,
];


Then, apply the middleware to your webhook route:

Route::post('/stripe/webhook', 'StripeWebhookController@handleWebhook')->middleware('webhook');


    
