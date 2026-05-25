<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('signalements', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('gerante_id')->constrained('users')->cascadeOnDelete();
            $table->string('type'); // produit | materiel | autre
            $table->string('titre');
            $table->text('description')->nullable();
            $table->string('urgence')->default('normale'); // normale | urgente
            $table->boolean('lu_par_admin')->default(false);
            $table->timestamp('lu_at')->nullable();
            $table->boolean('traite')->default(false);
            $table->timestamp('traite_at')->nullable();
            $table->text('note_admin')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('signalements');
    }
};
