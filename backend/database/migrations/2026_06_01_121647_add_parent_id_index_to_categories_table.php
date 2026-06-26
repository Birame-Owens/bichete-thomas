<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $exists = DB::select("SELECT 1 FROM pg_indexes WHERE tablename='categories' AND indexname='categories_parent_id_index'");
        if (empty($exists)) {
            Schema::table('categories', function (Blueprint $table) {
                $table->index('parent_id');
            });
        }
    }

    public function down(): void
    {
        Schema::table('categories', function (Blueprint $table) {
            $table->dropIndex(['parent_id']);
        });
    }
};
