# Deployment und Go-live

**Sprache:** [English](deployment.md) · Deutsch

**Stand:** 11. September 2026 · **Nino-Version:** 1.2.0-beta

Dieses Handbuch führt eine fertig entwickelte Nino-Webseite in den produktiven Betrieb. Falls du stattdessen ein frisches Projekt einrichten möchtest, beginne mit [Erste Schritte](getting-started.de.md); technische Erweiterungen behandelt das [Entwickler-Handbuch](development.de.md).

**Weitere Links:**
[README](../README.de.md) · [Grundkonzepte](concepts.de.md) · [Entwickler-Handbuch](development.de.md) · [Rezepte](recipes/README.md) · [Erste Schritte](getting-started.de.md) · [Einrichtungsassistent](setup.de.md) · [`/_admin`-Workbench](_admin.de.md) · [Features](features.de.md) · [Deployment](deployment.de.md) · [Security Policy](https://github.com/dapeio/nino/blob/main/SECURITY.md) · [Changelog](https://github.com/dapeio/nino/blob/main/CHANGELOG.md)

## Voraussetzungen des Zielsystems
Nino benötigt weder Datenbankserver noch Composer-Installation auf dem Zielsystem. Das vereinfacht zwar das Deployment, macht die Dateien des Projekts aber umso wichtiger: Konfiguration und redaktionelle Daten liegen direkt im Dateisystem und müssen beim Übertragen, Sichern und Berechtigen vollständig berücksichtigt werden.

Das produktive System benötigt:

- PHP 8.4 oder neuer;
- die Erweiterungen `gd`, `mbstring`, `session` und `json` sowie die von `Phar` bereitgestellte Klasse `PharData`;
- einen Webserver, der öffentliche Dateien direkt ausliefert und dynamische Anfragen an Nino weitergibt;
- HTTPS für alle öffentlich erreichbaren Verwaltungsoberflächen;
- vor der Einrichtung eine beschreibbare Projektwurzel, damit der Einrichtungsassistent die noch fehlenden Projektverzeichnisse erzeugen kann.

Führe den Umgebungscheck des Assistenten bereits auf einer Umgebung aus, die dem späteren Hosting entspricht. Eine lokal erfolgreiche Installation beweist noch nicht, dass der Webhosting-Tarif dieselben PHP-Erweiterungen und Schreibrechte bereitstellt.

## Deployment-Modell wählen

Für kleine Projekte gibt es zwei sinnvolle Wege:

1. Die Webseite wird in einer geschützten Zielumgebung eingerichtet und erst danach öffentlich geschaltet.
2. Die Webseite wird lokal vollständig eingerichtet und anschließend als kompletter Projektstand übertragen.

In beiden Fällen muss der Einrichtungsassistent einen frischen Checkout einmal in einen gültigen Projektstand überführen. Überträgst du ein bereits eingerichtetes Projekt, gehören die erzeugten Projektverzeichnisse vollständig zum Deployment. Details zur Ersteinrichtung stehen unter [Erste Schritte](getting-started.de.md); der Assistent ist kein Updatewerkzeug für laufende Projekte.

**Sicherheit:** Wird die Einrichtung auf dem Zielsystem vorgenommen, muss `/_admin` bis zum Abschluss des Assistenten durch einen vorgeschalteten Zugangsschutz, ein internes Netz oder eine noch nicht öffentliche Umgebung geschützt sein – bis dahin hat er keinen eigenen.

## Webroot und Routing

Der Einstiegspunkt der öffentlichen Webseite ist `index.php`. Die Workbench `/_admin` besitzt einen eigenen (`_admin/index.php`), der bis zum Bestehen des Projekts den Einrichtungsassistenten ausliefert; `/_admin/recovery.php` ist ein dritter. Der Webserver muss vorhandene statische Dateien direkt ausliefern und alle übrigen Webseitenanfragen an Nino weiterreichen.

Für die lokale Entwicklung übernimmt `router.php` dieses Verhalten:

```bash
php -S 127.0.0.1:8000 router.php
```

Das ist ein Entwicklungsserver, keine Produktionskonfiguration.

### Apache

Die mitgelieferte `.htaccess` setzt diese grundlegenden Regeln, sofern der Server `AllowOverride` für das Projekt zulässt:

- Dateien mit einem führenden Punkt werden nicht direkt ausgeliefert.
- Verzeichnisse ohne Indexdatei zeigen keine Dateiliste.
- Der HTTP-Header `Authorization` erreicht PHP, damit der Login unter `/_admin` seine Basic-Zugangsdaten lesen kann. Apache verbirgt diesen Header normalerweise vor CGI-/FastCGI-Skripten; ist die mitgelieferte Datei nicht aktiv, setze `CGIPassAuth On` in der entsprechenden Server- oder Virtual-Host-Konfiguration. Die Direktive setzt Apache 2.4.13 oder neuer voraus - eine ältere Version beantwortet jede Anfrage mit einem 500, weil sie sie nicht kennt; dort gehört die Zeile entfernt und der Header über die Serverkonfiguration durchgereicht.

Eine separate Schutzregel liegt in `private/.htaccess` und sperrt dieses Verzeichnis vollständig. Sie ist die wichtigste: In `private/` liegen `config.php`, die Templates sowie die Texte und Elemente, aus denen sie rendern, die Daten deiner Besucher und die Stylesheet- und Skriptquellen, aus denen das Asset-Bundle gebaut wird. Ohne sie liefert ein Aufruf von `private/templates/page-home.tpl` den Template-Quelltext im Klartext aus.

Prüfe in der Hosting-Konfiguration zusätzlich, wie nicht vorhandene Pfade an `index.php` übergeben werden. Eine `.htaccess`, die vom Server ignoriert wird, entfaltet keinerlei Schutzwirkung – und bei `private/` ist das keine Härtungsfrage, sondern eine Offenlegung. Wenn du dich auf `.htaccess` nicht verlassen kannst, richte stattdessen `NINO_PRIVATE_DIR` in der `index.php` auf ein Verzeichnis außerhalb des Webroots; dann braucht es gar keine Serverregel.

### Nginx und andere Webserver

Übertrage dasselbe Verhalten ausdrücklich in die Serverkonfiguration:

- vorhandene öffentliche Assets direkt ausliefern;
- normale Webseitenrouten an `index.php` weitergeben;
- `/_admin` an `_admin/index.php` routen und `/_admin/recovery.php` seiner eigenen Datei überlassen;
- Zugriffe auf Dotfiles und Dot-Verzeichnisse verweigern;
- **`private/` vollständig sperren** – es wird nie von einem Browser angefragt, sondern nur von PHP gelesen;
- **`app/` und `features/` vollständig sperren** – die eigenen Klassen des Projekts und die installierten Features sind serverseitiger Quelltext, den nie ein Browser anfragt; beide bringen für Apache eine eigene `.htaccess` mit;
- **`_admin/install/library/` vollständig sperren** – es ist das, woraus der Assistent ein Projekt kopiert, serverseitige Quelle ohne irgendetwas Öffentliches darin; dasselbe gilt für die Section-Presets unter `features/Templates/library/`, wo ein Projekt sie liegen hat, das den Template-Baukasten installiert hat;
- Verzeichnisauflistung deaktivieren;
- den HTTP-Header `Authorization` an PHP weitergeben. Bei nginx/PHP-FPM ist dafür normalerweise `fastcgi_param HTTP_AUTHORIZATION $http_authorization;` in der PHP-Location erforderlich;
- PHP-Quell- und Datendateien nicht als Text ausliefern.

Für nginx ist jeder der drei gesperrten Bäume ein einzelner Block:

```nginx
location ^~ /private/  { deny all; return 404; }
location ^~ /app/      { deny all; return 404; }
location ^~ /features/ { deny all; return 404; }
```

Oder du umgehst die Frage für `private/`, indem du das Verzeichnis mit `NINO_PRIVATE_DIR` aus dem Webroot verlegst; `NINO_APP_DIR` und `NINO_FEATURES_DIR` tun dasselbe für die beiden anderen.

Eine allgemeine Beispielkonfiguration kann die Pfade und PHP-FPM-Einstellungen eines konkreten Hostings nicht zuverlässig erraten. Prüfe deshalb nach dem Einrichten sowohl gewünschte Routen als auch bewusst verbotene Direktzugriffe.

## Schreibrechte

Vor der Ersteinrichtung muss PHP in der Projektwurzel Verzeichnisse und Dateien anlegen dürfen. Die noch fehlenden Projektpfade werden vom Assistenten beziehungsweise bei Bedarf vom Kernel erzeugt und sind keine manuell anzulegende Voraussetzung.

Im laufenden Betrieb benötigt Nino Schreibrechte nur für tatsächlich veränderliche Inhalte. Dazu gehören je nach Nutzung `private/config.php`, `private/text/`, `private/elements/`, `private/data/`, `private/.logs/`, `private/.backups/`, `private/assets/`, `public/images/` und `public/.cache/`. Das Templates-Panel benötigt zusätzlich `private/templates/`; es kann native Textschlüssel, Elementtypen und Bildplatz-Definitionen in der Konfiguration anlegen. Die Installer-Library unter `_admin/install/library/` selbst bleibt schreibgeschützt; `_admin/.cache/` dagegen braucht Schreibrechte, weil die Workbench ihre Bundles dort baut – das eine Verzeichnis im Werkzeugordner. Projektwurzel und PHP-Quellcode können ansonsten nach der Installation schreibgeschützt bleiben. Das Installieren eines Features aus dem Katalog im Panel Features ist die eine Ausnahme: Es schreibt `features/` und legt den Download unterhalb von `private/data/.features/` zwischen. Ohne Schreibrecht auf `features/` bietet das Panel stattdessen das Archiv zum Kopieren von Hand an, das Verzeichnis kann also schreibgeschützt bleiben, wo Features mit dem Projekt ausgeliefert werden.

Vergib diese Rechte an den Benutzer, unter dem PHP ausgeführt wird. Weltweit beschreibbare Rechte wie `0777` sind keine geeignete Dauerlösung. Nach dem Deployment sollten Kernel und übriger PHP-Quellcode nicht allgemein beschreibbar sein.

## Konfiguration und Application-Quellcode außerhalb des Webroots

Standardmäßig liegt der vollständige private Verzeichnisbaum einschließlich `config.php` in `private/`. `NINO_PRIVATE_DIR` verschiebt diesen vollständigen Baum in ein existierendes, beschreibbares Verzeichnis außerhalb des Webroots. Mit `NINO_CONFIG_DIR` kann zusätzlich nur `config.php` auf ein anderes existierendes, beschreibbares Verzeichnis zeigen.

```php
define('NINO_PRIVATE_DIR', '/pfad/ausserhalb/des/webroots/nino-private');
// Oder, um nur config.php zu verschieben:
// define('NINO_CONFIG_DIR', '/pfad/ausserhalb/des/webroots');
```

Trage eine der Definitionen vor dem Laden von `_nino/Nino.php` ein – in jedem Einstiegspunkt. `index.php`, `_admin/index.php` und `_admin/recovery.php` starten den Kernel jeweils für sich, und eine nur in einer davon definierte Konstante gilt für die anderen nicht: Steht sie allein in der `index.php` der Site, sucht der Workbench die `config.php` unter dem Standardpfad, findet keine und bietet auf einer laufenden Site den Setup-Assistenten an. Alle drei Dateien tragen die Zeilen auskommentiert. Ein ungültiger ausdrücklich gesetzter Pfad bricht den Start ab; Nino fällt nie still auf ein Verzeichnis im Projekt zurück. Wird der vollständige Baum mit `NINO_PRIVATE_DIR` verschoben, muss `private/` nicht mehr durch den Webserver geschützt werden. Wird nur `config.php` verschoben, dürfen die übrigen privaten Dateien weiterhin nicht direkt ausgeliefert werden.

Projekteigene PHP-Klassen werden davon getrennt standardmäßig aus `app/`
geladen, die installierten Features aus `features/`. Mit `NINO_APP_DIR` und
`NINO_FEATURES_DIR` kann der Autoloader auf andere absolute
Quellcode-Verzeichnisse zeigen; auch diese Konstanten müssen vor dem Laden des
Kernels definiert werden – in jedem Einstiegspunkt, wie die beiden oben. Jede
ersetzt ihr Verzeichnis als Ganzes: Ein Projekt, das den Features-Root
woandershin zeigen lässt, nimmt seine Features mit, oder der Kernel
überspringt ein Modul, das er nicht mehr laden kann, ohne ein Wort:

```php
define('NINO_APP_DIR', '/pfad/ausserhalb/des/webroots/nino-app');
define('NINO_FEATURES_DIR', '/pfad/ausserhalb/des/webroots/nino-features');
```

Diese Quellcode-Overrides verschieben weder Konfiguration noch Laufzeitdaten
und benötigen im Produktivbetrieb keine Schreibrechte – die Einstellungen eines
Features liegen in der `config.php`, seine Daten unter `data/`. Eine
projekteigene Klasse wird ausschließlich im App-Root gesucht, die Klasse eines
Features im Features-Root – `_nino/` ist für keine von beiden ein zweiter
Fundort. Klassen im kerneigenen Namespace `Nino\`, Ninos eigene Module
eingeschlossen, werden weiterhin ausschließlich aus `_nino/` geladen.

## Einstellungen für den Produktivbetrieb

Prüfe in `config.php` beziehungsweise über das Config-Panel der Workbench mindestens folgende Schlüssel:

| Schlüssel | Produktiver Wert | Wirkung |
|---|---|---|
| `/nino/error/display` | `false` | unterdrückt technische Fehlerdetails im Browser |
| `/nino/error/log` | `true` | schreibt Fehler für die spätere Diagnose ins Log |
| `/nino/session/force-secure-cookie` | `true` bei TLS-Terminierung vor PHP | erzwingt sichere Session-Cookies hinter einem HTTPS-Proxy |
| `/nino/admin/backups` | nach Betriebsentscheidung | steuert die tägliche verschlüsselte Sicherung der Workbench |
| `/nino/admin/logs` | nach Betriebsentscheidung | steuert das Aktivitätsprotokoll der Workbench |
| `/nino/catalogue/url` | der Standard, oder `''`, wo nichts aus dem Katalog installiert werden soll | woher das Panel Features den Feature-Katalog lädt – nur auf Anforderung, nie von selbst; leer schaltet den Katalog ab |

Fehlermeldungen sollten im Browser keine Dateipfade, Konfigurationswerte oder Stacktraces offenlegen. Prüfe nach dem Umschalten, dass Fehler weiterhin in einem geschützten Log ankommen und für den Betreiber erreichbar bleiben.

## Die Workbench absichern

Vor dem Go-live müssen die Konten funktionieren und starke Passwörter haben:

- `/_admin` ist die eine Verwaltungsoberfläche – Entwickler und Redakteure melden sich mit eigenen Konten an. Ein Konto der Rolle **Developer** hält `/*`, eines der Rolle **Editor** nur die Inhalt-Berechtigungen; das [`/_admin`-Handbuch](_admin.de.md#anmeldung-konten-und-rollen) listet jede Berechtigung.
- `/_admin/recovery.php` fragt nach dem Recovery-Passwort aus dem letzten Schritt des Assistenten und bietet eine Wiederherstellung und ein Zurücksetzen eines Passworts. Bewahre dieses Passwort dort auf, wo die Passwörter der Entwicklerkonten nicht liegen.

Vergib Redaktionsrechte so eng wie praktisch möglich; die Konten, die der Assistent anlegt, sind Entwickler, und weitere Konten brauchen diese Reichweite meist nicht. Halte die Zahl der Entwicklerkonten klein.

HTTPS schützt nicht nur Anmeldedaten, sondern auch Sitzungs-Cookies und alle redaktionell übertragenen Inhalte. Leite HTTP-Anfragen dauerhaft auf HTTPS um und teste die Anmeldung nur über die endgültige öffentliche Adresse.

Zusätzlicher Webserver-Schutz für `/_admin` – etwa IP-Freigaben oder HTTP-Authentifizierung – kann bei passenden Betriebsbedingungen eine sinnvolle zweite Barriere bilden. Er ersetzt die Konten nicht. Der Template-Baukasten ist ein Feature, sein ganzes Verzeichnis kann also aus `features/` verschwinden – genau dafür ist er eines; die Workbench selbst bleibt, weil die Redaktion darin arbeitet.

## Der Assistent nach der Einrichtung

Schließe den Assistenten vollständig ab. Der letzte Schritt setzt das Recovery-Passwort und sperrt den Assistenten. Entferne anschließend das Verzeichnis `_admin/install/` aus der produktiven Auslieferung.

Damit verschwindet auch die Installer-Library unter `_admin/install/library/`. Das ist beabsichtigt: Die Library ist Einrichtungsmaterial, kein Laufzeitfeature. Alles, was sie kopiert hat, liegt längst im Projekt – das Theme als `assets/theme.css`, die beiden Frames als `templates/theme.header.tpl` und `templates/theme.footer.tpl` – und bleibt von Hand und, für die Frames, über das Templates-Panel bearbeitbar. Zur Laufzeit liest niemand die Library.

Die Reihenfolge ist wesentlich:

1. das oder die Entwicklerkonten im Schritt Accounts anlegen;
2. das Recovery-Passwort setzen und den Assistenten abschließen;
3. Frontend und Workbench mit einem Entwicklerkonto prüfen, dann die Redaktionskonten anlegen und prüfen, was sie sehen;
4. `_admin/install/` aus dem Produktivsystem entfernen.

Eine unvollständige Installation wird durch das Löschen ihres Assistenten nicht gültig.

## Backups und Wiederherstellung

Bei aktivierten Backups legt die Workbench bei der ersten angemeldeten Anfrage des Tages automatisch ein verschlüsseltes Backup an. Die täglichen Sicherungen rotieren über 14 Tage und liegen unter `private/.backups/`; die Archive werden mit AES-256-GCM verschlüsselt, der Schlüssel liegt unter `private/.auth/`.

Die Wiederherstellung erfolgt im Panel Backups oder – wenn kein Konto mehr funktioniert – auf `/_admin/recovery.php`. Vor dem Einspielen erzeugt Nino eine zusätzliche Sicherung des aktuellen Standes, damit eine versehentlich falsche Wiederherstellung nicht sofort den vorherigen Zustand vernichtet.

Diese Backups schützen vor vielen redaktionellen Fehlern, sind aber kein vollständiges Hosting-Backup. Eine belastbare Sicherungsstrategie kopiert zusätzlich regelmäßig das gesamte Projekt einschließlich Konfiguration, Texte, Elemente, Bilder und der zur Wiederherstellung notwendigen Schlüssel an einen getrennten Ort. Prüfe die Wiederherstellung, bevor sie in einem Notfall gebraucht wird.

## Tests vor dem Go-live

Führe die mitgelieferten Smoke-Tests mit derselben PHP-Hauptversion aus, die später produktiv läuft:

```bash
php tests/kernel-smoke.php
php tests/admin-smoke.php
php tests/admin-system-smoke.php
php tests/install-smoke.php
php tests/features-smoke.php
php tests/catalogue-smoke.php
for test in features/*/tests/*-smoke.php; do [ -e "$test" ] || continue; php "$test" || exit 1; done
for test in tests/*-js-smoke.js; do node "$test"; done
php tests/concurrency-smoke.php
```

Die Smoke-Tests ersetzen keinen projektspezifischen Abnahmetest. Prüfe zusätzlich im Browser:

- alle öffentlichen Routen und die Fehlerseite;
- jede aktive Sprache und die Sprachumschaltung;
- responsive Darstellung und verwendete Bilder;
- Formulare einschließlich Validierung, Versand und Fehlermeldungen;
- Anmeldung, Abmeldung und die Rechte eines Redaktionskontos in `/_admin`;
- den Zugang eines Entwicklerkontos zu den Panels von Struktur und System;
- Zugriff und unveränderten Round-Trip im Template-Baukasten, sofern das Feature installiert ist;
- Schreiben und erneutes Laden eines redaktionellen Inhalts;
- Verhalten hinter CDN, Proxy oder Cache, sofern eingesetzt.

## Verbotene Direktzugriffe testen

Ein erfolgreicher Aufruf der Startseite belegt noch nicht, dass sensible Dateien geschützt sind. Prüfe mit nicht angemeldeten Anfragen, dass insbesondere folgende Kategorien nicht als Quelltext oder Verzeichnisinhalt erreichbar sind:

- Dotfiles und Dot-Verzeichnisse;
- `config.php` und PHP-Datendateien;
- versteckte Log- und Backup-Verzeichnisse;
- interne Dateien aus `_admin/`, `app/` und `features/`, die nicht als öffentliche Assets vorgesehen sind – die Panel-Templates, die Section-Presets und die Install-Einheit eines Features darunter;
- jede Datei unter `_admin/install/library/`;
- `_admin/install/`, nachdem es entfernt wurde.

Die erwartete Antwort kann je nach Server `403` oder `404` sein. Entscheidend ist, dass weder Inhalt noch Verzeichnisliste ausgeliefert werden.

## Updates und Rollback

Behandle ein Nino-Update wie eine Änderung am konkreten Webseitenprojekt, nicht wie das blinde Aktualisieren eines austauschbaren CMS-Kerns.

1. Sichere den aktuellen produktiven Stand außerhalb des Webroots.
2. Übernimm die Änderung zunächst in eine Entwicklungs- oder Staging-Umgebung.
3. Lege projekteigene PHP-Klassen in `app/` (oder `NINO_APP_DIR`) ab und vergleiche nur bewusste Kernel-Anpassungen mit dem neuen Stand. `_nino/` kann dann vollständig ersetzt werden – Ninos optionale Module unter `_nino/Nino/Modules/` eingeschlossen, denn ein Projekt schaltet sie in `/nino/modules` ein oder aus, statt sie zu bearbeiten –, und `_admin/` ebenso: Die Workbench trägt keinen Projektzustand – die Konten liegen in der `config.php`, das Recovery-Geheimnis in `private/.auth/pw.php`. Ein Feature wird für sich aktualisiert: Drücke **Update** auf dem Tab Verfügbar des Panels Features der Workbench, oder ersetze sein Verzeichnis unter `features/` von Hand durch die neue Fassung und drücke **Update** auf dem Tab Aktiv oder Inaktiv, wo es gerade steht. Die Install-Einheit des Features ergänzt, was neu ist, und überschreibt nichts, was das Projekt hat, und das Feature migriert seine eigenen Daten, bevor die neue Version aufgezeichnet wird; siehe [Features](features.de.md#aktualisieren).
4. Führe Smoke-Tests und projektspezifische Abnahme aus.
5. Übertrage den geprüften Stand und behalte die vorherige Version für ein Rollback.

Für ein Update, das nicht unsichtbar ablaufen kann, schalte vorher die Wartung im System-Panel ein und danach wieder aus, sobald Schritt 4 auf dem produktiven Stand bestanden hat – ein angemeldetes Konto sieht die Website währenddessen ohnehin weiter, die Prüfung selbst braucht den Schalter also nicht zuerst aus.

Nino verwendet eine Projektstruktur: Private Dateien liegen in `private/`, für
den Browser bestimmte Dateien in `public/`, projekteigener PHP-Quellcode in
`app/` und installierte Features in `features/`. Alternative
Verzeichnisstrukturen werden während eines Requests nicht migriert.
`NINO_PRIVATE_DIR` kann den vollständigen privaten Baum verschieben,
`NINO_APP_DIR` den Application-Root des Projekts ersetzen und
`NINO_FEATURES_DIR` den Features-Root. Eine Klasse außerhalb von `Nino\` wird
im App-Root aufgelöst und nirgends sonst – `_nino/` hält den Kernel und nichts
Projekteigenes.

Nino befindet sich in der Beta-Phase. Sicherheitskorrekturen erscheinen auf `main`; eine getrennte LTS-Linie gibt es derzeit nicht. Plane Updates deshalb als aktive Projektpflege ein und prüfe `SECURITY.md` sowie den Changelog vor einer Aktualisierung.

## Go-live-Checkliste

- [ ] PHP-Version und Erweiterungen entsprechen den Anforderungen.
- [ ] Öffentliche Routen werden korrekt an Nino übergeben.
- [ ] Dotfiles, Dot-Verzeichnisse und PHP-Datendateien sind nicht direkt erreichbar.
- [ ] `app/` und `features/` werden nicht ausgeliefert — beide tragen eine eigene `.htaccess`; prüfe es mit einer Anfrage nach einer Datei eines installierten Features, z. B. `/features/Newsletter/install/templates/mail-header.tpl`, sobald das Newsletter-Feature des Katalogs an Ort und Stelle ist – ein Checkout bringt kein Feature mit, also muss eines da sein, nach dem sich fragen lässt.
- [ ] `private/` wird nicht ausgeliefert — die eigene `.htaccess` sperrt das Verzeichnis, und jede PHP-Datei darin trägt einen 403-Stub; prüfe, ob beides auf deinem Webserver greift, oder verlege das Verzeichnis mit `NINO_PRIVATE_DIR` aus dem Webroot. Die Templates und die Asset-Quellen sind kein PHP und haben nur die Serverregel.
- [ ] Verzeichnisauflistung ist deaktiviert.
- [ ] Der Einrichtungsassistent konnte die Projektverzeichnisse aus der beschreibbaren Projektwurzel selbst erzeugen — ein Checkout liefert weder `private/` noch `public/` mit, der erste Schritt des Assistenten prüft genau das.
- [ ] Schreibrechte sind nach der Einrichtung auf die benötigten Pfade begrenzt.
- [ ] Der Einrichtungsassistent wurde vollständig abgeschlossen und `_admin/install/` anschließend produktiv entfernt.
- [ ] Entwickler- und Redaktionskonten sind getestet, und das Recovery-Passwort ist sicher verwahrt.
- [ ] Der Template-Baukasten ist entweder aus `features/` entfernt oder bewusst behalten, und nur Entwicklerkonten erreichen ihn.
- [ ] Editor-Nutzer haben nur die benötigten Berechtigungen.
- [ ] HTTPS und sichere Session-Cookies funktionieren an der endgültigen Adresse.
- [ ] Fehleranzeige ist deaktiviert und Fehlerprotokollierung geprüft.
- [ ] Smoke-Tests und Browser-Abnahme sind erfolgreich.
- [ ] Backups laufen, liegen zusätzlich extern vor und lassen sich wiederherstellen.
- [ ] Jedes Feature, das die Seite braucht, ist im Panel Features aktiviert und zeigt kein ausstehendes Update; jedes, das sie nicht braucht, ist deaktiviert.
- [ ] Der vorherige Projektstand ist für ein Rollback verfügbar.

## Wie es weitergeht

- [Erste Schritte](getting-started.de.md) beschreibt die notwendige Ersteinrichtung.
- [`/_admin`-Workbench](_admin.de.md) erklärt jedes Panel, die Konten, Sicherungen und die Recovery-Seite.
- Der **Template-Baukasten** – Seitentemplates aus ganzen Abschnitten – ist ein Feature aus dem Katalog [dapeio/nino-features](https://github.com/dapeio/nino-features); sein [Handbuch](https://github.com/dapeio/nino-features/blob/main/features/Templates/docs/templates.de.md) liegt dort ebenfalls.
- [Grundkonzepte](concepts.de.md) erklärt die technische Struktur hinter dem deployten Projekt.
