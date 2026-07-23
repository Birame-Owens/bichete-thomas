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
        Schema::create('paiements', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('reservation_id')
                ->nullable()
                ->constrained('reservations')
                ->nullOnDelete();
            $table->foreignId('client_id')
                ->nullable()
                ->constrained('clients')
                ->nullOnDelete();
            $table->foreignId('caisse_id')
                ->nullable()
                ->constrained('caisses')
                ->nullOnDelete();
            $table->foreignId('mouvement_caisse_id')
                ->nullable()
                ->constrained('mouvements_caisses')
                ->nullOnDelete();
            $table->string('numero_recu')->unique();
            $table->enum('type', ['acompte', 'solde', 'complet', 'remboursement', 'ajustement']);
            $table->enum('mode_paiement', ['especes', 'wave', 'orange_money', 'carte_bancaire', 'virement', 'autre']);
            $table->decimal('montant', 12, 2);
            $table->string('devise', 12)->default('FCFA');
            $table->enum('statut', ['en_attente', 'valide', 'annule', 'rembourse'])->default('valide');
            $table->timestamp('date_paiement')->useCurrent();
            $table->string('reference')->nullable();
            $table->text('notes')->nullable();
            $table->boolean('recu_envoye')->default(false);
            $table->timestamp('recu_envoye_at')->nullable();
            $table->timestamps();

            $table->index(['reservation_id', 'statut']);
            $table->index(['client_id', 'date_paiement']);
            $table->index(['statut', 'date_paiement']);
            $table->index(['mode_paiement', 'date_paiement']);
            $table->index(['type', 'date_paiement']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('paiements');
    }
};
