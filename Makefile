include .env
export

DB_HOST ?= localhost
DB_PORT ?= 5432
DB_DATABASE ?= madeiralegal
DB_USERNAME ?= postgres
DB_PASSWORD ?= password

PSQL = PGPASSWORD="$(DB_PASSWORD)" psql -h $(DB_HOST) -p $(DB_PORT) -U $(DB_USERNAME) -d $(DB_DATABASE)
LOCAL_COMPOSE = docker compose -f docker-compose.local.yml

setup:
	@echo "Stubbing .env..."
	@cp -n .env.example .env || true
	@echo "Building Docker images..."
	@docker compose build
	@echo "Installing dependencies..."
	@docker compose run --rm app composer install
	@echo "Generating app key..."
	@docker compose run --rm app php artisan key:generate
	@echo "Starting containers..."
	@docker compose up -d
	@echo "Running migrations..."
	@docker compose run --rm app php artisan migrate --seed
	@echo "Setup done! Access via http://localhost:8080"

up:
	docker compose up -d

down:
	docker compose down

up-local:
	$(LOCAL_COMPOSE) up -d --build

down-local:
	$(LOCAL_COMPOSE) down

restart-local:
	$(LOCAL_COMPOSE) down
	$(LOCAL_COMPOSE) up -d --build

build:
	docker compose build

shell:
	docker compose exec app bash

tinker:
	docker compose exec app php artisan tinker

migrate:
	docker compose exec app php artisan migrate

rollback:
	docker compose exec app php artisan migrate:rollback --step=1

seed:
	docker compose exec app php artisan db:seed

seed-master:
	docker compose exec app php artisan db:seed --class=MasterSeeder

fresh:
	docker compose exec app php artisan migrate:fresh --seed

wipe:
	docker compose exec app php artisan migrate:fresh

test:
	docker compose exec app php artisan test

seed-empresa:
	docker compose exec app php artisan db:seed --class="Database\\Seeders\\System\\CriarEmpresaSeeder"
	docker compose exec app php artisan db:seed --class="Database\\Seeders\\System\\CriarPatiosLotesSeeder"

seed-especies-serraria:
	docker compose exec app php artisan db:seed --class="Database\\Seeders\\System\\CriarEspeciesEmpresaSerrariaSeeder"

seed-especies-ambiental:
	docker compose exec app php artisan db:seed --class="Database\\Seeders\\System\\CriarEspeciesEmpresaAmbientalSeeder"

seed-categorias-anexo:
	docker compose exec app php artisan db:seed --class="Database\\Seeders\\AnexoCategoriaSeeder"

setup-produtos:
	@echo "Atualizando produtos..."
	@$(PSQL) -c "UPDATE public.produtos SET preco_compra = 10, preco_venda = 20, estoque_quantidade = 2000;"

	@$(PSQL) -c "UPDATE public.produtos p SET categoria_id = (SELECT c.id FROM public.categorias c WHERE c.nome = 'Caibro Maçaranduba') WHERE p.nome LIKE '%CAIBRO MAÇ%';"

	@$(PSQL) -c "UPDATE public.produtos p SET categoria_id = (SELECT c.id FROM public.categorias c WHERE c.nome = 'Linha Maçaranduba') WHERE p.nome LIKE '%LINHA MAÇ%';"

	@echo "Produtos atualizados!"
	@echo "Gerando DOFs para a primeira empresa..."
	@docker exec madeiralegal-app php artisan dof:gerar $$($(PSQL) -t -A -c "SELECT id FROM public.empresas ORDER BY created_at ASC LIMIT 1;")
	@echo "DOFs gerados com sucesso!"
