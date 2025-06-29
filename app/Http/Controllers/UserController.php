<?php

namespace App\Http\Controllers;

use Carbon\Carbon;
use App\Models\User;
use Illuminate\Support\Str;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use App\Models\password_reset_token;

class UserController extends Controller
{
    public function register(Request $request)
    {
        $request->validate([
            'firstname' => 'required',
            'lastname' => 'required',
            'email' => 'required|email',
            'password' => 'required|min:6'
        ]);

        $duplicate = User::where('email', $request->email)->first();
        if ($duplicate) {
            return response()->json(["status_code" => 422, "message" => "Email already exists"]);
        }

        $create = User::create([
            'firstname' => $request->firstname,
            'lastname' => $request->lastname,
            'email' => $request->email,
            'password' => Hash::make($request->password)
        ]);


        if ($create) {
            return response()->json([
                "statuscode" => 200,
                "message" => "Registration successful",
            ]);
        } else {
            return response()->json([
                "statuscode" => 422,
                "message" => "Registration failed",
            ]);
        }
    }

    public function login(Request $request)
    {
        $request->validate([
            'email' => 'required|email',
            'password' => 'required',
        ]); {
            $request->validate([
                'email' => 'required|email',
                'password' => 'required|string',
            ]);

            $credentials = request(['email', 'password']);

            if (!Auth::attempt($credentials)) {
                return response()->json(["status" => false, 'message' => 'Wrong password or email!'], 401);
            }

            $user = $request->user();
            $tokenresult = $user->createToken('Personal Access Token');
            $token = $tokenresult->plainTextToken;
            $expires_at = Carbon::now()->addweeks(1);

            return response()->json(["status" => true, 'data' => [
                'user' => Auth::user(),
                'access_token' => $token,
                'token_type' => 'Bearer',
                'expires_at' => $expires_at,
            ]]);
        }
    }


    public function forgotPassword(Request $request)
    {
        try {
            $user = User::where('email', $request->email)->first();

            if ($user) {
                $token = Str::random(40);
                $domain = URL::to('/');
                $url = $domain . '/resetpass?token=' . $token;

                $data['url'] = $url;
                $data['email'] = $request->email;
                $data['title'] = 'Password Reset';
                $data['body'] = 'Please click on the link below to reset your password!';

                Mail::send('resetpassmail', ['data' => $data], function ($message) use ($data) {
                    $message->to($data['email'])->subject($data['title']);
                });

                $datetime = Carbon::now()->format('Y-m-d H:i:s');

                // password_reset_token::updateOrCreate(
                //     ['email' => $request->email, 'token' => $token],
                //     [
                //         'email' => $request->email,
                //         'token' => $token,
                //         'created_at' => $datetime,
                //     ]
                // );

                $passreset = new password_reset_token;
                $passreset->email = $request->email;
                $passreset->token = $token;
                $passreset->created_at = $datetime;
                $passreset->save();

                return response()->json([
                    'status' => true,
                    'message' => 'Please check your email to reset your password!',
                ]);
            } else {
                return response()->json([
                    'status' => false,
                    'message' => 'User not found!',
                ]);
            }
        } catch (\Exception $e) {
            return response()->json([
                'status' => false,
                'message' => $e->getMessage(),
            ]);
        }
    }

    public function resetpass(Request $request)
    {
        if (isset($request->token)) {
            $resetData = Password_reset_token::where('token', $request->token)->first();

            if ($resetData) {
                $user = User::where('email', $resetData->email)->first();
                // dd($user['email']);
                return view('reset_password', compact('user'));
            } else {
                return 'Token not found';
            }
        } else {
            return 'No token provided';
        }
    }

    public function updatepass(Request $request)
    {
        $request->validate([
            'password' => 'required|string|min:6',
            'confirm_pass' => 'required|same:password',
        ]);

        $user = User::where('email',$request->email)->first();
        // dd($request->email);
        if($user)
        {
            $user->update([
                'password' => Hash::make($request->password),
            ]);
            password_reset_token::where('email', $user->email)->delete();
        }
        return "<h1>Your Password Reset was Successful!</h1>";

    }

      //for user info

      public function getuserinfo()
      {
          $userinfo = User::find(Auth::user()->id)->get();
  
          foreach($userinfo as $userinfos)    {
              $notifi = [];
  
              if($userinfos->notification == 1)
              {
                  $notifi[] = "subscribe";
              }else{
                  $notifi[] = "unsubscribe";
              }
          }
  
          if($userinfos)
          {
              return response()->json([
                  "status" => true,
                  "firstname" =>  $userinfos->firstname,
                  "lastname" => $userinfos->lastname,
                  "email" => $userinfos->email,
                  "company" => $userinfos->company,
                  "notification" => $notifi
              ]);
          }
      }

      public function updateuserinfo(Request $request)
      {
          $userId = Auth::user()->id;
          $updateuser = User::find($userId);

          $fillableCoulums = [
              'firstname', 'lastname', 'email', 'address',
              'phone', 'city', 'state', 'company',
              'website', 'notification', 'recieve_invoice'
          ];
      
          // Loop through the fillable fields and update them if present in the request
          foreach ($fillableCoulums as $coulum) {
              if ($request->has($coulum)) {
                  $updateuser->$coulum = $request->$coulum;
              }
          }
      
          if ($updateuser->save()) {
              return response()->json([
                  "status" => true,
                  "message" => "Profile has been updated successfully"
              ], 200);
          } else {
              return response()->json([
                  "status" => false,
                  "message" => "Unable to update profile"
              ], 422);
          }
      }
      
      
}
