# Deployment und Go-live

**Sprache:** [English](deployment.md) · Deutsch

**Stand:** 8. August 2026 · **Nino-Version:** 0.11.0-beta.1

Dieses Handbuch führt eine fertig entwickelte Nino-Webseite in den produktiven Betrieb. Falls du stattdessen ein frisches Projekt einrichten möchtest, beginne mit [Erste Schritte](getting-started.de.md); technische Erweiterungen behandelt das [Entwickler-Handbuch](development.de.md).

**Weitere Links:**
[README](../README.de.md) · [Grundkonzepte](concepts.de.md) · [Entwickler-Handbuch](development.de.md) · [Erste Schritte](getting-started.de.md) · [`/_install`-Referenz](_install.de.md) · [`/_admin`-Bedienung](_admin.de.md) · [`/_templates`-Bedienung](_templates.de.md) · [`/_editor`-Bedienung](_editor.de.md) · [Deployment](deployment.de.md) · [Security Policy](https://github.com/dapeio/nino/blob/main/SECURITY.md) · [Changelog](https://github.com/dapeio/nino/blob/main/CHANGELOG.md)

## Voraussetzungen des Zielsystems
Nino benötigt weder Datenbankserver noch Composer-Installation auf dem Zielsystem. Das vereinfacht zwar das Deployment, macht die Dateien des Projekts aber umso wichtiger: Konfiguration und redaktionelle Daten liegen direkt im Dateisystem und müssen beim Übertragen, Sichern und Berechtigen vollständig berücksichtigt werden.

Das produktive System benötigt:

- PHP 8.4 oder neuer;
- `gd`, `mbstring`, `session`, `json`, `Phar` und `PharData`;
- einen Webserver, der öffentliche Dateien direkt ausliefert und dynamische Anfragen an Nino weitergibt;
- HTTPS für alle öffentlich erreichbaren Verwaltungsoberflächen;
- vor der Einrichtung eine beschreibbare Projektwurzel, damit `/_install` die noch fehlenden Projektverzeichnisse erzeugen kann.

Führe den Umgebungscheck von `/_install` bereits auf einer Umgebung aus, die dem späteren Hosting entspricht. Eine lokal erfolgreiche Installation beweist noch nicht, dass der Webhosting-Tarif dieselben PHP-Erweiterungen und Schreibrechte bereitstellt.

## Deployment-Modell wählen

Für kleine Projekte gibt es zwei sinnvolle Wege:

1. Die Webseite wird in einer geschützten Zielumgebung eingerichtet und erst danach öffentlich geschaltet.
2. Die Webseite wird lokal vollständig eingerichtet und anschließend als kompletter Projektstand übertragen.

In beiden Fällen muss `/_install` einen frischen Checkout einmal in einen gültigen Projektstand überführen. Überträgst du ein bereits eingerichtetes Projekt, gehören die erzeugten Projektverzeichnisse vollständig zum Deployment. Details zur Ersteinrichtung stehen unter [Erste Schritte](getting-started.de.md); `/_install` ist kein Updatewerkzeug für laufende Projekte.

**Sicherheit:** Wird die Einrichtung auf dem Zielsystem vorgenommen, muss `/_install` bis zum Abschluss durch einen vorgeschalteten Zugangsschutz, ein internes Netz oder eine noch nicht öffentliche Umgebung geschützt sein.

## Webroot und Routing

Der Einstiegspunkt der öffentlichen Webseite ist `index.php`. Die Bereiche `/_editor`, `/_admin`, `/_templates` und während der Einrichtung `/_install` besitzen eigene Einstiegspunkte. Der Webserver muss vorhandene statische Dateien direkt ausliefern und alle übrigen Webseitenanfragen an Nino weiterreichen.

Für die lokale Entwicklung übernimmt `router.php` dieses Verhalten:

```bash
php -S 127.0.0.1:8000 router.php
```

Das ist ein Entwicklungsserver, keine Produktionskonfiguration.

### Apache

Die mitgelieferte `.htaccess` setzt zwei grundlegende Schutzregeln, sofern der Server `AllowOverride` für das Projekt zulässt:

- Dateien mit einem führenden Punkt werden nicht direkt ausgeliefert.
- Verzeichnisse ohne Indexdatei zeigen keine Dateiliste.

Eine dritte Regel liegt in `private/.htaccess` und sperrt dieses Verzeichnis vollständig. Sie ist die wichtigste: In `private/` liegen `config.php`, die Templates sowie die Texte und Elemente, aus denen sie rendern, und die Daten deiner Besucher. Ohne sie liefert ein Aufruf von `private/templates/page-home.tpl` den Template-Quelltext im Klartext aus.

Prüfe in der Hosting-Konfiguration zusätzlich, wie nicht vorhandene Pfade an `index.php` übergeben werden. Eine `.htaccess`, die vom Server ignoriert wird, entfaltet keinerlei Schutzwirkung – und bei `private/` ist das keine Härtungsfrage, sondern eine Offenlegung. Wenn du dich auf `.htaccess` nicht verlassen kannst, richte stattdessen `NINO_CONTENT_DIR` in der `index.php` auf ein Verzeichnis außerhalb des Webroots; dann braucht es gar keine Serverregel.

### Nginx und andere Webserver

Übertrage dasselbe Verhalten ausdrücklich in die Serverkonfiguration:

- vorhandene öffentliche Assets direkt ausliefern;
- normale Webseitenrouten an `index.php` weitergeben;
- `/_editor`, `/_admin`, `/_templates` und gegebenenfalls `/_install` an ihre eigenen Einstiegspunkte routen;
- Zugriffe auf Dotfiles und Dot-Verzeichnisse verweigern;
- **`private/` vollständig sperren** – es wird nie von einem Browser angefragt, sondern nur von PHP gelesen;
- Verzeichnisauflistung deaktivieren;
- PHP-Quell- und Datendateien nicht als Text ausliefern.

Für nginx ist der vorletzte Punkt ein einzelner Block:

```nginx
location ^~ /private/ { deny all; return 404; }
```

Oder du umgehst die Frage, indem du das Verzeichnis mit `NINO_CONTENT_DIR` aus dem Webroot verlegst.

Eine allgemeine Beispielkonfiguration kann die Pfade und PHP-FPM-Einstellungen eines konkreten Hostings nicht zuverlässig erraten. Prüfe deshalb nach dem Einrichten sowohl gewünschte Routen als auch bewusst verbotene Direktzugriffe.

## Schreibrechte

Vor der Ersteinrichtung muss PHP in der Projektwurzel Verzeichnisse und Dateien anlegen dürfen. Die noch fehlenden Projektpfade werden von `/_install` beziehungsweise bei Bedarf vom Kernel erzeugt und sind keine manuell anzulegende Voraussetzung.

Im laufenden Betrieb benötigt Nino Schreibrechte nur für tatsächlich veränderliche Inhalte. Dazu gehören je nach Nutzung `config.php`, `text/`, `elements/`, `images/`, `data/` und `.cache/` sowie die von `/_editor` angelegten Log- und Backup-Verzeichnisse. Der Template Builder unter `/_templates` benötigt Schreibrechte für bearbeitete Dateien unter `templates/`; er kann zusätzlich native Textschlüssel, Elementtypen und Bildplatz-Definitionen in der Konfiguration anlegen. `/_admin` schreibt je nach Funktion unter anderem Konfiguration, Texte, Elemente und Bilder.

Vergib diese Rechte an den Benutzer, unter dem PHP ausgeführt wird. Weltweit beschreibbare Rechte wie `0777` sind keine geeignete Dauerlösung. Nach dem Deployment sollten Kernel und übriger PHP-Quellcode nicht allgemein beschreibbar sein.

## Konfiguration außerhalb des Webroots

`index.php` unterstützt die PHP-Konstante `NINO_CONFIG_DIR`. Sie verweist auf ein existierendes, beschreibbares Verzeichnis, aus dem `config.php` geladen wird. Damit kann die zentrale Konfiguration außerhalb des öffentlich erreichbaren Projektverzeichnisses liegen.

```php
define('NINO_CONFIG_DIR', '/pfad/ausserhalb/des/webroots');
```

Trage die Definition vor dem Laden von `_nino/Nino.php` in `index.php` ein. Nutze diese Möglichkeit, wenn der Server einen geeigneten privaten Pfad bereitstellt. Der Webserver-Schutz bleibt trotzdem notwendig, weil weitere Projektdateien ebenfalls nicht direkt ausgeliefert werden dürfen.

## Einstellungen für den Produktivbetrieb

Prüfe in `config.php` beziehungsweise über das Config-Modul von `/_admin` mindestens folgende Schlüssel:

| Schlüssel | Produktiver Wert | Wirkung |
|---|---|---|
| `/nino/error/display` | `false` | unterdrückt technische Fehlerdetails im Browser |
| `/nino/error/log` | `true` | schreibt Fehler für die spätere Diagnose ins Log |
| `/nino/session/force-secure-cookie` | `true` bei TLS-Terminierung vor PHP | erzwingt sichere Session-Cookies hinter einem HTTPS-Proxy |
| `/nino/editor/backups` | entsprechend der Betriebsentscheidung | steuert automatische Backups des Editors |
| `/nino/editor/logs` | entsprechend der Betriebsentscheidung | steuert die Protokollierung des Editors |

**Wichtig:** Verwende für Editor-Backups und -Logs die Schlüssel unter `/nino/editor/*`. Ältere Konfigurationsstände oder Texte können noch `/nino/admin/*` nennen; der aktuelle Editor liest die Editor-Schlüssel.

Fehlermeldungen sollten im Browser keine Dateipfade, Konfigurationswerte oder Stacktraces offenlegen. Prüfe nach dem Umschalten, dass Fehler weiterhin in einem geschützten Log ankommen und für den Betreiber erreichbar bleiben.

## Verwaltungsbereiche absichern

Vor dem Go-live müssen beide Zugänge funktionieren und voneinander getrennte, starke Passwörter besitzen:

- `/_admin` ist die vollständige technische und inhaltliche Verwaltung für Entwickler.
- `/_templates` ist das sectionbasierte Alpha-Werkzeug und verwendet Passwort, Sperrstatus und Sitzung von `/_admin`.
- `/_editor` besitzt einzelne Nutzerkonten und Berechtigungen für Betreiber und Redakteure.

Vergib Editor-Rechte so eng wie praktisch möglich. Das während `/_install` angelegte erste Konto besitzt vollständige Rechte über `/*`; weitere Redakteure benötigen diese Reichweite meist nicht.

HTTPS schützt nicht nur Anmeldedaten, sondern auch die Session-Cookies und alle redaktionell übertragenen Inhalte. Leite HTTP-Anfragen dauerhaft auf HTTPS um und teste die Anmeldung erst über die endgültige öffentliche Adresse.

Zusätzlicher Webserver-Schutz für `/_admin`, `/_templates` – etwa IP-Freigaben oder HTTP-Authentifizierung – kann bei passenden Betriebsbedingungen eine sinnvolle zweite Barriere bilden. Er ersetzt das Nino-Passwort nicht. Werden die Werkzeuge nach Entwicklung und Abnahme nicht benötigt, können `_templates/` und gemeinsam damit auch `_admin/` aus der produktiven Auslieferung entfernt werden.

## `/_install` nach der Einrichtung

Schließe den Assistenten vollständig ab. Der letzte Schritt setzt das echte Passwort von `/_admin` und sperrt den Installer. Entferne anschließend das Verzeichnis `_install/` aus der produktiven Auslieferung.

Die Reihenfolge ist wesentlich:

1. mindestens einen funktionierenden Nutzer für `/_editor` anlegen;
2. das Passwort für `/_admin` setzen und den Assistenten abschließen;
3. Frontend, `/_editor` und `/_admin` prüfen;
4. `_install/` aus dem Produktivsystem entfernen.

Eine unvollständige Installation wird nicht dadurch gültig, dass ihr Installer gelöscht wird.

## Backups und Wiederherstellung

Bei aktivierten Backups legt `/_editor` beim ersten Login des Tages automatisch ein verschlüsseltes Backup an. Die vorgesehenen Sicherungen rotieren über 14 Tage und liegen in einem zufällig benannten, versteckten Verzeichnis innerhalb von `_editor/`. Die Archive werden mit AES-256-GCM verschlüsselt.

Die Wiederherstellung erfolgt über `/_admin`. Vor dem Einspielen erzeugt Nino eine zusätzliche Sicherung des aktuellen Standes, damit eine versehentlich falsche Wiederherstellung nicht sofort den vorherigen Zustand vernichtet.

Diese Backups schützen vor vielen redaktionellen Fehlern, sind aber kein vollständiges Hosting-Backup. Eine belastbare Sicherungsstrategie kopiert zusätzlich regelmäßig das gesamte Projekt einschließlich Konfiguration, Texte, Elemente, Bilder und der zur Wiederherstellung notwendigen Schlüssel an einen getrennten Ort. Prüfe die Wiederherstellung, bevor sie in einem Notfall gebraucht wird.

## Tests vor dem Go-live

Führe die mitgelieferten Smoke-Tests mit derselben PHP-Hauptversion aus, die später produktiv läuft:

```bash
php tests/kernel-smoke.php
php tests/editor-smoke.php
php tests/admin-smoke.php
php tests/install-smoke.php
php tests/templates-smoke.php
for test in tests/*-js-smoke.js; do node "$test"; done
php tests/concurrency-smoke.php
```

Die Smoke-Tests ersetzen keinen projektspezifischen Abnahmetest. Prüfe zusätzlich im Browser:

- alle öffentlichen Routen und die Fehlerseite;
- jede aktive Sprache und die Sprachumschaltung;
- responsive Darstellung und verwendete Bilder;
- Formulare einschließlich Validierung, Versand und Fehlermeldungen;
- Anmeldung, Abmeldung und Rechte in `/_editor`;
- technischen Zugang zu `/_admin`;
- Zugriff und unveränderten Round-Trip in `/_templates`, sofern der Alpha-Builder ausgeliefert wird;
- Schreiben und erneutes Laden eines redaktionellen Inhalts;
- Verhalten hinter CDN, Proxy oder Cache, sofern eingesetzt.

## Verbotene Direktzugriffe testen

Ein erfolgreicher Aufruf der Startseite belegt noch nicht, dass sensible Dateien geschützt sind. Prüfe mit nicht angemeldeten Anfragen, dass insbesondere folgende Kategorien nicht als Quelltext oder Verzeichnisinhalt erreichbar sind:

- Dotfiles und Dot-Verzeichnisse;
- `config.php` und PHP-Datendateien;
- versteckte Log- und Backup-Verzeichnisse;
- interne Dateien aus `_admin/`, `_templates/` und `_editor/`, die nicht als öffentliche Assets vorgesehen sind;
- `_install/`, nachdem es entfernt wurde.

Die erwartete Antwort kann je nach Server `403` oder `404` sein. Entscheidend ist, dass weder Inhalt noch Verzeichnisliste ausgeliefert werden.

## Updates und Rollback

Behandle ein Nino-Update wie eine Änderung am konkreten Webseitenprojekt, nicht wie das blinde Aktualisieren eines austauschbaren CMS-Kerns.

1. Sichere den aktuellen produktiven Stand außerhalb des Webroots.
2. Übernimm die Änderung zunächst in eine Entwicklungs- oder Staging-Umgebung.
3. Vergleiche eigene Anpassungen in Kernel, Templates und Verwaltungsoberflächen mit dem neuen Stand. Die Verwaltungsoberflächen selbst tragen keinen Projektzustand mehr: Das `/_admin`-Passwort liegt in `private/.auth/pw.php`, `_admin/` lässt sich also komplett ersetzen, ohne den Login zu verlieren oder `/_install` wieder zu öffnen.
4. Führe Smoke-Tests und projektspezifische Abnahme aus.
5. Übertrage den geprüften Stand und behalte die vorherige Version für ein Rollback.

Ein Projekt, das vor `private/` und `public/` eingerichtet wurde, behält seine Dateien im Projektstamm und wird bei jedem Request als solches erkannt – ein Update verschiebt nie etwas von selbst. Zum Umstellen verschiebst du `config.php`, `templates/`, `text/`, `elements/` und `data/` nach `private/` sowie `images/`, `assets/`, `favicon/`, `fonts/` und `.cache/` nach `public/`. Vor dem Umzug erzeugte Templates adressieren Bilder noch als `[[/nino/dir]]/images/...`; das muss im selben Schritt zu `[[/nino/public]]/images/...` werden, sonst laufen sie danach ins Leere.

Frühere Beta-Stände nannten dieses Verzeichnis `content/`; in einem kurzlebigen Stand war der neue Name zudem als `privat/` falsch geschrieben. Nino erkennt beide Namen weiterhin, damit ein Update vorhandene Projektdaten nicht abschneidet; kanonisch ist aber `private/`. Benenne das vollständige Verzeichnis um, während die Seite offline ist, und behalte nicht mehrere dieser Verzeichnisse.

Nino befindet sich in der Beta-Phase. Sicherheitskorrekturen erscheinen auf `main`; eine getrennte LTS-Linie gibt es derzeit nicht. Plane Updates deshalb als aktive Projektpflege ein und prüfe `SECURITY.md` sowie den Changelog vor einer Aktualisierung.

## Go-live-Checkliste

- [ ] PHP-Version und Erweiterungen entsprechen den Anforderungen.
- [ ] Öffentliche Routen werden korrekt an Nino übergeben.
- [ ] Dotfiles, Dot-Verzeichnisse und PHP-Datendateien sind nicht direkt erreichbar.
- [ ] `private/` wird nicht ausgeliefert — die eigene `.htaccess` sperrt das Verzeichnis, und jede Datei darin trägt einen 403-Stub; prüfe, ob beides auf deinem Webserver greift, oder verlege das Verzeichnis mit `NINO_CONTENT_DIR` aus dem Webroot.
- [ ] Verzeichnisauflistung ist deaktiviert.
- [ ] `/_install` konnte die Projektverzeichnisse aus der beschreibbaren Projektwurzel selbst erzeugen.
- [ ] Schreibrechte sind nach der Einrichtung auf die benötigten Pfade begrenzt.
- [ ] `/_install` wurde vollständig abgeschlossen und anschließend produktiv entfernt.
- [ ] `/_admin` und `/_editor` besitzen getestete, getrennte Zugänge.
- [ ] `/_templates` ist entweder entfernt oder mit dem Admin-Zugang geschützt und als Alpha bewusst freigegeben.
- [ ] Editor-Nutzer haben nur die benötigten Berechtigungen.
- [ ] HTTPS und sichere Session-Cookies funktionieren an der endgültigen Adresse.
- [ ] Fehleranzeige ist deaktiviert und Fehlerprotokollierung geprüft.
- [ ] Smoke-Tests und Browser-Abnahme sind erfolgreich.
- [ ] Backups laufen, liegen zusätzlich extern vor und lassen sich wiederherstellen.
- [ ] Der vorherige Projektstand ist für ein Rollback verfügbar.

## Wie es weitergeht

- [Erste Schritte](getting-started.de.md) beschreibt die notwendige Ersteinrichtung.
- [`/_admin`-Bedienung](_admin.de.md) erklärt Wiederherstellung, vollständige Inhalte, Konten und technische Konfiguration.
- [`/_templates`-Bedienung](_templates.de.md) beschreibt den optionalen Template-Builder im Alpha-Status.
- [`/_editor`-Bedienung](_editor.de.md) beschreibt die freigegebene Inhaltspflege, Betriebsdaten und tägliche Backups.
- [Grundkonzepte](concepts.de.md) erklärt die technische Struktur hinter dem deployten Projekt.
