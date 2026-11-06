# F3 – Egységes hibakezelés, API forgalom naplózása, OpenAPI + Basic Auth

**Modell / effort:** Opus 5.5 / high · **Előfeltétel:** F2 kész, `make qa` zöld

## Cél

A task „Követelmények" blokkjának 2–4. pontja: egységes hibakezelés, a request és response naplózása adatbázisba, OpenAPI dokumentáció Basic Auth-tal védve. Mindhárom a Laravel beépített megoldásaira épül, minimális saját kóddal.

## Indítás

Új terminálban futtasd. A parancs egy új, interaktív Claude Code munkamenetet nyit a megadott modellel és efforttal, és rögtön elküldi az indító promptot. A fájlszerkesztéseket automatikusan jóváhagyja (`acceptEdits`), a shell parancsokra továbbra is rákérdez.

```bash
cd ~/Documents/allaskereses/wd/device-registry
claude --model claude-opus-5-5 --effort high --permission-mode acceptEdits -n "F3 Hibakezeles naplozas OpenAPI" '
Olvasd el a docs/task.txt, a docs/roadmap/README.md és a docs/roadmap/03-hibakezeles-naplozas-openapi.md
fájlt, és hajtsd végre az F3 fázist. Egyszerű megoldás kell, a Laravel és a Scramble beépített
lehetőségeire építve. Teszt-először haladj. A Scramble aktuális konfigurációját a laravel-boost
MCP-vel vagy a csomag forrásában ellenőrizd, ne emlékezetből dolgozz.
Commit előtt a `make qa` legyen zöld.'
```

## Feladatok

### 1. Egységes hibakezelés
Minden a `bootstrap/app.php` → `withExceptions()` részben, egy helyen:
- [x] `shouldRenderJsonWhen(fn ($request) => $request->is('api/*'))`, vagyis `Accept` fejléc nélkül is JSON a válasz
- [x] `ApiException` → `{"message": ...}` a saját `status()` kóddal (409). Az F2-ben már van erre egy minimális `render()`, ezt kell beépíteni a teljes handlerbe.
- [x] `UniqueConstraintViolationException` → 409 `{"message": ...}`, nem 500 (F2-ből maradt). Egyidejű kérésnél ugyanaz a gyári szám két kérésben is átmehet a validáción, és ilyenkor a második az adatbázis unique megkötésén bukik el. Teszt: a validációt megkerülve, közvetlenül az adatbázisban létrehozott ütközés.
- [x] `NotFoundHttpException` (ide tartozik a `ModelNotFoundException` is) → 404 `{"message": "Resource not found."}`, modellnév és id nélkül
- [x] A többi esetet (422 validáció, 405, 429, 500) a Laravel már `{message[, errors]}` formában adja. Ezekhez **nem kell saját kód**, csak teszt, amely igazolja a formát.
- [x] Rate limit: `$middleware->throttleApi()` (alapból 60 kérés/perc)
- [x] Tesztek: `tests/Feature/ErrorHandlingTest.php`, egy dataset státuszonként (404 erőforrás, 404 ismeretlen útvonal, 405, 409, 422, 500). Mindegyiknél: JSON, van `message`, 422-nél `errors`, `APP_DEBUG=false` mellett nincs `exception`/`trace`/`file` kulcs, és a 404 nem tartalmazza az `App\Models` szöveget.

### 2. API forgalom naplózása adatbázisba
- [x] Migráció és modell: `api_logs` (README 3. pont), `ApiLog` modell, csak `created_at`
- [x] `config/api_logging.php`: `enabled` (env), `masked_headers` (`authorization`, `cookie`, `php-auth-pw`), `max_body_length` (pl. 65535)
- [x] `App\Http\Middleware\LogApiTraffic`, terminable middleware: a `handle()` csak továbbenged, a `terminate()` írja az `ApiLog`-ot (a futási időt a `LARAVEL_START` konstansból vagy egy request attribútumból számolja)
  - a maszkolt fejlécek értéke `***`; a túl hosszú body csonkolva
  - a naplózás hibája nem érintheti a választ: `try/catch` → `report($e)`
- [x] Regisztráció: `bootstrap/app.php` → `$middleware->api(append: [LogApiTraffic::class])`. Így csak az `/api/*` forgalom naplózódik, a docs nem.
- [x] Tesztek: sikeres kérés → egy sor a helyes mezőkkel; a 404-es és a 422-es kérés is naplózva; az `Authorization` fejléc maszkolva; `enabled=false` → nincs sor

### 3. OpenAPI dokumentáció Basic Auth mögött
- [x] `dedoc/scramble` telepítése, `config/scramble.php` publikálása
  - `info`: cím, verzió, rövid leírás (eszköztípus vs. gyári szám)
  - **a `middleware` listában a `RestrictedDocsAccess` helyett** `['web', DocsBasicAuth::class]`, mert az alap middleware csak local környezetben engedi a dokumentációt
- [x] `config/docs.php`: `username`, `password` az env-ből (`DOCS_USERNAME`, `DOCS_PASSWORD`)
- [x] `App\Http\Middleware\DocsBasicAuth` (kb. 20 sor):
  - `hash_equals` a felhasználónévre és a jelszóra
  - ha nincs beállítva felhasználónév vagy jelszó → mindig 401 (fail closed)
  - 401 + `WWW-Authenticate: Basic realm="API Docs"`
- [x] Mind a `/docs/api` (UI), mind a `/docs/api.json` (spec) védett
- [x] Rövid PHPDoc összefoglaló a controller metódusokon, hogy a dokumentáció olvasható legyen. A Scramble a Form Requestekből és a Resource-okból a többit maga generálja.
- [x] Spec export: `php artisan scramble:export --path=docs/openapi.json` (commitolva)
- [x] Tesztek: hitelesítés nélkül 401 + `WWW-Authenticate`; rossz jelszó 401; jó adatokkal 200; az `api.json` minden task-végpontot tartalmaz; üres env → 401 helyes adatokkal is

### Böngészős ellenőrzés (opcionális, MCP)
- [ ] Claude in Chrome: `http://user:pass@localhost:8080/docs/api` megnyitása, és egy képernyőkép arról, hogy minden végpont látszik. (A böngésző natív Basic Auth ablaka blokkolhatja a bővítményt, ezért kell a felhasználónév és a jelszó az URL-ben.)

## Elfogadási feltételek

- [x] Minden hiba `{message[, errors]}` JSON, megfelelő státuszkóddal
- [x] Minden `/api/*` kérés (a hibásak is) egy sort ír az `api_logs` táblába, maszkolt Authorization fejléccel
- [x] A `/docs/api` Basic Auth nélkül 401, vele a Scramble UI az összes végponttal
- [x] `make qa` zöld

## Commit(ok)

- `feat: render all API errors as consistent JSON`
- `feat: log API requests and responses to the database`
- `feat: publish OpenAPI documentation behind basic auth`

## Megvalósítás (2026-11-06)

**Állapot:** kész. `make qa` zöld: 125 teszt, 100% lefedettség. A futó stacken (nginx + FPM) curl-lel is ellenőrizve: a hibák JSON-ok, a kérések naplózódnak maszkolt `Authorization` fejléccel, a `/docs/api` beállított jelszó nélkül 401.

### Eltérések a tervtől

- **`LogApiTraffic` globális middleware** (`$middleware->append()`), nem az `api` csoport része. Az ismeretlen útvonal (404) és a rossz metódus (405) egyik route-hoz sem illeszkedik, így a csoport middleware-jei le sem futnak, és ezek a kérések kimaradnának a naplóból. A middleware a `terminate()`-ben az `api/*` útvonalra szűr, így a docs és a `/up` továbbra sem kerül a naplóba.
- **Futási idő:** a `REQUEST_TIME_FLOAT` szerverváltozóból számolva (a PHP-FPM a kérés elején állítja be, a teszt-kliens is kitölti). Így a `handle()` csak továbbenged, és nem kell request attribútum.
- **Rate limit:** a `throttleApi()` egy `api` nevű limitert vár, amelyet a Laravel 13 nem definiál (`php artisan install:api` nélkül `MissingRateLimiterException` lenne). Az `AppServiceProvider::boot()` definiálja: 60 kérés/perc kliens IP-nként.
- **`UniqueConstraintViolationException` → 409:** `$exceptions->map()` alakítja `ConflictException::duplicateRecord()`-dá, így ugyanaz az `ApiException` renderelő kezeli (DRY). Teszt: egy `creating` model eseményben beszúrt ütköző sor szimulálja a versenyhelyzetet, a valódi végponton keresztül.
- **`dontReport(ApiException::class)`:** a 409-es üzleti hibák várt kimenetek, nem kerülnek a `laravel.log`-ba.
- **404 csak az `api/*` alatt JSON** a saját üzenettel; a webes 404 marad HTML (teszt is van rá).
- **`DocsBasicAuth`:** `UnauthorizedHttpException`-t dob, így a 401-es választ (és a `WWW-Authenticate` fejlécet) a Laravel handler rendereli. A két `hash_equals` mindig lefut, az időzítés nem árulja el, melyik adat volt rossz.
- **Spec export helye:** `api/docs/openapi.json` (a `config/scramble.php` `export_path`-ja, így egy sima `php artisan scramble:export` frissíti). A konténer csak az `api/` könyvtárat látja. Az `ApiDocsTest` ellenőrzi, hogy az exportált fájl `paths` és `components` része megegyezik az élő specifikációval, vagyis kódváltozás után újra kell exportálni.
- **PUT/PATCH a specben:** a Scramble az `apiResource` `PUT|PATCH` route-ját egy `put` műveletként dokumentálja; a PHPDoc leírás jelzi, hogy a PATCH is működik.
- **Service visszatérési típus:** `Illuminate\Pagination\LengthAwarePaginator` (konkrét osztály) a `Contracts\Pagination` interface helyett. A Scramble csak a konkrét osztályból generálja a `links`/`meta` lapozási sémát, és a `paginate()` is ezt adja vissza.
- **Scramble attribútumok a controllereken:** `#[Group]` (Devices, Contracts, Contract devices), `#[QueryParameter]` a `per_page`/`page` paraméterhez (a közös `Controller::perPage()`-ben olvasott értéket a Scramble nem látja), `#[Response(409)]` a törléseknél és a hozzárendelésnél.

### Az F4–F5-re maradt

- A böngészős (Claude in Chrome) ellenőrzés nem készült el: a lokális `api/.env`-ben nincs `DOCS_USERNAME`/`DOCS_PASSWORD`. A sikeres belépést a konténerben, env változókkal indított `php -S` szerveren curl-lel ellenőriztem (UI és JSON: 200, rossz jelszó: 401).
- A 429-es válasz nincs a specben (globális viselkedés, a leírás említi).
- Az `api_logs` tábla nem törlődik automatikusan (`MassPrunable`, README 6. pont), és a request body-ban nincs mezőszintű maszkolás (jelenleg nincs érzékeny mező).

### Commitok

- `24f1b17 feat: render all API errors as consistent JSON`
- `214af21 feat: log API requests and responses to the database`
- `3f81bfd feat: publish OpenAPI documentation behind basic auth`
