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
        Schema::create('reservations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('client_id')
                ->nullable()
                ->constrained('clients')
                ->nullOnDelete();
            $table->foreignId('coiffeuse_id')
                ->nullable()
                ->constrained('coiffeuses')
                ->nullOnDelete();
            $table->foreignId('code_promo_id')
                ->nullable()
                ->constrained('codes_promo')
                ->nullOnDelete();
            $table->foreignId('regle_fidelite_id')
                ->nullable()
                ->constrained('regles_fidelite')
                ->nullOnDelete();
            $table->date('date_reservation');
            $table->time('heure_debut');
            $table->time('heure_fin');
            $table->unsignedInteger('duree_totale_minutes')->default(0);
            $table->enum('statut', [
                'en_attente',
                'confirmee',
                'acompte_paye',
                'en_cours',
                'terminee',
                'annulee',
                'absence',
            ])->default('en_attente');
            $table->enum('source', ['admin', 'en_ligne', 'whatsapp', 'telephone', 'physique'])->default('admin');
            $table->decimal('montant_total', 12, 2)->default(0);
            $table->decimal('montant_reduction', 12, 2)->default(0);
            $table->decimal('montant_acompte', 12, 2)->default(0);
            $table->decimal('montant_restant', 12, 2)->default(0);
            $table->string('devise', 12)->default('FCFA');
            $table->boolean('fidelite_appliquee')->default(false);
            $table->text('notes')->nullable();
            $table->timestamp('annulee_at')->nullable();
            $table->timestamp('terminee_at')->nullable();
            $table->timestamps();

            $table->index(['date_reservation', 'statut']);
            $table->index(['coiffeuse_id', 'date_reservation', 'heure_debut']);
            $table->index(['client_id', 'date_reservation']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('reservations');
    }
};
