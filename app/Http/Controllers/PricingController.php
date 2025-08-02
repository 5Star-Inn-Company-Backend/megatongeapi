<?php

namespace App\Http\Controllers;

use App\Models\Newsletter;
use App\Models\pricing;
use Illuminate\Http\Request;

class PricingController extends Controller
{
    public function pricing(Request $request)
    {
        $request->validate([
            "name" => "required",
            "amount" => "required",
            "description" => "required",
        ]);

        $price = new pricing;
        $price->name = $request->name;
        $price->amount = $request->amount;
        $price->description = $request->description;
        $price->mode = $request->mode;
        $price->save();

        return response()->json([
            "statusCode" => 200,
            "message" => "Price has been updated successfully",
        ]);
    }

    public function index(Request $request)
    {
        $price = pricing::get();

        return response()->json([
            "statusCode" => 200,
            "message" => $price,
        ]);
    }


    public function newsletter(Request $request)
    {
        $request->validate([
            "email" => "required"
        ]);

        if(Newsletter::where('email', $request->email)->exists()){
            return response()->json([
                "statusCode" => 400,
                "message" => "You have already subscribed to our Newsletter",
            ]);
        }

        $nw = new Newsletter;
        $nw->email = $request->email;
        $nw->save();

        return response()->json([
            "statusCode" => 200,
            "message" => "You have successfully subscribed to our Newsletter",
            "data" => $nw
        ]);
    }


}
