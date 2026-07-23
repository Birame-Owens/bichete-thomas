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
        Schema::create('details_reservations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('reservation_id')
                ->constrained('reservations')
                ->cascadeOnDelete();
            $table->foreignId('coiffure_id')
                ->nullable()
                ->constrained('coiffures')
                ->nullOnDelete();
            $table->foreignId('variante_coiffure_id')
                ->nullable()
                ->constrained('variantes_coiffures')
                ->nullOnDelete();
            $table->string('coiffure_nom');
            $table->string('variante_nom')->nullable();
            $table->decimal('prix_unitaire', 12, 2)->default(0);
            $table->unsignedInteger('duree_minutes')->default(0);
            $table->unsignedInteger('quantite')->default(1);
            $table->json('option_ids')->nullable();
            $table->json('options_snapshot')->nullable();
            $table->decimal('montant_options', 12, 2)->default(0);
            $table->decimal('montant_total', 12, 2)->default(0);
            $table->unsignedInteger('ordre')->default(1);
            $table->timestamps();

            $table->index(['reservation_id', 'ordre']);
            $table->index('coiffure_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('details_reservations');
    }
};
