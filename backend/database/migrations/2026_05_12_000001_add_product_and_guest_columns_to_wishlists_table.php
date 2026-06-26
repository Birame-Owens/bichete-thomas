<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('wishlists', function (Blueprint $table) {
            if (!Schema::hasColumn('wishlists', 'client_id')) {
                $table->foreignId('client_id')->nullable()->after('id')->constrained('clients')->nullOnDelete();
            }

            if (!Schema::hasColumn('wishlists', 'guest_identifier')) {
                $table->string('guest_identifier', 140)->nullable()->after('client_id');
            }

            if (!Schema::hasColumn('wishlists', 'produit_id')) {
                $table->foreignId('produit_id')->nullable()->after('guest_identifier')->constrained('produits')->cascadeOnDelete();
            }
        });

        $this->createIndexIfMissing('wishlists', 'wishlists_client_produit_unique', ['client_id', 'produit_id'], true);
        $this->createIndexIfMissing('wishlists', 'wishlists_guest_produit_unique', ['guest_identifier', 'produit_id'], true);
    }

    public function down(): void
    {
        Schema::table('wishlists', function (Blueprint $table) {
            $this->dropIndexIfExists('wishlists', 'wishlists_client_produit_unique');
            $this->dropIndexIfExists('wishlists', 'wishlists_guest_produit_unique');

            if (Schema::hasColumn('wishlists', 'produit_id')) {
                $table->dropConstrainedForeignId('produit_id');
            }

            if (Schema::hasColumn('wishlists', 'guest_identifier')) {
                $table->dropColumn('guest_identifier');
            }

            if (Schema::hasColumn('wishlists', 'client_id')) {
                $table->dropConstrainedForeignId('client_id');
            }
        });
    }

    private function createIndexIfMissing(string $table, string $index, array $columns, bool $unique = false): void
    {
        if ($this->indexExists($table, $index)) {
            return;
        }

        Schema::table($table, function (Blueprint $table) use ($index, $columns, $unique) {
            $unique ? $table->unique($columns, $index) : $table->index($columns, $index);
        });
    }

    private function dropIndexIfExists(string $table, string $index): void
    {
        if (!$this->indexExists($table, $index)) {
            return;
        }

        Schema::table($table, function (Blueprint $table) use ($index) {
            $table->dropIndex($index);
        });
    }

    private function indexExists(string $table, string $index): bool
    {
        $driver = DB::getDriverName();

        if ($driver === 'pgsql') {
            return DB::table('pg_indexes')
                ->where('tablename', $table)
                ->where('indexname', $index)
                ->exists();
        }

        if ($driver === 'sqlite') {
            return collect(DB::select("PRAGMA index_list('$table')"))
                ->contains(fn ($row) => ($row->name ?? null) === $index);
        }

        return DB::table('information_schema.statistics')
            ->where('table_schema', DB::getDatabaseName())
            ->where('table_name', $table)
            ->where('index_name', $index)
            ->exists();
    }
};
