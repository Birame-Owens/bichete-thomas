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
        Schema::create('preferences_clients', function (Blueprint $table) {
            $table->id();
            $table->foreignId('client_id')
                ->unique()
                ->constrained('clients')
                ->cascadeOnDelete();
            $table->json('coiffures_preferees')->nullable();
            $table->json('options_preferees')->nullable();
            $table->text('notes')->nullable();
            $table->boolean('notifications_whatsapp')->default(true);
            $table->boolean('notifications_promos')->default(true);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('preferences_clients');
    }
};
