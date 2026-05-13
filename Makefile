.PHONY: up down restart logs shell-backend shell-db migrate seed test deploy backup dev dev-down dev-logs setup admin wait-backend sync-db-password

dev: sync-db-password
	docker compose -f docker-compose.yml -f docker-compose.dev.yml up -d --build
	@$(MAKE) wait-backend
	@docker compose -f docker-compose.yml -f docker-compose.dev.yml exec -T backend php artisan migrate --force
	@echo ""
	@echo "Dev up. Create admin users with:"
	@echo "  make admin EMAIL=you@example.com NAME=you PASSWORD=yourpass"

dev-down:
	docker compose -f docker-compose.yml -f docker-compose.dev.yml down

dev-logs:
	docker compose -f docker-compose.yml -f docker-compose.dev.yml logs -f

up: sync-db-password
	docker compose up -d --build
	@$(MAKE) wait-backend
	@docker compose exec -T backend php artisan migrate --force
	@echo ""
	@echo "Backend up. Create admin users with:"
	@echo "  make admin EMAIL=you@example.com NAME=you PASSWORD=yourpass"

# Mirrors secrets/db_password.txt → DB_PASSWORD in repo-root .env so that
# docker compose's ${DB_PASSWORD} substitution gets a real value.
# Without this, the backend container starts with an empty DB_PASSWORD and
# Postgres rejects the connection (fe_sendauth: no password supplied).
sync-db-password:
	@if [ -f secrets/db_password.txt ]; then \
	  DB_PWD=$$(tr -d '\n' < secrets/db_password.txt); \
	  if grep -q '^DB_PASSWORD=' .env 2>/dev/null; then \
	    sed -i.bak "s|^DB_PASSWORD=.*|DB_PASSWORD=$$DB_PWD|" .env && rm -f .env.bak; \
	  else \
	    echo "DB_PASSWORD=$$DB_PWD" >> .env; \
	  fi; \
	fi

# Block until backend can run artisan — db healthcheck guarantees Postgres
# is up but PHP-FPM/the artisan binary takes a few extra seconds.
wait-backend:
	@echo "Waiting for backend container..."
	@until docker compose exec -T backend php artisan --version >/dev/null 2>&1; do sleep 1; done
	@echo "Backend ready."

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

# Create an admin user able to use the AI chat:
#   make admin EMAIL=kirill@example.com NAME=adexitum PASSWORD=secretpass
# Optional: TIMEZONE=Europe/Moscow (default UTC).
admin:
	@if [ -z "$(EMAIL)" ] || [ -z "$(NAME)" ] || [ -z "$(PASSWORD)" ]; then \
	  echo "Usage: make admin EMAIL=... NAME=... PASSWORD=... [TIMEZONE=...]"; \
	  exit 1; \
	fi
	docker compose exec -T backend php artisan users:create \
	  "$(EMAIL)" "$(NAME)" \
	  --password="$(PASSWORD)" \
	  --timezone="$(or $(TIMEZONE),UTC)" \
	  --admin

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
