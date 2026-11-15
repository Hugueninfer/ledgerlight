.PHONY: up down sh test test-api lint fmt migrate

up:
	docker compose up -d --build --wait

down:
	docker compose down

sh:
	docker compose exec api sh

test: test-api

test-api:
	docker compose exec -T api vendor/bin/pest

lint:
	docker compose exec -T api vendor/bin/pint --test

fmt:
	docker compose exec -T api vendor/bin/pint

migrate:
	docker compose exec -T api php artisan migrate
