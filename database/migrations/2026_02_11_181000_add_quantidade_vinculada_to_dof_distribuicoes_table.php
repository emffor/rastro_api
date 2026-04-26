<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('dof_distribuicoes', function (Blueprint $table) {
            $table->integer('quantidade_vinculada')->default(0)->after('quantidade');
        });
    }

    public function down(): void
    {
        Schema::table('dof_distribuicoes', function (Blueprint $table) {
            $table->dropColumn('quantidade_vinculada');
        });
    }
};
