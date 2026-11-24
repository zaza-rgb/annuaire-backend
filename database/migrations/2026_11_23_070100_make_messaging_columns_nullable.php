<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Messagerie étendue, étape 2 : deux colonnes deviennent facultatives.
// Séparée de l'étape 1 : si elle échoue, rien de l'étape 1 n'est touché.
return new class extends Migration
{
    public function up(): void
    {
        // Une conversation directe entre deux utilisateurs n'a pas de commerce.
        Schema::table('conversations', function (Blueprint $table) {
            $table->unsignedBigInteger('business_id')->nullable()->change();
        });

        // Un message peut ne contenir qu'une photo.
        Schema::table('messages', function (Blueprint $table) {
            $table->text('content')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('messages', function (Blueprint $table) {
            $table->text('content')->nullable(false)->change();
        });

        Schema::table('conversations', function (Blueprint $table) {
            $table->unsignedBigInteger('business_id')->nullable(false)->change();
        });
    }
};
