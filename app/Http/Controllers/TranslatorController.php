<?php

namespace App\Http\Controllers;

use App\Models\history;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class TranslatorController extends Controller
{
    public function languages(Request $request)
    {

        $curl = curl_init();

        curl_setopt_array($curl, array(
            CURLOPT_URL => env('TRANSLATOR_BASEURL', 'http://translator.cheapmailing.com.ng') . '/languages',
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => '',
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 0,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => 'GET',
            CURLOPT_HTTPHEADER => array(
                'Content-Type: application/json',
                'Cookie: session=edb08a19-057b-46e5-bd9e-00346901cf2e'
            ),
        ));

        $response = curl_exec($curl);

        curl_close($curl);

        $decoded_response = json_decode($response, true);

// Loop through each item and remove the "targets" key
        foreach ($decoded_response as &$item) {
            unset($item['targets']);
        }

        return response()->json([
            "status_code" => 200,
            "message" => $decoded_response,
        ]);
    }
    public function translator(Request $request)
    {

        // Validate the request data
        $validator = Validator::make($request->all(), [
            'q' => 'required',
            'source' => 'required',
            'target' => 'required',
            'format' => 'required',
        ]);

        if ($validator->fails()) {
            return response()->json([
                "status_code" => 422,
                "message" => "Validation failed",
                "errors" => $validator->errors(),
            ]);
        }

        $data = array(
            "q" => $request->q,
            "source" => $request->source,
            "target" => $request->target,
            "format" => $request->get('format')
        );

        $json_data = json_encode($data);

        $curl = curl_init();

        curl_setopt_array($curl, array(
            CURLOPT_URL => env('TRANSLATOR_BASEURL', 'http://translator.cheapmailing.com.ng') . '/translate',
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => '',
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 0,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => 'POST',
            CURLOPT_POSTFIELDS => $json_data,
            CURLOPT_HTTPHEADER => array(
                'Content-Type: application/json',
                'Cookie: session=edb08a19-057b-46e5-bd9e-00346901cf2e'
            ),
        ));

        $response = curl_exec($curl);

        curl_close($curl);

        $decoded_response = json_decode($response, true);

        // Check if the decoded_response contains the 'translatedText' key
        if (isset($decoded_response['translatedText'])) {
            $translated_text = $decoded_response['translatedText'];
        } else {
            $translated_text = 'Translation not available.';
        }

        $history = new history;
        $history->user_id = 0;
        $history->text = $request->q;
        $history->source_language = $request->source;
        $history->destination_language = $request->target;
        $history->format = $request->get('format');
        $history->response = $translated_text;

//         $user->history()->save($history);

        if ($history->save()) {
            return response()->json([
                "status_code" => 200,
                "message" => $translated_text,
            ]);
        } else {
            return response()->json([
                "status_code" => 422,
                "message" => "Error",
            ]);
        }
    }

    public function translatefile(Request $request)
    {

        // Validate the request data
        $validator = Validator::make($request->all(), [
            'file' => 'required|file',
            'source' => 'required',
            'target' => 'required'
        ]);

        if ($validator->fails()) {
            return response()->json([
                "status_code" => 422,
                "message" => "Validation failed",
                "errors" => $validator->errors(),
            ]);
        }

        if (!$request->hasfile('file')) {
            return response()->json([
                "status_code" => 422,
                "message" => "Validation failed. Kindly upload a valid file.",
                "errors" => "Valid File required",
            ]);
        }

        return response()->json([
            "status_code" => 403,
            "message" => "Kindly login to use this",
            "errors" => "Account required",
        ]);

        $file = $request->file('file');
        $filePath = $file->getPathname();
        $fileMimeType = $file->getMimeType();
        $fileName = $file->getClientOriginalName();

        $ref=rand()."_".$fileName;
        $request->file('file')->storeAs($ref);

        $filedata = [
            "file" => new \CURLFile($filePath, $fileMimeType, $fileName),
            "source" => $request->source,
            "target" => $request->target
        ];

        $curl = curl_init();

        curl_setopt_array($curl, array(
            CURLOPT_URL => env('TRANSLATOR_BASEURL', 'http://translator.cheapmailing.com.ng') . '/translate_file',
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => '',
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 0,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => 'POST',
            CURLOPT_POSTFIELDS => $filedata,
            CURLOPT_HTTPHEADER => array(
                'Content-Type: multipart/form-data',
                'Cookie: session=edb08a19-057b-46e5-bd9e-00346901cf2e'
            ),
        ));

        $response = curl_exec($curl);

        curl_close($curl);

        $decoded_response = json_decode($response, true);

        // Check if the decoded_response contains the 'translatedText' key
        if (isset($decoded_response['translatedFileUrl'])) {
            $translated_text = file_get_contents($decoded_response['translatedFileUrl']);
        } else {
            $translated_text = 'Translation not available.';
        }

        $data = new history;
        $data->user_id = 0;
        $data->text = $ref;
        $data->source_language = $request->source;
        $data->destination_language = $request->target;
        $data->format = "file";
        $data->response = $decoded_response['translatedFileUrl'];

//             $user->history()->save($data);


        if ($data->save()) {
            return response()->json([
                "status_code" => 200,
                "message" => $translated_text
            ]);
        } else {
            return response()->json([
                "status_code" => 422,
                "message" => "Error",
            ]);
        }

    }


}
