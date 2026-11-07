# F6 – README, végső review, átadás előkészítése

**Modell / effort:** 6a: Opus 5.5 / high · 6b: Sonnet 5.5 / medium · **Előfeltétel:** F5 kész

## Cél

Egy értékelő fejlesztő 5 perc alatt elindítsa a projektet, és lássa a tervezési döntéseket. A kód egy utolsó helyességi review-n is átesik.

## Indítás

Új terminálban futtasd. Két lépésben, két külön munkamenetben, ebben a sorrendben. Mindkét parancs egy új, interaktív Claude Code munkamenetet nyit a megadott modellel és efforttal, és rögtön elküldi az indító promptot. A fájlszerkesztéseket automatikusan jóváhagyja (`acceptEdits`), a shell parancsokra továbbra is rákérdez.

**6a – végső review** (Opus 5.5 / high):

```bash
cd ~/Documents/allaskereses/wd/device-registry
claude --model claude-opus-5-5 --effort high --permission-mode acceptEdits -n "F6a Vegso review" '
Olvasd el a docs/roadmap/README.md és a docs/roadmap/06-dokumentacio-atadas.md fájlt, és hajtsd végre
az F6 "Végső review" szakaszát: /code-review high a teljes kódbázisra, utána /simplify, majd a többi
pont. A jogos találatokat javítsd, külön commitban. A README-t és az átadást még ne csináld.'
```

**6b – README és átadás** (Sonnet 5.5 / medium):

```bash
cd ~/Documents/allaskereses/wd/device-registry
claude --model claude-sonnet-5-5 --effort medium --permission-mode acceptEdits -n "F6b README atadas" '
Olvasd el a docs/task.txt, a docs/roadmap/README.md és a docs/roadmap/06-dokumentacio-atadas.md
fájlt, és hajtsd végre az F6 fázist. A README angolul készüljön, egy értékelő backend
fejlesztőnek szóljon: tömör, pontos, minden parancs működjön tiszta klónból.
A "Végső review" szakasz már kész (előző munkamenet), azt hagyd ki.'
```

## Feladatok

### Végső review
- [x] `/code-review high` (Opus 5.5) a teljes kódbázisra; a jogos találatok javítása külön commitban
- [x] `/simplify`: duplikáció, felesleges absztrakció (DRY)
- [x] TODO, FIXME, kikommentezett kód, `dd`/`dump` nincs a kódban
- [x] A Laravel vázból maradt, nem használt fájlok törlése (pl. a `User` modell és migráció, ha nem kell; `resources/js`, ha nincs frontend). **Előtte ellenőrizd, hogy semmi nem hivatkozik rájuk.**

### README.md (angol)
- [ ] Cím, egy mondatos leírás, CI badge
- [ ] **Requirements coverage**: táblázat a task minden pontjáról → hol van megoldva (fájl vagy végpont) → melyik teszt igazolja
- [ ] **Quick start** (tiszta klónból, lépésről lépésre):
  ```
  git clone … && cd device-registry
  make up                         # creates .env (random MySQL passwords), builds and starts the stack
  make install                    # composer install, api/.env from api/.env.example, app key
  # set DOCS_USERNAME / DOCS_PASSWORD in api/.env
  make fresh                      # migrate:fresh --seed
  ```
  plusz make nélkül: a gyökérben `cp .env.example .env` és a két MySQL jelszó kitöltése, utána `docker compose up -d --build` és a `docker compose exec app …` parancsok (F5 óta a compose jelszó nélkül nem indul, lásd `docs/SECURITY_REVIEW.md` F-13)
- [ ] **API documentation**: URL (`http://localhost:8080/docs/api`), Basic Auth adatok az `.env`-ből, `docs/openapi.json`
- [ ] **Endpoints**: rövid táblázat + 2–3 curl példa (létrehozás, hozzárendelés, szerződés lekérdezése eszközökkel)
- [ ] **Data model**: diagram (Mermaid `erDiagram`) + az eszköztípus és a gyári szám magyarázata
- [ ] **Error format**: példa (`{message, errors}`)
- [ ] **API traffic logging**: mit naplóz, mit maszkol, megőrzés, lekérdezési példa
- [ ] **Architecture & design decisions**: rétegek; miért egyszerű (nincs repository, DTO), és miért van mégis service interface (Dependency Inversion, cserélhető implementáció); **Extending the API** alszakasz a roadmap README 6. pontja alapján (hová kerül egy új szabály, mező, verzió, auth); részleges PUT; 409 törlésnél; globálisan egyedi gyári szám; nincs API auth (a task szerint) és éles javaslat; miért Scramble
- [ ] **Quality**: PSR-4/PSR-12 ellenőrzés (Pint + phpcs), PHPStan level, lefedettség, CI
- [ ] **Security**: link a `docs/SECURITY_REVIEW.md`-re
- [ ] **Development process**: rövid leírás arról, hogyan készült a projekt. Linkek: `docs/task.txt` (az eredeti feladat), `docs/roadmap/` (a fázisokra bontott terv, döntések, manuális tesztforgatókönyv; magyarul), `docs/SECURITY_REVIEW.md`. Egy mondat arról, hogy a fejlesztés Claude Code-dal, fázisonként és tesztvezérelten történt, a `CLAUDE.md` és a `.mcp.json` ennek a munkafolyamatnak a része.
- [ ] **Testing**: `make test`, `make coverage`, `make qa`

### Átadás előkészítése
- [ ] Tiszta klón próbája egy ideiglenes könyvtárban (`git clone` → Quick start lépései → `make qa`). Ha bármi hiányzik, javítsd.
- [ ] `docs/openapi.json` újraexportálva, naprakész
- [ ] A `docs/task.txt` anonimizálása: a megszólítás, az aláírás és a telefonszám törlése, a feladatleírás változatlan marad (lásd roadmap README 10. pont)
- [ ] Ellenőrzés: a `.gitignore` csak a generált és titkos fájlokat zárja ki (`.env`, `vendor/`, `coverage/`, cache), a `docs/` teljes tartalma commitolva van
- [ ] Opcionális git tag: `v1.0.0`
- [ ] Push, CI zöld

## Elfogadási feltételek

- [ ] A README alapján egy idegen gépen 5 percen belül fut a projekt
- [ ] A „Requirements coverage" táblázatban a task összes pontja szerepel
- [ ] CI zöld a `main` branchen

## Commit(ok)

- `refactor: address final review findings`
- `docs: add README with setup, API usage and design decisions`
