# F7 – Manuális tesztforgatókönyv

**Végrehajtó:** te · **Előfeltétel:** F6 kész, push megtörtént

Minden lépésnél pipáld ki a sort, ha az eredmény megegyezik az elvárttal. Eltérésnél írd mellé, mit láttál, és add át Claude-nak javításra.

## Hogyan tesztelj

Minden tesztpontot kétféleképpen lehet végrehajtani. Válaszd, amelyik kényelmesebb:

- **📖 Dokumentációs felület (Try it):** `http://localhost:8080/docs/api`. A bal oldali menüben válaszd ki a műveletet (pl. *Devices → List device types*), a jobb oldali **Try It** panelen töltsd ki a paramétereket és a body-t, majd kattints a **Send API Request** gombra. A válasz alatta jelenik meg: a státuszkód (pl. `200 OK`) és a JSON body.
- **⌨️ Terminál (curl):** a lent megadott parancsok. Ehhez egyszer állítsd be a segédfüggvényeket.

Néhány pont csak terminálból tesztelhető (pl. fejléc nélküli kérés, rossz HTTP metódus, adatbázis). Ezeknél nincs 📖 sor.

A részleges frissítés a felületen az **Update a …** (PUT) műveletnél van: csak azokat a mezőket írd a body-ba, amelyeket módosítani akarsz. Az API a PUT-ot és a PATCH-et is részlegesen kezeli.

Segédfüggvények (egy terminálablakban egyszer kell bemásolni). A terminál a klón könyvtárában legyen (`cd /tmp/dr-check`), mert a `docker compose` parancsok csak ott működnek:

```bash
API=http://localhost:8080/api/v1
H=(-H "Accept: application/json" -H "Content-Type: application/json")
api()  { curl -s -i "${H[@]}" "$@"; echo; }                          # státusz + fejlécek + body
apij() { curl -s "${H[@]}" "$@" | jq; }                               # csak a body, formázva
code() { curl -s -o /dev/null -w "%{http_code}\n" "${H[@]}" "$@"; }   # csak a státuszkód
```

A létrehozott elemek azonosítóját a curl-ös lépések automatikusan változóba teszik (`DEVICE_ID`, `CONTRACT_ID` stb.). A felületen a válaszból olvasd ki a `data.id` értéket, és írd be a következő lépések path paramétereibe.

Ha egy percen belül több mint 60 kérést küldesz, `429` jön. Ilyenkor várj egy percet.

---

## 0. Telepítés tiszta klónból (a task: Docker + migrációk)

- [x] `git clone https://github.com/marton-laszlo-attila/device-registry.git /tmp/dr-check && cd /tmp/dr-check`. A repó **bejelentkezés nélkül** is klónozható (próbáld ki privát ablakban a GitHub oldalát is).
- [x] A repóban van `docker-compose.yaml`.
- [x] A README Quick start lépései változtatás nélkül végigfutnak.
- [x] `docker compose exec app php -v` → **PHP 8.5.x**
- [x] `docker compose exec app php artisan --version` → **Laravel 13.x**
- [x] `docker compose exec app php artisan migrate:fresh --seed` → hiba nélkül lefut
- [x] `docker compose exec app php artisan migrate:status` → minden migráció `Ran`

## 1. OpenAPI dokumentáció (Basic Auth)

- [x] Böngészőben a `http://localhost:8080/docs/api` → a böngésző felhasználónevet és jelszót kér
- [x] Mégse / rossz adat → 401, nincs dokumentáció
- [x] Helyes adatok (`.env`: `DOCS_USERNAME` / `DOCS_PASSWORD`) → a Scramble UI betölt
- [x] A dokumentációban szerepel mind a 12 API végpont (5 eszköz, 5 szerződés, 2 hozzárendelés)
- [x] A végpontoknál látszanak a request mezők, a validációs szabályok, az enum értékek, és a 422-es validációs hibaválasz
- [x] A „Try it" funkcióval egy `GET /devices` hívás sikeres
- [x] A spec terminálból: `U=$(grep '^DOCS_USERNAME=' api/.env | cut -d= -f2-); P=$(grep '^DOCS_PASSWORD=' api/.env | cut -d= -f2-); curl -s -u "$U:$P" http://localhost:8080/docs/api.json | jq '.openapi, (.paths | keys)'` → `"3.1.2"` és 6 útvonal

## 2. Eszköz CRUD

- [x] **Lista**
  - 📖 *Devices → List device types* → Send API Request
  - ⌨️ `code $API/devices` és `apij $API/devices | jq '{tipus: (.data|type), db: (.data|length), links: (.links|keys), meta: (.meta|{current_page, per_page, total})}'`
  - ✅ `200`; a `data` tömb, és nem üres; a `links` kulcsai: `first`, `last`, `next`, `prev`; a `meta`-ban `current_page: 1`, `per_page: 15`, a `total` nagyobb, mint 0
- [x] **Lapozás**
  - 📖 *List device types*, paraméterek: `per_page` = `2`, `page` = `2`
  - ⌨️ `apij "$API/devices?per_page=2&page=2" | jq '{db: (.data|length), meta: (.meta|{current_page, per_page})}'`
  - ✅ `db: 2` (ha legalább 4 eszköz van), `current_page: 2`, `per_page: 2`
- [x] **per_page felső korlát**
  - 📖 *List device types*, `per_page` = `1000`
  - ⌨️ `apij "$API/devices?per_page=1000" | jq .meta.per_page`
  - ✅ `200`, a `meta.per_page` értéke `100` (a túl nagy értéket 100-ra szorítja, nem ad hibát)
- [x] **Létrehozás**
  - 📖 *Create a device type*, body:
    ```json
    {"manufacturer":"Lenovo","model":"ThinkPad T14 Gen 5","category":"laptop","description":"Fejlesztői gép"}
    ```
  - ⌨️
    ```bash
    DEVICE_ID=$(curl -s "${H[@]}" -X POST $API/devices -d '{"manufacturer":"Lenovo","model":"ThinkPad T14 Gen 5","category":"laptop","description":"Fejlesztői gép"}' | jq -r .data.id); echo $DEVICE_ID
    ```
  - ✅ `201`; a válasz `data` objektumában ott van az `id` és a küldött négy mező. A curl kiírja az új azonosítót (szám, nem `null`).
- [x] **Egy elem**
  - 📖 *Get a device type*, `device` = az új id
  - ⌨️ `apij $API/devices/$DEVICE_ID`
  - ✅ `200`, a `data` mezői megegyeznek a létrehozáskor küldöttekkel
- [x] **Részleges módosítás**
  - 📖 *Update a device type*, `device` = az új id, body: `{"description":"Módosítva"}`
  - ⌨️ `apij -X PATCH $API/devices/$DEVICE_ID -d '{"description":"Módosítva"}' | jq .data`
  - ✅ `200`; a `description` értéke `"Módosítva"`, a `manufacturer`, `model` és `category` változatlan
- [x] **Teljes módosítás**
  - 📖 *Update a device type*, body: `{"manufacturer":"Lenovo","model":"ThinkPad T14 Gen 5","category":"laptop","description":"PUT teszt"}`
  - ⌨️ `apij -X PUT $API/devices/$DEVICE_ID -d '{"manufacturer":"Lenovo","model":"ThinkPad T14 Gen 5","category":"laptop","description":"PUT teszt"}' | jq .data.description`
  - ✅ `200`, a `description` értéke `"PUT teszt"`
- [x] **Validáció: üres body**
  - 📖 *Create a device type*, body: `{}`
  - ⌨️ `apij -X POST $API/devices -d '{}' | jq '.errors | keys'`
  - ✅ `422`; az `errors` kulcsai: `category`, `manufacturer`, `model`
- [x] **Érvénytelen enum**
  - 📖 *Create a device type*, body: `{"manufacturer":"X","model":"Y","category":"toaster"}`
  - ⌨️ `apij -X POST $API/devices -d '{"manufacturer":"X","model":"Y","category":"toaster"}' | jq .errors`
  - ✅ `422`, csak a `category` mezőre jön hiba
- [x] **Duplikált típus**
  - 📖 *Create a device type*, ugyanaz a body, mint a Létrehozásnál
  - ⌨️ `apij -X POST $API/devices -d '{"manufacturer":"Lenovo","model":"ThinkPad T14 Gen 5","category":"laptop"}' | jq .errors`
  - ✅ `422`, a hiba a `model` mezőn van (ugyanaz a gyártó + modell már létezik)
- [x] **Nem létező elem**
  - 📖 *Get a device type*, `device` = `999999`
  - ⌨️ `api $API/devices/999999`
  - ✅ `404`, a body pontosan `{"message":"Resource not found."}`, nincs benne `App\Models` vagy azonosító
- [x] **Nem numerikus id** (csak terminál)
  - ⌨️ `code $API/devices/abc`
  - ✅ `404`
- [x] **Törlés** (egy külön, szerződéshez nem rendelt eszközzel)
  - 📖 *Create a device type* `{"manufacturer":"Törlendő","model":"T1","category":"other"}`, majd *Delete a device type* az új id-val, végül *Get a device type* ugyanazzal az id-val
  - ⌨️
    ```bash
    TMP_ID=$(curl -s "${H[@]}" -X POST $API/devices -d '{"manufacturer":"Törlendő","model":"T1","category":"other"}' | jq -r .data.id)
    code -X DELETE $API/devices/$TMP_ID; code $API/devices/$TMP_ID
    ```
  - ✅ a törlés `204` (üres body), az utána következő lekérdezés `404`

## 3. Szerződés CRUD

- [x] **Létrehozás**
  - 📖 *Contracts → Create a contract*, body:
    ```json
    {"contract_number":"SZ-2026-001","partner_name":"Árvíztűrő Kft.","start_date":"2026-01-01","end_date":"2026-12-31","status":"active"}
    ```
  - ⌨️
    ```bash
    CONTRACT_ID=$(curl -s "${H[@]}" -X POST $API/contracts -d '{"contract_number":"SZ-2026-001","partner_name":"Árvíztűrő Kft.","start_date":"2026-01-01","end_date":"2026-12-31","status":"active"}' | jq -r .data.id); echo $CONTRACT_ID
    ```
  - ✅ `201`, a válaszban az `id`; a `partner_name` értéke pontosan `"Árvíztűrő Kft."` (az ékezetek épek)
- [x] ⚠️ **Lista eszközök nélkül** (task követelmény)
  - 📖 *List contracts* → nézd meg a válasz `data` elemeit: egyikben sincs `devices` kulcs
  - ⌨️ `apij $API/contracts | jq '[.data[] | has("devices")] | any'`
  - ✅ `200`, a curl kimenete `false`
- [x] ⚠️ **Egy elem eszközökkel** (task követelmény)
  - 📖 *Get a contract with its devices*, `contract` = az új id
  - ⌨️ `apij $API/contracts/$CONTRACT_ID | jq '.data | has("devices"), .devices'`
  - ✅ `200`; a `data`-ban van `devices` kulcs, és most üres tömb (`[]`)
- [x] **Részleges módosítás**
  - 📖 *Update a contract*, body: `{"status":"terminated"}`
  - ⌨️ `apij -X PATCH $API/contracts/$CONTRACT_ID -d '{"status":"terminated"}' | jq .data.status`
  - ✅ `200`, `"terminated"`; a többi mező változatlan
- [x] **Dátum validáció**
  - 📖 *Create a contract*, body: `{"contract_number":"SZ-X","partner_name":"A","start_date":"2026-05-01","end_date":"2026-01-01","status":"active"}`
  - ⌨️ `apij -X POST $API/contracts -d '{"contract_number":"SZ-X","partner_name":"A","start_date":"2026-05-01","end_date":"2026-01-01","status":"active"}' | jq .errors`
  - ✅ `422`, a hiba az `end_date` mezőn van
- [x] **Dátum validáció részleges módosításnál**
  - 📖 *Update a contract*, `contract` = az új id, body: `{"end_date":"2025-01-01"}`
  - ⌨️ `apij -X PATCH $API/contracts/$CONTRACT_ID -d '{"end_date":"2025-01-01"}' | jq .errors`
  - ✅ `422`, a hiba az `end_date` mezőn van (korábbi, mint a tárolt `start_date`: 2026-01-01)
- [x] **Duplikált szerződésszám**
  - 📖 *Create a contract*, body: `{"contract_number":"SZ-2026-001","partner_name":"B","start_date":"2026-01-01","status":"active"}`
  - ⌨️ `apij -X POST $API/contracts -d '{"contract_number":"SZ-2026-001","partner_name":"B","start_date":"2026-01-01","status":"active"}' | jq .errors`
  - ✅ `422`, a hiba a `contract_number` mezőn van
- [x] **Nem létező elem**
  - 📖 *Get a contract with its devices*, `contract` = `999999`
  - ⌨️ `api $API/contracts/999999`
  - ✅ `404`, `{"message":"Resource not found."}`

## 4. Eszköz hozzáadása és levétele szerződésről

Előfeltétel: a 2. és 3. szakaszban létrehozott `DEVICE_ID` és `CONTRACT_ID`.

- [x] **Hozzáadás**
  - 📖 *Contract devices → Add a device to a contract*, `contract` = a szerződés id, body: `{"device_id": <eszköz id>, "serial_number": "SN-0001"}`
  - ⌨️
    ```bash
    ASSIGNMENT_ID=$(curl -s "${H[@]}" -X POST $API/contracts/$CONTRACT_ID/devices -d "{\"device_id\":$DEVICE_ID,\"serial_number\":\"SN-0001\"}" | jq -r .data.id); echo $ASSIGNMENT_ID
    ```
  - ✅ `201`; a `data`-ban `id` (ez a hozzárendelés azonosítója), `serial_number: "SN-0001"`, `assigned_at` és a beágyazott `device`
- [x] **Ugyanaz a típus még egyszer, más gyári számmal**
  - 📖 ugyanott, body: `{"device_id": <eszköz id>, "serial_number": "SN-0002"}`
  - ⌨️ `code -X POST $API/contracts/$CONTRACT_ID/devices -d "{\"device_id\":$DEVICE_ID,\"serial_number\":\"SN-0002\"}"`
  - ✅ `201` (task: „eszközönként más gyári számmal")
- [x] **Ugyanaz a gyári szám még egyszer**
  - 📖 ugyanott, body: `{"device_id": <eszköz id>, "serial_number": "SN-0001"}`
  - ⌨️ `apij -X POST $API/contracts/$CONTRACT_ID/devices -d "{\"device_id\":$DEVICE_ID,\"serial_number\":\"SN-0001\"}" | jq .errors`
  - ✅ `422`, a hiba a `serial_number` mezőn van
- [ ] **Második szerződés létrehozása** a következő lépésekhez
  - 📖 *Create a contract*, body: `{"contract_number":"SZ-2026-002","partner_name":"Másik Bt.","start_date":"2026-02-01","status":"active"}`
  - ⌨️
    ```bash
    CONTRACT2_ID=$(curl -s "${H[@]}" -X POST $API/contracts -d '{"contract_number":"SZ-2026-002","partner_name":"Másik Bt.","start_date":"2026-02-01","status":"active"}' | jq -r .data.id); echo $CONTRACT2_ID
    ```
  - ✅ `201`
- [x] **Ugyanaz a gyári szám egy másik szerződésen**
  - 📖 *Add a device to a contract*, `contract` = a második szerződés id, body: `{"device_id": <eszköz id>, "serial_number": "SN-0001"}`
  - ⌨️ `apij -X POST $API/contracts/$CONTRACT2_ID/devices -d "{\"device_id\":$DEVICE_ID,\"serial_number\":\"SN-0001\"}" | jq .errors`
  - ✅ `422` a `serial_number` mezőn (egy fizikai eszköz egyszerre csak egy szerződésen lehet)
- [x] **Ugyanaz a típus egy másik szerződésen, új gyári számmal**
  - 📖 ugyanott, body: `{"device_id": <eszköz id>, "serial_number": "SN-0003"}`
  - ⌨️
    ```bash
    OTHER_ASSIGNMENT_ID=$(curl -s "${H[@]}" -X POST $API/contracts/$CONTRACT2_ID/devices -d "{\"device_id\":$DEVICE_ID,\"serial_number\":\"SN-0003\"}" | jq -r .data.id); echo $OTHER_ASSIGNMENT_ID
    ```
  - ✅ `201` (task: „egy eszköztípus több szerződésen is rajta lehet")
- [x] **Nem létező eszköz**
  - 📖 *Add a device to a contract*, `contract` = az első szerződés id, body: `{"device_id": 999999, "serial_number": "SN-9999"}`
  - ⌨️ `apij -X POST $API/contracts/$CONTRACT_ID/devices -d '{"device_id":999999,"serial_number":"SN-9999"}' | jq .errors`
  - ✅ `422`, a hiba a `device_id` mezőn van
- [x] **Nem létező szerződés**
  - 📖 *Add a device to a contract*, `contract` = `999999`, body: `{"device_id": <eszköz id>, "serial_number": "SN-9999"}`
  - ⌨️ `code -X POST $API/contracts/999999/devices -d "{\"device_id\":$DEVICE_ID,\"serial_number\":\"SN-9999\"}"`
  - ✅ `404`
- [x] ⚠️ **A szerződés lekérdezése most** (task követelmény)
  - 📖 *Get a contract with its devices*, `contract` = az első szerződés id
  - ⌨️ `apij $API/contracts/$CONTRACT_ID | jq '.data.devices | map({id, serial_number, device: .device.model})'`
  - ✅ 2 elem: `SN-0001` és `SN-0002`, mindkettőnél a `device` adatai (`ThinkPad T14 Gen 5`)
- [x] ⚠️ **A lista továbbra sem tartalmaz eszközöket**
  - ⌨️ `apij $API/contracts | jq '[.data[] | has("devices")] | any'`
  - ✅ `false`
- [x] **Levétel**
  - 📖 *Remove a device from a contract*, `contract` = az első szerződés id, `assignment` = az `SN-0001` hozzárendelés id-ja
  - ⌨️ `code -X DELETE $API/contracts/$CONTRACT_ID/devices/$ASSIGNMENT_ID`
  - ✅ `204`; utána a *Get a contract with its devices* már csak az `SN-0002`-t mutatja
- [x] **Idegen hozzárendelés levétele**
  - 📖 *Remove a device from a contract*, `contract` = az **első** szerződés id, `assignment` = a **második** szerződés `SN-0003` hozzárendelésének id-ja
  - ⌨️ `code -X DELETE $API/contracts/$CONTRACT_ID/devices/$OTHER_ASSIGNMENT_ID`
  - ✅ `404` (a hozzárendelés nem ehhez a szerződéshez tartozik)
- [x] **A levett gyári szám újra felhasználható**
  - 📖 *Add a device to a contract*, `contract` = az első szerződés id, body: `{"device_id": <eszköz id>, "serial_number": "SN-0001"}`
  - ⌨️ `code -X POST $API/contracts/$CONTRACT_ID/devices -d "{\"device_id\":$DEVICE_ID,\"serial_number\":\"SN-0001\"}"`
  - ✅ `201`

## 5. Törlési szabályok

- [x] **Szerződésen lévő eszköztípus törlése**
  - 📖 *Delete a device type*, `device` = az eszköz id
  - ⌨️ `api -X DELETE $API/devices/$DEVICE_ID`
  - ✅ `409`, `{"message":"The device cannot be deleted while it is assigned to a contract."}`
- [x] **Eszközzel rendelkező szerződés törlése**
  - 📖 *Delete a contract*, `contract` = az első szerződés id
  - ⌨️ `api -X DELETE $API/contracts/$CONTRACT_ID`
  - ✅ `409`, `{"message":"The contract cannot be deleted while it has devices assigned."}`
- [x] **Törlés az eszközök levétele után**
  - 📖 az első szerződés lekérdezéséből olvasd ki a hozzárendelések `id`-ját, mindegyikre *Remove a device from a contract*, majd *Delete a contract*
  - ⌨️
    ```bash
    for A in $(curl -s "${H[@]}" $API/contracts/$CONTRACT_ID | jq -r '.data.devices[].id'); do code -X DELETE $API/contracts/$CONTRACT_ID/devices/$A; done
    code -X DELETE $API/contracts/$CONTRACT_ID
    ```
  - ✅ minden levétel `204`, a szerződés törlése is `204`
- [x] **Az eszköztípus törlése, amikor már sehol nincs**
  - 📖 a második szerződésről vedd le az `SN-0003`-at, majd *Delete a device type*
  - ⌨️
    ```bash
    code -X DELETE $API/contracts/$CONTRACT2_ID/devices/$OTHER_ASSIGNMENT_ID
    code -X DELETE $API/devices/$DEVICE_ID
    ```
  - ✅ mindkettő `204`

## 6. Egységes hibakezelés

Ezek a pontok csak terminálból tesztelhetők, mert a felület mindig küld `Accept` fejlécet, és csak a dokumentált metódusokat kínálja.

- [x] **Azonos szerkezet:** a fenti hibaválaszok mind `{"message": ...}` alakúak, a 422-esek emellett `errors`-t is tartalmaznak
  - ⌨️ `for u in devices/999999 nincs-ilyen; do apij $API/$u | jq -c 'keys'; done; apij -X POST $API/devices -d '{}' | jq -c 'keys'`
  - ✅ `["message"]`, `["message"]`, `["errors","message"]`
- [x] **Accept fejléc nélkül is JSON**
  - ⌨️ `curl -s -i $API/devices/999999 | grep -iE "^HTTP|^content-type|message"`
  - ✅ `404`, `Content-Type: application/json`, a body JSON (nem HTML)
- [x] **Ismeretlen útvonal**
  - ⌨️ `api $API/nincs-ilyen`
  - ✅ `404`, `{"message":"Resource not found."}`
- [x] **Rossz metódus**
  - ⌨️ `api -X DELETE $API/devices`
  - ✅ `405`, JSON `message`
- [x] **Nincs belső információ**
  - ⌨️ `{ for u in devices/999999 nincs-ilyen; do curl -s "${H[@]}" $API/$u; done; curl -s "${H[@]}" -X DELETE $API/devices; curl -s -H "Accept: application/json" -X POST -d "model=x" $API/devices; } | grep -cE "trace|exception|App\\\\|\.php"`
  - ✅ `0`: a 404-es, a 405-ös és a 415-ös válaszban sincs trace, kivételnév, osztálynév vagy fájlnév. A gyári `.env`-ben `APP_DEBUG=true`, a kliens hibái (4xx) mégis tiszták; trace csak az 500-as szerverhibákban lehet debug módban (SECURITY_REVIEW F-20).

## 7. API forgalom naplózása (adatbázis)

Csak terminálból. A MySQL jelszót a konténer a saját környezetéből veszi, nem kell beírni.

- [x] **Az utolsó kérések a naplóban**
  - ⌨️
    ```bash
    docker compose exec mysql sh -c 'MYSQL_PWD="$MYSQL_ROOT_PASSWORD" mysql -u root device_registry -e "SELECT id, method, url, status_code, duration_ms, created_at FROM api_logs ORDER BY id DESC LIMIT 20"'
    ```
  - ✅ a fenti lépések sorban látszanak: `GET`/`POST`/`PUT`/`PATCH`/`DELETE`, a státuszok között `200`, `201`, `204`, `404`, `405`, `409`, `422`
- [x] **Request és response body mentése**
  - ⌨️
    ```bash
    docker compose exec mysql sh -c 'MYSQL_PWD="$MYSQL_ROOT_PASSWORD" mysql -u root device_registry -e "SELECT request_body, response_body FROM api_logs WHERE method=\"POST\" ORDER BY id DESC LIMIT 1\G"'
    ```
  - ✅ a `request_body` a küldött JSON, a `response_body` a kapott válasz
- [x] **Érzékeny fejléc maszkolása**
  - ⌨️
    ```bash
    curl -s -o /dev/null "${H[@]}" -H "Authorization: Bearer titok123" $API/devices
    docker compose exec mysql sh -c 'MYSQL_PWD="$MYSQL_ROOT_PASSWORD" mysql -u root device_registry -e "SELECT request_headers FROM api_logs ORDER BY id DESC LIMIT 1\G"' | grep -io "authorization[^,]*"
    ```
  - ✅ az `authorization` értéke `***`, a `titok123` sehol nem szerepel
- [x] **A dokumentáció forgalma nincs naplózva**
  - ⌨️
    ```bash
    docker compose exec mysql sh -c 'MYSQL_PWD="$MYSQL_ROOT_PASSWORD" mysql -u root device_registry -e "SELECT COUNT(*) FROM api_logs WHERE url LIKE \"%/docs/%\""'
    ```
  - ✅ `0`

## 8. Standardek és automata tesztek

- [x] `make qa` → minden zöld
- [x] `make coverage` → 100%
- [x] `docker compose exec app composer dump-autoload --strict-psr` → nincs figyelmeztetés (PSR-4)
- [x] `docker compose exec app vendor/bin/phpcs` → 0 hiba (PSR-12)
- [x] A GitHubon a CI zöld a `main` branchen
- [x] Szúrópróba: nyiss meg 3 véletlenszerű fájlt az `app/`-ból. Van bennük `declare(strict_types=1)`, a controller vékony, nincs benne üzleti logika.

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
