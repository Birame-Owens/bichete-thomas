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
        Schema::create('caisses', function (Blueprint $table) {
            $table->id();
            $table->date('date')->unique();
            $table->decimal('solde_ouverture', 12, 2)->default(0);
            $table->decimal('solde_fermeture', 12, 2)->nullable();
            $table->enum('statut', ['ouverte', 'fermee'])->default('ouverte');
            $table->timestamp('ouverte_at')->nullable();
            $table->timestamp('fermee_at')->nullable();
            $table->text('note')->nullable();
            $table->timestamps();

            $table->index('statut');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('caisses');
    }
};
