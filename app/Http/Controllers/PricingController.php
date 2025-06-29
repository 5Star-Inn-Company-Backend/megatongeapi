<?php

namespace App\Http\Controllers;

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

}
