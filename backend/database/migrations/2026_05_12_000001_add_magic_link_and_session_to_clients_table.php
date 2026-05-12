<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('clients', function (Blueprint $table): void {
            // Magic link : token single-use (24h) envoye par WhatsApp apres paiement.
            // On stocke le hash SHA-256 du token brut (le brut ne vit que dans le lien).
            $table->string('magic_link_token', 128)->nullable()->unique()->after('notes');
            $table->timestamp('magic_link_expires_at')->nullable()->after('magic_link_token');

            // Session persistante (90 jours) posee apres verification du magic link.
            // Meme principe : hash en base, token brut uniquement dans le cookie httpOnly.
            $table->string('session_token', 128)->nullable()->unique()->after('magic_link_expires_at');
            $table->timestamp('session_expires_at')->nullable()->after('session_token');
        });
    }

    public function down(): void
    {
        Schema::table('clients', function (Blueprint $table): void {
            $table->dropColumn(['magic_link_token', 'magic_link_expires_at', 'session_token', 'session_expires_at']);
        });
    }
};
