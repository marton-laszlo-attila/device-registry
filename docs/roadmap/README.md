# Device Registry – megvalósítási roadmap

Ez a fájl az áttekintés. A teendők a fázisfájlokban vannak, mindegyiket **külön Claude Code munkamenetben** futtasd, hogy a kontextus kicsi és fókuszált maradjon.

| # | Fázis | Fájl | Modell | Effort | Ki |
|---|---|---|---|---|---|
| F1 | Infrastruktúra, projektváz, minőségi eszközök | [01-infrastruktura.md](01-infrastruktura.md) | Sonnet 5.5 | medium | Claude |
| F2 | Domain, CRUD, hozzárendelés + tesztek | [02-domain-crud.md](02-domain-crud.md) | Opus 5.5 | high | Claude |
| F3 | Hibakezelés, API naplózás, OpenAPI + Basic Auth + tesztek | [03-hibakezeles-naplozas-openapi.md](03-hibakezeles-naplozas-openapi.md) | Opus 5.5 | high | Claude |
| F4 | Teszt-keményítés, statikus analízis, CI | [04-tesztek-minoseg.md](04-tesztek-minoseg.md) | Sonnet 5.5 | high | Claude |
| F5 | Biztonsági teszt | [05-biztonsag.md](05-biztonsag.md) | Opus 5.5 | xhigh | Claude + te |
| F6 | README, végső review, átadás előkészítése | [06-dokumentacio-atadas.md](06-dokumentacio-atadas.md) | Sonnet 5.5 | medium | Claude |
| F7 | Manuális tesztforgatókönyv | [07-manualis-teszt.md](07-manualis-teszt.md) | – | – | **te** |

Indítás: minden fázisfájl „Indítás" szakaszában van egy kész parancs, ezt új terminálban, bárhonnan futtathatod (a projekt gyökerébe lép, és a megfelelő `--model` és `--effort` kapcsolóval nyit új munkamenetet). Menet közbeni váltáshoz a `/model` parancs is használható. Ökölszabály: tervezés, domain és biztonság → Opus; gépies, jól specifikált munka → Sonnet; formázás vagy apró javítás → Haiku 4.5 (low).

---

> **Könyvtárszerkezet:** a Laravel alkalmazás az `api/` könyvtárban van, a repó gyökerében a `docs/`, `docker/`, `docker-compose.yaml` és a `Makefile` található. A fázisfájlokban szereplő `app/`, `routes/`, `tests/`, `composer.json` stb. útvonalak az `api/`-hoz képest értendők; a `make` célok a gyökérből futnak.

## 1. Alapelv: egyszerű, de bővíthető

A feladat egy kis CRUD API. **Csak annyi absztrakció legyen, amennyit a mostani követelmények indokolnak**, a Laravel beépített megoldásaira építve (Form Request, API Resource, route model binding, exception handler). A bővíthetőséget az adja, hogy minden felelősségnek egyetlen, egyértelmű helye van (lásd 5. pont), nem pedig előre legyártott rétegek.

Amit **szándékosan nem** csinálunk: repository réteg, DTO-k, CQRS/action osztályok, event/listener, saját hibaformátum-szabvány.

Amit **szándékosan igen**: a service-ek interface-t kapnak, és a controllerek az interface-től függnek (Dependency Inversion). Kevés plusz kód, de így a SOLID elv látható, és egy service implementációja a controllerek módosítása nélkül cserélhető.

## 2. Rögzített döntések

| Téma | Döntés | Indoklás |
|---|---|---|
| Keretrendszer | Laravel 13, PHP 8.5 (csak Dockerben; a lokális PHP 8.3 nem használható) | task |
| Adatbázis | MySQL 8.4 LTS, külön `device_registry_test` adatbázis a tesztekhez | task; a tesztek ugyanazon a motoron futnak (FK, unique) |
| OpenAPI | Scramble (`dedoc/scramble`), saját Basic Auth middleware mögött | a kódból generálja a specet, nincs annotáció |
| Tesztek | Pest (+ `pest-plugin-laravel`) | – |
| Telepítés | nincs; a dokumentáció a lokális `/docs/api` útvonalon érhető el | a WD megerősítette: csak a repó kell |
| Nyelv | kód, commitok, README, API doc angolul; roadmap és tesztforgatókönyv magyarul | – |
| API azonosítás | nincs (a task csak a dokumentációhoz kér Basic Authot) | task |
| Hibaformátum | `{ "message": ..., "errors": {...} }`, a Laravel saját formátuma, minden hibára egységesítve | nem kell új formátumot kitalálni |
| API verzió | `/api/v1/...` | olcsó, és később nem kell törni a klienseket |
| Auth csomag | **nincs Sanctum**: `php artisan install:api` helyett kézi `routes/api.php` | nem kell |

## 3. Adatmodell

```
contracts                      contract_device (pivot, saját id)          devices
─────────                      ─────────────────────────────────          ───────
id                        ┌──< id                                    >──┐ id
contract_number  UNIQUE   │    contract_id  FK → contracts  RESTRICT    │ manufacturer
partner_name              │    device_id    FK → devices    RESTRICT    │ model
start_date                │    serial_number UNIQUE (globális)          │ category (enum)
end_date  NULL            │    created_at / updated_at                  │ description NULL
status (enum)             │                                             │ created_at / updated_at
notes NULL                │                                             │ UNIQUE(manufacturer, model)
created_at / updated_at ──┘                                             └──

api_logs: id, method, url, status_code, request_headers (json), request_body (longtext),
          response_body (longtext), ip, duration_ms, created_at   INDEX(created_at)
```

- A `devices` egy **eszköztípust** jelent. A fizikai példányt a `contract_device` sor és rajta a `serial_number` azonosítja.
- Egy típus több szerződésen is szerepelhet, egy szerződésen akár többször, de minden példány más gyári számmal (N:N, a task szerint).
- `serial_number` globálisan egyedi: egy fizikai eszköz egyszerre csak egy szerződésen lehet.
- Törlés: ha a szerződésen van eszköz, vagy az eszköztípus szerepel szerződésen → **409 Conflict**. Nincs kaszkád, nincs soft delete.
- Enumok: `ContractStatus` (`draft`, `active`, `expired`, `terminated`), `DeviceCategory` (`laptop`, `desktop`, `monitor`, `printer`, `phone`, `network`, `other`).

## 4. API szerződés

| Metódus | Útvonal | Siker | Megjegyzés |
|---|---|---|---|
| GET | `/api/v1/devices` | 200 | lapozott (`per_page` max. 100) |
| POST | `/api/v1/devices` | 201 | |
| GET | `/api/v1/devices/{device}` | 200 | |
| PUT/PATCH | `/api/v1/devices/{device}` | 200 | részleges frissítés (`sometimes`) |
| DELETE | `/api/v1/devices/{device}` | 204 | 409, ha szerződésen van |
| GET | `/api/v1/contracts` | 200 | lapozott, **eszközök nélkül** |
| POST | `/api/v1/contracts` | 201 | |
| GET | `/api/v1/contracts/{contract}` | 200 | **eszközökkel** (gyári szám, hozzárendelés id) |
| PUT/PATCH | `/api/v1/contracts/{contract}` | 200 | részleges frissítés |
| DELETE | `/api/v1/contracts/{contract}` | 204 | 409, ha van rajta eszköz |
| POST | `/api/v1/contracts/{contract}/devices` | 201 | body: `device_id`, `serial_number` |
| DELETE | `/api/v1/contracts/{contract}/devices/{assignment}` | 204 | scoped binding: idegen hozzárendelés → 404 |
| GET | `/docs/api`, `/docs/api.json` | 200 | Basic Auth, különben 401 |

Hibaválasz (minden hibára azonos szerkezet, az `errors` csak 422-nél):

```json
{
  "message": "The serial number has already been taken.",
  "errors": { "serial_number": ["The serial number has already been taken."] }
}
```

Lefedett státuszok: 404 (nem létező erőforrás vagy útvonal), 405, 409, 422, 429, 500. Az 500-as válasz nem tartalmaz stack trace-t, ha `APP_DEBUG=false`. A 404 üzenete nem árulja el a modell osztálynevét.

## 5. Architektúra

```
Request → LogApiTraffic middleware → FormRequest (validáció)
        → Controller (vékony) → Service (üzleti szabály) → Eloquent Model
        → API Resource (válasz)
Exception → bootstrap/app.php withExceptions() (egy helyen)
```

| Elem | Felelősség | Fájl(ok) |
|---|---|---|
| Form Request | validáció | `app/Http/Requests/*` |
| Controller | HTTP: validált adat → service → Resource / státuszkód | `app/Http/Controllers/Api/V1/*` |
| Service interface | a service-ek szerződése; ettől függ a controller | `app/Services/Interfaces/DeviceServiceInterface`, `ContractServiceInterface` |
| Service | listázás, írás és üzleti szabály (törlési feltétel, hozzárendelés, tranzakció) | `app/Services/DeviceService`, `ContractService` |
| Binding | interface → implementáció | `AppServiceProvider::$bindings` |
| Model | relációk, castok, `$fillable` | `Device`, `Contract`, `ContractDevice` (pivot modell) |
| API Resource | válaszforma; elválasztja a DB sémát az API-tól | `app/Http/Resources/*` |
| Exception | üzleti hiba → HTTP státusz | `ApiException` (abstract, `status()`), `ConflictException` (409, named constructorok) |
| Handler | minden hiba egységes JSON-ná alakítása | `bootstrap/app.php` |
| Naplózás | request/response mentése | `LogApiTraffic` middleware + `ApiLog` model + `config/api_logging.php` |

Szabályok:
- `declare(strict_types=1);` minden PHP fájlban.
- A controllerben nincs üzleti `if` és nincs query, a service-ben nincs `Request`.
- A controller konstruktora mindig az interface-t kéri, soha a konkrét service osztályt (ezt arch teszt ellenőrzi).
- Az interface-ek a `App\Services\Interfaces` névtérben vannak, nem a Laravel szokásos `Contracts` névterében, mert az összekeverhető a `Contract` (szerződés) modellel.
- A service a `$request->validated()` tömböt kapja. Ha később bonyolódik az input, ezen a ponton lehet DTO-t bevezetni.
- Repository nincs: az Eloquent ezt a szerepet már betölti (DRY). A README-ben egy mondattal indokolni kell.

## 6. Bővíthetőség: hová kerül egy jövőbeli változás

| Jövőbeli igény | Hol kell módosítani | Mit nem kell bántani |
|---|---|---|
| Új mező a hozzárendelésen (pl. `assigned_at`, `returned_at`, megjegyzés) | migráció + `ContractDevice` pivot modell + Resource | controllerek |
| Új üzleti szabály (pl. lejárt szerződésre nem lehet eszközt tenni) | a megfelelő service + egy `ConflictException` named constructor | controller, handler |
| Új hibatípus (pl. 403) | új `ApiException` leszármazott | handler (általánosan kezeli) |
| Új enum érték (státusz, kategória) | az enum | validáció (`Rule::enum`), adatbázis (string oszlop) |
| Szűrés vagy rendezés a listában | a service `list()` metódusa vagy query scope | controller, Resource |
| API azonosítás | egy middleware a `v1` route csoporton (pl. Sanctum) | controllerek, service-ek |
| Másik service implementáció (pl. cache-elt listázás, külső rendszerrel szinkronizáló változat) | új osztály, amely implementálja az interface-t, és a binding átállítása | controllerek |
| Új API verzió | `routes/api.php` `v2` csoport + `Controllers\Api\V2` | v1 kliensek |
| Log megőrzés vagy kikapcsolás | `ApiLog` → `MassPrunable`; `config/api_logging.php` | middleware |
| Új entitás (pl. `Partner` tábla a mostani szöveges `partner_name` helyett) | ugyanaz a minta: migráció, model, request, service interface + service, resource, controller | meglévő entitások |

## 7. Standardek és minőségi kapuk

Minden fázis végén **mind zöld kell legyen** (a Makefile-ban `make qa`):

| Kapu | Parancs (konténerben) | Mit ellenőriz |
|---|---|---|
| PSR-4 | `composer dump-autoload --strict-psr` | namespace ↔ útvonal egyezés |
| PSR-12 | `vendor/bin/pint --test` (preset: `psr12`) | formázás |
| PSR-12 független ellenőrzés | `vendor/bin/phpcs --standard=PSR12 app database routes tests` | a Pint mellett egy második eszköz is igazolja |
| Statikus analízis | `vendor/bin/phpstan analyse` (Larastan, level 6) | típusok, hibák |
| Tesztek | `vendor/bin/pest --coverage --min=100` | funkció + lefedettség |
| Függőségek | `composer audit` | ismert sérülékenységek |
| Migrációk | `php artisan migrate:fresh --seed` | tiszta adatbázisból felállítható |

## 8. MCP és eszközök

| MCP / eszköz | Mire | Fázis |
|---|---|---|
| **Laravel Boost MCP** (`laravel/boost`, dev függőség) | Laravel 13-ra verzióhelyes dokumentáció keresése, DB séma, tinker, utolsó hibák. Konfiguráció a `.mcp.json`-ban: `docker compose exec -T app php artisan boost:mcp` | F1-ben telepíteni, F2–F5-ben használni |
| **Claude in Chrome** | a `/docs/api` Scramble UI és a Basic Auth vizuális ellenőrzése, GIF felvétel | F3, F5 |
| **VS Code diagnostics** (`mcp__claude-vscode__getDiagnostics`) | IDE hibák (intelephense) gyors ellenőrzése | minden fázis |
| `gh` CLI (nem MCP) | publikus GitHub repó létrehozása, CI állapot | F1, F6 |
| `/security-review` skill | a változások biztonsági átnézése | F5 |
| `/code-review high` skill | végső helyességi review | F6 |

A metabase MCP nem kell ehhez a projekthez.

## 9. Commit konvenció

Conventional Commits angolul (`feat:`, `test:`, `chore:`, `docs:`, `fix:`, `refactor:`), fázisonként legalább egy commit. Csak zöld minőségi kapukkal commitolj.

## 10. Nyitott pontok és válaszok

- **A WD válasza a kérdésekre** (a technikai kapcsolattartó, senior fejlesztő):
  - Telepíteni **nem kell**, csak a forráskódot tartalmazó repót kell megosztani. A „publikus URL" tehát a projektben elérhető `/docs/api` útvonal Basic Auth mögött. Ez egyezik a 2. pont döntésével, nem kell módosítani semmit.
  - A leírt követelményeken túl **nincs egzakt elvárás**, a többi kérdés (gyári szám egyedisége, törlési szabályok, mezők, API auth, tesztelés) a mi döntésünk. A problémamegoldásra és a kreativitásra kíváncsiak, ezért a README „Design decisions" szakasza (F6) kiemelten fontos: minden döntésnél legyen ott az indoklás.
- ~~A `docs/roadmap/` bekerüljön-e a repóba?~~ **Eldöntve: igen.** A `device-registry` teljes tartalma bekerül a publikus repóba (`docs/task.txt`, `docs/roadmap/`, `CLAUDE.md`, `.mcp.json` is), mert a fejlesztési folyamatot is dokumentálja. Ezt a kísérő levélben is jelezni kell (F7).
- Kivétel: a `docs/task.txt`-ből a publikálás előtt ki kell venni a személyes adatokat (a HR-es kolléga telefonszámát és aláírását, a megszólítást). A feladatleírás maradjon szó szerint (F6).
