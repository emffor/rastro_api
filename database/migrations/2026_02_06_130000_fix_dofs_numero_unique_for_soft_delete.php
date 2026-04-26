<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        try {
            Schema::table('dofs', function (Blueprint $table) {
                // Constraint criada por $table->string('numero')->unique()
                $table->dropUnique('dofs_numero_unique');
            });
        } catch (\Throwable) {
            // Ignora se o índice já não existir neste ambiente
        }

        $driver = DB::getDriverName();

        if ($driver === 'pgsql') {
            DB::statement(
                'CREATE UNIQUE INDEX dofs_empresa_numero_ativos_unique ON dofs (empresa_id, numero) WHERE deleted_at IS NULL'
            );
            return;
        }

        // Fallback para bancos sem partial index
        Schema::table('dofs', function (Blueprint $table) {
            $table->unique(['empresa_id', 'numero', 'deleted_at'], 'dofs_empresa_numero_deleted_at_unique');
        });
    }

    public function down(): void
    {
        $driver = DB::getDriverName();

        if ($driver === 'pgsql') {
            DB::statement('DROP INDEX IF EXISTS dofs_empresa_numero_ativos_unique');
        } else {
            try {
                Schema::table('dofs', function (Blueprint $table) {
                    $table->dropUnique('dofs_empresa_numero_deleted_at_unique');
                });
            } catch (\Throwable) {
                // Ignora se o índice não existir
            }
        }

        Schema::table('dofs', function (Blueprint $table) {
            $table->unique('numero', 'dofs_numero_unique');
        });
    }
};
