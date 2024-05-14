<?php

namespace Vanguard\Http\Controllers\Web;

use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Vanguard\Dependant;
use Vanguard\Http\Controllers\Controller;
use Vanguard\User;
use Vanguard\Http\Requests\Dependant\CreateDependantRequest;
use Vanguard\Http\Requests\Dependant\CreateMembershipRequest;
use App\Services\MembershipNumberGenerator;
use Vanguard\Payment;

use Stripe\Stripe;
use Stripe\Plan;
use Stripe\Product;

// use function PHPSTORM_META\map;

class DependantsController extends Controller
{
    public function __construct()
    {
        // Allow access to authenticated users only.
        $this->middleware('auth');

        // Allow access to users with 'users.manage' permission.
        $this->middleware('permission:users.dependants');
    }

    public function stripe(Request $request)
    {
        // Set your Stripe API key
        Stripe::setApiKey(env('STRIPE_SECRET'));

        $response = $this->checkProduct($request);

        dd($response);

        try {
            // Create a new plan on Stripe
            $plan = Plan::create([
                'amount' => 2000, // Plan amount in cents (e.g., 1000 for $10.00)
                'currency' => 'gbp', // Currency of the plan
                'interval' => 'month', // Billing interval: month, year, week, etc.
                'product' => [
                    'name' => 'Product_2000', // Name of the product associated with the plan
                ],
                'nickname' => $request->nickname, // Nickname for the plan (optional)
                'id' => $request->id, // Unique identifier for the plan
            ]);

            dump($plan->id);

            // Plan created successfully
            return response()->json(['message' => 'Plan created successfully', 'plan' => $plan], 201);
        } catch (\Exception $e) {
            // Plan creation failed
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    /**
     * Search for a value in an associative array and return the corresponding key.
     *
     * @param  mixed  $value
     * @param  array  $array
     * @return mixed|null
     */
    private function searchKeyByValue($value, $array)
    {
        foreach ($array as $key => $val) {
            if ($val === $value) {
                return $key;
            }
        }

        return null; // If value is not found in the array
    }

    /**
     * Check if a product with a specific name exists and return its ID.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    private function findProduct(Request $request)
    {
        try {
            // Your array of products with keys as product names and values as IDs
            $products = [
                'product1' => 'ID1',
                'product2' => 'ID2',
                'product3' => 'ID3',
            ];

            // Search for the value in the array and return the corresponding key (product name)
            $productName = $this->searchKeyByValue($request->name, $products);

            if ($productName !== null) {
                // Product with the specified name exists
                return response()->json(['message' => 'Product exists', 'product_id' => $products[$productName]], 200);
            } else {
                // Product with the specified name does not exist
                return response()->json(['message' => 'Product does not exist'], 404);
            }
        } catch (\Exception $e) {
            // Error occurred
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    private function checkProduct(Request $request)
    {
        // Set your Stripe API key
        Stripe::setApiKey(env('STRIPE_SECRET'));

        try {
            // Retrieve a list of products from Stripe
            $products = Product::all();

            // $product = $products->data->filter(function ($object) {
            //     return $object->name === 'Product_2000';
            // });

            $subscription_products = [];
            foreach ($products->data as $product) {
                $subscription_products[$product->id] = $product->name;
            }

            dump($subscription_products);

            dd($products->data);

            // Search for the product with the specified name
            $existingProduct = $products->data->first(function ($product) use ($request) {
                return $product->name === 'Product_2000';
            });

            if ($existingProduct) {
                // Product with the specified name exists
                return response()->json(['message' => 'Product exists', 'product' => $existingProduct], 200);
            } else {
                // Product with the specified name does not exist
                return response()->json(['message' => 'Product does not exist'], 404);
            }
        } catch (\Exception $e) {
            // Error occurred while retrieving products
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    public function index()
    {
        $user = $this->authenticatedUser();

        $filter_dependants = Dependant::where('user_id', $user->id)->get();

        $next_of_kin = $filter_dependants->filter(function ($object) {
            return $object->type === 'kin';
        });

        $dependants = $filter_dependants->filter(function ($object) {
            return $object->type === 'dependant';
        });

        $total_dependants = count($dependants);

        $total_payments = count(Payment::where('user_id', Auth::user()->id)->where('payment_type', 'registration')->get()) ?? 0;

        $regions = $this->regions();

        return view('dependant.list', compact('dependants', 'next_of_kin', 'total_dependants', 'regions', 'user', 'total_payments'));
    }

    public function create(): View
    {
        $user = Auth::user();

        return view('dependant.add', [
            'gender' => $this->salutation(),
            'statuses' => $this->relationship(),
            'user' => $this->authenticatedUser(),
            'classification' => $this->classification(),
        ]);
    }

    public function store(CreateDependantRequest $request, Dependant $dependant)
    {
        $filter_dependants = Dependant::where('user_id', $this->authenticatedUser()->id)->get();

        $next_of_kin = $filter_dependants->filter(function ($object) {
            return $object->relationship === 'kin';
        });

        $dependants = $filter_dependants->filter(function ($object) {
            return $object->relationship === 'dependant';
        });

        $total_dependants = count($dependants);

        // if ($total_dependants >= 3) {
        //     return redirect()->route('dependants')->withErrors(['error' => 'You have reached the maximum number of dependants allowed.']);
        // }

        $dependant->create($request->validated());
        return redirect()->route('dependants')->with('success', 'New record created successfully.');
    }

    public function membership(CreateMembershipRequest $request, User $user_id)
    {
        $user = $this->authenticatedUser();

        // Update region field if present in request
        if ($request->has('region')) {
            $user->region = $request->input('region');
        }

        $prefix = 'PAM';
        $numericPart = str_pad($user->id, 4, '0', STR_PAD_LEFT);
        $membershipNumber = $prefix . $numericPart;

        // Update membership_number field if present in request
        $user->membership_number = $membershipNumber;

        $user->save();

        return redirect()->route('dependants')->with('success', 'Membership Number generated successfully.');
    }

    private function classification() {
        return [
            '' => __('Please select ...'),
            'dependant' => __('Dependant'),
            'kin' => __('Next of Kin'),
        ];
    }

    private function authenticatedUser() {
        return Auth::user();
    }

    private function salutation() {
        return [
            '' => __('Please select ...'),
            "Mr" => __("Mr"),
            "Ms" => __("Ms"),
            "Mrs" => __("Mrs"),
            "Miss" => __("Miss"),
            "Dr" => __("Dr"),
            "Prof" => __("Prof"),
            "Rev" => __("Rev"),
            "Sir" => __("Sir"),
            "Madam" => __("Madam"),
            "Mx" => __("Mx"),
            "Other" => __("Other")
        ];
    }

    private function relationship() {
        return [
            "daughter" => __("Daughter"),
            "son" => __("Son"),
            "partner" => __("Spouse / Partner"),
            "parent" => __("Parent"),
            "sibling" => __("Sibling"),
        ];
    }

    private function regions() {
        return [
            '__' => __('Please select ...'),
            'LN' => 'London',
            'SE' => 'South East England',
            'SW' => 'South West England',
            'EE' => 'East of England',
            'NE' => 'North East of England',
            'WM' => 'West Midlands',
            'EM' => 'East Midlands',
            'YH' => 'Yorkshire and the Humber',
            'NW' => 'North West England',
            'WL' => 'Wales',
            'NI' => 'Northern Ireland',
            'SC' => 'Scotland',
        ];
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  \App\Models\Dependant  $dependant
     * @return \Illuminate\Http\Response
     */
    // public function edit(Dependant $dependant)
    // {

    //     return view('dependants.add', compact('dependant'));
    // }

    public function edit($id): View
    {
        // $firstParameterName = $request->query->keys()->first();
        $dependant = Dependant::find($id);

        return view('dependant.edit', [
            'id' => $id,
            'edit' => true,
            'gender' => $this->salutation(),
            'statuses' => $this->relationship(),
            'user' => $this->authenticatedUser(),
            'dependant' => $dependant,
            'classification' => $this->classification(),
        ]);
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \App\Http\Requests\DependantRequest  $request
     * @param  \App\Models\Dependant  $dependant
     * @return \Illuminate\Http\Response
     */
    public function update(Dependant $dependant, CreateDependantRequest $request)
    {
        $dependant = Dependant::find($request->id);

        if (!$dependant) {
            return response()->json(['message' => 'Dependant not found'], 404);
        }

        $dependant->first_name = $request->input('first_name');
        $dependant->middle_name = $request->input('middle_name');
        $dependant->last_name = $request->input('last_name');
        $dependant->relationship = $request->input('relationship');
        $dependant->gender = $request->input('gender');
        $dependant->type = $request->input('type');
        $dependant->birthday = $request->input('birthday');
        $dependant->information = $request->input('information');
        $dependant->terms = $request->input('terms');

        $dependant->save();

        return redirect()->route('dependants')->with('success', 'Dependant updated successfully.');
    }

     /**
     * Update the specified resource in storage.
     *
     * @param  \App\Http\Requests\DependantRequest  $request
     * @param  \App\Models\Dependant  $dependant
     * @return \Illuminate\Http\Response
     */
    public function destroy(Dependant $dependant)
    {
        if (!$dependant) {
            return response()->json(['message' => 'Dependant not found'], 404);
        }

        $dependant->delete();

        return redirect()->route('dependants')->with('success', 'Dependant deleted successfully.');
    }
}


<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Stripe\Stripe;
use Stripe\Product;

class SubscriptionController extends Controller
{
    /**
     * Search for a Stripe product by name and return the product object.
     *
     * @param  string  $productName
     * @return \Stripe\Product|null
     */
    public function searchProductByName($productName)
    {
        // Set your Stripe API key
        Stripe::setApiKey(env('STRIPE_SECRET'));

        try {
            // Retrieve a list of products from Stripe
            $products = Product::all();

            // Search for the product with the specified name
            foreach ($products->data as $product) {
                if ($product->name === $productName) {
                    return $product;
                }
            }

            return null; // If product with the specified name is not found
        } catch (\Exception $e) {
            // Error occurred while retrieving products
            return null;
        }
    }

    /**
     * Check if a product with a specific name exists and return its object.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function checkProduct(Request $request)
    {
        try {
            // Get the product name from the request
            $productName = $request->name;

            // Search for the product by name
            $product = $this->searchProductByName($productName);

            if ($product !== null) {
                // Product with the specified name exists
                return response()->json(['message' => 'Product exists', 'product' => $product], 200);
            } else {
                // Product with the specified name does not exist
                return response()->json(['message' => 'Product does not exist'], 404);
            }
        } catch (\Exception $e) {
            // Error occurred
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }
}



<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Stripe\Stripe;
use Stripe\Product;
use Stripe\Subscription;

class SubscriptionController extends Controller
{
    /**
     * Search for a Stripe product by name and return the product object.
     *
     * @param  string  $productName
     * @return \Stripe\Product|null
     */
    public function searchProductByName($productName)
    {
        // Set your Stripe API key
        Stripe::setApiKey(env('STRIPE_SECRET'));

        try {
            // Retrieve a list of products from Stripe
            $products = Product::all();

            // Search for the product with the specified name
            foreach ($products->data as $product) {
                if ($product->name === $productName) {
                    return $product;
                }
            }

            return null; // If product with the specified name is not found
        } catch (\Exception $e) {
            // Error occurred while retrieving products
            return null;
        }
    }

    /**
     * Subscribe the user to a product.
     *
     * @param  string  $productId
     * @param  string  $customerId
     * @return \Stripe\Subscription|null
     */
    public function subscribeUserToProduct($productId, $customerId)
    {
        try {
            // Subscribe the customer to the product
            $subscription = Subscription::create([
                'customer' => $customerId,
                'items' => [
                    [
                        'price' => $productId,
                    ],
                ],
            ]);

            return $subscription; // Return the subscription object
        } catch (\Exception $e) {
            // Error occurred while subscribing the user
            return null;
        }
    }

    /**
     * Check if a product with a specific name exists, if not create a new product and then subscribe the user.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function checkOrCreateAndSubscribe(Request $request)
    {
        try {
            // Get the product name from the request
            $productName = $request->name;

            // Search for the product by name
            $product = $this->searchProductByName($productName);

            if ($product === null) {
                // Product with the specified name does not exist, create a new product
                $newProduct = Product::create([
                    'name' => $productName,
                    // Add other product details here if needed
                ]);

                // Subscribe the user to the new product
                $subscription = $this->subscribeUserToProduct($newProduct->id, $request->user()->stripe_id);

                return response()->json(['message' => 'Product created and user subscribed successfully', 'subscription' => $subscription], 201);
            } else {
                // Product with the specified name exists, subscribe the user to it
                $subscription = $this->subscribeUserToProduct($product->id, $request->user()->stripe_id);

                return response()->json(['message' => 'User subscribed to existing product successfully', 'subscription' => $subscription], 200);
            }
        } catch (\Exception $e) {
            // Error occurred
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }
}


