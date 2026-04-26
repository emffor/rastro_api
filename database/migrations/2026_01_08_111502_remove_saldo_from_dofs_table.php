<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('dofs', function (Blueprint $table) {
            $table->dropColumn(['saldo_inicial', 'saldo_atual']);
        });
    }

    public function down(): void
    {
        Schema::table('dofs', function (Blueprint $table) {
            $table->decimal('saldo_inicial', 10, 4)->after('serie');
            $table->decimal('saldo_atual', 10, 4)->after('saldo_inicial');
        });
    }
};
