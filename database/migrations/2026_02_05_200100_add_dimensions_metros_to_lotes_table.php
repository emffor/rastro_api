<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('lotes', function (Blueprint $table) {
            $table->decimal('largura_metros', 8, 2)->after('altura')->nullable();
            $table->decimal('comprimento_metros', 8, 2)->after('largura_metros')->nullable();
            $table->decimal('altura_metros', 8, 2)->after('comprimento_metros')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('lotes', function (Blueprint $table) {
            $table->dropColumn(['largura_metros', 'comprimento_metros', 'altura_metros']);
        });
    }
};
