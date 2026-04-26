<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('patio_areas_bloqueadas', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('patio_id')->constrained('patios')->onDelete('cascade');
            $table->string('nome')->nullable();
            $table->decimal('pos_x', 10, 2)->default(0);
            $table->decimal('pos_y', 10, 2)->default(0);
            $table->decimal('largura', 10, 2)->default(50);
            $table->decimal('altura', 10, 2)->default(50);
            $table->string('cor')->default('#CCCCCC');
            $table->timestamps();

            $table->index('patio_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('patio_areas_bloqueadas');
    }
};
