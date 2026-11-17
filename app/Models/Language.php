<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Language extends Model
{
    protected $fillable = ['code', 'name'];

    public function users()
    {
        return $this->hasMany(User::class);
    }

    public function categoryTranslations()
    {
        return $this->hasMany(CategoryTranslation::class);
    }

    public function countryTranslations()
    {
        return $this->hasMany(CountryTranslation::class);
    }

    public function businesses()
    {
        return $this->belongsToMany(Business::class, 'business_language');
    }
}