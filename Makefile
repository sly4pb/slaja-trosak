# ─────────────────────────────────────────────────────────────
#  Makefile — slaja-trosak Docker shortcuts
#  Upotreba: make <komanda>
# ─────────────────────────────────────────────────────────────

.PHONY: help up down build restart logs shell migrate fresh seed \
        composer artisan tinker test npm redis-cli

# ─── Default: prikaži pomoć ───────────────────────────────
help:
	@echo ""
	@echo "  make up           Pokreni sve servise"
	@echo "  make down         Zaustavi sve servise"
	@echo "  make build        Build Docker image-a"
	@echo "  make restart      Restart svih servisa"
	@echo "  make logs         Prati logove (svi servisi)"
	@echo "  make shell        Uđi u app container (bash)"
	@echo "  make migrate      Pokreni migracije"
	@echo "  make fresh        migrate:fresh --seed"
	@echo "  make composer     composer install"
	@echo "  make test         php artisan test"
	@echo "  make redis-cli    Redis CLI"
	@echo ""

# ─── Docker ───────────────────────────────────────────────
up:
	docker compose up -d

down:
	docker compose down

build:
	docker compose build --no-cache

restart:
	docker compose restart

logs:
	docker compose logs -f

# ─── App container ────────────────────────────────────────
shell:
	docker compose exec app bash

artisan:
	docker compose exec app php artisan $(filter-out $@,$(MAKECMDGOALS))

composer:
	docker compose exec app composer install

migrate:
	docker compose exec app php artisan migrate

fresh:
	docker compose exec app php artisan migrate:fresh --seed

tinker:
	docker compose exec app php artisan tinker

test:
	docker compose exec app php artisan test

# ─── Redis CLI ────────────────────────────────────────────
redis-cli:
	docker compose exec redis redis-cli

# ─── Catch-all za artisan argumente ───────────────────────
%:
	@:
