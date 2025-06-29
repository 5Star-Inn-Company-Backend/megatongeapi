<?php

use App\Http\Controllers\MegaController;
use App\Http\Controllers\PaymentController;
use App\Http\Controllers\PricingController;
use App\Http\Controllers\TranslatorController;
use App\Http\Controllers\UserController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');


Route::post('register', [UserController::class, 'register']);
Route::post('login', [UserController::class, 'login']);
Route::post('forgotpass', [UserController::class, 'forgotPassword']);
Route::get('resetpass', [UserController::class, 'resetpass']);
Route::post('updatepassword', [UserController::class, 'updatepass']);

Route::get("languages", [TranslatorController::class, 'languages']);

Route::middleware(['throttle:freeTranslation'])->group(function () {
    Route::post("free-translator", [TranslatorController::class, 'translator']);
    Route::post('free-translatefile', [TranslatorController::class, 'translatefile']);
});


Route::post('/translatetext', function (Request $request) {
    event(new TranslationEvent($request->input('text')));

    return response()->json(['message' => 'Text sent for translation']);
});

Route::get('/clear', function (Request $request) {
    \Illuminate\Support\Facades\Artisan::call('optimize:clear');
    \Illuminate\Support\Facades\Artisan::call('config:clear');
    return \Illuminate\Support\Facades\Artisan::call('cache:clear');
});

Route::get('pricing', [PricingController::class, 'index']);

Route::group(['middleware' => 'auth:sanctum'], function (){
    Route::post('apikey', [MegaController::class, 'apikey']);
    Route::post('pricing', [PricingController::class, 'pricing']);
    Route::post('addreview', [MegaController::class, "addreview"]);
    Route::get('getreviews', [MegaController::class, 'getreviews']);
    Route::get('getapiusage', [MegaController::class, 'getapiusage']);
    Route::get('getapikey', [MegaController::class, 'getapikey']);
    Route::get('getfaq', [MegaController::class, 'getfaq']);
    Route::get('getuserinfo', [UserController::class, 'getuserinfo']);
    Route::post('updateuserinfo', [UserController::class, 'updateuserinfo']);

    //for payment integration

    Route::post('stripePayment', [PaymentController::class, 'stripePayment']);
    Route::post('paystackpayment', [PaymentController::class, 'paystackpayment']);
    Route::post('handlePaymentCallback', [PaymentController::class,'handlePaymentCallback']);

    Route::get('getpaymentmethod', [PaymentController::class, 'getpaymentmethod']);
    Route::get('getsubscribplan', [PaymentController::class, 'getsubscribplan']);

    Route::post("translator", [MegaController::class, 'translator']);
    Route::post('translatefile', [MegaController::class, 'translatefile']);
});
