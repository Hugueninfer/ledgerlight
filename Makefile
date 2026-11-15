.PHONY: setup up down sh test test-api test-web lint lint-api lint-web fmt migrate

# compose.yaml builds the dev images with the host user's uid/gid (`${UID:-1000}`), so bind-mounted files stay yours.
export UID := $(shell id -u)
export GID := $(shell id -g)

# Fresh clone: env file, images, dependencies (api + web), app key, migrations and demo data.
setup:
	test -f api/.env || cp api/.env.example api/.env
	# Create the web mountpoints as the host user: otherwise Docker creates them as root when the volumes mount over ./web.
	mkdir -p web/node_modules web/.nuxt
	docker compose build
	docker compose run --rm --no-deps api composer install
	grep -q '^APP_KEY=.\+' api/.env || docker compose run --rm --no-deps api php artisan key:generate
	docker compose run --rm --no-deps web pnpm install --frozen-lockfile
	@if command -v pnpm >/dev/null 2>&1; then pnpm --dir web install --frozen-lockfile; else echo "pnpm not found on the host: skipping the host install. It is only needed for the web targets of 'make lint' and 'make test'."; fi
	docker compose up -d --wait mysql redis
	docker compose run --rm api php artisan migrate --seed --force

up:
	docker compose up -d --build --wait

down:
	docker compose down

sh:
	docker compose exec api sh

test: test-api test-web

test-api:
	docker compose exec -T api vendor/bin/pest

test-web:
	pnpm --dir web run test

lint: lint-api lint-web

lint-api:
	docker compose exec -T api vendor/bin/pint --test
	docker compose exec -T api vendor/bin/phpstan analyse --memory-limit=1G --no-progress

lint-web:
	pnpm --dir web run lint
	pnpm --dir web run typecheck

fmt:
	docker compose exec -T api vendor/bin/pint
	pnpm --dir web exec eslint --fix .

migrate:
	docker compose exec -T api php artisan migrate
