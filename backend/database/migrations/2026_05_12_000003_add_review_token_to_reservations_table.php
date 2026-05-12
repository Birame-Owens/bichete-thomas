<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('reservations', function (Blueprint $table): void {
            // review_token : hash SHA-256 du token brut envoye dans le lien WhatsApp.
            // Null = invitation pas encore envoyee OU token deja consomme apres soumission.
            // review_invited_at : horodatage de l envoi — sert de verrou pour ne pas
            // re-envoyer meme si le token a ete consomme (null = jamais invite).
            $table->string('review_token', 128)->nullable()->unique()->after('notes');
            $table->timestamp('review_token_expires_at')->nullable()->after('review_token');
            $table->timestamp('review_invited_at')->nullable()->after('review_token_expires_at');
        });
    }

    public function down(): void
    {
        Schema::table('reservations', function (Blueprint $table): void {
            $table->dropColumn(['review_token', 'review_token_expires_at', 'review_invited_at']);
        });
    }
};
