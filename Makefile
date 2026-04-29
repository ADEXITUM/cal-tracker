.PHONY: up down restart logs shell-backend shell-db migrate seed test deploy backup

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
	docker compose exec -T backend php artisan test
	docker compose exec -T frontend npm run test

deploy:
	bash deploy.sh

backup:
	docker compose exec -T db pg_dump -U dt dt | gzip > backups/manual/dt-$$(date +%Y-%m-%d-%H%M%S).sql.gz
