# F7 – Manuális tesztforgatókönyv

**Végrehajtó:** te · **Előfeltétel:** F6 kész, push megtörtént

Minden lépésnél pipáld ki a sort, ha az eredmény megegyezik az elvárttal. Eltérésnél írd mellé, mit láttál, és add át Claude-nak javításra.

Segédváltozók (a terminálban egyszer állítsd be):

```bash
API=http://localhost:8080/api/v1
# fejlécekkel együtt (státuszkód, Content-Type ellenőrzéséhez):
api()  { curl -s -i -H "Accept: application/json" -H "Content-Type: application/json" "$@"; echo; }
# csak a body, formázva:
apij() { curl -s -H "Accept: application/json" -H "Content-Type: application/json" "$@" | jq; }
```

---

## 0. Telepítés tiszta klónból (a task: Docker + migrációk)

- [ ] `git clone <publikus repó URL> /tmp/dr-check && cd /tmp/dr-check`. A repó **bejelentkezés nélkül** is klónozható (próbáld ki privát ablakban a GitHub oldalát is).
- [ ] A repóban van `docker-compose.yaml`.
- [ ] A README Quick start lépései változtatás nélkül végigfutnak.
- [ ] `docker compose exec app php -v` → **PHP 8.5.x**
- [ ] `docker compose exec app php artisan --version` → **Laravel 13.x**
- [ ] `docker compose exec app php artisan migrate:fresh --seed` → hiba nélkül lefut
- [ ] `docker compose exec app php artisan migrate:status` → minden migráció `Ran`

## 1. OpenAPI dokumentáció (Basic Auth)

- [ ] Böngészőben a `http://localhost:8080/docs/api` → a böngésző felhasználónevet és jelszót kér
- [ ] Mégse / rossz adat → 401, nincs dokumentáció
- [ ] Helyes adatok (`.env`: `DOCS_USERNAME` / `DOCS_PASSWORD`) → a Scramble UI betölt
- [ ] A dokumentációban szerepel mind a 12 API végpont (5 eszköz, 5 szerződés, 2 hozzárendelés)
- [ ] A végpontoknál látszanak a request mezők, a validációs szabályok, az enum értékek, és a 422-es validációs hibaválasz
- [ ] A „Try it" funkcióval egy `GET /devices` hívás sikeres
- [ ] `curl -s -u USER:PASS http://localhost:8080/docs/api.json | jq '.openapi, (.paths | keys)'` → verzió és útvonalak

## 2. Eszköz CRUD

- [ ] **Lista:** `apij $API/devices` → 200, `data` tömb, `links` és `meta` lapozással
- [ ] **Lapozás:** `?per_page=2&page=2` → 2 elem, `meta.current_page = 2`
- [ ] **per_page felső korlát:** `?per_page=1000` → legfeljebb 100 elem (vagy 422; a README szerinti viselkedés)
- [ ] **Létrehozás:**
  ```bash
  api -X POST $API/devices -d '{"manufacturer":"Lenovo","model":"ThinkPad T14 Gen 5","category":"laptop","description":"Fejlesztői gép"}'
  ```
  → **201**, a válaszban `id`. Jegyezd fel: `DEVICE_ID=…`
- [ ] **Egy elem:** `apij $API/devices/$DEVICE_ID` → 200, a fenti adatok
- [ ] **Módosítás (PATCH):** `-X PATCH -d '{"description":"Módosítva"}'` → 200, csak a leírás változott
- [ ] **Módosítás (PUT):** `-X PUT` az összes mezővel → 200
- [ ] **Validáció:** POST üres body-val → **422**, `errors` alatt `manufacturer`, `model`, `category`
- [ ] **Érvénytelen enum:** `"category":"toaster"` → 422
- [ ] **Duplikált típus:** ugyanaz a `manufacturer` és `model` másodszor → 422
- [ ] **Nem létező:** `$API/devices/999999` → **404**, `{"message": "Resource not found."}`
- [ ] **Nem numerikus id:** `$API/devices/abc` → 404
- [ ] **Törlés:** egy szerződéshez nem rendelt eszköz → **204**, utána GET → 404

## 3. Szerződés CRUD

- [ ] **Létrehozás:**
  ```bash
  api -X POST $API/contracts -d '{"contract_number":"SZ-2026-001","partner_name":"Árvíztűrő Kft.","start_date":"2026-01-01","end_date":"2026-12-31","status":"active"}'
  ```
  → **201**. Jegyezd fel: `CONTRACT_ID=…`
- [ ] Az ékezetes `partner_name` hibátlanul jön vissza
- [ ] **Lista:** `$API/contracts` → 200, lapozott
- [ ] ⚠️ **A listában NINCS `devices` kulcs egyik elemnél sem** (task követelmény):
  `apij $API/contracts | jq '[.data[] | has("devices")] | any'` → `false`
- [ ] **Egy elem:** `$API/contracts/$CONTRACT_ID` → 200, van `devices` kulcs (most üres tömb)
- [ ] **PATCH:** `{"status":"terminated"}` → 200
- [ ] **Dátum validáció:** `end_date` < `start_date` → 422
- [ ] **PATCH dátum validáció:** csak `{"end_date":"2025-01-01"}` (korábbi a meglévő kezdésnél) → 422
- [ ] **Duplikált szerződésszám:** → 422
- [ ] **Nem létező:** `$API/contracts/999999` → 404

## 4. Eszköz hozzáadása és levétele szerződésről

- [ ] **Hozzáadás:**
  ```bash
  api -X POST $API/contracts/$CONTRACT_ID/devices -d "{\"device_id\":$DEVICE_ID,\"serial_number\":\"SN-0001\"}"
  ```
  → **201**, a válaszban `assignment_id`. Jegyezd fel: `ASSIGNMENT_ID=…`
- [ ] **Ugyanaz a típus még egyszer, más gyári számmal** (`SN-0002`) → 201 (N:N, „eszközönként más gyári számmal")
- [ ] **Ugyanaz a gyári szám még egyszer** (`SN-0001`) → **422** vagy **409**
- [ ] **Ugyanaz a gyári szám egy MÁSIK szerződésen** → hiba (egy fizikai eszköz egy szerződésen)
- [ ] **Ugyanaz a típus egy MÁSIK szerződésen, új gyári számmal** (`SN-0003`) → 201 (a task: „egy eszköztípus több szerződésen is rajta lehet")
- [ ] **Nem létező eszköz:** `device_id: 999999` → 422
- [ ] **Nem létező szerződés:** `$API/contracts/999999/devices` → 404
- [ ] ⚠️ **Szerződés lekérdezése most:** `$API/contracts/$CONTRACT_ID` → a `devices` tömbben 2 elem, gyári számmal és eszközadatokkal (task követelmény)
- [ ] ⚠️ **A lista továbbra sem tartalmaz eszközöket** (lásd 3. pont, jq parancs)
- [ ] **Levétel:** `-X DELETE $API/contracts/$CONTRACT_ID/devices/$ASSIGNMENT_ID` → **204**
- [ ] A szerződés lekérdezésében már csak 1 eszköz van
- [ ] **Idegen hozzárendelés levétele:** egy másik szerződés `assignment_id`-ja ezzel a `CONTRACT_ID`-val → 404
- [ ] A levett `SN-0001` újra hozzáadható → 201

## 5. Törlési szabályok

- [ ] **Eszköztípus törlése, amely szerződésen van** → **409**, érthető `message`
- [ ] **Szerződés törlése, amelyen van eszköz** → **409**
- [ ] Az összes eszköz levétele után a szerződés törölhető → 204
- [ ] Ezután az eszköztípus is törölhető (ha máshol nincs) → 204

## 6. Egységes hibakezelés

- [ ] Minden eddigi hibaválasz azonos szerkezetű: `message` (422-nél plusz `errors`)
- [ ] `Content-Type: application/json` a hibáknál
- [ ] **Accept fejléc nélkül** (`curl -s -i $API/devices/999999`) is JSON a válasz, nem HTML
- [ ] **Ismeretlen útvonal:** `$API/nincs-ilyen` → 404 JSON
- [ ] **Rossz metódus:** `-X DELETE $API/devices` → 405 JSON
- [ ] Egyik hibaválaszban sincs stack trace, fájlnév vagy `App\…` osztálynév

## 7. API forgalom naplózása (adatbázis)

- [ ] Lekérdezés:
  ```bash
  docker compose exec mysql mysql -u root -p device_registry \
    -e "SELECT id, method, path, status_code, duration_ms, created_at FROM api_logs ORDER BY id DESC LIMIT 20"
  ```
- [ ] A fenti tesztlépések mind látszanak (2xx, 404, 409, 422, 405 is)
- [ ] Egy POST sorban a `request_body` tartalmazza a küldött JSON-t, a `response_body` a választ
- [ ] Egy `-H "Authorization: Bearer titok"` fejléccel küldött kérésnél a naplóban a fejléc maszkolva van (`***`)
- [ ] A `/docs/api` hívások nincsenek naplózva

## 8. Standardek és automata tesztek

- [ ] `make qa` → minden zöld
- [ ] `make coverage` → 100%
- [ ] `docker compose exec app composer dump-autoload --strict-psr` → nincs figyelmeztetés (PSR-4)
- [ ] `docker compose exec app vendor/bin/phpcs` → 0 hiba (PSR-12)
- [ ] A GitHubon a CI zöld a `main` branchen
- [ ] Szúrópróba: nyiss meg 3 véletlenszerű fájlt az `app/`-ból. Van bennük `declare(strict_types=1)`, a controller vékony, nincs benne üzleti logika.

## 9. Biztonság

- [ ] Az F5 „Kézi biztonsági ellenőrzés" listája kipipálva
- [ ] A `docs/SECURITY_REVIEW.md` létezik, és nincs nyitott magas súlyosságú találat

## 10. Repó és átadás

- [ ] A README minden linkje működik a GitHubon (a Mermaid diagram is megjelenik)
- [ ] Nincs a repóban `.env`, `vendor/`, `coverage/`, és a `docs/task.txt`-ben nincs személyes adat (telefonszám, aláírás)
- [ ] A `docs/roadmap/` és a `docs/task.txt` megjelenik a GitHubon, és a README „Development process" szakaszának linkjei működnek
- [ ] A commit történet olvasható (Conventional Commits)
- [ ] Ha a levélre jött válasz, az abban kértek teljesülnek
- [ ] **Kísérő levél** a technikai kapcsolattartóknak (másolatban a HR-es kollégának), a határidő előtt. Tartalma:
  - [ ] a publikus repó linkje
  - [ ] indítás egy mondatban (`docker compose up` + README Quick start)
  - [ ] a dokumentáció URL-je és a Basic Auth adatok helye (`.env.example`)
  - [ ] **jelzés, hogy a fejlesztési folyamat is dokumentálva van**: a `docs/roadmap/` könyvtárban a fázisokra bontott terv, a döntések indoklása és a manuális tesztforgatókönyv; a `docs/SECURITY_REVIEW.md`-ben a biztonsági átvilágítás
  - [ ] a fontosabb saját döntések 2–3 pontban (pl. gyári szám a kapcsolótáblán, 409 törlésnél, service interface-ek)

---

**Eredmény:** ___ / ___ sikeres · Eltérések: …
