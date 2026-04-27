<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('dofs', function (Blueprint $table) {
            $table->string('unidade_medida', 10)->default('m³')->after('volume_saldo_m3');
        });
    }

    public function down(): void
    {
        Schema::table('dofs', function (Blueprint $table) {
            $table->dropColumn('unidade_medida');
        });
    }
};
