<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class OpeningHour extends Model
{
    protected $fillable = ['business_id', 'day_of_week', 'opens_at', 'closes_at'];

    public function business()
    {
        return $this->belongsTo(Business::class);
    }
}