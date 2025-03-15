<?php

namespace App\Services;

use App\Models\otp;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;

class AuthService
{
    public function login(array $data)
    {
        $user = User::where('email', $data['email'])->first();

        if (!$user || !Hash::check($data['password'], $user->password)) {
            return null;
        }

        $otp = Otp::where('email', $data['email'])->first();
        if (!$otp || !$otp->is_verified) {
            return null;
        }

        Auth::login($user);
        return $user->createToken($user->user_name . '-AuthToken')->plainTextToken;
    }

    public function getUserData()
    {
        $user = Auth::user();
    
        if ($user) {
            $user->image = $user->image ? url($user->image) : "";
        }
    
        return $user;
    }
    

    public function logout()
    {
        $user = Auth::user();

        if ($user) {
            $user->tokens()->delete();
            return true;
        }

        return false;
    }

    public function updateProfile(array $data)
    {
        $user = Auth::user();

        $user->update($data);

        return $user;
    }

    public function changePassword(string $currentPassword, string $newPassword)
    {
        $user = Auth::user();

        if (!$user || !Hash::check($currentPassword, $user->password)) {
            return [
                'status' => false,
                'message' => __('auth.current_password_incorrect')
            ];
        }

        $user->update(['password' => Hash::make($newPassword)]);

        return [
            'status' => true,
            'message' => __('auth.password_changed_successfully')
        ];
    }

    public function resetPassword(string $otp, string $newPassword)
    {
        $otpRecord = Otp::where('otp', $otp)->first();

        if (!$otpRecord || $otpRecord->isExpired()) {
            return [
                'status' => false,
                'message' => __('auth.invalid_or_expired_otp')
            ];
        }

        $user = $otpRecord->user;
        $user->update(['password' => Hash::make($newPassword)]);

        $otpRecord->delete();

        return [
            'status' => true,
            'message' => __('auth.password_changed_successfully')
        ];
    }
}
