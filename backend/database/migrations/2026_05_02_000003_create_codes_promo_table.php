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
        Schema::create('codes_promo', function (Blueprint $table) {
            $table->id();
            $table->string('code')->unique();
            $table->string('nom')->nullable();
            $table->enum('type_reduction', ['pourcentage', 'montant']);
            $table->decimal('valeur', 12, 2);
            $table->dateTime('date_debut')->nullable();
            $table->dateTime('date_fin')->nullable();
            $table->unsignedInteger('limite_utilisation')->nullable();
            $table->unsignedInteger('nombre_utilisations')->default(0);
            $table->boolean('actif')->default(true);
            $table->timestamps();

            $table->index(['actif', 'date_debut', 'date_fin']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('codes_promo');
    }
};
