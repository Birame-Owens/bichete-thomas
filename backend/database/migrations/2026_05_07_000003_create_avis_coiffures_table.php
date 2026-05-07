<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('avis_coiffures', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('coiffure_id')
                ->constrained('coiffures')
                ->cascadeOnDelete();
            $table->foreignId('client_id')
                ->nullable()
                ->constrained('clients')
                ->nullOnDelete();
            $table->foreignId('reservation_id')
                ->nullable()
                ->constrained('reservations')
                ->nullOnDelete();
            $table->string('nom_client');
            $table->string('telephone')->nullable();
            $table->string('email')->nullable();
            $table->unsignedTinyInteger('note');
            $table->text('commentaire');
            $table->string('photo_url')->nullable();
            $table->enum('statut', ['en_attente', 'approuve', 'rejete'])->default('en_attente');
            $table->boolean('verifie')->default(false);
            $table->timestamp('publie_at')->nullable();
            $table->timestamps();

            $table->index(['coiffure_id', 'statut', 'publie_at']);
            $table->index(['client_id', 'coiffure_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('avis_coiffures');
    }
};
