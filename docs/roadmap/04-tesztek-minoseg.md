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
- [ ] `make coverage` (`--coverage --min=100`), a nem lefedett sorok átnézése: ahol valós viselkedés maradt teszteletlenül, ott pótolni kell
- [ ] Követelmény-mátrix teszt-szinten: a `docs/task.txt` minden pontjához legyen legalább egy teszt, a nevében hivatkozással (pl. `it('shows devices on contract detail but not in list')`)
- [ ] Szélsőséges esetek, ha még hiányoznak:
  - `per_page=0` és `per_page=1000` (a felső korlát érvényesül)
  - nem numerikus id (`/devices/abc`) → 404
  - túl hosszú string (max+1) → 422
  - ékezetes adat (pl. `partner_name: "Árvíztűrő Kft."`) változatlanul jön vissza
  - levétel után ugyanaz a gyári szám újra hozzáadható

### Standardek ellenőrzése
- [ ] `composer dump-autoload --strict-psr` figyelmeztetés nélkül (PSR-4)
- [ ] `vendor/bin/phpcs` hiba **és** figyelmeztetés nélkül (PSR-12; a 120 karakteres soft limit is)
- [ ] `vendor/bin/pint --test` tiszta
- [ ] PHPStan level 6 hibátlan, baseline nélkül
- [ ] Arch teszt: a service-ek nem függenek a `Illuminate\Http\Request`-től (`expect('App\Services')->not->toUse(Request::class)`), mert ez a rétegszabály a bővíthetőség alapja
- [ ] Arch teszt: `expect('App\Services\Interfaces')->toBeInterfaces()`; `expect('App\Http\Controllers')->not->toUse([DeviceService::class, ContractService::class])`; `expect(DeviceService::class)->toImplement(DeviceServiceInterface::class)` (és ugyanígy a `ContractService`-re)

### CI
- [ ] `.github/workflows/ci.yml`: `ubuntu-latest`, `mysql:8.4` service, `shivammathur/setup-php` (8.5, `pcov`), composer cache; lépések: validate → install → strict-psr → pint → phpcs → phpstan → pest --coverage --min=100 → composer audit
- [ ] CI badge a README-be
- [ ] Push után `gh run watch` → zöld

## Elfogadási feltételek

- [ ] `make qa` zöld, lefedettség 100%
- [ ] A CI a GitHubon zöld
- [ ] Nincs kihagyott (`skip`) vagy `todo` teszt

## Commit(ok)

- `test: harden edge cases and requirement coverage`
- `ci: run quality gates on GitHub Actions`
