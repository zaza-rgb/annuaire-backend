<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Casts\Attribute;

class Business extends Model
{
    protected $fillable = [
        'name', 'description', 'city', 'address', 'latitude', 'longitude',
    'phone', 'pricing', 'status', 'category_id', 'country_id',
    'owner_id', 'proposed_by',
    ];

    protected $casts = [
        'visible_until' => 'datetime',
    ];
    protected $hidden = ['proposed_by'];
    

protected function averageRating(): Attribute
{
    return Attribute::get(
        fn ($value) => $value === null ? null : round((float) $value, 1)
    );
}
    public function category()
    {
        return $this->belongsTo(Category::class);
    }

    public function country()
    {
        return $this->belongsTo(Country::class);
    }

    public function owner()
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    public function proposer()
    {
        return $this->belongsTo(User::class, 'proposed_by');
    }

    public function languages()
    {
        return $this->belongsToMany(Language::class, 'business_language');
    }

    public function favoritedBy()
    {
        return $this->belongsToMany(User::class, 'favorites')->withTimestamps();
    }

    public function openingHours()
    {
        return $this->hasMany(OpeningHour::class);
    }

    public function reviews()
    {
        return $this->hasMany(Review::class);
    }

    public function photos()
    {
        return $this->hasMany(Photo::class);
    }

    public function reports()
    {
        return $this->hasMany(Report::class);
    }

    public function claims()
    {
        return $this->hasMany(Claim::class);
    }

    public function conversations()
    {
        return $this->hasMany(Conversation::class);
    }
    
}