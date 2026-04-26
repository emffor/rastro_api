<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // PostgreSQL: Remover a constraint de checagem antiga
        // O nome padrão costuma ser table_column_check
        DB::statement("ALTER TABLE pedidos DROP CONSTRAINT IF EXISTS pedidos_status_check");

        // Atualizar os dados
        DB::table('pedidos')->where('status', 'RASCUNHO')->update(['status' => 'ORCAMENTO']);

        // Adicionar nova constraint
        DB::statement("ALTER TABLE pedidos ADD CONSTRAINT pedidos_status_check CHECK (status::text = ANY (ARRAY['ORCAMENTO'::text, 'PENDENTE'::text, 'FINALIZADO'::text, 'CANCELADO'::text]))");
        
        // Alterar o default
        DB::statement("ALTER TABLE pedidos ALTER COLUMN status SET DEFAULT 'ORCAMENTO'");
    }

    public function down(): void
    {
        // Remover constraint
        DB::statement("ALTER TABLE pedidos DROP CONSTRAINT IF EXISTS pedidos_status_check");

        // Voltar dados
        DB::table('pedidos')->where('status', 'ORCAMENTO')->update(['status' => 'RASCUNHO']);

        // Voltar constraint antiga
        DB::statement("ALTER TABLE pedidos ADD CONSTRAINT pedidos_status_check CHECK (status::text = ANY (ARRAY['RASCUNHO'::text, 'PENDENTE'::text, 'FINALIZADO'::text, 'CANCELADO'::text]))");

        // Voltar default
        DB::statement("ALTER TABLE pedidos ALTER COLUMN status SET DEFAULT 'RASCUNHO'");
    }
};
