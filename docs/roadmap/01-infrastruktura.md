# F1 – Infrastruktúra, projektváz, minőségi eszközök

**Modell / effort:** Sonnet 5.5 / medium · **Előfeltétel:** Docker, `gh` bejelentkezve

## Cél

Egy üres, de teljesen működő Laravel 13 projekt Dockerben (PHP 8.5, nginx, MySQL 8.4), amelyen minden minőségi kapu már az első naptól fut. A git repó és a publikus GitHub repó is elkészül.

## Indítás

Új terminálban futtasd. A parancs egy új, interaktív Claude Code munkamenetet nyit a megadott modellel és efforttal, és rögtön elküldi az indító promptot. A fájlszerkesztéseket automatikusan jóváhagyja (`acceptEdits`), a shell parancsokra továbbra is rákérdez.

```bash
cd ~/Documents/allaskereses/wd/device-registry
claude --model claude-sonnet-5-5 --effort medium --permission-mode acceptEdits -n "F1 Infrastruktura" '
Olvasd el a docs/task.txt, a docs/roadmap/README.md és a docs/roadmap/01-infrastruktura.md fájlt,
és hajtsd végre az F1 fázist. A lokális PHP 8.3, ezért minden composer/artisan/pest
parancsot a Docker app konténerben futtass. A végén futtasd a `make qa`-t, és jelezd az eredményt.'
```

## Feladatok

### Git
- [ ] `git init`, `main` branch, `.gitignore` (Laravel alap + `.env`, `/vendor`, `/node_modules`, `coverage/`, `.phpunit.cache`)
- [ ] `.editorconfig` (UTF-8, LF, 4 szóköz PHP-ban, a fájl végén újsor)

### Docker
- [ ] `docker/php/Dockerfile`: `php:8.5-fpm-alpine`, kiterjesztések: `pdo_mysql`, `bcmath`, `intl`, `zip`, `opcache`, `pcov` (lefedettséghez; ha nincs 8.5-re, akkor `xdebug` coverage módban). A Composert a `composer:2` image-ből másold be. **Nem root felhasználó** (UID/GID build argból, alapból 1000).
- [ ] `docker/nginx/default.conf`: `public/` a gyökér, `fastcgi_pass app:9000`, `client_max_body_size 1m`, `server_tokens off`, `add_header X-Content-Type-Options nosniff`
- [ ] `docker/mysql/init/01-test-database.sql`: `device_registry_test` adatbázis és jogosultság az app felhasználónak
- [ ] `docker-compose.yaml` (a task ezt a fájlnevet kéri):
  - `app` (build), `nginx` (`nginx:stable-alpine`, `8080:80`), `mysql` (`mysql:8.4`, healthcheck, nevesített volume)
  - a MySQL port **ne** legyen publikálva, vagy csak `127.0.0.1:3306`-ra
  - az `app` a `mysql`-re `condition: service_healthy`-vel várjon
- [ ] `Makefile`: `up`, `down`, `install`, `migrate`, `fresh`, `test`, `coverage`, `lint`, `fix`, `stan`, `cs`, `audit`, `qa` (= minden kapu sorban), `shell`

### Laravel
- [ ] Laravel 13 telepítése a konténerben. Mivel a könyvtár nem üres (`docs/`), előbb ideiglenes könyvtárba (`composer create-project laravel/laravel:^13.0 /tmp/app`), onnan másold át.
- [ ] `.env.example`: MySQL beállítások, `APP_NAME="Device Registry"`, `DOCS_USERNAME`, `DOCS_PASSWORD` (üres alapértékkel), `API_LOGGING_ENABLED=true`
- [ ] **Ne** futtasd az `install:api`-t (Sanctumot telepítene). Hozd létre kézzel a `routes/api.php`-t, és regisztráld a `bootstrap/app.php`-ban (`withRouting(api: ..., apiPrefix: 'api')`).
- [ ] Az alapból felesleges dolgokat töröld vagy hagyd minimálisan: a welcome view maradhat; a frontend (Vite, npm) nem kell, ezt a README-ben jelezd.
- [ ] `phpunit.xml`: tesztkörnyezetben MySQL, `DB_DATABASE=device_registry_test`

### Minőségi eszközök
- [ ] Pest: `pestphp/pest`, `pestphp/pest-plugin-laravel`, `--init`, a példatesztek Pestre átírva vagy törölve
- [ ] `pint.json`: `"preset": "psr12"`, plusz szabályok: `declare_strict_types`, `no_unused_imports`, `ordered_imports` (alpha), `single_quote`, `trailing_comma_in_multiline`
- [ ] `phpcs.xml`: `PSR12` standard az `app`, `config`, `database`, `routes`, `tests` könyvtárakra. Ha a Pint és a phpcs ütközik, a phpcs-nek legyen igaza (ez a független PSR-12 ellenőrzés).
- [ ] Larastan (`larastan/larastan`), `phpstan.neon`, level 6, `app/` és `database/` path
- [ ] A Laravel által generált fájlokra is fusson le a `pint`, és legyen bennük `declare(strict_types=1)`
- [ ] `composer.json` scriptek: `lint`, `stan`, `test`, `qa`
- [ ] `tests/ArchTest.php` (néhány sor): `arch()->preset()->php()`, `arch()->preset()->security()`, `expect('App')->toUseStrictTypes()`

### MCP
- [ ] `laravel/boost` dev függőségként, `php artisan boost:install` (Claude Code guideline-ok)
- [ ] `.mcp.json` a projekt gyökerében:
  ```json
  { "mcpServers": { "laravel-boost": { "command": "docker", "args": ["compose", "exec", "-T", "app", "php", "artisan", "boost:mcp"] } } }
  ```
- [ ] `CLAUDE.md` kiegészítése egy projekt-szakasszal: a konténerben kell futtatni, `make qa` kötelező commit előtt, link a `docs/roadmap/README.md`-re

### GitHub
- [ ] `gh repo create device-registry --public --source . --remote origin` (**előtte kérdezz rá a felhasználónál**), első push

## Elfogadási feltételek

- [ ] `docker compose up -d --build` hiba nélkül elindul; a `http://localhost:8080/up` válasza 200
- [ ] `docker compose exec app php -v` → PHP 8.5.x
- [ ] `docker compose exec app php artisan --version` → Laravel 13.x
- [ ] `make fresh` (migrate:fresh --seed) lefut
- [ ] `make qa` zöld (Pint, phpcs, PHPStan, Pest, `dump-autoload --strict-psr`, `composer audit`)
- [ ] A Claude Code a `/mcp` alatt látja a `laravel-boost` szervert

## Commit

`chore: bootstrap Laravel 13 on PHP 8.5 with Docker, Pest and quality tooling`
