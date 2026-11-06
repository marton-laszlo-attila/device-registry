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
- [ ] `shouldRenderJsonWhen(fn ($request) => $request->is('api/*'))`, vagyis `Accept` fejléc nélkül is JSON a válasz
- [ ] `ApiException` → `{"message": ...}` a saját `status()` kóddal (409). Az F2-ben már van erre egy minimális `render()`, ezt kell beépíteni a teljes handlerbe.
- [ ] `UniqueConstraintViolationException` → 409 `{"message": ...}`, nem 500 (F2-ből maradt). Egyidejű kérésnél ugyanaz a gyári szám két kérésben is átmehet a validáción, és ilyenkor a második az adatbázis unique megkötésén bukik el. Teszt: a validációt megkerülve, közvetlenül az adatbázisban létrehozott ütközés.
- [ ] `NotFoundHttpException` (ide tartozik a `ModelNotFoundException` is) → 404 `{"message": "Resource not found."}`, modellnév és id nélkül
- [ ] A többi esetet (422 validáció, 405, 429, 500) a Laravel már `{message[, errors]}` formában adja. Ezekhez **nem kell saját kód**, csak teszt, amely igazolja a formát.
- [ ] Rate limit: `$middleware->throttleApi()` (alapból 60 kérés/perc)
- [ ] Tesztek: `tests/Feature/ErrorHandlingTest.php`, egy dataset státuszonként (404 erőforrás, 404 ismeretlen útvonal, 405, 409, 422, 500). Mindegyiknél: JSON, van `message`, 422-nél `errors`, `APP_DEBUG=false` mellett nincs `exception`/`trace`/`file` kulcs, és a 404 nem tartalmazza az `App\Models` szöveget.

### 2. API forgalom naplózása adatbázisba
- [ ] Migráció és modell: `api_logs` (README 3. pont), `ApiLog` modell, csak `created_at`
- [ ] `config/api_logging.php`: `enabled` (env), `masked_headers` (`authorization`, `cookie`, `php-auth-pw`), `max_body_length` (pl. 65535)
- [ ] `App\Http\Middleware\LogApiTraffic`, terminable middleware: a `handle()` csak továbbenged, a `terminate()` írja az `ApiLog`-ot (a futási időt a `LARAVEL_START` konstansból vagy egy request attribútumból számolja)
  - a maszkolt fejlécek értéke `***`; a túl hosszú body csonkolva
  - a naplózás hibája nem érintheti a választ: `try/catch` → `report($e)`
- [ ] Regisztráció: `bootstrap/app.php` → `$middleware->api(append: [LogApiTraffic::class])`. Így csak az `/api/*` forgalom naplózódik, a docs nem.
- [ ] Tesztek: sikeres kérés → egy sor a helyes mezőkkel; a 404-es és a 422-es kérés is naplózva; az `Authorization` fejléc maszkolva; `enabled=false` → nincs sor

### 3. OpenAPI dokumentáció Basic Auth mögött
- [ ] `dedoc/scramble` telepítése, `config/scramble.php` publikálása
  - `info`: cím, verzió, rövid leírás (eszköztípus vs. gyári szám)
  - **a `middleware` listában a `RestrictedDocsAccess` helyett** `['web', DocsBasicAuth::class]`, mert az alap middleware csak local környezetben engedi a dokumentációt
- [ ] `config/docs.php`: `username`, `password` az env-ből (`DOCS_USERNAME`, `DOCS_PASSWORD`)
- [ ] `App\Http\Middleware\DocsBasicAuth` (kb. 20 sor):
  - `hash_equals` a felhasználónévre és a jelszóra
  - ha nincs beállítva felhasználónév vagy jelszó → mindig 401 (fail closed)
  - 401 + `WWW-Authenticate: Basic realm="API Docs"`
- [ ] Mind a `/docs/api` (UI), mind a `/docs/api.json` (spec) védett
- [ ] Rövid PHPDoc összefoglaló a controller metódusokon, hogy a dokumentáció olvasható legyen. A Scramble a Form Requestekből és a Resource-okból a többit maga generálja.
- [ ] Spec export: `php artisan scramble:export --path=docs/openapi.json` (commitolva)
- [ ] Tesztek: hitelesítés nélkül 401 + `WWW-Authenticate`; rossz jelszó 401; jó adatokkal 200; az `api.json` minden task-végpontot tartalmaz; üres env → 401 helyes adatokkal is

### Böngészős ellenőrzés (opcionális, MCP)
- [ ] Claude in Chrome: `http://user:pass@localhost:8080/docs/api` megnyitása, és egy képernyőkép arról, hogy minden végpont látszik. (A böngésző natív Basic Auth ablaka blokkolhatja a bővítményt, ezért kell a felhasználónév és a jelszó az URL-ben.)

## Elfogadási feltételek

- [ ] Minden hiba `{message[, errors]}` JSON, megfelelő státuszkóddal
- [ ] Minden `/api/*` kérés (a hibásak is) egy sort ír az `api_logs` táblába, maszkolt Authorization fejléccel
- [ ] A `/docs/api` Basic Auth nélkül 401, vele a Scramble UI az összes végponttal
- [ ] `make qa` zöld

## Commit(ok)

- `feat: render all API errors as consistent JSON`
- `feat: log API requests and responses to the database`
- `feat: publish OpenAPI documentation behind basic auth`
