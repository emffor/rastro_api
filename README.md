# Rastro API

Sistema de gestão de conformidade e logística de estoque de madeiras com foco no saldo do DOF (IBAMA).

## Tecnologias

- Laravel 11 / PHP 8.3
- PostgreSQL 16
- Redis 7
- Docker & Docker Compose
- Nginx

## Setup Inicial

O projeto possui um Makefile para facilitar o setup.

1. **Setup Completo (Primeira vez)**:

    ```bash
    make setup
    ```

    Isso irá:
    - Copiar `.env.example` para `.env`
    - Subir os containers (Docker Compose)
    - Instalar dependências (Composer)
    - Gerar chave da aplicação
    - Rodar migrations e seeders

2. **Iniciar o ambiente**:

    ```bash
    make up
    ```

3. **Parar o ambiente**:
    ```bash
    make down
    ```

## Acesso

- API Base URL: `http://localhost:8080/api`

## Endpoints Principais

### Movimentação de Estoque

`POST /api/movimentacoes-estoque`

Body (Exemplo):

```json
{
    "produto_id": "a0c99776-00ae-43cc-a1e8-48ece3e77c46",
    "tipo": "SAIDA",
    "quantidade": 1.5,
    "data_movimentacao": "2024-01-01",
    "observacao": "Venda nota 123"
}
```

### Consultas

- `GET /api/especies`
- `GET /api/dofs`
- `GET /api/produtos`

## Estrutura do Projeto

- `app/Services`: Lógica de negócio (ex: `MovimentacaoEstoqueService`)
- `app/Repository`: Acesso a dados (ex: `MovimentacaoEstoqueRepository`, `DofRepository`)
- `app/DTOs`: Transferência de dados
- `app/Models`: Modelos Eloquent (`Especie`, `Dof`, `Produto`, etc.)

## Testes

Para rodar os testes:

```bash
make test
```

## Comandos Docker

### Migrations

```bash
# Executar migrations
docker exec rastro-app php artisan migrate

# Forçar migrations (produção)
docker exec rastro-app php artisan migrate --force

# Ver status das migrations
docker exec rastro-app php artisan migrate:status

# Rollback da última migration
docker exec rastro-app php artisan migrate:rollback
```

### Cache

```bash
# Limpar cache de configuração
docker exec rastro-app php artisan config:clear

# Limpar cache de rotas
docker exec rastro-app php artisan route:clear

# Limpar cache da aplicação
docker exec rastro-app php artisan cache:clear

# Limpar todos os caches
docker exec rastro-app php artisan optimize:clear
```

### Seeders

```bash
# Executar todos os seeders
docker exec rastro-app php artisan db:seed

# Executar seeder específico
docker exec rastro-app php artisan db:seed --class=NomeDoSeeder
```

### Acesso ao Container

```bash
# Entrar no container da aplicação
docker exec -it rastro-app bash

# Acessar o Tinker (REPL do Laravel)

# Limpa o banco e roda as migrations
docker exec rastro-app php artisan migrate:fresh

# Limpa o banco e roda as migrations e seeders
docker exec rastro-app php artisan migrate:fresh --seed
```

# Primeira execução: cria o master

```bash
# Executar todos os seeders
docker exec rastro-app php artisan db:seed --class=MasterSeeder
```

```bash
# Executar seeder específico
docker exec rastro-app php artisan db:seed --class="Database\\Seeders\\System\\CriarEmpresaSeeder"
docker exec rastro-app php artisan db:seed --class="Database\\Seeders\\System\\CriarCargosSeeder"
docker exec rastro-app php artisan db:seed --class="Database\\Seeders\\System\\CriarCategoriasSeeder"
docker exec rastro-app php artisan db:seed --class="Database\\Seeders\\System\\CriarEspeciesEmpresaSerrariaSeeder"
docker exec rastro-app php artisan db:seed --class="Database\\Seeders\\System\\CriarProdutosSeeder"
docker exec rastro-app php artisan db:seed --class="Database\\Seeders\\System\\PermissaoSystemSeeder"
docker exec rastro-app php artisan dof:gerar {empresa_id}
```

### Reset do Banco

Para zerar o banco e recriar do zero:

```bash
make wipe && make migrate && make seed-master && make seed-categorias-anexo
```
