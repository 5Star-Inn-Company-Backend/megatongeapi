<?php

namespace App\Http\Controllers;

use App\Models\faq;
use App\Models\User;
use App\Models\review;
use App\Models\history;
use App\Models\pricing;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Http\Request;
use OpenApi\Annotations as OA;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;

/**
 * @OA\Info(title="MegaTongue Api Documentation", version="1.0.0")
 */
class MegaController extends Controller
{
    public function apikey(Request $request)
    {
        $user = User::find(Auth::user()->id);
        $apikey = Str::random(40);
        if ($user) {
            $user->api_key = $apikey;
            $user->update();

            return response()->json([
                'statusCode' => true,
                'message' => 'Apikey has been created successfully',
            ]);
        };
    }


    /**
     * @OA\Post(
     *     path="/translator",
     *     summary="Translate Text",
     *     description="Translate text from one language to another.",
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             @OA\Property(property="q", type="string", description="Text to be translated."),
     *             @OA\Property(property="source", type="string", description="Source language code."),
     *             @OA\Property(property="target", type="string", description="Target language code."),
     *             @OA\Property(property="format", type="string", description="Translation format.")
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Successful translation.",
     *         @OA\JsonContent(
     *             @OA\Property(property="status_code", type="integer", example=200),
     *             @OA\Property(property="message", type="string", example="Translation successful"),
     *             @OA\Property(property="translated_text", type="string", example="Translated text goes here")
     *         )
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="Error in translation.",
     *         @OA\JsonContent(
     *             @OA\Property(property="status_code", type="integer", example=422),
     *             @OA\Property(property="message", type="string", example="Translation error")
     *         )
     *     )
     * )
     */

    public function translator(Request $request)
    {
        // Get the API key from the request header or authorization bearer token
        $apiKey = $request->header('apikey'); // Adjust the header name as needed

        if (empty($apiKey)) {
            return response()->json([
                "status_code" => 422,
                "message" => "Please provide your API key in the header or as a bearer token."
            ]);
        }

        // Verify the API key against the keys stored in the users' table
        $user = User::where('api_key', $apiKey)->first();

        if (!$user) {
            return response()->json([
                "status_code" => 401,
                "message" => "Invalid API key."
            ]);
        }

        if ($user->plan == 1) {
            return response()->json([
                "status_code" => 401,
                "message" => "API usage not available in free mode."
            ]);
        }

        if($user->plan < 3) {
            $apiusage = history::where('user_id', $user->id)->whereBetween('created_at', [
                Carbon::now()->startOfMonth(), Carbon::now()->endOfMonth(),
            ])->count();
            $pricing = pricing::find($user->plan);


            if ($apiusage > $pricing->max_limit) {
                return response()->json([
                    "status_code" => 401,
                    "message" => "API usage limit exceeded."
                ]);
            }
        }

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
            CURLOPT_SSL_VERIFYHOST => false,
            CURLOPT_SSL_VERIFYPEER => false,
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
        $history->text = $request->q;
        $history->source_language = $request->source;
        $history->destination_language = $request->target;
        $history->format = $request->get('format');
        $history->response = $translated_text;

//         $user->history()->save($history);

        if ($user->history()->save($history)) {
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


    public function validateArrayData($data)
    {
        foreach ($data as $key => $values) {
            foreach ($values as $value) {
                if (is_null($value) || $value == '') {
                    unset($data[$key]);
                }
            }
        }
        return $data;
    }

    /**
     * @OA\Post(
     *     path="/api/translatefile",
     *     summary="Translate and save DOC file content",
     *     tags={"Translation"},
     *     @OA\RequestBody(
     *         description="DOC File to Translate",
     *         required=true,
     *         @OA\MediaType(
     *             mediaType="multipart/form-data",
     *             @OA\Schema(
     *                 @OA\Property(
     *                     property="csvfile",
     *                     description="DOC file to translate",
     *                     type="file",
     *                 ),
     *             ),
     *         ),
     *     ),
     *     @OA\Response(
     *         response="200",
     *         description="DOC file translated and saved successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="statusCode", type="integer", example=200),
     *             @OA\Property(property="message", type="string", example="CSV file translated and saved."),
     *         ),
     *     ),
     *     @OA\Response(
     *         response="422",
     *         description="Unprocessable Entity",
     *         @OA\JsonContent(
     *             @OA\Property(property="statusCode", type="integer", example=422),
     *             @OA\Property(property="message", type="string", example="Error message if applicable"),
     *         ),
     *     ),
     * )
     */

    public function translatefile(Request $request)
    {
        // Get the API key from the request header or authorization bearer token
        $apiKey = $request->header('apikey'); // Adjust the header name as needed

        if (empty($apiKey)) {
            return response()->json([
                "status_code" => 422,
                "message" => "Please provide your API key in the header or as a bearer token."
            ]);
        }

        // Verify the API key against the keys stored in the users' table
        $user = User::where('api_key', $apiKey)->first();

        if (!$user) {
            return response()->json([
                "status_code" => 401,
                "message" => "Invalid API key."
            ]);
        }


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
            CURLOPT_URL => env('TRANSLATOR_BASEURL') . '/translate_file',
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => '',
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 0,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => 'POST',
            CURLOPT_POSTFIELDS => $filedata,
            CURLOPT_SSL_VERIFYHOST => false,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_HTTPHEADER => array(
                'Content-Type: multipart/form-data',
                'Cookie: session=edb08a19-057b-46e5-bd9e-00346901cf2e'
            ),
        ));

        $response = curl_exec($curl);

        curl_close($curl);

        Log::info(json_encode($filedata)." - File Translation Response: " . $response);

        $decoded_response = json_decode($response, true);

        if (isset($decoded_response['error'])){
            return response()->json([
                "status_code" => 422,
                "message" => $decoded_response['error'].". Supported file formats: .txt, .odt, .odp, .docx, .pptx, .epub, .html",
            ]);
        }

        // Check if the decoded_response contains the 'translatedText' key
        if (isset($decoded_response['translatedFileUrl'])) {
            $translated_text = str_replace(env('TRANSLATOR_BASEURL_D'),env('APP_URL'),$decoded_response['translatedFileUrl']);
        } else {
            $translated_text = 'Translation not available.';
        }

        $data = new history;
        $data->text = $ref;
        $data->source_language = $request->source;
        $data->destination_language = $request->target;
        $data->format = "file";
        $data->response = $translated_text;

        if ($user->history()->save($data)) {
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


    //for reviews

    public function addreview(Request $request)
    {
        $add = review::create([
            "user_id" => Auth::user()->id,
            'review' => $request->review,
        ]);

        if ($add) {
            return response()->json([
                "status" => true,
                "message" => "Added review",
            ], 200);
        } else {
            return response()->json([
                "status" => false,
                "message" => "error",
            ], 422);
        }
    }

    public function getreviews(Request $request)
    {
        $getreview = review::all();
        if ($getreview) {
            return response()->json([
                "status" => true,
                "message" => $getreview,
            ], 200);
        } else {
            return response()->json([
                "status" => 200,
                "message" => "No Review yet!",
            ], 200);
        }
    }
    /**
     * @OA\Get(
     *     path="/getapiusage",
     *     summary="Get API Usage History",
     *     description="Retrieve the history of API usage (entire data in the history table).",
     *     @OA\Response(
     *         response=200,
     *         description="Successful operation.",
     *         @OA\JsonContent(
     *             @OA\Property(property="status_code", type="integer", example=200),
     *             @OA\Property(property="message", type="string", example="API usage history retrieved successfully")
     *         )
     *     )
     * )
     */

    //for api
    public function getapiusage(Request $request)
    {
        $userId = Auth::user()->id;
        $apiusage = history::where('user_id', $userId)->whereBetween('created_at',[
            Carbon::now()->startOfMonth(),Carbon::now()->endOfMonth(),
        ])->count();
        $pricing=pricing::find(Auth::user()->plan);

        return response()->json([
            "status" => true,
            "message" => $apiusage,
            "limit" => $pricing->max_limit == 0 ? "Unlimited" : $pricing->max_limit,
            "date" => Carbon::now()->startOfMonth()
        ], 200);
    }


    public function getapikey()
    {
        $apikey=Auth::user()->api_key;

        if ($apikey) {
            return response()->json([
                "status" => true,
                "message" => $apikey,
            ], 200);
        } else {
            return response()->json([
                "status" => true,
                "message" => "You do not have Api Access key, You can request for it!",
            ], 200);
        }
    }

    public function getfaq()
    {
        $faqs = faq::all();

        if ($faqs) {
            return response()->json([
                "status" => true,
                "message" => $faqs,
            ], 200);
        } else {
            return response()->json([
                "status" => 200,
                "message" => "No Faq yet!",
            ], 200);
        }
    }

    function file_get_contents_curl($url) {
        $ch = curl_init();

        curl_setopt($ch, CURLOPT_AUTOREFERER, TRUE);
        curl_setopt($ch, CURLOPT_HEADER, 0);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, TRUE);

        $data = curl_exec($ch);
        curl_close($ch);

        return $data;
    }

}
