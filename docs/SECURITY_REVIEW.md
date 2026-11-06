# Security review

| | |
|---|---|
| Date | 2026-11-06 |
| Baseline | commit `009d2fd` (end of phase F4); file:line references in the findings point to this commit |
| Scope | the whole repository: the Laravel application in `api/`, `docker-compose.yaml`, `docker/` (nginx, PHP image, MySQL init), `Makefile`, `.github/workflows/ci.yml`, environment templates, git history |
| Out of scope | vulnerabilities inside third-party packages (covered by `composer audit`), the host machine, a production deployment (there is none) |
| Result | 18 findings: 7 medium, 10 low, 1 informational. **All fixed**, each with a regression test or a reproducible check. No critical or high finding. 13 intentional decisions documented with their reasons. |

## Method

1. **Threat model.** The API has no authentication by design (the task asks for Basic Auth on the documentation only), so whoever can reach it can read and change every record. **Network reachability is the only access control**, and the review asks for every finding how it widens that reachability (other hosts, other web pages, forged addresses) or what an unauthenticated client can do beyond plain CRUD (exhaust resources, erase its traces, learn internals).
2. **Code review of the whole codebase** following the method of the `/security-review` skill: context and existing controls first, then data flow (middleware → validation → service → Eloquent → resource → exception handler), then candidates, each verified against the actual code path, including framework and package behaviour in `vendor/`. The skill itself only reviews the diff against `origin/HEAD`, which is empty for a clean `main`, so the review was done by hand with the same method over every file.
3. **Independent second review.** A separate reviewer agent got the code but not my conclusions. It confirmed my findings and added three (F-03, F-04, F-06) and one detail (F-10). Every one of its findings was reproduced before it was accepted.
4. **Dynamic checks** with curl against the running Docker stack, before and after each fix (excerpts below).
5. **Automated checks:** `composer audit`, PHPStan (Larastan level 6), Pest `arch()->preset()->security()`, secret scan of the git history.
6. **Regression proof:** every fix has a test. A script put back the vulnerable code for each application-level fix one by one, and the matching tests failed in **13 of 13** cases. Infrastructure fixes (nginx, Docker) cannot be reached from Pest; they are verified with the curl and `docker compose` checks shown here and in the manual checklist (`docs/roadmap/05-biztonsag.md`).

## Automated checks

| Check | Result |
|---|---|
| `composer audit` | No security vulnerability advisories found. |
| PHPStan (level 6) | No errors. |
| `arch()->preset()->security()` | Passes: no `eval`, `exec`, `shell_exec`, `system`, `unserialize`, `md5`/`sha1`, `rand`, `extract`, etc. in `app/`. |
| Secrets in the git history (`git log -p --all`) | No real secret. No `APP_KEY` value was ever committed, no `.env` file was ever tracked. `api/.env` is ignored, the new root `.env` is ignored. The only credentials ever committed were the local placeholders `root` / `secret` in `docker-compose.yaml` (F-13, removed) and the CI service database (accepted, A-5). Test fixtures (`docs-secret`, `top-secret-value`) are not credentials. |
| `make qa` after the fixes | Green: PSR-4, Pint, phpcs, PHPStan, 262 Pest tests with 100 % coverage, `composer audit`. |

## Findings

| ID | Finding | OWASP API 2023 | Severity | Status |
|---|---|---|---|---|
| F-01 | Any web page can write to the API (cross-site form POST, `_method` override, CORS `*`) | API8, API2 | Medium | Fixed |
| F-02 | The unauthenticated API listens on every network interface | API8 | Medium | Fixed |
| F-03 | Forged `X-Forwarded-For` bypasses the rate limit and falsifies the logged IP | API4, API8 | Medium | Fixed |
| F-04 | One invalid UTF-8 byte erases a request from the audit log | API8 (logging) | Medium | Fixed |
| F-05 | No brute-force protection on the documentation's Basic Auth | API2 | Medium | Fixed |
| F-06 | Unauthenticated Laravel Boost endpoint writes any text into a log read by the coding agent | API8, API9 | Medium | Fixed |
| F-07 | Unknown `/api` URLs are not rate limited, yet each one writes a log row | API4 | Medium | Fixed |
| F-08 | The API log grows without limit (no retention, size limit counted in characters) | API4 | Low | Fixed |
| F-09 | Any `Host` header is accepted (DNS rebinding) | API8 | Low | Fixed |
| F-10 | User input is serialized into the `unique` validation rule (500, internals with debug on) | API8, injection | Low | Fixed |
| F-11 | A huge `page` number causes a 500 | API4 | Low | Fixed |
| F-12 | `X-Powered-By` reveals the exact PHP version | API8 | Low | Fixed |
| F-13 | MySQL passwords hardcoded in `docker-compose.yaml` | API8 | Low | Fixed |
| F-14 | Floating image tags and GitHub Actions versions | supply chain | Low | Fixed |
| F-15 | Look-alike serial numbers get around the global uniqueness | API6 | Low | Fixed |
| F-16 | Foreign key races end in a 500 instead of a 409 | API6 | Low | Fixed |
| F-17 | Several credential headers are logged in clear text | logging | Low | Fixed |
| F-18 | Unused file download/upload routes (`storage/{path}`) | API9 | Info | Fixed |

### F-01 – Any web page can write to the API · Medium

**Problem.** Without authentication, a browser is enough to attack an API that the attacker cannot reach directly (a developer machine, an intranet): the victim only has to open a page.
- No `config/cors.php`, so the framework default applied: `allowed_origins => ['*']`, all methods and headers (`vendor/laravel/framework/config/cors.php`). Any origin could send and read every request with `fetch()`.
- Even with a strict CORS policy, an HTML form sends `application/x-www-form-urlencoded`, `multipart/form-data` or `text/plain` without a preflight. Laravel parses such bodies, and it enables the `_method` override, read from the query string as well (`vendor/laravel/framework/src/Illuminate/Foundation/Http/Kernel.php:142`). A form can therefore also update and delete.

**Evidence (before the fix):**
```
$ curl -X POST -H "Origin: https://evil.example" -H "Content-Type: application/x-www-form-urlencoded" \
    --data "manufacturer=CSRF&model=Probe&category=other" http://localhost:8080/api/v1/devices
HTTP/1.1 201 Created
$ curl -X POST -H "Content-Type: text/plain" --data "x=y" "http://localhost:8080/api/v1/devices/5?_method=DELETE"
HTTP/1.1 204 No Content                       # then GET /api/v1/devices/5 → 404
$ curl -X OPTIONS -H "Origin: https://evil.example" -H "Access-Control-Request-Method: DELETE" .../api/v1/devices/1
HTTP/1.1 204 No Content
Access-Control-Allow-Origin: *
```

**Fix.**
- `app/Http/Middleware/RequireJsonBody.php` (api group): POST, PUT and PATCH must send JSON, otherwise 415. It checks the *real* method, so `_method` cannot get around it. A JSON body always needs a CORS preflight.
- `config/cors.php`: no origin is allowed unless listed in `CORS_ALLOWED_ORIGINS`. The documentation's "Try it" runs on the same origin.
- Documented in the OpenAPI description ("Request bodies must be JSON …, otherwise 415").

**After:** both requests above → `415 {"message":"The request body must be JSON (Content-Type: application/json)."}`; the preflight carries no `Access-Control-Allow-Origin`.

**Tests:** `SecurityTest` › cross-site requests (form, multipart, text/plain and no content type on POST/PUT/PATCH, `_method` in the query string and in a form field, JSON with charset and `+json` still accepted, CORS refused and allowed); `ErrorHandlingTest` › 415 in the uniform error format.

### F-02 – The unauthenticated API listens on every network interface · Medium

**Problem.** `docker-compose.yaml:19` published nginx as `"8080:80"`, that is on `0.0.0.0` and `[::]`. Anyone on the same network (office, café Wi-Fi) could read and change the data.

**Evidence:** `docker compose ps` → `0.0.0.0:8080->80/tcp, [::]:8080->80/tcp`.

**Fix:** `"127.0.0.1:${APP_PORT:-8080}:80"`. **After:** `127.0.0.1:8080->80/tcp`. (MySQL was already bound to 127.0.0.1, see A-4.)

### F-03 – Forged `X-Forwarded-For` bypasses the rate limit and falsifies the logged IP · Medium

**Problem.** `bootstrap/app.php:23-28` left `TrustProxies` at its defaults. For a `Host` ending in `.on-forge.com` or `.on-vapor.com`, Laravel then trusts every proxy (`vendor/laravel/framework/src/Illuminate/Http/Middleware/TrustProxies.php:71-78`), so `$request->ip()` returns whatever the client puts in `X-Forwarded-For`. nginx accepted any Host (`docker/nginx/default.conf:3`). The rate limiter and `api_logs.ip` both use `$request->ip()`.

**Evidence (before):**
```
$ for i in $(seq 1 65); do curl -s -o /dev/null -w "%{http_code}\n" -H "Host: x.on-forge.com" \
    -H "X-Forwarded-For: 10.9.0.$i" http://localhost:8080/api/v1/devices; done | sort | uniq -c
     65 200                                   # the same loop without the headers: 60 × 200, then 429
api_logs.ip of the last rows: 10.9.0.65, 10.9.0.64
```

**Fix.** No reverse proxy speaks HTTP to PHP (nginx passes the client address over FastCGI), so `TrustProxies` is removed from the global middleware: forwarded headers are never trusted. A deployment behind a load balancer must add it back with the balancer's addresses (see the recommendations). F-09 also refuses such a Host at nginx (now `421`).

**Note:** `trustProxies(at: [])` would *not* have fixed it. An empty list is falsy, and the middleware falls back to the automatic trust.

**Tests:** `SecurityTest` › client address (the logged IP stays `127.0.0.1` for `localhost`, `*.on-forge.com` and `*.on-vapor.com`; 60 requests with 60 different forged addresses, then 429).

### F-04 – One invalid UTF-8 byte erases a request from the audit log · Medium

**Problem.** `LogApiTraffic` stored header values and bodies as they came (`app/Http/Middleware/LogApiTraffic.php:67`, `:73-84`). One invalid byte made the `array` cast's `json_encode` fail, or MySQL reject the row (error 1366, strict mode). The `catch` (`:51`) swallows the error, as it should, so that logging never breaks the response. The result: the request was executed, but nothing recorded it. For an API without authentication, this log is the only record of who changed what.

**Evidence (before):**
```
$ curl -X DELETE -H $'X-Note: \xff' http://localhost:8080/api/v1/devices/6     → 204
$ printf '{"manufacturer":"\xff"}' | curl -X POST -H "Content-Type: application/json" --data-binary @- .../devices → 422
api_logs: neither request appears; the newest row is the POST that created device 6.
```

**Fix:** URL, header values and bodies are cleaned with `mb_scrub()` (invalid sequences become `?`) before saving. **After:** the same DELETE is logged with `"x-note": "?"`.

**Tests:** `ApiLoggingTest` › logs a request / a body with invalid UTF-8 instead of losing it.

### F-05 – No brute-force protection on the documentation's Basic Auth · Medium

**Problem.** `config/scramble.php:171-174` protected `/docs/api` and `/docs/api.json` with `DocsBasicAuth` only. The comparison is correct (`hash_equals`, fail closed, `WWW-Authenticate`), but unlimited guesses were possible.

**Evidence (before):** 12 wrong attempts in a row → `401` × 12.

**Fix:** named limiter `docs` (`AppServiceProvider`) in front of `DocsBasicAuth`. Only failed logins (401) count, 10 per minute per client IP, so reading the documentation is never limited. While locked out, the correct password gets 429 as well, so the lockout does not confirm a guess.

**After:** `401` × 10, then `429` with `Retry-After`.

**Tests:** `ApiDocsTest` › lockout after 10 failed logins (with the right password during the lockout and after it expires), successful logins do not count, per-IP counting.

### F-06 – Unauthenticated Laravel Boost endpoint writes any text into a log read by the coding agent · Medium

**Problem.** `laravel/boost` (dev dependency) registers `POST /_boost/browser-logs` whenever `APP_ENV=local` or `APP_DEBUG=true`, which is the default local setup. It has no authentication, is exempt from CSRF, accepts `text/plain` (`sendBeacon`), and writes any text into `storage/logs/browser.log` (`vendor/laravel/boost/src/BoostServiceProvider.php:73-74`, `:105-148`). The Boost MCP server (`.mcp.json`) gives that file to the coding agent as context. Any client (on the network before F-02, any web page via a form) could fill the disk and **inject instructions into the AI agent working on the project**. The project has no frontend, so the feature had no use.

**Evidence (before):** `curl -X POST -H "Content-Type: text/plain" --data '{"logs":[{"type":"info","data":["F5 security probe"],...}]}' .../_boost/browser-logs` → `200 {"status":"logged"}`, and the line appears in `browser.log`.

**Fix:** `config/boost.php` sets `browser_logs_watcher` to `false` (override: `BOOST_BROWSER_LOGS_WATCHER`). **After:** `404`; `route:list` no longer has the route.

**Test:** `SecurityTest` › inventory (Boost does not boot in tests, so the test pins the configuration; the route list test covers every other route).

### F-07 – Unknown `/api` URLs are not rate limited, yet each one writes a log row · Medium

**Problem.** The throttle sat in the `api` route group (`bootstrap/app.php:24`), but logging is global (`:27`). Requests that match no route (404, 405) never run group middleware. So an unauthenticated client could write unlimited rows, each with a body of up to 1 MB (nginx limit), cut to 65 535 *characters*, which is up to ~256 KB of UTF-8.

**Evidence (before):** 70 requests to `/api/v1/nope` → `404` × 70; a 900 KB body to it → a row with a 65 550-byte `request_body`.

**Fix:** the `api` limiter now runs as global middleware (`ThrottleRequests::using('api')`, before routing) and returns `Limit::none()` outside `/api`. Every `/api` request, matched or not, shares the 60 per minute per IP budget. **After:** 60 × `404`, then `429`.

**Tests:** `ErrorHandlingTest` › unknown API routes count against the rate limit too; the API limit does not apply outside the API.

### F-08 – The API log grows without limit · Low

**Problem.** Nothing ever deleted `api_logs` rows (`app/Models/ApiLog.php:23`, `routes/console.php`), and the body limit was counted in characters (`config/api_logging.php:31`). With F-07 fixed, growth is bounded per minute, but not over time.

**Fix.**
- `ApiLog` is `MassPrunable`: rows older than `API_LOG_RETENTION_DAYS` (default 30) are deleted.
- `routes/console.php` schedules `model:prune` daily.
- A new `scheduler` service in `docker-compose.yaml` runs `schedule:work`.
- The body limit is counted in bytes (`max_body_bytes`, `mb_strcut()` never splits a character).

Worst case is now bounded by rate × row size × retention.

**Tests:** `ApiLoggingTest` › deletes rows older than the retention period, prunes the log every day, truncates by bytes without splitting a character.

### F-09 – Any `Host` header is accepted (DNS rebinding) · Low

**Problem.** `server_name _;` (`docker/nginx/default.conf:3`) answered every Host. With DNS rebinding, a page on `attacker.example` re-resolves its own name to `127.0.0.1`. The browser then treats the API as same-origin, which defeats F-01's CORS protection (the reason Rails, for example, checks hosts in development). The Host header also ends up in the absolute pagination links and in the log.

**Fix:** a default server returns `421` for every Host other than `localhost`, `127.0.0.1` and `[::1]`. **After:** `curl -H "Host: attacker.example" .../api/v1/devices` → `421`.

### F-10 – User input is serialized into the `unique` validation rule · Low

**Problem.** `StoreDeviceRequest::uniqueDeviceType()` (`app/Http/Requests/StoreDeviceRequest.php:44-46`) passed the counterpart value with `->where($column, $value)`. Laravel turns that into the rule string `unique:devices,model,NULL,id,manufacturer,"…"` and only escapes `"`, while the parser also treats `\` as an escape character. A manufacturer such as `a\",nope,x` adds a condition to the rule. This is not SQL injection (columns are quoted and values are bound), but it is a 500. With `APP_DEBUG=true` that 500 shows the SQL and the database host. A partial update falls back to the stored manufacturer, so the same applied to updating such a record.

**Evidence (before):**
```
$ curl -X POST -H "Content-Type: application/json" --data '{"manufacturer":"a\\\",nope,x","model":"m","category":"laptop"}' .../devices
500 "SQLSTATE[42S22]: Column not found: 1054 Unknown column 'nope' in 'where clause' (Connection: mysql, Host: mysql, Port: 3306, Database: device_registry, SQL: ...)"
'a\",nope' → 500 "Undefined array key 3"
```

**Fix:** the value goes in through a query callback (`->where(fn (Builder $query) => $query->where(...))`), which Laravel never serializes.

**Tests:** `SecurityTest` › injection (create, duplicate check and update with `\"` payloads; an array instead of a string).

### F-11 – A huge `page` number causes a 500 · Low

**Problem.** `per_page` was clamped (`app/Http/Controllers/Controller.php:21`), but `page` went to `paginate()` as it came. With `page=922337203685477580&per_page=100` the offset overflows an integer, and PHP 8.5 raises "The float … is not representable as an int" → 500 (`ContractService.php:17`, `DeviceService.php:16`). Any client could produce error log entries at will.

**Fix:** `Controller::page()` clamps the page to 1 … `intdiv(PHP_INT_MAX, $perPage)`, and the services receive it explicitly (`list(int $perPage, int $page)`). Pages past the last one are simply empty.

**Test:** `SecurityTest` › resource consumption (both list endpoints, a value that overflows and one beyond `PHP_INT_MAX`).

### F-12 – `X-Powered-By` reveals the exact PHP version · Low

**Evidence (before):** `curl -I .../api/v1/devices` → `X-Powered-By: PHP/8.5.12`. The image had no ini file, so `expose_php` stayed `On`.

**Fix:** `docker/php/conf.d/security.ini` (`expose_php = Off`), copied into the image. **After:** no `X-Powered-By` header. `Server: nginx` carries no version (`server_tokens off` was already set), and `X-Content-Type-Options: nosniff` is present.

### F-13 – MySQL passwords hardcoded in `docker-compose.yaml` · Low

**Problem.** `MYSQL_ROOT_PASSWORD: root` and `MYSQL_PASSWORD: secret` (`docker-compose.yaml:33-34`, root password repeated in the health check at `:39`). The port is bound to 127.0.0.1, which lowers the risk, but every clone had the same root password.

**Fix.**
- `docker-compose.yaml` reads `MYSQL_ROOT_PASSWORD` and `DB_PASSWORD` from the root `.env` (git-ignored) and refuses to start without them.
- `make up` creates that file once with random passwords.
- `.env.example` documents the variables.
- The app container receives `DB_PASSWORD` from Compose, so `api/.env.example` no longer contains a password.
- The health check needs no password: `mysqladmin ping` succeeds even on "access denied".

### F-14 – Floating image tags and GitHub Actions versions · Low

**Problem.** `nginx:stable-alpine` (`docker-compose.yaml:17`) jumps to a new nginx line without notice. `composer:2` (`docker/php/Dockerfile:12`) floats too. The actions (`ci.yml:47, 50, 68`) were referenced by movable tags, so a hijacked tag (as with tj-actions in 2025) would run in CI.

**Fix:**
- `nginx:1.30-alpine` and `composer:2.11`, pinned to the minor version, so security patches still arrive.
- `php:8.5-fpm-alpine` and `mysql:8.4` were already pinned the same way.
- The three actions are pinned to full commit SHAs, with the tag in a comment.

### F-15 – Look-alike serial numbers get around the global uniqueness · Low

**Problem.** `serial_number` accepted any text up to 100 characters (`app/Http/Requests/AssignDeviceRequest.php:21`). MySQL (`utf8mb4_unicode_ci`) treats `SN-ABC-1` and `SN-АВС-1` (Cyrillic) as different text. So the same physical unit could be registered on two contracts, which breaks the "one unit, one contract" rule.

**Fix:** allowlist `^[A-Za-z0-9][A-Za-z0-9._/-]*\z` with a clear message. It is visible in the OpenAPI document as `pattern`. Leading and trailing whitespace (including U+200B) is already trimmed by `TrimStrings`.

**Tests:** `ContractDeviceTest` › accepts common formats, rejects inner space, leading hyphen, line break, zero-width space, accented letter, SQL and markup, does not take a look-alike of an existing serial number.

### F-16 – Foreign key races end in a 500 instead of a 409 · Low

**Problem.**
- Deleting a device checks `assignments()->exists()` and then deletes (`DeviceService.php:33-37`). An assignment created in between makes MySQL refuse the delete (1451).
- Assigning validates `exists:devices,id` and then inserts. A device deleted in between makes the insert fail (1452).

Integrity is safe (FK `RESTRICT`), but the client got a 500, and the error was logged as a crash. The serial number race was already handled (409).

**Fix:** `bootstrap/app.php` maps `QueryException` 1451/1452 to `ConflictException::concurrentChange()` (409). Other database errors stay a 500 without details.

**Tests:** `ErrorHandlingTest` › both races simulated with model events, plus "keeps other database errors a 500 without details".

### F-17 – Several credential headers are logged in clear text · Low

**Problem.** Only `authorization`, `cookie` and `php-auth-pw` were masked (`config/api_logging.php:22-26`). `Proxy-Authorization`, `X-Api-Key`, `X-CSRF-Token`, `X-XSRF-Token` were stored as sent. So were `php-auth-user` and `php-auth-digest`, which PHP derives from an `Authorization: Basic/Digest` header.

**Fix:** all of these are masked.

**Tests:** `ApiLoggingTest` › masks sensitive headers (6 headers); does not store the credentials PHP derives from a Basic or Digest header (raw database value checked).

### F-18 – Unused file download/upload routes · Info

`config/filesystems.php:38` (`'serve' => true`) registered `GET` and `PUT storage/{path}`. They require a signed URL (`403` without one), and the application never creates one. Unused surface, so it is switched off (`'serve' => false`). **Test:** `SecurityTest` › inventory: the route list must be exactly the API, the documentation, `/up` and `/`. The Scramble dev-tools asset, which exists only with `APP_DEBUG=true`, is never served without the documentation credentials.

## OWASP API Security Top 10 (2023) and the phase checklist

| # | Area | Result | Evidence |
|---|---|---|---|
| API1 | Object level authorization | No users, so there is no object owner and BOLA does not apply. Objects are only reached through their parent where it matters: an assignment is resolved through its contract (scoped binding), so another contract's assignment answers 404 and stays. Identifiers must be plain integers (`1abc` does not resolve to 1). | `ContractDeviceTest` › returns 404 for an assignment of another contract; `RouteParametersTest` |
| API2 | Authentication | The API is open by design (A-1). Docs: `hash_equals` on both values, fail closed when unconfigured, `WWW-Authenticate: Basic`, now throttled (F-05). F-01 and F-09 keep the open API from being driven by other web pages. | `ApiDocsTest` |
| API3 | Object property level authorization | `$fillable` lists exactly the API fields; services only receive `validated()`; `id`, `created_at`, `contract_id` in a body are ignored; resources whitelist the output fields. | `DeviceTest`/`ContractTest` › ignores fields that are not part of the contract; `ContractDeviceTest` › takes the contract from the URL, not from the body |
| API4 | Unrestricted resource consumption | 60 requests per minute per IP on all of `/api` (F-03, F-07), `per_page` ≤ 100, `page` clamped (F-11), nginx `client_max_body_size 1m` → 413, log bodies cut at 64 KB and kept 30 days (F-08). | `ErrorHandlingTest`, `SecurityTest`; `curl` 2 MB body → `413` |
| API5 | Function level authorization | No admin or internal endpoint; the full route list is pinned by a test; docs and Scramble dev tools are behind Basic Auth; `/up` shows only "Application up". Boost's endpoint removed (F-06), file routes removed (F-18). | `SecurityTest` › inventory |
| API6 | Sensitive business flows | Serial number race → DB unique + 409 (existing); foreign key races → 409 (F-16); look-alike serial numbers (F-15). | `ErrorHandlingTest`, `ContractDeviceTest` |
| API7 | SSRF | Not applicable: the application makes no outgoing HTTP request and takes no URL as input. | code review |
| API8 | Security misconfiguration | 500 without trace or message when `APP_DEBUG=false`; 404 without model class or id; no `X-Powered-By` (F-12); no nginx version; `nosniff`; CORS closed (F-01); loopback only (F-02); Host allowlist (F-09); proxies not trusted (F-03). `APP_DEBUG=true` is a local default (A-3). | `ErrorHandlingTest`; curl |
| API9 | Improper inventory management | Only `v1` exists; `api/docs/openapi.json` is regenerated and a test fails if it drifts from the code; unused routes removed (F-06, F-18). | `ApiDocsTest` › keeps the exported specification in sync; `SecurityTest` › inventory |
| API10 | Unsafe consumption of APIs | Not applicable: no third-party API is called. | code review |
| – | Injection | Every query goes through Eloquent or the query builder with bindings; there is no raw SQL, `orderBy`/filter never takes user input; route parameters are integers; SQL payloads in text fields are stored and returned as plain text; query parameters never reach SQL; the serialized rule string is gone (F-10); serial number allowlist (F-15). | `SecurityTest` › injection, `RouteParametersTest` |
| – | Logging | No `Authorization`, cookie, password or token in `api_logs` (F-17); nothing can write or read the log from outside (no endpoint); invalid UTF-8 cannot suppress a row (F-04); bounded growth (F-07, F-08); documentation traffic is not logged. | `ApiLoggingTest` |
| – | Docker | `app` and `scheduler` run as `uid=1000(app)`, not root; PHP-FPM port 9000 is not published; nginx and MySQL bound to 127.0.0.1 (F-02, A-4); no password in the compose file (F-13); images pinned (F-14). | `docker compose exec app id`, `docker compose ps` |

## Intentional decisions and accepted risks

| ID | Decision | Reason |
|---|---|---|
| A-1 | **No authentication on the API.** | The task requires Basic Auth for the documentation only, and the WD contact confirmed that everything beyond the written requirements is our call. The risk is contained instead: reachable from this machine only (F-02), not drivable by other web pages (F-01, F-09), rate limited, fully logged. For real use see the recommendations. |
| A-2 | BOLA tests are limited to the scoped binding. | There are no users, so there is nothing to own; the only parent–child boundary (contract → assignment) is enforced and tested. |
| A-3 | `api/.env.example` keeps `APP_ENV=local` and `APP_DEBUG=true`. | It is the template for local development, where stack traces help. With debug on, error responses contain traces; tests pin the production behaviour (`APP_DEBUG=false` → no trace, no file, no internal message). Production must set `APP_ENV=production` and `APP_DEBUG=false`. |
| A-4 | MySQL stays published, on `127.0.0.1:3307` only. | The phase plan asked for an unpublished database. A loopback binding is not reachable from other hosts and lets developers use a local database client; it now needs the random password from `.env`. Remove the `ports` entry if no client is needed. |
| A-5 | CI uses fixed database passwords (`root` / `secret`). | The MySQL service of a GitHub Actions job is throwaway, reachable only from that runner, and holds test data. The workflow has `permissions: contents: read`, runs on `pull_request` (not `pull_request_target`) and uses no secrets. |
| A-6 | The documentation UI loads Stoplight Elements 8.4.2 from unpkg without SRI; `/up` loads Tailwind from jsdelivr. | Both views ship with Scramble and Laravel. The Elements version is pinned and the page is behind Basic Auth. Self-hosting or adding SRI means overriding vendor views. |
| A-7 | Session cookies of the documentation have no `Secure` flag. | The local stack is plain HTTP. Production: HTTPS and `SESSION_SECURE_COOKIE=true`. The API itself sets no cookies. |
| A-8 | The rate limit is per IP. | Clients behind one NAT share a budget, and a distributed attack has many IPs. With no client identity, the IP is the only key; real limits belong at the edge (WAF) and per API client once there is authentication. |
| A-9 | The log stores client IPs and full bodies. | That is what the task asks for (log requests and responses). IPs are personal data (GDPR): rows are kept 30 days by default (F-08), and anonymization is recommended for production. |
| A-10 | Images are pinned to a minor version, not to a digest. | A minor tag still receives security patches without a manual bump; a digest needs Renovate/Dependabot to stay current. The CI actions, which run with repository access, are pinned to SHAs. |
| A-11 | Pagination links are absolute and use the request's Host. | Only the sender sees them and there is no shared cache; in the Docker setup only the three local host names get through (F-09). Production: `trustHosts()` and a correct `APP_URL`. |
| A-12 | `contract_number` has no character allowlist. | Contract numbers are issued by the business, and their format is not specified; uniqueness is enforced by validation and a unique index. Once the numbering scheme is fixed, the same kind of pattern as F-15 should be added. |
| A-13 | nginx answers an oversized body (> 1 MB) with its own HTML `413`. | The request is rejected before PHP runs, which is the point; the status code is correct for every client. |

## Recommendations for production use

1. **Authentication and authorization:** bearer tokens per API client (Laravel Sanctum, or OAuth 2 client credentials with Passport for machine clients). They travel in a header, not in a cookie, so cross-site requests cannot use them. They also make per-client rate limits, an actor in the audit log, and read-only or admin scopes possible.
2. **HTTPS everywhere:** TLS at the edge, HSTS, `SESSION_SECURE_COOKIE=true`.
3. **Proxies and hosts:** behind a load balancer, `trustProxies(at: [...])` with its exact addresses (never `*`), `trustHosts()` with the public host name, a correct `APP_URL`, and an nginx `server_name` instead of the local names.
4. **Configuration:** `APP_ENV=production`, `APP_DEBUG=false`, `composer install --no-dev` (no Boost, Pail or Pao on the server), secrets from a secret manager instead of `.env`, cached config.
5. **Edge protection:** a WAF or API gateway with global and per-client rate limits, request size limits and bot protection; the in-app limiter remains the inner layer.
6. **Logging:** ship request logs to a log platform or SIEM instead of the application database; pseudonymize IPs (truncate or keyed hash); keep the retention short and documented; alert on spikes of 4xx/429/5xx.
7. **Supply chain:** Renovate or Dependabot for Composer, Docker digests and Actions; `composer audit` already runs in CI.
8. **Database:** a separate migration user; give the application user only `SELECT/INSERT/UPDATE/DELETE`; backups and their restore tested.
9. **Documentation page:** self-host the Elements assets or add SRI, and send `Content-Security-Policy` and `X-Frame-Options: DENY` for it.

## Manual verification

The manual security checklist ("Kézi biztonsági ellenőrzés") is in [docs/roadmap/05-biztonsag.md](roadmap/05-biztonsag.md) and is carried out by the author; its results are not part of this report.
