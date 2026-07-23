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
        Schema::create('regles_fidelite', function (Blueprint $table) {
            $table->id();
            $table->string('nom');
            $table->unsignedInteger('nombre_reservations_requis');
            $table->enum('type_recompense', ['pourcentage', 'montant']);
            $table->decimal('valeur_recompense', 12, 2);
            $table->boolean('actif')->default(true);
            $table->timestamps();

            $table->index('actif');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('regles_fidelite');
    }
};
