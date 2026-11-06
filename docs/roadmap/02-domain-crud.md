# F2 – Domain, CRUD, eszköz hozzárendelése + automata tesztek

**Modell / effort:** Opus 5.5 / high · **Előfeltétel:** F1 kész, `make qa` zöld

## Cél

A task összes funkcionális követelménye: eszköz és szerződés CRUD, a szerződés egyedi lekérdezése eszközökkel (a listában eszközök nélkül), eszköz hozzáadása és levétele szerződésről. Minden végponthoz előbb a Pest feature teszt készül.

**Egyszerűség:** csak a README 5. pontjában felsorolt elemek készüljenek el (Form Request, vékony controller, 2 service interface + 2 service, model, Resource, 2 exception osztály). Ne legyen repository, DTO, action osztály vagy event.

## Indítás

Új terminálban futtasd. A parancs egy új, interaktív Claude Code munkamenetet nyit a megadott modellel és efforttal, és rögtön elküldi az indító promptot. A fájlszerkesztéseket automatikusan jóváhagyja (`acceptEdits`), a shell parancsokra továbbra is rákérdez.

```bash
cd ~/Documents/allaskereses/wd/device-registry
claude --model claude-opus-5-5 --effort high --permission-mode acceptEdits -n "F2 Domain CRUD" '
Olvasd el a docs/task.txt, a docs/roadmap/README.md és a docs/roadmap/02-domain-crud.md fájlt.
Hajtsd végre az F2 fázist a README 3–6. pontjában rögzített adatmodell, API szerződés és
architektúra szerint. Egyszerű megoldás kell: ne vezess be a README 5. pontján túli
absztrakciót. Minden végponthoz előbb írd meg a Pest feature tesztet, utána az implementációt.
A Laravel 13 API-khoz a laravel-boost MCP dokumentációkeresőjét használd.
Commit előtt a `make qa` legyen zöld.'
```

## Feladatok

### Adatbázis
- [x] Migrációk: `create_devices_table`, `create_contracts_table`, `create_contract_device_table` (FK-k `restrictOnDelete()`, `serial_number` unique), lásd README 3. pont
- [x] Enumok: `App\Enums\ContractStatus`, `App\Enums\DeviceCategory` (backed string enum). Az oszlop `string`, nem MySQL `ENUM`, így egy új érték nem igényel migrációt.
- [x] Modellek: `Device`, `Contract`, `ContractDevice` (`extends Pivot`, `$incrementing = true`, `$table = 'contract_device'`)
  - `Contract::devices()`: `belongsToMany(Device::class)->using(ContractDevice::class)->withPivot('id', 'serial_number')->withTimestamps()`
  - `Contract::assignments()`: `hasMany(ContractDevice::class)` (a scoped bindinghoz és a törlési feltételhez)
  - `Device::assignments()` ugyanígy
  - `$fillable` explicit, `casts()`: enum, `date`
- [x] Factory-k mindhárom modellhez
- [x] `DatabaseSeeder`: néhány eszköztípus, szerződés és hozzárendelés valószerű adatokkal

### Alkalmazásréteg
- [x] Form Requestek: `StoreDeviceRequest`, `UpdateDeviceRequest`, `StoreContractRequest`, `UpdateContractRequest`, `AssignDeviceRequest`
  - Contract: `contract_number` kötelező, max. 50, unique (frissítésnél ignore önmagára); `partner_name` kötelező, max. 255; `start_date` kötelező dátum; `end_date` nullable, `after_or_equal:start_date`; `status` `Rule::enum`; `notes` nullable
  - Device: `manufacturer` és `model` kötelező, max. 100, a kettő együtt unique; `category` `Rule::enum`; `description` nullable
  - Assign: `device_id` kötelező, `exists:devices,id`; `serial_number` kötelező, max. 100, unique a `contract_device` táblában
  - Update: ugyanazok a szabályok `sometimes`-szal (PUT és PATCH is részleges, a README-ben jelezni kell). A Store és az Update közös szabályait ne másold: az Update örökölhet a Store-ból, vagy legyen egy közös `rules()` forrás (DRY).
- [x] Exceptionök (`App\Exceptions`):
  - `ApiException` (abstract, `RuntimeException`, `abstract public function status(): int`)
  - `ConflictException extends ApiException` (409), named constructorokkal: `deviceInUse()`, `contractHasDevices()`
  - A HTTP-re fordítás az F3-ban lesz, addig a Laravel alapból 500-at ad. A tesztek F3 után lesznek véglegesek, vagy már most legyen egy minimális `render()` a `bootstrap/app.php`-ban.
- [x] Service interface-ek (`App\Services\Interfaces`): `DeviceServiceInterface`, `ContractServiceInterface`, a lenti metódusokkal és pontos típusokkal (paraméter és visszatérési érték, PHPDoc-ban az array shape)
- [x] Binding: `AppServiceProvider` → `public array $bindings = [DeviceServiceInterface::class => DeviceService::class, ...]`
- [x] Service-ek (`App\Services`, `final`, implementálják az interface-t):
  - `DeviceService`: `list(int $perPage)`, `create`, `update`, `delete` (ha van hozzárendelés → `ConflictException::deviceInUse()`)
  - `ContractService`: `list(int $perPage)`, `create`, `update`, `delete` (ha van eszköz → `ConflictException::contractHasDevices()`), `assignDevice(Contract, array)`, `removeAssignment(ContractDevice)`
  - a `list()` egyelőre csak `orderBy("id")->paginate()`, de ide kerül később a szűrés és a rendezés (README 6. pont); az egy elem lekérdezése route model bindinggel megy
- [x] API Resource-ok: `DeviceResource`, `ContractResource` (`devices` csak `whenLoaded`), `AssignmentResource` (`id`, `serial_number`, beágyazott `device`)
  - **Követelmény:** a `GET /contracts` listában **nincs** `devices` kulcs, a `GET /contracts/{id}` válaszban **van**
- [x] Controllerek (`App\Http\Controllers\Api\V1`): `DeviceController`, `ContractController` (`apiResource`), `ContractDeviceController` (`store`, `destroy`), konstruktoros DI **az interface-re** (pl. `public function __construct(private ContractServiceInterface $contracts)`)
  - `store` → 201; `destroy` → 204
  - `show` → `$contract->load('devices')` (nincs N+1)
  - `index` → `paginate()`, a `per_page` 1 és 100 közé szorítva
- [x] Route-ok: `Route::prefix('v1')`, `apiResource`, a hozzárendelésnél `->scopeBindings()` (idegen szerződés hozzárendelése → 404)

### Automata tesztek (Pest)
- [x] `tests/Feature/DeviceTest.php`: lista lapozással, létrehozás (201 + DB), validációs hibák (dataset a kötelező mezőkre), egyedi lekérdezés, 404, PATCH, unique ütközés, törlés (204), törlés szerződésen lévő eszköznél (409)
- [x] `tests/Feature/ContractTest.php`: ugyanez, plusz: **az `index` nem tartalmaz `devices`-t**, **a `show` tartalmazza** a hozzárendeléseket gyári számmal, `end_date < start_date` → 422, törlés eszközökkel → 409
- [x] `tests/Feature/ContractDeviceTest.php`: hozzárendelés (201), ugyanaz a típus kétszer más gyári számmal (201), ugyanaz a gyári szám másodszor, akár másik szerződésen is (422), nem létező eszköz (422), nem létező szerződés (404), levétel (204), idegen szerződés hozzárendelése (404)
- [x] Az üzleti szabályokat a feature tesztek lefedik; a service-ekhez csak akkor kell külön unit teszt, ha egy eset HTTP-n keresztül nehezen állítható elő

## Elfogadási feltételek

- [x] `make fresh` után a seedelt adatokkal minden végpont működik (curl)
- [x] A task „Megvalósítandó feladatok" mind a 4 pontjához van zöld teszt
- [x] A controllerekben nincs üzleti `if`, a service-ekben nincs `Request`
- [x] A controllerek csak interface-t importálnak, konkrét service osztályt nem
- [x] `make qa` zöld

## Commit(ok)

- `feat: add device and contract models with migrations, factories and seeders`
- `feat: add CRUD endpoints for devices and contracts`
- `feat: add device assignment to contracts with serial numbers`

## Megvalósítás (2026-11-06)

**Állapot:** kész. `make qa` zöld: 87 teszt, 100% lefedettség, és a küszöb is 100%.

### Eltérések a tervtől

- **Model attribútumok:** a `$fillable`, `$table` és `$incrementing` property helyett a Laravel 13 osztályattribútumai szerepelnek (`#[Fillable([...])]`, `#[Table(name: 'contract_device', incrementing: true)]`). A Laravel 13 dokumentációja az auto-increment kulcsú pivot modellhez ezt írja elő. A működés ugyanaz.
- **Szerződés `show`:** `$contract->load('assignments.device')` a `load('devices')` helyett. Így a szerződés nézet `devices` tömbje és a `POST /contracts/{contract}/devices` válasza ugyanazt az `AssignmentResource`-t használja (`id`, `serial_number`, `assigned_at`, `device`). Ez is 2 lekérdezés, nincs N+1. A `Contract::devices()` és a `Device::contracts()` N:N reláció megmaradt, a seeder és a `DomainModelTest` használja.
- **Részleges frissítés validálása:** PATCH-nél akkor is ellenőrizzük a gyártó és a modell páros egyediségét, illetve a dátumok sorrendjét, ha csak az egyik mező érkezik. A hiányzó értéket a tárolt rekordból vesszük.
- **`per_page`:** a 1..100 közé szorítás egy helyen van, a közös `Controller::perPage()` metódusban (DRY), nem mindkét controllerben külön.

### A tervhez képest pluszban

- `tests/Feature/DomainModelTest.php`: az adatbázis szintű garanciák tesztje (globálisan egyedi gyári szám, `UNIQUE(manufacturer, model)`, RESTRICT FK). Ezeket a feature tesztek nem érik el, mert a validáció előbb megfogja a hibát.
- `tests/ArchTest.php`: a konkrét service-eket csak az `App\Providers` használja; a service-ek `final`-ok, implementálják az interface-üket, és nem használnak HTTP osztályt.
- `phpstan.neon`: `parseModelCastsMethod: true`. Enélkül a Larastan nem látja a `casts()` metódusban megadott dátum- és enum castokat.
- `tests/Unit/.gitkeep` (F1 hiba): a könyvtár nélkül friss klónon a Pest egyetlen tesztet sem futtat, mégis 0-s kóddal lép ki.
- A lefedettségi küszöb 80%-ról 100%-ra nőtt (`Makefile`, `composer.json`, roadmap).

### Az F3-ra maradt

- `APP_DEBUG=true` mellett a 404-es válasz kiírja a modell osztálynevét (`No query results for model [App\Models\...]`) és a stack trace-t.
- Egyidejű kérések esetén ugyanazt a gyári számot két kérés is átengedheti a validáción. Ilyenkor a második az adatbázis unique megkötésén bukik el (`UniqueConstraintViolationException`), és jelenleg 500-at kap. A handlerben 409-re kell fordítani (felvéve a 03-as fázisfájlba).
- Az `ApiException` → JSON fordítás egyelőre csak egy minimális `render()` a `bootstrap/app.php`-ban; a teljes, egységes handler az F3 feladata.

### Commitok

- `43a218a fix: track tests/Unit so Pest runs on a fresh clone`
- `e0804ad feat: add device and contract models with migrations, factories and seeders`
- `bbdf8ee feat: add CRUD endpoints for devices and contracts`
- `b020333 feat: add device assignment to contracts with serial numbers`
- `12ce7a5 test: require 100% code coverage in the quality gate`
