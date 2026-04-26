<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('especies') || ! Schema::hasColumn('especies', 'codigo_ibama')) {
            return;
        }

        Schema::table('especies', function (Blueprint $table) {
            $table->dropColumn('codigo_ibama');
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('especies') || Schema::hasColumn('especies', 'codigo_ibama')) {
            return;
        }

        Schema::table('especies', function (Blueprint $table) {
            $table->string('codigo_ibama')->nullable()->after('nome_popular');
        });
    }
};
