<?php

namespace App\Http\Controllers\Api\Auth;

use App\Http\Controllers\Controller;
use App\Services\SocialUserResolver;
use App\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Laravel\Passport\Client;
use Laravel\Socialite\Facades\Socialite;



use Carbon\Carbon;
use Illuminate\Events\Dispatcher;
use Laravel\Passport\Bridge\AccessToken;
use Laravel\Passport\Bridge\AccessTokenRepository;
use Laravel\Passport\Bridge\Scope;
use Laravel\Passport\TokenRepository;
use League\OAuth2\Server\CryptKey;
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


    public function redirectToProvider($provider)
    {
        return Socialite::driver($provider)->redirect();
    }

    /**
     * Obtain the user information from GitHub.
     *
     * @return \Illuminate\Http\Response
     */
    public function handleProviderCallback($provider)
    {

        $user = Socialite::driver($provider)->user();

        $savedUser=(new SocialUserResolver())->resolveUserByProviderCredentials($provider,$user->token);

        if ($savedUser==null){
            return  response()->json('The user was not found in the system',404);
        }else{
            //the user was found, use the user to get the access token


            //determine if the access token already exist
            $token = AccessToken::where('user_id', $savedUser->id)->where('expires_at', '>', Carbon::now())
                ->orderBy('created_at', 'desc')->first();

            if ($token===null){

                $user = $savedUser;
                $token = new AccessToken($user->id);
                $token->setIdentifier(generateUniqueIdentifier());
                $token->setClient(new Client(2, null, null));
                $token->setExpiryDateTime(Carbon::now()->addYear());
                $token->addScope(new Scope('activity'));
                $privateKey = new CryptKey('file://'.storage_path('oauth-private.key'));

                $accessTokenRepository = new AccessTokenRepository(new TokenRepository, new Dispatcher);
                $accessTokenRepository->persistNewAccessToken($token);
                $jwtAccessToken = $token->convertToJWT($privateKey);


            }else{
                $jwtAccessToken = $token->convertToJWT();


            }

            $responseParams = [
                'token_type'   => 'Bearer',
                'expires_in'   => 31622400,
                'access_token' => (string) $jwtAccessToken,
                'user'         => $user->toArray()
            ];


            return response()->json( $responseParams);
        }
    }
}
