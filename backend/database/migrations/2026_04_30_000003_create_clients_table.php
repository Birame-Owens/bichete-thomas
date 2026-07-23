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
        Schema::create('clients', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();
            $table->string('nom');
            $table->string('prenom');
            $table->string('telephone')->unique();
            $table->string('email')->nullable();
            $table->enum('source', ['en_ligne', 'physique'])->default('en_ligne');
            $table->unsignedInteger('nombre_reservations_terminees')->default(0);
            $table->boolean('fidelite_disponible')->default(false);
            $table->boolean('est_blackliste')->default(false);
            $table->timestamps();

            $table->index(['nom', 'prenom']);
            $table->index('source');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('clients');
    }
};
