<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Conversation extends Model
{
    protected $fillable = ['user_id', 'business_id'];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function business()
    {
        return $this->belongsTo(Business::class);
    }

    public function messages()
    {
        return $this->hasMany(Message::class);
    }

    public function lastMessage()
    {
        return $this->hasOne(Message::class)->latestOfMany();
    }

    // Participants : le client et le propriétaire du commerce
    public function isParticipant(User $user): bool
    {
        return (int) $this->user_id === (int) $user->id
            || (int) optional($this->business)->owner_id === (int) $user->id;
    }
}