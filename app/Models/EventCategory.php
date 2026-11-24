<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class EventCategory extends Model
{
    protected $fillable = ['code'];

    protected $appends = ['name'];

    protected $hidden = ['translations'];

    public function translations()
    {
        return $this->hasMany(EventCategoryTranslation::class);
    }
    
     public function events()
    {
        return $this->hasMany(Event::class);
    }
    // Nom dans la langue de la requête (fr par défaut), sinon le code
    public function getNameAttribute()
    {
        $languageId = Language::where('code', app()->getLocale())->value('id');

        return $this->translations->firstWhere('language_id', $languageId)?->name
            ?? $this->translations->firstWhere('language_id', Language::where('code', 'fr')->value('id'))?->name
            ?? $this->code;
    }
}