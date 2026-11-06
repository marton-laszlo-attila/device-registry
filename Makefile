.PHONY: up down install migrate fresh test coverage lint fix stan cs audit qa shell

APP := docker compose exec -T app

up:
	docker compose up -d --build

down:
	docker compose down

install:
	$(APP) composer install
	@test -f api/.env || cp api/.env.example api/.env
	$(APP) php artisan key:generate --force

migrate:
	$(APP) php artisan migrate --force

fresh:
	$(APP) php artisan migrate:fresh --seed --force

test:
	$(APP) vendor/bin/pest

coverage:
	$(APP) vendor/bin/pest --coverage --min=100

lint:
	$(APP) vendor/bin/pint --test

fix:
	$(APP) vendor/bin/pint

stan:
	$(APP) vendor/bin/phpstan analyse --memory-limit=1G

cs:
	$(APP) vendor/bin/phpcs

audit:
	$(APP) composer audit

qa:
	$(APP) composer dump-autoload --strict-psr
	$(MAKE) lint
	$(MAKE) cs
	$(MAKE) stan
	$(MAKE) coverage
	$(MAKE) audit

shell:
	docker compose exec app sh
