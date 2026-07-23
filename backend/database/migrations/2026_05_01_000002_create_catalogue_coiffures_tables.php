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
        Schema::create('categories_coiffures', function (Blueprint $table) {
            $table->id();
            $table->string('nom')->unique();
            $table->text('description')->nullable();
            $table->boolean('actif')->default(true);
            $table->timestamps();
        });

        Schema::create('coiffures', function (Blueprint $table) {
            $table->id();
            $table->foreignId('categorie_coiffure_id')
                ->constrained('categories_coiffures')
                ->restrictOnDelete();
            $table->string('nom');
            $table->text('description')->nullable();
            $table->string('image')->nullable();
            $table->boolean('actif')->default(true);
            $table->timestamps();

            $table->unique(['categorie_coiffure_id', 'nom']);
            $table->index('actif');
        });

        Schema::create('variantes_coiffures', function (Blueprint $table) {
            $table->id();
            $table->foreignId('coiffure_id')
                ->constrained('coiffures')
                ->cascadeOnDelete();
            $table->string('nom');
            $table->decimal('prix', 12, 2);
            $table->unsignedSmallInteger('duree_minutes');
            $table->boolean('actif')->default(true);
            $table->timestamps();

            $table->unique(['coiffure_id', 'nom']);
            $table->index('actif');
        });

        Schema::create('options_coiffures', function (Blueprint $table) {
            $table->id();
            $table->string('nom')->unique();
            $table->decimal('prix', 12, 2);
            $table->boolean('actif')->default(true);
            $table->timestamps();

            $table->index('actif');
        });

        Schema::create('coiffure_option', function (Blueprint $table) {
            $table->id();
            $table->foreignId('coiffure_id')
                ->constrained('coiffures')
                ->cascadeOnDelete();
            $table->foreignId('option_coiffure_id')
                ->constrained('options_coiffures')
                ->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['coiffure_id', 'option_coiffure_id']);
        });

        Schema::create('images_coiffures', function (Blueprint $table) {
            $table->id();
            $table->foreignId('coiffure_id')
                ->constrained('coiffures')
                ->cascadeOnDelete();
            $table->string('url');
            $table->string('alt')->nullable();
            $table->unsignedSmallInteger('ordre')->default(0);
            $table->boolean('principale')->default(false);
            $table->timestamps();

            $table->index(['coiffure_id', 'principale']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('images_coiffures');
        Schema::dropIfExists('coiffure_option');
        Schema::dropIfExists('options_coiffures');
        Schema::dropIfExists('variantes_coiffures');
        Schema::dropIfExists('coiffures');
        Schema::dropIfExists('categories_coiffures');
    }
};
