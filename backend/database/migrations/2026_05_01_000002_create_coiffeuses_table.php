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
        Schema::create('coiffeuses', function (Blueprint $table) {
            $table->id();
            $table->string('nom');
            $table->string('prenom');
            $table->string('telephone')->nullable();
            $table->decimal('pourcentage_commission', 5, 2)->default(0);
            $table->boolean('actif')->default(true);
            $table->timestamps();

            $table->index(['nom', 'prenom']);
            $table->index('actif');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('coiffeuses');
    }
};
