<?php

use App\Http\Controllers\PaymentController;
use App\Http\Controllers\UserController;
use App\Models\history;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

Route::get('/login', function () {
    return view('welcome');
})->name('login');


Route::get('resetpass', [UserController::class, 'resetpass']);
Route::post('updatepassword', [UserController::class, 'updatepass']);

Route::get('handlePaymentCallback', [PaymentController::class,'handlePaymentCallback'])->name('handlePaymentCallback');


Route::get('/download_file/{filename}', function ($filename) {

    $path = env('TRANSLATOR_BASEURL').'/download_file/'.$filename;

    $filenam = explode("/", $path);
    $filenam = end($filenam);


    try {
        $mime = 'application/force-download';

        header('Content-Type: ' . $mime);

        header('Content-Description: File Transfer');
        header('Content-Disposition: attachment; filename=' . $filenam);
        header('Content-Transfer-Encoding: binary');
        header('Expires: 0');
        header('Cache-Control: must-revalidate');
        header('Pragma: public');
        ob_clean();
        flush();
        readfile($path);
    } catch (Exception $e) {
        echo "File not found";
    }


})->name('download.translatedfile');

