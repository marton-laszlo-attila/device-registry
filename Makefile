.PHONY: up down install migrate fresh test coverage lint fix stan cs audit qa shell

APP := docker compose exec -T app

# 16 random bytes as 32 hex characters.
RANDOM_SECRET = $$(head -c 16 /dev/urandom | od -An -tx1 | tr -d ' \n')

up: .env
	docker compose up -d --build

# Docker Compose variables; created once, with random MySQL passwords.
.env:
	@printf 'DB_PASSWORD=%s\nMYSQL_ROOT_PASSWORD=%s\n' "$(RANDOM_SECRET)" "$(RANDOM_SECRET)" > .env
	@echo "Created .env with random MySQL passwords."

down:
	docker compose down

install:
	$(APP) composer install
	@test -f api/.env || cp api/.env.example api/.env
	@grep -q '^APP_KEY=.' api/.env || $(APP) php artisan key:generate --force

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
