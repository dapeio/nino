# Deployment und Go-live

**Sprache:** [English](deployment.md) · Deutsch

**Stand:** 5. September 2026 · **Nino-Version:** 0.13.0-beta

Dieses Handbuch führt eine fertig entwickelte Nino-Webseite in den produktiven Betrieb. Falls du stattdessen ein frisches Projekt einrichten möchtest, beginne mit [Erste Schritte](getting-started.de.md); technische Erweiterungen behandelt das [Entwickler-Handbuch](development.de.md).

**Weitere Links:**
[README](../README.de.md) · [Grundkonzepte](concepts.de.md) · [Entwickler-Handbuch](development.de.md) · [Erste Schritte](getting-started.de.md) · [Einrichtungsassistent](setup.de.md) · [`/_admin`-Workbench](_admin.de.md) · [Templates-Panel](templates.de.md) · [Design-Panel](appearance.de.md) · [Deployment](deployment.de.md) · [Security Policy](https://github.com/dapeio/nino/blob/main/SECURITY.md) · [Changelog](https://github.com/dapeio/nino/blob/main/CHANGELOG.md)

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

Die mitgelieferte `.htaccess` setzt zwei grundlegende Schutzregeln, sofern der Server `AllowOverride` für das Projekt zulässt:

- Dateien mit einem führenden Punkt werden nicht direkt ausgeliefert.
- Verzeichnisse ohne Indexdatei zeigen keine Dateiliste.

Eine dritte Regel liegt in `private/.htaccess` und sperrt dieses Verzeichnis vollständig. Sie ist die wichtigste: In `private/` liegen `config.php`, die Templates sowie die Texte und Elemente, aus denen sie rendern, die Daten deiner Besucher und die Stylesheet- und Skriptquellen, aus denen das Asset-Bundle gebaut wird. Ohne sie liefert ein Aufruf von `private/templates/page-home.tpl` den Template-Quelltext im Klartext aus.

Prüfe in der Hosting-Konfiguration zusätzlich, wie nicht vorhandene Pfade an `index.php` übergeben werden. Eine `.htaccess`, die vom Server ignoriert wird, entfaltet keinerlei Schutzwirkung – und bei `private/` ist das keine Härtungsfrage, sondern eine Offenlegung. Wenn du dich auf `.htaccess` nicht verlassen kannst, richte stattdessen `NINO_PRIVATE_DIR` in der `index.php` auf ein Verzeichnis außerhalb des Webroots; dann braucht es gar keine Serverregel.

### Nginx und andere Webserver

Übertrage dasselbe Verhalten ausdrücklich in die Serverkonfiguration:

- vorhandene öffentliche Assets direkt ausliefern;
- normale Webseitenrouten an `index.php` weitergeben;
- `/_admin` an `_admin/index.php` routen und `/_admin/recovery.php` seiner eigenen Datei überlassen;
- Zugriffe auf Dotfiles und Dot-Verzeichnisse verweigern;
- **`private/` vollständig sperren** – es wird nie von einem Browser angefragt, sondern nur von PHP gelesen;
- direkte Zugriffe auf `_admin/install/library/` bis auf `_admin/install/library/themes/<key>/preview.svg` sperren – die übrigen Dateien sind serverseitige Darstellungsquellen; dasselbe gilt für die Section-Presets unter `app/Nino/Modules/Templates/library/`;
- Verzeichnisauflistung deaktivieren;
- PHP-Quell- und Datendateien nicht als Text ausliefern.

Für nginx ist der vorletzte Punkt ein einzelner Block:

```nginx
location ^~ /private/ { deny all; return 404; }
```

Oder du umgehst die Frage, indem du das Verzeichnis mit `NINO_PRIVATE_DIR` aus dem Webroot verlegst.

Eine allgemeine Beispielkonfiguration kann die Pfade und PHP-FPM-Einstellungen eines konkreten Hostings nicht zuverlässig erraten. Prüfe deshalb nach dem Einrichten sowohl gewünschte Routen als auch bewusst verbotene Direktzugriffe.

## Schreibrechte

Vor der Ersteinrichtung muss PHP in der Projektwurzel Verzeichnisse und Dateien anlegen dürfen. Die noch fehlenden Projektpfade werden vom Assistenten beziehungsweise bei Bedarf vom Kernel erzeugt und sind keine manuell anzulegende Voraussetzung.

Im laufenden Betrieb benötigt Nino Schreibrechte nur für tatsächlich veränderliche Inhalte. Dazu gehören je nach Nutzung `private/config.php`, `private/text/`, `private/elements/`, `private/data/`, `private/.logs/`, `private/.backups/`, `private/assets/`, `public/images/` und `public/.cache/`. Das Templates-Panel benötigt zusätzlich `private/templates/`; es kann native Textschlüssel, Elementtypen und Bildplatz-Definitionen in der Konfiguration anlegen. Das Anwenden von Darstellungsvarianten im Design-Panel benötigt `private/templates/`, `private/assets/`, `public/fonts/` sowie jedes weitere Ziel, das ein Theme-Manifest erklärt. Der Darstellungskatalog unter `_admin/install/library/` selbst bleibt schreibgeschützt; `_admin/.cache/` dagegen braucht Schreibrechte, weil die Workbench ihre Bundles dort baut – das eine Verzeichnis im Werkzeugordner. Projektwurzel und PHP-Quellcode können ansonsten nach der Installation schreibgeschützt bleiben.

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
geladen. Mit `NINO_APP_DIR` kann der Autoloader auf ein anderes absolutes
Quellcode-Verzeichnis zeigen; auch diese Konstante muss vor dem Laden des
Kernels definiert werden – in jedem Einstiegspunkt, wie die beiden oben. Sie
ersetzt `app/` als Ganzes, und Ninos eigene optionale Module – Design,
Templates, Form, Newsletter, Navigation, Localepicker, Search – liegen unter
`app/Nino/Modules/`: Ein Projekt, das den Root woandershin zeigen lässt, nimmt
sie mit, oder der Kernel überspringt ein Modul, das er nicht mehr laden kann,
ohne ein Wort:

```php
define('NINO_APP_DIR', '/pfad/ausserhalb/des/webroots/nino-app');
```

Dieser Quellcode-Override verschiebt weder Konfiguration noch Laufzeitdaten und
benötigt im Produktivbetrieb keine Schreibrechte. Eine projekteigene Klasse wird
ausschließlich dort gesucht – `_nino/` ist kein zweiter Fundort. Klassen im kerneigenen
Namespace `Nino\` werden weiterhin ausschließlich aus `_nino/` geladen.

## Einstellungen für den Produktivbetrieb

Prüfe in `config.php` beziehungsweise über das Config-Panel der Workbench mindestens folgende Schlüssel:

| Schlüssel | Produktiver Wert | Wirkung |
|---|---|---|
| `/nino/error/display` | `false` | unterdrückt technische Fehlerdetails im Browser |
| `/nino/error/log` | `true` | schreibt Fehler für die spätere Diagnose ins Log |
| `/nino/session/force-secure-cookie` | `true` bei TLS-Terminierung vor PHP | erzwingt sichere Session-Cookies hinter einem HTTPS-Proxy |
| `/nino/admin/backups` | nach Betriebsentscheidung | steuert die tägliche verschlüsselte Sicherung der Workbench |
| `/nino/admin/logs` | nach Betriebsentscheidung | steuert das Aktivitätsprotokoll der Workbench |

Fehlermeldungen sollten im Browser keine Dateipfade, Konfigurationswerte oder Stacktraces offenlegen. Prüfe nach dem Umschalten, dass Fehler weiterhin in einem geschützten Log ankommen und für den Betreiber erreichbar bleiben.

## Die Workbench absichern

Vor dem Go-live müssen die Konten funktionieren und starke Passwörter haben:

- `/_admin` ist die eine Verwaltungsoberfläche – Entwickler und Redakteure melden sich mit eigenen Konten an. Ein Konto der Rolle **Developer** hält `/*`, eines der Rolle **Editor** nur die Inhalt-Berechtigungen; das [`/_admin`-Handbuch](_admin.de.md#anmeldung-konten-und-rollen) listet jede Berechtigung.
- `/_admin/recovery.php` fragt nach dem Recovery-Passwort aus dem letzten Schritt des Assistenten und bietet eine Wiederherstellung und ein Zurücksetzen eines Passworts. Bewahre dieses Passwort dort auf, wo die Passwörter der Entwicklerkonten nicht liegen.

Vergib Redaktionsrechte so eng wie praktisch möglich; die Konten, die der Assistent anlegt, sind Entwickler, und weitere Konten brauchen diese Reichweite meist nicht. Halte die Zahl der Entwicklerkonten klein.

HTTPS schützt nicht nur Anmeldedaten, sondern auch Sitzungs-Cookies und alle redaktionell übertragenen Inhalte. Leite HTTP-Anfragen dauerhaft auf HTTPS um und teste die Anmeldung nur über die endgültige öffentliche Adresse.

Zusätzlicher Webserver-Schutz für `/_admin` – etwa IP-Freigaben oder HTTP-Authentifizierung – kann bei passenden Betriebsbedingungen eine sinnvolle zweite Barriere bilden. Er ersetzt die Konten nicht. Die beiden Entwickler-Panels, die als Module ausgeliefert werden, Templates und Design, lassen sich aus einer Produktivauslieferung entfernen, indem `app/Nino/Modules/Templates/` und `app/Nino/Modules/Design/` gelöscht werden; die Workbench selbst bleibt, weil die Redaktion darin arbeitet.

## Der Assistent nach der Einrichtung

Schließe den Assistenten vollständig ab. Der letzte Schritt setzt das Recovery-Passwort und sperrt den Assistenten. Entferne anschließend das Verzeichnis `_admin/install/` aus der produktiven Auslieferung.

Damit verschwindet auch der Darstellungskatalog unter `_admin/install/library/`. Das ist beabsichtigt: Der Katalog ist Einrichtungsmaterial, kein Laufzeitfeature. Angewendete Themes und Frames liegen längst als Projektdateien in `assets/` und `templates/` und bleiben von Hand sowie über das Templates-Panel bearbeitbar. Im Design-Panel arbeitet der Tab Design unverändert weiter — er erzeugt die Palette und das Raster, statt Dateien zu kopieren; die drei katalogbasierten Tabs haben danach nichts mehr aufzulisten und sagen das auch. Wer Theme, Header und Footer dauerhaft umschaltbar halten will, liefert das gesperrte `_admin/install/` bewusst mit aus.

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
php tests/design-smoke.php
php tests/templates-smoke.php
php tests/search-smoke.php
php tests/demo-catalogue-smoke.php
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
- alle vier Tabs des Design-Panels einschließlich einer Frame-Vorschau, sofern das Modul ausgeliefert wird;
- Zugriff und unveränderten Round-Trip im Templates-Panel, sofern das Modul ausgeliefert wird;
- Schreiben und erneutes Laden eines redaktionellen Inhalts;
- Verhalten hinter CDN, Proxy oder Cache, sofern eingesetzt.

## Verbotene Direktzugriffe testen

Ein erfolgreicher Aufruf der Startseite belegt noch nicht, dass sensible Dateien geschützt sind. Prüfe mit nicht angemeldeten Anfragen, dass insbesondere folgende Kategorien nicht als Quelltext oder Verzeichnisinhalt erreichbar sind:

- Dotfiles und Dot-Verzeichnisse;
- `config.php` und PHP-Datendateien;
- versteckte Log- und Backup-Verzeichnisse;
- interne Dateien aus `_admin/` und `app/`, die nicht als öffentliche Assets vorgesehen sind – die Panel-Templates und die Section-Presets darunter;
- Dateien unter `_admin/install/library/` mit Ausnahme von `_admin/install/library/themes/*/preview.svg`;
- `_admin/install/`, nachdem es entfernt wurde.

Die erwartete Antwort kann je nach Server `403` oder `404` sein. Entscheidend ist, dass weder Inhalt noch Verzeichnisliste ausgeliefert werden.

## Updates und Rollback

Behandle ein Nino-Update wie eine Änderung am konkreten Webseitenprojekt, nicht wie das blinde Aktualisieren eines austauschbaren CMS-Kerns.

1. Sichere den aktuellen produktiven Stand außerhalb des Webroots.
2. Übernimm die Änderung zunächst in eine Entwicklungs- oder Staging-Umgebung.
3. Lege projekteigene PHP-Klassen in `app/` (oder `NINO_APP_DIR`) ab und vergleiche nur bewusste Kernel-Anpassungen mit dem neuen Stand. `_nino/` kann dann vollständig ersetzt werden, und `_admin/` ebenso: Die Workbench trägt keinen Projektzustand – die Konten liegen in der `config.php`, das Recovery-Geheimnis in `private/.auth/pw.php`. Ninos optionale Module unter `app/Nino/Modules/` aktualisiert das Projekt selbst: Vergleiche jedes behaltene Verzeichnis mit der Kopie der neuen Version und übernimm die Änderungen, oder ersetze es vollständig, wenn du es nie verändert hast.
4. Führe Smoke-Tests und projektspezifische Abnahme aus.
5. Übertrage den geprüften Stand und behalte die vorherige Version für ein Rollback.

Nino verwendet eine Projektstruktur: Private Dateien liegen in `private/`, für
den Browser bestimmte Dateien in `public/` und projekteigener PHP-Quellcode in
`app/`. Alternative Verzeichnisstrukturen werden während eines Requests nicht
migriert. `NINO_PRIVATE_DIR` kann den vollständigen privaten Baum verschieben,
`NINO_APP_DIR` den Application-Root des Projekts ersetzen. Für Klassen außerhalb
von `Nino\` bleibt ein Kompatibilitäts-Fallback unter `_nino/`; er ist für
bestehende Projekte gedacht, nicht für neuen Code.

Nino befindet sich in der Beta-Phase. Sicherheitskorrekturen erscheinen auf `main`; eine getrennte LTS-Linie gibt es derzeit nicht. Plane Updates deshalb als aktive Projektpflege ein und prüfe `SECURITY.md` sowie den Changelog vor einer Aktualisierung.

## Go-live-Checkliste

- [ ] PHP-Version und Erweiterungen entsprechen den Anforderungen.
- [ ] Öffentliche Routen werden korrekt an Nino übergeben.
- [ ] Dotfiles, Dot-Verzeichnisse und PHP-Datendateien sind nicht direkt erreichbar.
- [ ] `app/` wird nicht ausgeliefert — die eigene `.htaccess` sperrt das Verzeichnis; prüfe es mit einer Anfrage nach einem Modul-Installationstemplate, z. B. `/app/Nino/Modules/Newsletter/install/templates/mail-header.tpl`.
- [ ] `private/` wird nicht ausgeliefert — die eigene `.htaccess` sperrt das Verzeichnis, und jede PHP-Datei darin trägt einen 403-Stub; prüfe, ob beides auf deinem Webserver greift, oder verlege das Verzeichnis mit `NINO_PRIVATE_DIR` aus dem Webroot. Die Templates und die Asset-Quellen sind kein PHP und haben nur die Serverregel.
- [ ] Verzeichnisauflistung ist deaktiviert.
- [ ] Der Einrichtungsassistent konnte die Projektverzeichnisse aus der beschreibbaren Projektwurzel selbst erzeugen — ein Checkout liefert weder `private/` noch `public/` mit, der erste Schritt des Assistenten prüft genau das.
- [ ] Schreibrechte sind nach der Einrichtung auf die benötigten Pfade begrenzt.
- [ ] Der Einrichtungsassistent wurde vollständig abgeschlossen und `_admin/install/` anschließend produktiv entfernt.
- [ ] Wird `_admin/install/` mitgeliefert, um Theme/Header/Footer umschaltbar zu halten, ist es gesperrt und von seinem Katalog sind nur die Theme-Vorschauen direkt erreichbar.
- [ ] Entwickler- und Redaktionskonten sind getestet, und das Recovery-Passwort ist sicher verwahrt.
- [ ] Die Module Design und Templates sind entweder entfernt oder bewusst als Alpha ausgeliefert, und nur Entwicklerkonten erreichen sie.
- [ ] Editor-Nutzer haben nur die benötigten Berechtigungen.
- [ ] HTTPS und sichere Session-Cookies funktionieren an der endgültigen Adresse.
- [ ] Fehleranzeige ist deaktiviert und Fehlerprotokollierung geprüft.
- [ ] Smoke-Tests und Browser-Abnahme sind erfolgreich.
- [ ] Backups laufen, liegen zusätzlich extern vor und lassen sich wiederherstellen.
- [ ] Der vorherige Projektstand ist für ein Rollback verfügbar.

## Wie es weitergeht

- [Erste Schritte](getting-started.de.md) beschreibt die notwendige Ersteinrichtung.
- [`/_admin`-Workbench](_admin.de.md) erklärt jedes Panel, die Konten, Sicherungen und die Recovery-Seite.
- [Templates-Panel](templates.de.md) beschreibt den optionalen Template-Builder im Alpha-Status.
- [Design-Panel](appearance.de.md) beschreibt die vier Erscheinungsbild-Editoren.
- [Grundkonzepte](concepts.de.md) erklärt die technische Struktur hinter dem deployten Projekt.
