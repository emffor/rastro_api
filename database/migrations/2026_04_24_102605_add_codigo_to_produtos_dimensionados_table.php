<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('produtos_dimensionados', function (Blueprint $table) {
            $table->string('codigo', 12)->unique()->nullable()->after('id');
        });
        
        $produtos = DB::table('produtos_dimensionados')->whereNull('codigo')->get();
        
        foreach ($produtos as $produto) {
            $maxTentativas = 10;
            $codigoGerado = null;
            
            for ($i = 0; $i < $maxTentativas; $i++) {
                $codigo = 'PD-' . str_pad((string) random_int(0, 99999999), 8, '0', STR_PAD_LEFT);
                
                $existe = DB::table('produtos_dimensionados')
                    ->where('codigo', $codigo)
                    ->exists();
                
                if (!$existe) {
                    $codigoGerado = $codigo;
                    break;
                }
            }
            
            if ($codigoGerado) {
                DB::table('produtos_dimensionados')
                    ->where('id', $produto->id)
                    ->update(['codigo' => $codigoGerado]);
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('produtos_dimensionados', function (Blueprint $table) {
            $table->dropColumn('codigo');
        });
    }
};
