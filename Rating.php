<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Rating extends Model
{
    //
    protected $fillable=[
        'rating',
        'user_id',
        'organization_id'
    ];
    public function user(){
        return $this->belongsTo(User::class);
    }
    public function organization(){
        return $this->belongsTo(Organization::class);
    }

}
