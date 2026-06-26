<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('shipping_settings', function (Blueprint $table) {
            if (!Schema::hasColumn('shipping_settings', 'default_cost')) {
                $table->decimal('default_cost', 10, 2)->default(2500)->after('id');
            }

            if (!Schema::hasColumn('shipping_settings', 'free_threshold')) {
                $table->decimal('free_threshold', 10, 2)->default(50000)->after('default_cost');
            }

            if (!Schema::hasColumn('shipping_settings', 'is_enabled')) {
                $table->boolean('is_enabled')->default(true)->after('free_threshold');
            }
        });
    }

    public function down(): void
    {
        Schema::table('shipping_settings', function (Blueprint $table) {
            if (Schema::hasColumn('shipping_settings', 'is_enabled')) {
                $table->dropColumn('is_enabled');
            }

            if (Schema::hasColumn('shipping_settings', 'free_threshold')) {
                $table->dropColumn('free_threshold');
            }

            if (Schema::hasColumn('shipping_settings', 'default_cost')) {
                $table->dropColumn('default_cost');
            }
        });
    }
};
