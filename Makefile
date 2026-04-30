.PHONY: up down restart logs shell-backend shell-db migrate seed test deploy backup dev dev-down dev-logs setup

dev:
	docker compose -f docker-compose.yml -f docker-compose.dev.yml up -d --build

dev-down:
	docker compose -f docker-compose.yml -f docker-compose.dev.yml down

dev-logs:
	docker compose -f docker-compose.yml -f docker-compose.dev.yml logs -f

up:
	docker compose up -d

down:
	docker compose down

restart:
	docker compose restart

logs:
	docker compose logs -f

shell-backend:
	docker compose exec backend bash

shell-db:
	docker compose exec db psql -U dt dt

migrate:
	docker compose exec -T backend php artisan migrate

seed:
	docker compose exec -T backend php artisan db:seed

test:
	cd backend && php artisan test
	cd frontend && npm run test

deploy:
	bash deploy.sh

backup:
	@mkdir -p backups/manual
	docker compose exec -T db pg_dump -U dt dt | gzip > backups/manual/dt-$$(date +%Y-%m-%d-%H%M%S).sql.gz

setup:
	@echo "Creating secrets directory..."
	@mkdir -p secrets backups/manual
	@if [ ! -f secrets/db_password.txt ]; then \
	  openssl rand -base64 32 > secrets/db_password.txt; \
	  echo "Generated secrets/db_password.txt"; \
	else \
	  echo "secrets/db_password.txt already exists"; \
	fi
	@echo "Done. Set DOMAIN and APP_URL env vars before running 'make deploy'."
