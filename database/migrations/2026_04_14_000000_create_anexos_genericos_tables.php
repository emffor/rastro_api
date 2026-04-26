<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('anexo_categorias', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('slug')->unique();
            $table->string('nome');
            $table->text('descricao')->nullable();
            $table->boolean('ativo')->default(true);
            $table->unsignedInteger('limite_mensal_por_empresa')->nullable();
            $table->unsignedInteger('tamanho_max_kb')->default(500);
            $table->json('mime_types_permitidos')->nullable();
            $table->timestamps();
        });

        Schema::create('anexos', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('empresa_id');
            $table->string('categoria');
            $table->string('path', 2048);
            $table->string('original_name');
            $table->string('mime_type', 150);
            $table->unsignedBigInteger('size_bytes');
            $table->string('storage_disk', 50)->default('s3');
            $table->string('hash_arquivo', 128);
            $table->uuid('uploaded_by')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('empresa_id')->references('id')->on('empresas')->cascadeOnDelete();
            $table->foreign('categoria')->references('slug')->on('anexo_categorias')->cascadeOnUpdate()->restrictOnDelete();
            $table->foreign('uploaded_by')->references('id')->on('users')->nullOnDelete();
            $table->index(['empresa_id', 'categoria']);
            $table->index(['empresa_id', 'hash_arquivo']);
            $table->index(['categoria', 'hash_arquivo']);
        });

        Schema::create('anexos_relacionaveis', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('anexo_id')->constrained('anexos')->cascadeOnDelete();
            $table->uuidMorphs('anexable');
            $table->string('campo')->nullable();
            $table->unsignedInteger('ordem')->default(0);
            $table->timestamp('created_at')->nullable();

            $table->index(['anexo_id', 'anexable_type', 'anexable_id']);
        });

        Schema::create('empresa_upload_limite_categoria', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('empresa_id');
            $table->string('categoria_slug');
            $table->string('mes_referencia', 7);
            $table->unsignedInteger('uploads_usados')->default(0);
            $table->timestamps();

            $table->foreign('empresa_id')->references('id')->on('empresas')->cascadeOnDelete();
            $table->foreign('categoria_slug')->references('slug')->on('anexo_categorias')->cascadeOnUpdate()->restrictOnDelete();
            $table->unique(['empresa_id', 'categoria_slug', 'mes_referencia']);
            $table->index(['empresa_id', 'categoria_slug', 'mes_referencia'], 'idx_upload_limite_categoria_mes');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('empresa_upload_limite_categoria');
        Schema::dropIfExists('anexos_relacionaveis');
        Schema::dropIfExists('anexos');
        Schema::dropIfExists('anexo_categorias');
    }
};
