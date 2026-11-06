# Device Registry – project rules

- PHP 8.3 is installed locally but the project targets PHP 8.5: run every `composer`, `artisan`, `pest` command inside the Docker `app` container (`docker compose exec app ...` or the `make` targets). Start the stack with `make up`.
- `make qa` (PSR-4, Pint, phpcs, PHPStan, Pest with coverage, `composer audit`) must be green before every commit.
- Roadmap, decisions and architecture: [docs/roadmap/README.md](docs/roadmap/README.md). Phase files live next to it.
- Commits: Conventional Commits in English. Code, README and API docs are in English; the roadmap and the manual test plan are in Hungarian.
- Layout: the Laravel application lives in `api/` (all `app/`, `routes/`, `tests/`, `composer.json` paths in the docs are relative to it). The repository root holds `docs/`, `docker/`, `docker-compose.yaml`, the `Makefile` and this file. Laravel Boost guidelines are in `api/CLAUDE.md`.
- The frontend (Vite, npm) is intentionally not part of this project.
