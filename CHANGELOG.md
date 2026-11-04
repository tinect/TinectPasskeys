# Changelog

## Passkey-Setup-Modal nach Login

Nach der Anmeldung mit E-Mail und Passwort wird auf der Account-Übersichtsseite geprüft, ob für das aktuelle Gerät bereits ein Passkey eingerichtet wurde. Ist das nicht der Fall, erscheint ein Modal-Dialog, der den Kunden zur Passkey-Registrierung einlädt.

### Funktionsweise

- `AccountProfilePageSubscriber` abonniert zusätzlich `AccountOverviewPageLoadedEvent` und setzt `showSetupModal` in der `tinectPasskeys` Page-Extension. Das Flag ist nur `true`, wenn kein Geräte-Cookie vorhanden ist.
- Das Template `storefront/page/account/index.html.twig` erweitert die Account-Übersicht und rendert ein Bootstrap-Modal mit dem vollständigen Passkey-Registrierungsformular, wenn `showSetupModal` gesetzt ist.
- Das Modal öffnet automatisch mit `backdrop: static`, damit es nicht durch Klick außerhalb geschlossen werden kann.

### Geräte-Cookie `tinect_passkey_device`

- Wird nach erfolgreicher Passkey-Registrierung auf `1` gesetzt (1 Jahr Laufzeit) — via `tinect-passkey-register.plugin.js`.
- Wird ebenfalls gesetzt, wenn der Kunde im Modal auf „Jetzt nicht" klickt.
- Solange der Cookie vorhanden ist, wird das Modal auf diesem Gerät nicht mehr angezeigt — unabhängig davon, ob auf anderen Geräten Passkeys existieren.
- Wird der Cookie gelöscht (z. B. Browser-Daten löschen), erscheint das Modal beim nächsten Login erneut.

### Geänderte / neue Dateien

- `src/Subscriber/AccountProfilePageSubscriber.php` — `AccountOverviewPageLoadedEvent`-Subscription ergänzt, Cookie-Prüfung, `showSetupModal`-Flag
- `src/Resources/views/storefront/page/account/index.html.twig` — neu erstellt
- `src/Resources/app/storefront/src/plugin/tinect-passkey-register.plugin.js` — setzt Geräte-Cookie nach erfolgreicher Registrierung
- `src/Resources/snippet/messages.de-DE.json` — `tinect-passkeys.setup.title/description/skip` ergänzt
- `src/Resources/snippet/messages.en-GB.json` — `tinect-passkeys.setup.title/description/skip` ergänzt
- `src/Resources/snippet/messages.fr-FR.json` — `tinect-passkeys.setup.title/description/skip` ergänzt
- `src/Resources/snippet/messages.nl-NL.json` — `tinect-passkeys.setup.title/description/skip` ergänzt

---

## Security Review — Findings & Hardening

### Findings (behoben)

#### Medium

**Open Redirect über unkontrollierte `redirectParameters`**
`src/Controller/PasskeyController.php` · `getRedirectUrl()`

`redirectParameters` wurden aus dem Request-Body per `json_decode()` deserialisiert und ohne Validierung an `generateUrl()` übergeben. Ein Angreifer konnte beliebige Parameter an beliebige `frontend.*`-Routen übergeben.

_Fix:_ `redirectParameters` vollständig entfernt. `redirectTo` wird gegen eine feste Whitelist von 8 bekannten, sicheren Routen ohne Parameter geprüft. Die Routen müssen ggf. angepasst bzw. erweitert werden!

---

**Potenzielle XSS durch `|raw`-Filter mit Benutzerdaten**
`src/Resources/views/storefront/page/account/profile/index.html.twig`

Der Passkey-Name wurde per `|trans({ '%name%': passkey.name|e })|raw` in den Lösch-Bestätigungstext interpoliert. Der `|raw`-Filter hebt Twigs Auto-Escaping auf; ein manipulierter Passkey-Name hätte HTML einschleusen können.

_Fix:_ `|raw` durch `|sw_sanitize` ersetzt. Der `|e`-Filter auf dem Parameter ist damit ebenfalls nicht mehr nötig.

---

#### Low

**Serializer als nullable Instanzvariable gecacht (nicht thread-safe)**
`src/Service/WebauthnService.php`

Der Webauthn-Serializer wurde lazy als `private ?SerializerInterface $serializer = null` gecacht. Unter Swoole/RoadRunner mit shared state zwischen Requests hätte dies zu einem Race Condition führen können.

_Fix:_ Serializer wird als `private readonly SerializerInterface $serializer` im Konstruktor initialisiert.

---

**Statischer Placeholder-Hash nicht thread-safe**
`src/Decorator/PasswordLoginRouteDecorator.php`

Der Timing-Attack-Schutz-Hash wurde als `private static ?string` mit lazy `??=`-Initialisierung implementiert. Unter Swoole/RoadRunner hätte dies zu einem Race Condition bei der ersten Initialisierung führen können (mehrere Hashes werden generiert).

_Fix:_ Statische Eigenschaft und Hilfsmethode entfernt. Hash wird als `private readonly string $placeholderPasswordHash` im Konstruktor per `password_hash(bin2hex(random_bytes(16)), PASSWORD_BCRYPT, ['cost' => 12])` generiert.

---

**Hardcodierter bcrypt-Hash im Quellcode**
`src/Decorator/PasswordLoginRouteDecorator.php`

Der Placeholder-Hash `$2y$12$PVcA5R6ri9kS...` war als Konstante im Quellcode hinterlegt. Automatische Secret-Scanner meldeten dies als Credential-Leak.

_Fix:_ Konstante entfernt, Hash wird zur Laufzeit im Konstruktor zufällig generiert (siehe oben).

---

**`DROP TABLE` ohne Transaktionsschutz beim Uninstall**
`src/TinectPasskeys.php` · `uninstall()`

Die `DROP TABLE`-Statements und die nachfolgenden `DELETE`-Statements wurden ohne Transaktion ausgeführt. Ein Fehler nach dem `DROP TABLE` hätte verwaiste Mail-Templates und Flows in der Datenbank hinterlassen.

_Fix:_ Gesamte `uninstall()`-Logik in `$connection->transactional()` gewrappt.

---

**Race Condition zwischen Hash-Lookup und Password-Reset**
`src/Decorator/ResetPasswordRouteDecorator.php` · `resetPassword()`

Die `customerId` wurde aus `customer_recovery` vor dem Aufruf von `decorated->resetPassword()` gelesen. Falls der Reset fehlschlug, wurde trotzdem versucht, das Password-Login zu aktivieren.

_Fix:_ Kommentar ergänzt, der explizit dokumentiert, dass `enable()` nur nach einem erfolgreichen Reset aufgerufen wird, da `decorated->resetPassword()` bei Fehler eine Exception wirft.

---

**Veraltetes `redirectParameters`-Feld wird noch gesendet**
`src/Resources/app/storefront/src/plugin/tinect-passkey-login.plugin.js`

Nach dem serverseitigen Open-Redirect-Fix wurde `redirectParameters` vom Server ignoriert, aber das JS-Plugin sendete das Feld weiterhin mit — toter Code mit unnötigem Netzwerkverkehr.

_Fix:_ `redirectParameters` aus dem `postForm()`-Aufruf in `_login()` entfernt.

---

**Fehlermeldung vom Server wird ungefiltert als `textContent` gesetzt**
`src/Resources/app/storefront/src/helper/webauthn.helper.js`

`json.message` vom Server wurde direkt als `error.serverMessage` gesetzt und in den Plugins als `textContent` ausgegeben. Die Verwendung von `textContent` verhindert XSS korrekt, aber die Invariante war nicht dokumentiert.

_Fix:_ Kommentar ergänzt, der `textContent`-only-Nutzung als Pflicht dokumentiert.

---

#### Info

**Challenge-Ablaufzeit basiert auf server-seitigem `time()`**
`src/Service/WebauthnService.php` · `popOptions()`

Die serverseitige Challenge-Ablaufprüfung und der Browser-Timeout sind beide auf 300 Sekunden gesetzt. Bei NTP-Uhrzeitabweichungen zwischen Prozessen könnte eine Challenge kurz vor Ablauf serverseitig als abgelaufen gelten.

_Fix:_ Dokumentiert als bekanntes, akzeptiertes Verhalten. Das Risiko ist minimal, da der Unterschied nur wenige Sekunden beträgt und die Challenge ohnehin single-use ist.

---

### Härtungsmaßnahmen (umgesetzt)

**1. Credential-Payload-Größe begrenzt**
`src/Controller/PasskeyController.php`

Ohne Größenbeschränkung konnten beliebig große JSON-Payloads an den Webauthn-Deserializer übergeben werden.

_Umsetzung:_ Konstante `MAX_CREDENTIAL_SIZE = 65536` (64 KB) eingeführt. Prüfung in `login()`, `register()` und `confirmWithPasskey()` vor dem Deserializer-Aufruf.

---

**2. `passkey.name` explizit escaped**
`src/Resources/views/storefront/page/account/profile/index.html.twig`

`{{ passkey.name }}` verließ sich auf Twigs implizites Auto-Escaping, was bei zukünftigen Erweiterungen mit `|raw` versehentlich umgangen werden könnte.

_Umsetzung:_ `{{ passkey.name|e }}` — explizites Escaping macht die Absicht klar.

---

**3. `redirectUrl` clientseitig validiert**
`src/Resources/app/storefront/src/plugin/tinect-passkey-login.plugin.js`

Die vom Server zurückgegebene `redirectUrl` wurde ohne Prüfung an `window.location.assign()` übergeben. Defense in Depth erfordert eine clientseitige Absicherung.

_Umsetzung:_ Prüfung auf `typeof redirectUrl !== 'string'` und `/^javascript:/i`-Schema vor dem Aufruf von `window.location.assign()`. Bei ungültiger URL wird auf `/` weitergeleitet.

---

**4. Zwei DB-Abfragen im Profil-Subscriber zu einer zusammengefasst**
`src/Service/PasskeyRepository.php` · `src/Subscriber/AccountProfilePageSubscriber.php`

`findByCustomerId()` und `isPasswordLoginDisabled()` wurden als zwei separate Abfragen ausgeführt. Zwischen den Abfragen konnte sich der Zustand ändern (TOCTOU).

_Umsetzung:_ Neue Methode `findByCustomerIdWithSettings()` in `PasskeyRepository` eingeführt, die beide Werte mit demselben Parameter-Binding abruft. `AccountProfilePageSubscriber` auf die neue Methode umgestellt.

---

**5. User-Agent-Länge in `DeviceDescriber` begrenzt**
`src/Service/DeviceDescriber.php`

Der `User-Agent`-Header wurde ohne Längenbeschränkung verarbeitet und in Security-Mails eingebettet. Ein Angreifer konnte einen sehr langen oder speziell formatierten User-Agent senden.

_Umsetzung:_ Konstante `MAX_USER_AGENT_LENGTH = 512` eingeführt. Der User-Agent wird per `mb_substr()` auf 512 Zeichen gekürzt, bevor er verarbeitet wird.

---

**6. Betreiber-Hinweis zu `session.cookie_samesite` in README ergänzt**
`README.md`

Die WebAuthn-Challenge wird in der PHP-Session gespeichert. Ohne `SameSite`-Schutz des Session-Cookies ist die Challenge anfällig für Cross-Site-Request-Forgery.

_Umsetzung:_ Hinweis in der README ergänzt, dass `session.cookie_samesite` auf `Strict` oder `Lax` gesetzt werden sollte.

---

**7. DB-Schema: `name`-Spalte bereits als `VARCHAR(255)` definiert**
`src/Migration/Migration1793318400CreatePasskeyTable.php`

Geprüft: Die `name`-Spalte ist bereits als `VARCHAR(255) NOT NULL` im Schema definiert und erzwingt die Längenbeschränkung auf Datenbankebene als zweite Verteidigungslinie zur serverseitigen `mb_substr()`-Kürzung.

_Ergebnis:_ Kein Handlungsbedarf.
