<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('dofs', function (Blueprint $table) {
            if (!Schema::hasColumn('dofs', 'nota_fiscal')) {
                $table->string('nota_fiscal')
                    ->nullable()
                    ->after('destino');
            }
        });
    }

    public function down(): void
    {
        Schema::table('dofs', function (Blueprint $table) {
            if (Schema::hasColumn('dofs', 'nota_fiscal')) {
                $table->dropColumn('nota_fiscal');
            }
        });
    }
};
