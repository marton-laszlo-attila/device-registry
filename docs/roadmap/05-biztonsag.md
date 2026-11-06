# F5 – Biztonsági teszt

**Modell / effort:** Opus 5.5 / xhigh · **Előfeltétel:** F4 kész, CI zöld

## Cél

A teljes kódbázis biztonsági átvilágítása az OWASP API Security Top 10 (2023) szempontjai szerint, automatikus és kézi ellenőrzéssel. A talált hibák javítása tesztekkel együtt. Az eredményből egy rövid jelentés készül a repóba (`docs/SECURITY_REVIEW.md`).

## Indítás

Új terminálban futtasd. A parancs egy új, interaktív Claude Code munkamenetet nyit a megadott modellel és efforttal, és rögtön elküldi az indító promptot. A fájlszerkesztéseket automatikusan jóváhagyja (`acceptEdits`), a shell parancsokra továbbra is rákérdez.

```bash
cd ~/Documents/allaskereses/wd/device-registry
claude --model claude-opus-5-5 --effort xhigh --permission-mode acceptEdits -n "F5 Biztonsag" '
Olvasd el a docs/roadmap/README.md és a docs/roadmap/05-biztonsag.md fájlt, és hajtsd végre
az F5 fázist. Először futtasd a /security-review skillt a teljes kódbázisra, utána menj végig
a lenti ellenőrzőlistán. Minden találatnál: súlyosság, bizonyíték (fájl:sor vagy curl kimenet),
javítás + regressziós teszt. Ami szándékosan nincs megoldva (pl. nincs API auth), azt
indoklással írd bele a jelentésbe. A végén a "Kézi biztonsági ellenőrzés" részt hagyd nekem.'
```

## Automatikus ellenőrzések (Claude)

- [x] `/security-review` skill (2026-11-06): a skill csak az `origin/HEAD`-hez képesti diffet nézi, ami tiszta `main`-en üres, ezért a teljes kódbázist a skill módszerével kézzel néztem át, plusz egy független reviewer agenttel. Eredmény: [docs/SECURITY_REVIEW.md](../SECURITY_REVIEW.md), 18 találat, mind javítva.
- [x] `composer audit`: nincs ismert sérülékeny függőség
- [x] PHPStan + Pest `arch()->preset()->security()` zöld (nincs `eval`, `exec`, `shell_exec`, `md5` jelszóra, `unserialize` stb.)
- [x] Titkok: `git log -p | grep -iE "password|secret|key="`: nincs commitolt titok; a `.env` a `.gitignore`-ban; az `.env.example`-ben nincs valós érték; az `APP_KEY` nincs commitolva. A compose-ban volt `root`/`secret` helyi jelszó (F-13), ez most a gyökér `.env`-ből jön (véletlen érték, `make up` hozza létre).

## Ellenőrzőlista (OWASP API Top 10 alapján)

| # | Terület | Ellenőrzés | Elvárt |
|---|---|---|---|
| API1 | Objektum szintű jogosultság | Nincs felhasználói modell, ezért BOLA nem értelmezhető. A scoped binding megakadályozza, hogy idegen szerződés hozzárendelését módosítsák. | indoklás a jelentésben + teszt |
| API2 | Hitelesítés | Docs Basic Auth: `hash_equals`, fail closed, throttle, `WWW-Authenticate`. Az API szándékosan nyitott (task szerint). | teszt + javaslat: éles használathoz Sanctum token |
| API3 | Tulajdonság szintű jogosultság / mass assignment | `$fillable` explicit, a service csak `validated()` adatot kap; extra mező (`id`, `created_at`) a bodyban figyelmen kívül marad | teszt |
| API4 | Erőforrás-fogyasztás | rate limit (429), `per_page` max. 100, nginx `client_max_body_size`, a log body csonkolva | teszt + curl |
| API5 | Funkció szintű jogosultság | nincs admin végpont; a docs védett; a `/up` nem árul el semmit | ellenőrzés |
| API6 | Üzleti folyamat visszaélés | gyári szám ütközés versenyhelyzetben → DB unique + 409 | teszt |
| API7 | SSRF | nincs kimenő HTTP hívás | indoklás |
| API8 | Hibás konfiguráció | `APP_DEBUG=false` élesben; a hibaválaszban nincs stack trace vagy osztálynév; `server_tokens off`; `X-Powered-By` eltávolítva (`expose_php=Off`); `X-Content-Type-Options: nosniff` (egy sor az nginx configban) | curl + teszt |
| API9 | Nyilvántartás | `docs/openapi.json` naprakész, csak `v1` létezik | ellenőrzés |
| API10 | Harmadik fél API | nincs | indoklás |
| – | Injection | minden query Eloquent/binding; `orderBy` és szűrés nem fogad nyers user inputot; a `serial_number` regex | teszt SQLi payloadokkal |
| – | Naplózás | az `api_logs`-ban nincs `Authorization`, jelszó vagy cookie; a log nem írható kívülről; nem nő korlátlanul | teszt |
| – | Docker | az app nem rootként fut; a MySQL nincs publikálva; `MYSQL_ROOT_PASSWORD` nincs hardcode-olva a compose-ban (env-ből); image verziók rögzítve | `docker compose exec app id`, `docker compose ps` |

## Kézi biztonsági ellenőrzés (te)

Futtasd, és pipáld ki. Ahol eltérés van, jelezd Claude-nak.

- [ ] `curl -i http://localhost:8080/docs/api` → `401` és `WWW-Authenticate: Basic`
- [ ] `curl -i -u admin:rossz http://localhost:8080/docs/api.json` → `401`
- [ ] 11 rossz próbálkozás egy percen belül → `429`
  ```bash
  for i in $(seq 1 11); do curl -s -o /dev/null -w "%{http_code}\n" -u x:y http://localhost:8080/docs/api.json; done
  ```
- [ ] `curl -i http://localhost:8080/api/v1/devices/999999` → a válaszban nincs `App\Models`, `trace`, `file`
- [ ] SQLi kísérlet: `curl -i "http://localhost:8080/api/v1/devices?per_page=1%20OR%201=1"` → 200 vagy 422, nem 500
- [ ] Hibás JSON: `curl -i -X POST -H "Content-Type: application/json" -d '{"manufacturer":' http://localhost:8080/api/v1/devices` → 4xx JSON hibaüzenet, nem 500
- [ ] Nagy body (2 MB): `head -c 2000000 /dev/zero | tr '\0' 'a' | curl -i -X POST -H "Content-Type: application/json" --data-binary @- http://localhost:8080/api/v1/devices` → 413, nem 500
- [ ] Rate limit: 61 kérés egy percen belül → az utolsó `429` + `Retry-After`
- [ ] `curl -I http://localhost:8080/api/v1/devices` fejlécei: nincs `X-Powered-By`, nincs nginx verzió, van `X-Content-Type-Options: nosniff`
- [ ] `docker compose exec mysql mysql -u root -p -e "SELECT request_headers, request_body FROM device_registry.api_logs ORDER BY id DESC LIMIT 5"` → nincs olvasható jelszó vagy Authorization (a root jelszó a gyökér `.env` `MYSQL_ROOT_PASSWORD` sorában van)
- [ ] `docker compose exec app id` → nem `uid=0`
- [ ] `git status --ignored` → a `.env` ignorálva, nincs commitolva

## Kimenet

- [x] `docs/SECURITY_REVIEW.md`: dátum, hatókör, módszer, találatok táblázata (súlyosság, státusz), szándékos döntések indoklással, javaslatok éles használathoz (API auth, HTTPS, log anonimizálás, WAF)
- [x] Minden javított találathoz regressziós teszt (az alkalmazásszintűeknél Pest, és mindegyik elbukik a javítás nélkül, 13/13; a nginx/Docker javításoknál curl és `docker compose` ellenőrzés, ezek a kézi listában is szerepelnek)
- [x] `make qa` zöld

## Commit(ok)

- `fix(security): …` (találatonként)
- `docs: add security review report`
