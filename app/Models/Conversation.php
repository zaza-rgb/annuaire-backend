<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Conversation extends Model
{
    protected $fillable = ['user_id', 'business_id', 'type'];

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

    public function participants()
    {
        return $this->hasMany(ConversationParticipant::class);
    }

    // Conversations d'un utilisateur : client, propriétaire du commerce ou participant
    public function scopeForUser($query, User $user)
    {
        return $query->where(function ($q) use ($user) {
            $q->where('user_id', $user->id)
              ->orWhereHas('business', fn ($b) => $b->where('owner_id', $user->id))
              ->orWhereHas('participants', fn ($p) => $p->where('user_id', $user->id));
        });
    }

    // Participants : ceux de conversation_participants ;
    // repli sur l'ancienne règle (client + propriétaire du commerce)
    // pour les conversations créées avant la table.
    public function isParticipant(User $user): bool
    {
        if ($this->participants()->where('user_id', $user->id)->exists()) {
            return true;
        }

        return (int) $this->user_id === (int) $user->id
            || (int) optional($this->business)->owner_id === (int) $user->id;
    }

    // Crée les lignes de conversation_participants manquantes
    // (client et propriétaire du commerce) ; sans effet si elles existent.
    public function ensureParticipants(): void
    {
        $ids = array_filter([$this->user_id, optional($this->business)->owner_id]);

        foreach ($ids as $userId) {
            ConversationParticipant::firstOrCreate([
                'conversation_id' => $this->id,
                'user_id'         => $userId,
            ]);
        }
    }
}
