<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('saida_consumos', function ($table) {
            $table->dropForeign(['dof_lote_id']);
        });

        DB::statement('ALTER TABLE saida_consumos ALTER COLUMN dof_lote_id DROP NOT NULL');
        DB::statement('ALTER TABLE saida_consumos ADD CONSTRAINT saida_consumos_dof_lote_id_foreign FOREIGN KEY (dof_lote_id) REFERENCES dof_lotes(id) ON DELETE SET NULL');
    }

    public function down(): void
    {
        Schema::table('saida_consumos', function ($table) {
            $table->dropForeign(['dof_lote_id']);
        });

        DB::statement('ALTER TABLE saida_consumos ALTER COLUMN dof_lote_id SET NOT NULL');
        DB::statement('ALTER TABLE saida_consumos ADD CONSTRAINT saida_consumos_dof_lote_id_foreign FOREIGN KEY (dof_lote_id) REFERENCES dof_lotes(id) ON DELETE CASCADE');
    }
};
