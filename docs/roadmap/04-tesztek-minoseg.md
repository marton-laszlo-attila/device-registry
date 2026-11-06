# F4 – Teszt-keményítés, statikus analízis, CI

**Modell / effort:** Sonnet 5.5 / high · **Előfeltétel:** F3 kész

## Cél

Az F2–F3-ban írt tesztek hiányainak felderítése és pótlása, a standardek független ellenőrzése, valamint egy GitHub Actions CI, amely a publikus repóban is mutatja, hogy minden kapu zöld.

## Indítás

Új terminálban futtasd. A parancs egy új, interaktív Claude Code munkamenetet nyit a megadott modellel és efforttal, és rögtön elküldi az indító promptot. A fájlszerkesztéseket automatikusan jóváhagyja (`acceptEdits`), a shell parancsokra továbbra is rákérdez.

```bash
cd ~/Documents/allaskereses/wd/device-registry
claude --model claude-sonnet-5-5 --effort high --permission-mode acceptEdits -n "F4 Tesztek minoseg" '
Olvasd el a docs/roadmap/README.md és a docs/roadmap/04-tesztek-minoseg.md fájlt, és hajtsd végre
az F4 fázist. Ne írj tesztet csak a lefedettség kedvéért: minden új teszt egy valós viselkedést
vagy szélsőséges esetet rögzítsen. A meglévő kódot csak akkor módosítsd, ha a teszt hibát talál;
ezt külön `fix:` commitba tedd, és jelezd nekem.'
```

## Feladatok

### Lefedettség és hiányelemzés
- [x] `make coverage` (`--coverage --min=100`), a nem lefedett sorok átnézése: ahol valós viselkedés maradt teszteletlenül, ott pótolni kell
- [x] Követelmény-mátrix teszt-szinten: a `docs/task.txt` minden pontjához legyen legalább egy teszt, a nevében hivatkozással (pl. `it('shows devices on contract detail but not in list')`)
- [x] Szélsőséges esetek, ha még hiányoznak:
  - `per_page=0` és `per_page=1000` (a felső korlát érvényesül)
  - nem numerikus id (`/devices/abc`) → 404
  - túl hosszú string (max+1) → 422
  - ékezetes adat (pl. `partner_name: "Árvíztűrő Kft."`) változatlanul jön vissza
  - levétel után ugyanaz a gyári szám újra hozzáadható

### Standardek ellenőrzése
- [x] `composer dump-autoload --strict-psr` figyelmeztetés nélkül (PSR-4)
- [x] `vendor/bin/phpcs` hiba **és** figyelmeztetés nélkül (PSR-12; a 120 karakteres soft limit is)
- [x] `vendor/bin/pint --test` tiszta
- [x] PHPStan level 6 hibátlan, baseline nélkül
- [x] Arch teszt: a service-ek nem függenek a `Illuminate\Http\Request`-től (`expect('App\Services')->not->toUse(Request::class)`), mert ez a rétegszabály a bővíthetőség alapja
- [x] Arch teszt: `expect('App\Services\Interfaces')->toBeInterfaces()`; `expect('App\Http\Controllers')->not->toUse([DeviceService::class, ContractService::class])`; `expect(DeviceService::class)->toImplement(DeviceServiceInterface::class)` (és ugyanígy a `ContractService`-re)

### CI
- [x] `.github/workflows/ci.yml`: `ubuntu-latest`, `mysql:8.4` service, `shivammathur/setup-php` (8.5, `pcov`), composer cache; lépések: validate → install → strict-psr → pint → phpcs → phpstan → pest --coverage --min=100 → composer audit
- [x] CI badge a README-be
- [x] Push után `gh run watch` → zöld

## Elfogadási feltételek

- [x] `make qa` zöld, lefedettség 100%
- [x] A CI a GitHubon zöld
- [x] Nincs kihagyott (`skip`) vagy `todo` teszt

## Commit(ok)

- `test: harden edge cases and requirement coverage`
- `ci: run quality gates on GitHub Actions`

## Megvalósítás (2026-11-06)

**Állapot:** kész. A CI a GitHubon zöld (run 42279254883, 1 perc 13 mp, minden lépés). `make qa` zöld: 195 teszt (F3-ban 125), 100% lefedettség, phpcs hiba **és figyelmeztetés** nélkül, PHPStan level 6 baseline nélkül, `composer audit` tiszta, nincs `skip`/`todo`.

### Hibák, amelyeket az új tesztek találtak (külön `fix:` commit)

- **Nem egész szám azonosító feloldódott egy másik rekordra.** A MySQL az egész oszlopot szöveggel úgy hasonlítja, hogy a szöveget számmá alakítja, ezért `GET /api/v1/devices/1abc` (és `1.0`) a 200-as választ adta az 1-es eszközről, `DELETE /api/v1/devices/1abc` pedig törölte volna az 1-es eszközt. Javítás: a `v1` route csoport `where()` megkötése (`device`, `contract`, `assignment` = `[0-9]+`), így ezek 404-et adnak az egységes üzenettel. Teszt: `RouteParametersTest`.
- **Laza dátumvalidáció.** A `date` szabály elfogadta a `10000-01-01`-et, és az **`2000-01-01`-ként mentődött** (csendes adatromlás); a `01/02/2026`-ot hónap/nap sorrendben értelmezte; a dátum-idő szöveget (`2026-01-01 10:00:00`, ISO 8601) csendben levágta. Javítás: `date_format:Y-m-d` a `StoreContractRequest`-ben (a válaszok is ezt a formátumot adják). Mellékhatás, javítva: a kérés-séma dátum mezői a specben tévesen `date-time`-ok voltak, most `date`; az `api/docs/openapi.json` újra exportálva. Teszt: `ContractDateFormatTest`.

### Új tesztek (`test:` commit)

- `RequirementsTest`: a `docs/task.txt` követelmény-mátrixa, végponttól végpontig, csak HTTP-n keresztül (a nevek `task:` előtagúak). A többi követelmény fájlonkénti megfeleltetése a fájl fejlécében van.
- Szélsőséges esetek: lapozás folytonossága és üres oldal az utolsó után; `per_page=0/1000`; hosszú szöveg (`description`, `notes`) max+1 → 422, pontosan a maximum (többbájtos karakterekkel is, tehát karakter- és nem bájtszámolás) → 201; ékezetes és emoji szöveg változatlanul jön vissza; `id`/`contract_id`/`created_at` a body-ban figyelmen kívül marad; explicit `null` törli a nullable mezőt, kihagyás megtartja; azonos napon kezdődő és végződő szerződés; egy szerződés csak a saját eszközeit mutatja; gyári szám kis-/nagybetű-függetlenül egyedi; levétel után ugyanaz a gyári szám újra (akár másik szerződésre) hozzáadható; az eszköztípus 409 marad, amíg egy másik szerződésen is rajta van; hibás JSON body egységes 422, nem 500.
- `SeederTest`: a `make fresh` demóadata konzisztens, és kiszolgálható az API-n.
- Arch: a controller nem használ DB-t; az `App\Exceptions` osztályai az `ApiException` leszármazottai; az enumok string-backed-ek.

### Eltérések a tervtől

- A roadmap három arch szabálya már megvolt, egyenértékű formában: `toOnlyBeUsedIn('App\Providers')` a konkrét service-ekre (erősebb, mint a controllerekre kimondott `not->toUse`), `not->toUse(['Illuminate\Http', ...])` a service-ekre, `toBeInterfaces()` és `toImplement()`. Duplikálni nem kellett őket.
- A `make coverage` már a fázis elején 100% volt, nem maradt lefedetlen sor. A hiányokat tehát viselkedés szintjén kerestem, nem sorokból.
- A teszt-segédek (`createDeviceViaApi`, ...) a `Tests\TestCase` metódusai, mert a globális függvény a phpcs PSR-1 mellékhatás-szabályát sértette.
- A CI a `migrate:fresh --seed` lépést is futtatja (a README 7. pontja kapuként sorolja fel, a `make qa` viszont nem), a teszt-adatbázist pedig ugyanazzal a SQL szkripttel hozza létre, mint a Docker (`docker/mysql/init`).

### Megfigyelés, nem módosítva

- Nem numerikus `per_page` (`abc`, üres) a legkisebb oldalmérettet (1) adja, nem az alapértelmezett 15-öt. A dokumentált „1–100 közé szorítás"-nak megfelel, ezért nem hiba, de félreérthető. Ha az alapértelmezett lenne a jobb, a `Controller::perPage()` egy sora.
- A `Latitude` és `latitude`, valamint az ékezet nélküli alak (`utf8mb4_unicode_ci`) ugyanannak számít az egyediségi ellenőrzésben. A gyári számnál ez kívánatos (tesztelve), az eszköztípusnál elfogadott mellékhatás.

### Commitok

- `c5aabc8 fix: reject non-integer route identifiers`
- `82d7583 fix: accept only Y-m-d dates for contracts`
- `69b2089 test: harden edge cases and requirement coverage`
- `deef3a3 ci: run quality gates on GitHub Actions`

### Az F5–F6-ra maradt

- A GitHub figyelmeztet, hogy az `actions/checkout@v4` és `actions/cache@v4` Node 20-at céloz (a runner kényszerítve Node 24-gyel futtatja). Jelenleg nem hiba; az F6 átnézésénél érdemes a legújabb főverzióra emelni.
