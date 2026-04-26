# DIRETRIZES DE DESENVOLVIMENTO BACKEND

## 1. ESCOPO E PRECEDENCIA

- Este arquivo define as regras gerais para este projeto backend.
- Em caso de conflito, siga esta ordem:
    1. instrucao direta do usuario
    2. AGENTS.md mais proximo do arquivo alterado
    3. AGENTS.md de niveis superiores
    4. documentacao interna do repositorio
    5. padroes da ferramenta

## 2. COMPORTAMENTO E COMUNICACAO

- Priorize seguranca, precisao, contexto e previsibilidade acima de velocidade.
- Responda de forma direta, objetiva e natural.
- Comunicacao exclusivamente em pt-BR.
- Antes de implementar, entenda o objetivo, as restricoes tecnicas e o impacto da mudanca.
- Em caso de ambiguidade, adote a premissa mais segura e coerente com o contexto.
- Quando houver risco relevante, explicite a premissa adotada e os impactos esperados.

## 3. WORKFLOW DE DESENVOLVIMENTO

- Antes de alterar arquivos, apresente um plano curto e objetivo.
- Antes de implementar, valide premissas funcionais e tecnicas.
- Considere edge cases, regressao funcional, impacto em dados existentes e compatibilidade retroativa.
- Ao concluir, resuma objetivamente:
    - o que foi alterado
    - riscos ou impactos identificados
    - o que foi validado
    - o que nao foi validado

## 4. RESTRICOES CRITICAS E OPERACOES DE RISCO

### 4.1. Banco de Dados

- Nunca execute automaticamente comandos destrutivos ou potencialmente destrutivos.
- Sempre peca confirmacao explicita antes de qualquer operacao irreversivel ou com risco de perda de dados.
- Isso inclui, mas nao se limita a:
    - `migrate`
    - `migrate:fresh`
    - `migrate:refresh`
    - `migrate:reset`
    - `rollback`
    - `db:wipe`
    - `drop table`
    - `truncate`
    - `delete` em massa sem filtro claro
    - `update` em massa sem filtro claro
    - seeds que possam sobrescrever ou corromper dados existentes
- Em tarefas de banco, priorize analise, geracao de codigo e instrucoes de execucao, sem rodar comandos automaticamente.
- Sempre explicite:
    - impacto esperado
    - risco para dados existentes
    - possibilidade de reversao
    - necessidade de backup, quando aplicavel

### 4.2. Operacoes de Risco Geral

- Nunca execute overwrite irreversivel, remocao ampla de arquivos ou refactors estruturais de alto impacto sem confirmacao explicita.
- Priorize sempre preservacao, compatibilidade e reversibilidade.

## 5. PADROES DE CODIGO E ARQUITETURA

### 5.1. Integridade e Qualidade

- Entregue apenas codigo completo, funcional, coerente com o contexto e executavel dentro da arquitetura do projeto.
- Proibido usar `TODO`, `FIXME`, placeholders ou mocks permanentes sem alinhamento explicito.
- Prefira responsabilidade unica por modulo, classe, funcao ou metodo.
- Use nomes claros, descritivos e consistentes com o dominio.
- Evite aninhamento excessivo; use early returns quando fizer sentido.
- Remova imports, variaveis, funcoes e trechos nao utilizados.
- Nao deixe comentarios mortos, logs de debug, `dd`, `dump`, `var_dump`, `print_r` ou equivalentes sem justificativa real.
- Evite duplicacao desnecessaria.
- Nao introduza abstracoes prematuras ou desnecessarias.

### 5.2. Organizacao Arquitetural

- Respeite a arquitetura adotada no projeto.
- Mantenha camadas bem definidas e evite misturar responsabilidades.
- Regras de negocio devem ficar na camada apropriada, e nao em controllers, rotas ou models com excesso de responsabilidade.
- Evite duplicidade de logica ou multiplas fontes de verdade.
- Preserve contratos existentes sempre que possivel.

## 6. SEGURANCA E PERFORMANCE

### 6.1. Seguranca

- Valide e sanitize entradas vindas de usuarios, arquivos e integracoes externas.
- Nao exponha segredos, credenciais ou detalhes internos em erros, logs ou respostas.
- Nunca confie apenas na validacao do cliente para regras criticas.
- Aplique autenticacao e autorizacao conforme o padrao do projeto.

### 6.2. Performance

- Evite consultas N+1 e queries dentro de loops.
- Evite desperdicio de processamento, consultas redundantes e recomputacoes desnecessarias.
- Priorize simplicidade primeiro; otimize apenas quando houver ganho real e impacto relevante.

## 7. PADRAO DE IMPLEMENTACAO DE FEATURES

- Ao criar endpoints, siga este padrao:
    - Controller para entrada e resposta
    - Service para regra de negocio e orquestracao
    - Resource para transformacao da saida

### 7.1. Controllers

- Devem:
    - receber a request
    - validar a entrada
    - chamar apenas o service responsavel
    - retornar resposta padronizada
- Devem usar `FormRequest` quando esse for o padrao adotado no contexto.
- Caso nao exista `FormRequest` no modulo, a validacao pode ficar no controller de forma enxuta.
- Nao devem conter:
    - regra de negocio relevante
    - queries complexas
    - transformacoes complexas de payload

### 7.2. Services

- Devem concentrar toda a regra de negocio e a orquestracao principal.
- Devem ser responsaveis por:
    - preparar parametros
    - aplicar filtros
    - ordenar resultados
    - paginar resultados
    - montar ou orquestrar consultas de dados
- Quando houver regra complexa:
    - decompor em metodos privados coesos
    - usar constantes para valores fixos, aliases e colunas permitidas

### 7.3. Resources

- Devem transformar o payload final da API.
- Devem definir os campos expostos pela resposta.
- Devem aplicar mascaramento, criptografia ou ofuscacao de IDs sensiveis quando isso fizer parte do contrato.
- Nao devem conter regra de negocio.

### 7.4. CONVENCOES ESPECIFICAS DE IMPLEMENTACAO

- Quando o contexto exigir filtros, ordenacao e paginacao, preferir os helpers padrao ja adotados no projeto, como `QueryHelper`.
- Quando houver busca textual com `like`, preferir o uso de helpers utilitarios ja existentes no projeto, como `StringHelper`, para normalizacao e escape seguro.
- Quando houver colunas permitidas para ordenacao, preferir declarar listas explicitas em constantes da classe, como:
    - `COLUNAS_ORDENACAO`
    - `ALIASES_ORDENACAO`
- Quando a entrada receber IDs ofuscados/criptografados, realizar a conversao no ponto apropriado da camada de entrada ou service usando o helper padrao do projeto, como `AuthHelper::decryptId(...)`.
- Quando a resposta expuser IDs protegidos, aplicar a transformacao no Resource usando o helper padrao do projeto, como `AuthHelper::encryptId(...)`.
- Sempre preservar o contrato de seguranca e serializacao de IDs ja adotado no modulo existente.
- Em novo codigo, use `snake_case` por padrao; mantenha `camelCase` apenas quando ja existir contrato, padrao pronto ou dependencia do framework.

### 7.5. PADRAO DE RESPOSTA COM HELPERS

- Sempre que o modulo seguir o padrao atual do projeto, utilizar `ResponseHelper` para padronizar respostas da API.
- Em respostas de sucesso nao paginadas, preferir `ResponseHelper::successResponse(...)`.
- Em respostas paginadas, preferir `ResponseHelper::paginatedResponse(...)`.
- Em respostas de erro, preferir `ResponseHelper::errorResponse(...)`.
- Sempre que aplicavel, transformar o payload com `Resource` antes de retornar a resposta.
- Preservar mensagens, status code e estrutura de resposta conforme o contrato ja adotado no projeto.
- Evitar montar manualmente arrays de resposta quando ja existir helper padrao para o mesmo caso de uso.
- Exemplo de sucesso simples:
    - `ResponseHelper::successResponse(...)`
- Exemplo de sucesso paginado:
    - `ResponseHelper::paginatedResponse(...)`
- Exemplo de erro:
    - `ResponseHelper::errorResponse(...)`

## 8. EXEMPLO ESTRUTURAL

```php
class AlgumController extends Controller
{
    public function __construct(
        private readonly AlgumService $algumService
    ) {}

    public function index(Request $request): JsonResponse
    {
        $parametros = $request->all(); // ou FormRequest validado

        $resultado = $this->algumService->listar($parametros);

        return ResponseHelper::paginatedResponse(
            mensagem: 'REGISTROS_ENCONTRADOS',
            dados: AlgumResource::collection($resultado['data']),
            pagina: $resultado['pagina'],
            itensPorPagina: $resultado['itens_por_pagina'],
            total: $resultado['total'],
        );
    }
}

class AlgumService
{
    public function listar(array $parametros): array
    {
        $query = $this->criarQueryBase();

        $this->aplicarFiltros($query, $parametros);

        return [
            'data' => $query->get(),
            'pagina' => 1,
            'itens_por_pagina' => 10,
            'total' => 100,
        ];
    }

    private function criarQueryBase()
    {
        // base da consulta
    }

    private function aplicarFiltros($query, array $parametros): void
    {
        // filtros da feature
    }
}
```

# Sugestão de Commit

Ao finalizar qualquer resposta que envolva criação, alteração ou remoção de código, a resposta deve encerrar na seguinte ordem:

1. Sugestão de commit
2. Resumo do que foi alterado (conforme seção WORKFLOW)

A sugestão de commit deve seguir o formato:

```bash
<descricao curta em pt-BR>
```

A descrição deve ser clara, no imperativo e em pt-BR.

Exemplos:

```bash
adiciona grafico de vendas com filtro por periodo
corrige validacao de token expirado
simplifica logica de calculo de comissao
```

## 9. CHECKLIST PARA FINALIZACAO

- Ha risco de regressao funcional ou de contrato?
- A entrada foi validada nos pontos criticos?
- Existe risco de consulta ineficiente, N+1 ou query desnecessaria?
- O tratamento de erro expoe dados sensiveis?
- A implementacao respeita a arquitetura existente?
- O codigo esta limpo, coeso e sem debugs ou comentarios desnecessarios?
