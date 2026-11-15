.PHONY: setup up down sh test test-api lint fmt migrate

# Fresh clone: env file, images, dependencies (api + web), app key, migrations.
setup:
	test -f api/.env || cp api/.env.example api/.env
	# Create the web mountpoints as the host user: otherwise Docker creates them as root when the volumes mount over ./web.
	mkdir -p web/node_modules web/.nuxt
	docker compose build
	docker compose run --rm --no-deps api composer install
	grep -q '^APP_KEY=.\+' api/.env || docker compose run --rm --no-deps api php artisan key:generate
	docker compose run --rm --no-deps web pnpm install --frozen-lockfile
	pnpm --dir web install --frozen-lockfile
	docker compose up -d --wait mysql redis
	docker compose run --rm api php artisan migrate --force

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
