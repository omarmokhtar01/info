<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\ChangePasswordRequest;
use App\Http\Requests\Auth\LoginRequest;
use App\Http\Requests\Auth\RegisterRequest;
use App\Http\Requests\Auth\UpdateProfileRequest;
use App\Models\otp;
use App\Models\User;
use App\Services\AuthService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;

class AuthController extends Controller
{
    protected AuthService $authService;

    public function __construct(AuthService $authService)
    {
        $this->authService = $authService;
    }

    public function login(LoginRequest $request)
    {
        $token = $this->authService->login($request->validated());
    
        if (!$token) {
            return $this->formatResponse([], __('messages.login_invalid'), false, 401);
        }
    
        $user = auth()->user();
        $user->image = url($user->image) ?? "";
    
        return response()->json([
            'success' => true,
            'message' => __('messages.login_success'),
            'data' => $user,
            'token' => $token
        ], 200);
    }
    

    public function getUser()
    {
        $user = $this->authService->getUserData();
        return $user
            ? $this->formatResponse($user, __('messages.user_data_retrieved'))
            : $this->formatResponse([], __('messages.user_not_found'), false, 404);
    }

    public function logout()
    {
        return $this->authService->logout()
            ? $this->formatResponse([], __('auth.logged_out'))
            : $this->formatResponse([], __('auth.unauthenticated'), false, 401);
    }

    public function updateProfile(UpdateProfileRequest $request)
    {
        $user = $this->authService->updateProfile($request->validated());
        if ($request->hasFile('image')) {
            Storage::disk('public')->delete($user->image);
            $user->image = $request->file('image')->store('user_images', 'public');
        }
        $user->save();
        $user->image = $user->image ? url($user->image) : null;
        return $this->formatResponse($user, __('messages.update_success'));
    }

    public function changePassword(ChangePasswordRequest $request)
    {
        $result = $this->authService->changePassword($request->current_password, $request->new_password);
        return $this->formatResponse([], $result['message'], $result['status'], $result['status'] ? 200 : 400);
    }

    public function register(RegisterRequest $request)
    {
        $user = User::create($request->only(['name', 'phone', 'email', 'location']) + ['password' => Hash::make($request->password)]);
        return $this->sendOtp($user->email, __('messages.user_registered'));
    }

    public function verifyOtp(Request $request)
    {
        $request->validate(['email' => 'required|email', 'otp' => 'required|string']);
        $otpRecord = otp::where('email', $request->email)->where('otp', $request->otp)->first();

        if (!$otpRecord || $otpRecord->isExpired()) {
            return $this->formatResponse([], __('messages.invalid_otp'), false, 400);
        }

        $otpRecord->update(['is_verified' => true]);
        return $this->formatResponse([], __('messages.otp_verified'));
    }

    public function forgotPassword(Request $request)
    {
        $request->validate(['email' => 'required|email']);
        $user = User::where('email', $request->email)->first();
        return $user ? $this->sendOtp($user->email, __('messages.otp_sent')) : $this->formatResponse([], __('messages.user_not_found'), false, 404);
    }

    public function resetPassword(Request $request)
    {
        $request->validate([
            'email' => 'required|email',
            'otp' => 'required|string',
            'password' => 'required|min:8|confirmed',
        ]);

        $otpRecord = otp::where('email', $request->email)->where('otp', $request->otp)->first();
        if (!$otpRecord || $otpRecord->isExpired()) {
            return $this->formatResponse([], __('messages.invalid_otp'), false, 400);
        }

        User::where('email', $request->email)->update(['password' => Hash::make($request->password)]);
        return $this->formatResponse([], __('messages.password_reset'));
    }

    private function sendOtp(string $email, string $message)
    {
        $otp = Otp::generateOtpForUser($email);
    
        try {
            // Mail::html("<p>Your OTP code is: <strong>{$otp->otp}</strong>. It expires in 10 minutes.</p>", function ($msg) use ($email) {
            //     $msg->to($email)->subject('Your OTP Code');
            // });
    
            return response()->json([
                'data' => [
                    'email' => $email,
                    'otp' => $otp->otp,
                ],
                'message' => $message,
                'status' => true
            ]);
        } catch (\Exception $e) {
            Log::error('Mail sending failed: ' . $e->getMessage());
            return response()->json(['error' => 'Failed to send OTP. Please try again later.'], 500);
        }
    }
    
}
