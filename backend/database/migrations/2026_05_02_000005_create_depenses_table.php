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
        Schema::create('depenses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('categorie_depense_id')
                ->nullable()
                ->constrained('categories_depenses')
                ->nullOnDelete();
            $table->string('titre');
            $table->decimal('montant', 12, 2);
            $table->date('date_depense');
            $table->text('description')->nullable();
            $table->string('mode_paiement')->nullable();
            $table->string('reference')->nullable();
            $table->timestamps();

            $table->index('date_depense');
            $table->index('categorie_depense_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('depenses');
    }
};
