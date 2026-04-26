<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('dofs', function (Blueprint $table) {
            $table->date('data_emissao')->nullable()->after('numero');
            $table->decimal('volume_total', 10, 4)->nullable()->after('valido_ate');
            $table->string('origem')->nullable()->after('volume_total');
            $table->string('destino')->nullable()->after('origem');
        });
    }

    public function down(): void
    {
        Schema::table('dofs', function (Blueprint $table) {
            $table->dropColumn(['data_emissao', 'volume_total', 'origem', 'destino']);
        });
    }
};
