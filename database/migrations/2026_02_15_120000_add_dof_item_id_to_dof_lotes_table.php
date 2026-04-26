<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('dof_lotes', function (Blueprint $table) {
            if (!Schema::hasColumn('dof_lotes', 'dof_item_id')) {
                $table->foreignUuid('dof_item_id')
                    ->nullable()
                    ->after('dof_id')
                    ->constrained('dof_itens')
                    ->nullOnDelete();

                $table->index(['dof_id', 'dof_item_id', 'lote_id'], 'dof_lotes_dof_item_lote_idx');
            }
        });
    }

    public function down(): void
    {
        Schema::table('dof_lotes', function (Blueprint $table) {
            if (Schema::hasColumn('dof_lotes', 'dof_item_id')) {
                $table->dropIndex('dof_lotes_dof_item_lote_idx');
                $table->dropConstrainedForeignId('dof_item_id');
            }
        });
    }
};
