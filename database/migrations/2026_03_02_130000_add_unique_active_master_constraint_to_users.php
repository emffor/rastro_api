<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        $mastersAtivos = DB::table('users')
            ->where('is_master', true)
            ->whereNull('deleted_at')
            ->count();

        if ($mastersAtivos > 1) {
            throw new RuntimeException(
                'Existem múltiplos usuários master ativos. Regularize os dados antes de aplicar a migration.'
            );
        }

        DB::statement(
            'CREATE UNIQUE INDEX IF NOT EXISTS users_single_active_master_unique ON users (is_master) WHERE is_master = true AND deleted_at IS NULL'
        );
    }

    public function down(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        DB::statement('DROP INDEX IF EXISTS users_single_active_master_unique');
    }
};
