<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Carbon\Carbon;

class Otp extends Model
{
    protected $fillable = [
        'otp',
        'user_id',
        'otp_expiry',
        'is_verified',
        'email'
    ];

    protected $casts = [
        'otp_expiry' => 'datetime',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function isExpired()
    {
        return Carbon::parse($this->otp_expiry)->isPast();
    }

    public static function generateOtpForUser($email)
    {
        $otp = (string) rand(100000, 999999);
        $otpExpiry = now()->addMinutes(10);

        return self::create([
            'email' => $email, 
            'otp' => $otp,
            'otp_expiry' => $otpExpiry->toDateTimeString(),
        ]);
    }
    
}