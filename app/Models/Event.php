<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Event extends Model
{
    protected $fillable = [
        'title', 'description', 'photo_path', 'city', 'address',
        'latitude', 'longitude', 'starts_at', 'ends_at', 'capacity',
        'status', 'event_category_id', 'organizer_id',
    ];

       protected $casts = [
        'starts_at' => 'datetime:Y-m-d H:i:s',
        'ends_at' => 'datetime:Y-m-d H:i:s',
    ];

    public function category()
    {
        return $this->belongsTo(EventCategory::class, 'event_category_id');
    }

    public function organizer()
    {
        return $this->belongsTo(User::class, 'organizer_id');
    }

    public function languages()
    {
        return $this->belongsToMany(Language::class, 'event_languages');
    }

    public function registrations()
    {
        return $this->hasMany(EventRegistration::class);
    }
}