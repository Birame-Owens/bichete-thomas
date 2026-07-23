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
        Schema::create('parametres_systeme', function (Blueprint $table) {
            $table->id();
            $table->string('cle')->unique();
            $table->json('valeur')->nullable();
            $table->string('type')->default('string');
            $table->text('description')->nullable();
            $table->boolean('modifiable')->default(true);
            $table->timestamps();

            $table->index('type');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('parametres_systeme');
    }
};
