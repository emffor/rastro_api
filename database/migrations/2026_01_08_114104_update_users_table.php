<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->foreignUuid('empresa_id')->nullable()->after('id')->constrained('empresas')->nullOnDelete();
            $table->foreignUuid('cargo_id')->nullable()->after('empresa_id')->constrained('cargos')->nullOnDelete();
            $table->boolean('is_master')->default(false)->after('cargo_id');
            $table->boolean('is_admin')->default(false)->after('is_master');
            $table->boolean('ativo')->default(true)->after('is_admin');
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropForeign(['empresa_id']);
            $table->dropForeign(['cargo_id']);
            $table->dropColumn(['empresa_id', 'cargo_id', 'is_master', 'is_admin', 'ativo', 'deleted_at']);
        });
    }
};
