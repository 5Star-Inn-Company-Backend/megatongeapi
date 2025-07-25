<?php

namespace App\Http\Controllers;

use App\Models\pricing;
use Exception;
use Carbon\Carbon;
use Illuminate\Support\Facades\Validator;
use Stripe\Stripe;
use App\Models\User;
use App\Models\history;
use Stripe\StripeClient;
use Illuminate\Http\Request;
use App\Models\stripepayment;
use Illuminate\Support\Facades\Auth;

class PaymentController extends Controller
{

    //stripe payment

    public function stripePayment(Request $request)
    {

        $request->validate([
            'subscriptionplan' => 'required',
            'description' => 'required',
        ]);

        try {
            $stripe = new StripeClient(
                env('STRIPE_SECRET'),
            );

            $res =   $stripe->tokens->create(array(
                "card" => array(
                    "number" => $request->number,
                    "exp_month" => $request->exp_month,
                    "exp_year" => $request->exp_year,
                    "cvc" => $request->cvc
                )
            ));



            Stripe::setApiKey(env('STRIPE_SECRET'));

            $response =  $stripe->charges->create([
                'amount' => $request->subscriptionplan,
                'currency' => 'myr',
                'source' => $res->id,
                'description' => $request->description,
            ]);

            $subscriptionplan = ""; // Initialize as an integer

            if ($request->subscriptionplan == 2400) {

                $subscriptionplan = "Silver"; // Convert $24 to cents (24 * 100)
            } elseif ($request->subscriptionplan == 4800) {

                $subscriptionplan = "Gold"; // Convert $48 to cents (48 * 100)
            } else {
                $subscriptionplan = "Free";
            }

            $stripepay = new stripepayment();
            $stripepay->user_id = Auth::user()->id;
            $stripepay->email = Auth::user()->email;
            $stripepay->payment_type = $res->type;
            $stripepay->payment_method = 'Stripe';
            $stripepay->payment_id = $res->id;
            $stripepay->subscriptionplan = $subscriptionplan;
            $stripepay->currency = 'USD';
            $stripepay->description = $request->description;
            $stripepay->dateofpayment = Carbon::now();
            $stripepay->save();

            return response()->json([
                'status' => $response->status,
                'message' => 'Payment Integration was Successful',
                'data' => $res
            ], 201);
        } catch (Exception  $e) {
            return response()->json([
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    //for paystack payment gateway

    public function paystackpayment(Request $request)
    {

        // Validate the request data
        $validator = Validator::make($request->all(), [
            'plan' => 'required|int',
        ]);

        if ($validator->fails()) {
            return response()->json([
                "status_code" => 422,
                "message" => implode(",",$validator->errors()->all()),
                "errors" => $validator->errors(),
            ]);
        }
        $price = pricing::find($request->plan);

        if(!$price){
            return response()->json([
                "status_code" => 422,
                "message" => "Invalid Plan ID"
            ]);
        }


        if($price->amount == 0){
            return response()->json([
                "status_code" => 422,
                "message" => "You cannot pay on free plan"
            ]);
        }

        $amount = $price->amount * 100;

        // Read the Paystack secret key from .env
        $secretKey = env('PAYSTACK_SECRET_KEY');

        $url = "https://api.paystack.co/transaction/initialize";


        $fields = [
            'email' => Auth::user()->email,
            'amount' => $amount,
            'callback_url' => route('handlePaymentCallback'),
            "metadata" => [
                "custom_fields" =>[
                    [
                    "display_name" => "From",
                    "variable_name" => "from",
                    "value" => "Megatongue"
                  ],
                    [
                    "display_name" => "mtplan",
                    "variable_name" => "mtplan",
                    "value" => "$request->plan"
                  ]
                ]
            ]
        ];

        $fields_string = http_build_query($fields);

        // Initialize cURL session
        $ch = curl_init();

        // Set cURL options
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $fields_string);
        curl_setopt($ch, CURLOPT_HTTPHEADER, array(
            "Authorization: Bearer $secretKey",
            "Cache-Control: no-cache",
        ));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);

        // Execute the cURL request
        $result = curl_exec($ch);

        // Check for cURL errors
        if (curl_errno($ch)) {
            return response()->json(['error' => 'Curl error: ' . curl_error($ch)], 500);
        }

        // Close the cURL session
        curl_close($ch);

        // Decode the JSON response
        $decodedResult = json_decode($result, true);

        // Check for JSON decoding errors
        if (json_last_error() !== JSON_ERROR_NONE) {
            return response()->json(['error' => 'JSON decoding error: ' . json_last_error_msg()], 500);
        }

        // Return the decoded JSON response
        return response()->json($decodedResult);
    }

    public function handlePaymentCallback(Request $request)
    {
        $secretKey = env('PAYSTACK_SECRET_KEY');
        // Extract the reference from the request
        $reference = $request->input('reference');

        if (empty($reference)) {
            return response()->json(['error' => 'Reference is required'], 400);
        }

        // Make an API request to Paystack to verify the payment status using the reference
        $verificationUrl = "https://api.paystack.co/transaction/verify/$reference";

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $verificationUrl);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            "Authorization: Bearer $secretKey",
            "Cache-Control: no-cache",
        ]);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);

        $verificationResult = curl_exec($ch);

        if (curl_errno($ch)) {
            return response()->json(['error' => 'Curl error: ' . curl_error($ch)], 500);
        }

        curl_close($ch);

        $decodedVerificationResult = json_decode($verificationResult, true);

        if (json_last_error() !== JSON_ERROR_NONE || !$decodedVerificationResult['status']) {
            return response()->json(['error' => 'Payment verification failed'], 500);
        }

        // Extract relevant payment details from the Paystack verification response
        $paymentData = $decodedVerificationResult['data'];

        $subscriptionplan = $paymentData['metadata']['custom_fields'][1]['value'];


        // Find the authenticated user's ID based on their email
        $user = User::where('email', $paymentData['customer']['email'])->first();

        if (!$user) {
            return response()->json(['error' => 'User not found'], 404);
        }

        $user->plan=$subscriptionplan;
        $user->save();


        // Update the stripepayment table with payment details
        StripePayment::create([
            'user_id' => $user->id,
            'email' => $paymentData['customer']['email'],
            'payment_id' => $paymentData['id'],
            'reference_code' => $paymentData['reference'],
            'payment_method' => 'paystack',
            'subscriptionplan' =>  $subscriptionplan,
            'currency' => $paymentData['authorization']['country_code'],
            'dateofpayment' => Carbon::now(),
            'payment_type' => $paymentData['authorization']['channel'],
        ]);

        return redirect()->away(env('FRONTEND_URL').'/app/pricing');

        // Return a JSON response with payment details
//        return response()->json([
//            'message' => 'Payment successful',
//            'payment_type' => $paymentData
//        ]);
    }

    public function getpaymentmethod()
    {
        $getpayment = stripepayment::where('user_id', Auth::user()->id)->first();

        if ($getpayment) {
            return response()->json([
                "status" => true,
                "message" => $getpayment->payment_method
            ], 200);
        } else {
            return response()->json([
                "status" => true,
                "message" => "You dont have a payment method, you are probably on free mode"
            ], 200);
        }
    }

    public function getsubscribplan()
    {
        $userId = Auth::user()->id;
        $getpayment = stripepayment::where('user_id', $userId)->latest()->first(); // Use first() instead of get()

        if ($getpayment) {
            // Calculate the renew date by adding one month to the dateofpayment
            $renewDate = Carbon::parse($getpayment->dateofpayment)->addMonth();

            // Formating the billing period
            $billingPeriod = [
                'start' => Carbon::parse($getpayment->dateofpayment)->format('Y-m-d H:i:s'),
                'end' => $renewDate->format('Y-m-d H:i:s'),
            ];

            $apiusage = history::where('user_id', $userId);

            $pricing=pricing::find($getpayment->subscriptionplan);

            return response()->json([
                "status" => true,
                "subscription_plan" => strtoupper($pricing->name),
                "renew" => $renewDate->format('Y-m-d H:i:s'),
                'billing_period' => $billingPeriod,
                'api_usage' => $apiusage->count()
            ], 200);
        } else {
            $pricing=pricing::find(Auth::user()->plan);
            $billingPeriod = [
                'start' => Carbon::now()->startOfMonth(),
                'end' => Carbon::now()->endOfMonth(),
            ];
            return response()->json([
                "status" => true,
                "message" => "You don't have a payment method, you are probably on free mode",
                "subscription_plan" => strtoupper($pricing->name),
                'billing_period' => $billingPeriod,
            ], 200);
        }
    }
}
