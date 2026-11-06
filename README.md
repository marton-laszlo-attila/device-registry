# Device Registry

[![CI](https://github.com/marton-laszlo-attila/device-registry/actions/workflows/ci.yml/badge.svg)](https://github.com/marton-laszlo-attila/device-registry/actions/workflows/ci.yml)

REST API (Laravel 13, PHP 8.5, MySQL 8.4) for managing contracts and the devices on them.

```bash
make up        # build and start the stack (app, nginx, mysql)
make install   # composer install + app key
make fresh     # migrate:fresh --seed
make qa        # all quality gates
```

The Laravel application lives in `api/`; the repository root holds the Docker setup, `docs/` and the `Makefile`.

The API is served on http://localhost:8080. There is no frontend, so Vite and npm are not used.

The full README (design decisions, API reference) is written in the final roadmap phase.
