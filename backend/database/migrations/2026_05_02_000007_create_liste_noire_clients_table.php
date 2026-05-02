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
        Schema::create('liste_noire_clients', function (Blueprint $table) {
            $table->id();
            $table->foreignId('client_id')
                ->constrained('clients')
                ->cascadeOnDelete();
            $table->text('raison')->nullable();
            $table->boolean('actif')->default(true);
            $table->timestamp('blackliste_at')->nullable();
            $table->timestamp('retire_at')->nullable();
            $table->timestamps();

            $table->index(['client_id', 'actif']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('liste_noire_clients');
    }
};
