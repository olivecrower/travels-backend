<?php

namespace App\Http\Controllers;

use App\Http\Requests\SignupRequest;
use App\Models\Social_Account;
use App\User;
use Illuminate\Validation\ValidationException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Laravel\Socialite\Facades\Socialite;
use Illuminate\Support\Str;

class AuthController extends Controller
{
    public function login(Request $request) {
        $user = User::where('email', $request->email)->first();

        if(!$user || !Hash::check($request->password, $user->password)) {
            throw ValidationException::withMessages([
                'email' => ['The provided credentials are incorrect']
            ]);
        }

        return $user->createToken('Auth Token')->accessToken;
    }

    public function signup(SignupRequest $request) {
        if(User::create($request->all())) {
            $loginResponse = $this->login($request);
        }

        return $loginResponse;
    }

    public function me() {
        return response()->json(auth()->user());
    }

    public function logout() {
        Auth::user()->tokens()->delete();

        return response()->json(['message' => 'Successfully logged out']);
    }

    public function refresh() {
        return $this->respondWithToken(auth()->refresh());
    }

    public function redirectToProvider($provider) {
        return Socialite::driver($provider)->stateless()->redirect();
    }

    public function handleProviderCallback($provider) {
        $user = Socialite::driver($provider)->stateless()->user();


        if(!$user->token) {
            dd('failed');
        }

        $appUser = User::whereEmail($user->email)->first();

        if(!$appUser) {
            // create user and add the provider
            $appUser = User::create([
                'name' => $user->name,
                'email' => $user->email,
                'password' => Str::random(7)
            ]);

            $social_account = $appUser->social_accounts()->where('provider', $provider)->first();

            
            if(!$social_account) {
                // create provider
                $social_account = Social_Account::create([
                    'provider' => $provider,
                    'provider_user_id' => $user->id,
                    'user_id' => $appUser->id
                ]);
            }

        } else {
            // user already exists
            $social_account = $appUser->social_accounts()->where('provider', $provider)->first();

            
            if(!$social_account) {
                // create provider
                $social_account = Social_Account::create([
                    'provider' => $provider,
                    'provider_user_id' => $user->id,
                    'user_id' => $appUser->id
                ]);
            }
        }

        // login user
        $access_token = $appUser->createToken('Auth Token')->accessToken;

        return response()->json([
            'access_token' => $access_token
        ]);

        dd($appUser, $user, $access_token);
        
    }

    protected function respondWithToken($token) {
        return response()->json([
            'access_token' => $token,
            'token_type' => 'bearer',
            'expires_in' => auth()->factory()->getTTL() * 60,
            'user' => auth()->user()
        ]);
    }
}
