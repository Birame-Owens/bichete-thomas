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
        Schema::create('mouvements_caisses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('caisse_id')
                ->constrained('caisses')
                ->cascadeOnDelete();
            $table->enum('type', ['entree', 'sortie']);
            $table->decimal('montant', 12, 2);
            $table->text('description')->nullable();
            $table->string('source')->nullable();
            $table->string('reference')->nullable();
            $table->timestamp('date_mouvement')->useCurrent();
            $table->timestamps();

            $table->index(['caisse_id', 'type']);
            $table->index('date_mouvement');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('mouvements_caisses');
    }
};
