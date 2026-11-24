<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

// Messagerie étendue, étape 1 : ajouts uniquement (aucune colonne existante modifiée).
// Le code actuel (lignes 26 à 30 de la checklist) continue de fonctionner tel quel.
return new class extends Migration
{
    public function up(): void
    {
        // Participants d'une conversation (client et propriétaire pour les commerces,
        // deux utilisateurs pour les conversations directes à venir).
        Schema::create('conversation_participants', function (Blueprint $table) {
            $table->id();
            $table->foreignId('conversation_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->timestamp('last_read_at')->nullable();
            $table->boolean('is_blocked')->default(false);
            $table->timestamps();
            $table->unique(['conversation_id', 'user_id']);
        });

        // Type de conversation : 'business' pour toutes les conversations existantes.
        Schema::table('conversations', function (Blueprint $table) {
            $table->string('type', 20)->default('business')->after('id');
        });

        // Photo facultative dans un message.
        Schema::table('messages', function (Blueprint $table) {
            $table->string('image_path')->nullable()->after('content');
        });

        // Signalement d'un message : un seul par utilisateur et par message.
        Schema::create('message_reports', function (Blueprint $table) {
            $table->id();
            $table->foreignId('message_id')->constrained()->cascadeOnDelete();
            $table->foreignId('reporter_id')->constrained('users')->cascadeOnDelete();
            $table->string('reason', 100);
            $table->string('status', 20)->default('open');
            $table->timestamps();
            $table->unique(['message_id', 'reporter_id']);
        });

        // Reprise des conversations existantes : le client...
        DB::statement(
            'INSERT INTO conversation_participants (conversation_id, user_id, created_at, updated_at)
             SELECT id, user_id, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP FROM conversations'
        );

        // ... et le propriétaire du commerce (s'il existe et n'est pas déjà le client).
        DB::statement(
            'INSERT INTO conversation_participants (conversation_id, user_id, created_at, updated_at)
             SELECT c.id, b.owner_id, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP
             FROM conversations c
             JOIN businesses b ON b.id = c.business_id
             WHERE b.owner_id IS NOT NULL AND b.owner_id <> c.user_id'
        );
    }

    public function down(): void
    {
        Schema::dropIfExists('message_reports');

        Schema::table('messages', function (Blueprint $table) {
            $table->dropColumn('image_path');
        });

        Schema::table('conversations', function (Blueprint $table) {
            $table->dropColumn('type');
        });

        Schema::dropIfExists('conversation_participants');
    }
};
