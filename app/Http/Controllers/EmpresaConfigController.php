<?php

namespace App\Http\Controllers;

use App\Models\Empresa;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class EmpresaConfigController extends Controller
{
    private function usuarioPodeGerenciarEmpresa($user): bool
    {
        return (bool) ($user?->is_admin || $user?->is_master);
    }

    private function empresaDaRequisicao(Request $request): ?Empresa
    {
        $empresaId = $request->input('empresa_id') ?: $request->user()?->empresa_id;

        if (!$empresaId) {
            return null;
        }

        return Empresa::query()->find($empresaId);
    }

    /**
     * Retorna os dados da empresa do usuário logado.
     */
    public function show(Request $request): JsonResponse
    {
        $user = $request->user();

        if (!$this->usuarioPodeGerenciarEmpresa($user)) {
            return response()->json([
                'mensagem' => 'Apenas administradores podem acessar as configurações da empresa.',
            ], 403);
        }

        $empresa = $this->empresaDaRequisicao($request);

        if (!$empresa) {
            return response()->json([
                'mensagem' => 'Empresa não encontrada.',
            ], 404);
        }
        
        // Construir URL completa do logo se existir
        $empresaData = $empresa->toArray();
        if ($empresa->logo_url) {
            $empresaData['logo_url'] = env('MINIO_ENDPOINT') . $empresa->logo_url;
        }
        
        return response()->json($empresaData);
    }

    /**
     * Atualiza os dados da empresa.
     */
    public function update(Request $request): JsonResponse
    {
        $user = $request->user();
        
        if (!$this->usuarioPodeGerenciarEmpresa($user)) {
            return response()->json([
                'mensagem' => 'Apenas administradores podem editar as configurações da empresa.',
            ], 403);
        }

        $validated = $request->validate([
            'nome' => 'sometimes|string|max:255',
            'cnpj' => 'sometimes|string|max:20',
            'email' => 'sometimes|email|max:255',
            'telefone' => 'sometimes|string|max:20',
            'endereco' => 'nullable|string|max:255',
            'cidade' => 'nullable|string|max:100',
            'estado' => 'nullable|string|max:2',
            'cep' => 'nullable|string|max:10',
            'inscricao_estadual' => 'nullable|string|max:30',
        ]);

        $empresa = $this->empresaDaRequisicao($request);

        if (!$empresa) {
            return response()->json([
                'mensagem' => 'Empresa não encontrada.',
            ], 404);
        }

        $empresa->update($validated);

        return response()->json([
            'mensagem' => 'Configurações atualizadas com sucesso.',
            'empresa' => $empresa->fresh(),
        ]);
    }

    /**
     * Faz upload do logo da empresa para o S3/MinIO.
     */
    public function uploadLogo(Request $request): JsonResponse
    {
        $user = $request->user();
        
        if (!$this->usuarioPodeGerenciarEmpresa($user)) {
            return response()->json([
                'mensagem' => 'Apenas administradores podem fazer upload do logo.',
            ], 403);
        }

        $request->validate([
            'logo' => 'required|image|mimes:jpeg,png,jpg,gif,svg,webp|max:2048',
        ]);

        $empresa = $this->empresaDaRequisicao($request);

        if (!$empresa) {
            return response()->json([
                'mensagem' => 'Empresa não encontrada.',
            ], 404);
        }

        $file = $request->file('logo');
        
        // Gerar nome único para o arquivo
        $filename = 'logos/' . $empresa->id . '-' . Str::random(10) . '.' . $file->getClientOriginalExtension();
        
        // Fazer upload para S3/MinIO
        $path = Storage::disk('s3')->putFileAs('', $file, $filename, 'public');
        
        // Salvar apenas o path relativo (sem endpoint) no banco
        $bucket = config('filesystems.disks.s3.bucket');
        $relativePath = '/' . $bucket . '/' . $path;
        
        // Atualizar empresa com novo logo (apenas o path)
        $empresa->update(['logo_url' => $relativePath]);
        
        // Retornar URL completa para o frontend usar imediatamente
        $fullUrl = env('MINIO_ENDPOINT') . $relativePath;

        return response()->json([
            'mensagem' => 'Logo atualizado com sucesso.',
            'logo_url' => $fullUrl,
        ]);
    }

    /**
     * Remove o logo da empresa.
     */
    public function removeLogo(Request $request): JsonResponse
    {
        $user = $request->user();
        
        if (!$this->usuarioPodeGerenciarEmpresa($user)) {
            return response()->json([
                'mensagem' => 'Apenas administradores podem remover o logo.',
            ], 403);
        }

        $empresa = $this->empresaDaRequisicao($request);

        if (!$empresa) {
            return response()->json([
                'mensagem' => 'Empresa não encontrada.',
            ], 404);
        }
        
        // Tentar deletar do S3 se existir
        if ($empresa->logo_url) {
            $path = parse_url($empresa->logo_url, PHP_URL_PATH);
            Storage::disk('s3')->delete(ltrim($path, '/'));
        }
        
        $empresa->update(['logo_url' => null]);

        return response()->json([
            'mensagem' => 'Logo removido com sucesso.',
        ]);
    }
}
