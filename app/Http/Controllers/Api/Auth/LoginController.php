<?php

namespace App\Http\Controllers\Api\Auth;

use App\Http\Controllers\Controller;
use App\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Laravel\Passport\Client;

class LoginController extends Controller
{
    private $client;

    public function __construct()
    {
        $this->client=Client::find(2);
    }

    public function login(Request $request)
    {
        $this->validate($request,[
            'username'=>'required',
            'password'=>'required',

        ]);

        $params=[
            'grant_type' => 'password',
            'client_id' => $this->client->id,
            'client_secret' =>$this->client->secret,
            'username'=>request('username'),
            'password'=>request('password'),
            'scope'=>'*'
        ];

        $request->request->add($params);

        $proxy=Request::create('oauth/token','POST');

        return Route::dispatch($proxy);
    }

    public function refresh(Request $request)
    {
        $this->validate($request,[
            'refresh_token'=>'required',
        ]);
        $params=[
            'grant_type' => 'refresh_token',
            'client_id' => $this->client->id,
            'client_secret' =>$this->client->secret,
            'username'=>request('username'),
            'password'=>request('password'),
            'scope'=>'*'
        ];

        $request->request->add($params);

        $proxy=Request::create('oauth/token','POST');

        return Route::dispatch($proxy);


    }

    public function logout(Request $request)
    {
        $accessTokens=Auth::user()->token();
        DB::table('oauth_refresh_tokens')
            ->where('access_token_id',$accessTokens->id)
            ->where(['revoked'=>true]);
        $accessTokens->revoke();

        return response()->json([],204);
    }


    public function recoverPassword(Request $request)
    {
        $this->validate($request,[
            'email'=>'required'
        ]);

        //send an email to the user with the password then change the password of the user to the confirmation code
        $password=PasswordGenerator::createPassword();
        $user= User::where('email',request('email'))->first();

        if ($user==null){
            //no such email exist in the database

            return response()->json([
                'error'=>'The email does not exist in the system'
            ],422);
        }else{
            $user->password=bcrypt($password);
            $user->save();

            $user->notify(new RecoverPasswordNotification($password));

            return response()->json([
                'success'=>'The Password has been successfully sent to the email',
                'code'=>$password
            ]);
        }

    }


    public function changePassword(Request $request)
    {

        $this->validate($request,[
            'password'=>'required',
        ]);

        $user=Auth::user();
        $user->password=bcrypt($request->password);
        $user->save();


        return response()->json();
//        $this->refresh($request);

    }
}
