# Erste Schritte mit Nino

**Sprache:** [English](getting-started.md) · Deutsch

**Stand:** 11. September 2026 · **Nino-Version:** 1.2.0-beta

Dieses Handbuch führt auf dem kürzesten Weg von einem frischen Checkout zu einer lokal laufenden Nino-Webseite. Falls du stattdessen jedes Feld und jeden Schreibvorgang des Assistenten nachschlagen möchtest, lies die Referenz [Einrichtungsassistent](setup.de.md); technische Hintergründe stehen in den [Grundkonzepten](concepts.de.md).

**Weitere Links:**
[README](../README.de.md) · [Grundkonzepte](concepts.de.md) · [Entwickler-Handbuch](development.de.md) · [Rezepte](recipes/README.md) · [Erste Schritte](getting-started.de.md) · [Einrichtungsassistent](setup.de.md) · [`/_admin`-Workbench](_admin.de.md) · [Features](features.de.md) · [Deployment](deployment.de.md) · [Security Policy](https://github.com/dapeio/nino/blob/main/SECURITY.md) · [Changelog](https://github.com/dapeio/nino/blob/main/CHANGELOG.md)

**Wichtig:** Ein frischer Checkout enthält Kernel, Workbench, Module, Features und die Installations-Library, aber noch keinen vollständigen Projektstand. Der Einrichtungsassistent – das, was `/_admin` zeigt, bis er abgeschlossen ist – erzeugt und befüllt die benötigten Projektverzeichnisse; erst danach läuft die Webseite.

## Voraussetzungen

Für die lokale Einrichtung werden PHP 8.4 oder neuer, die von Nino geprüften PHP-Erweiterungen und Schreibrechte in der Projektwurzel benötigt. Git ist erforderlich, wenn das Projekt direkt aus dem Repository ausgecheckt wird.

Der erste Installationsschritt prüft Version, Erweiterungen und Schreibrechte. Fehlende Verzeichnisse wie `templates/`, `text/`, `elements/` oder `images/` sind zu diesem Zeitpunkt erwartbar – PHP muss sie lediglich anlegen dürfen.

> **Sicherheit:** Führe die Einrichtung lokal oder in einer anderweitig geschützten Umgebung aus. Bis zum Abschluss besitzt der Assistent keinen Zugangsschutz, und es existiert noch kein Konto.

## Projekt auschecken und starten

```bash
git clone https://github.com/dapeio/nino.git meine-webseite
cd meine-webseite
php -S 127.0.0.1:8000 router.php
```

Öffne anschließend <http://127.0.0.1:8000/_admin>. `router.php` bildet das lokale Routing ab; für den Produktivbetrieb ist eine eigene Webserver-Konfiguration erforderlich.

## Die zehn Schritte

Solange der Assistent nicht abgeschlossen ist, kannst du zu früheren Schritten zurückkehren und Einstellungen erneut anwenden. Was dabei ersetzt, ergänzt oder erhalten wird, beschreibt die Referenz [Einrichtungsassistent](setup.de.md#navigation-und-speichern).

| Schritt | Entscheidung |
|---|---|
| [1. Umgebung](setup.de.md#1-umgebung) | Sind PHP, Erweiterungen und Schreibrechte einsatzbereit? |
| [2. Sprachen](setup.de.md#2-sprachen) | Welche Sprachen und funktionalen Module benötigt das Projekt? |
| [3. Routes](setup.de.md#3-routes) | Welche ersten Seiten, öffentlichen Pfade und Metadaten werden angelegt? |
| [4. Persönliche Angaben](setup.de.md#4-persönliche-angaben) | Welche zentralen Unternehmens- und Webseitenwerte stehen als Textfills bereit? |
| [5. Accounts](setup.de.md#5-accounts) | Welche Entwicklerkonten melden sich mit Vollzugriff an der Workbench an? |
| [6. Finish](setup.de.md#6-finish) | Welches Recovery-Passwort öffnet `/_admin/recovery.php`, wenn die Konten kaputt sind – und sperrt den Assistenten? |

Der Assistent löst Abhängigkeiten zwischen gewählten Modulen sowie den verwendeten Seitenvorlagen automatisch auf. Das Aussehen gehört nicht zu seinen Fragen: Die Base-Einheit liefert ein Theme aus, `assets/theme.css`, und die beiden Frame-Templates, gegen die es gezeichnet ist – siehe [Das Aussehen](setup.de.md#das-aussehen).

Die Konten aus Schritt 5 sind Entwickler mit vollen Rechten. Redaktionskonten mit reinen Inhaltsrechten entstehen später im Panel Nutzer der Workbench.

## Ergebnis prüfen

Öffne nach dem Abschluss:

| Adresse | Erwartetes Ergebnis |
|---|---|
| `/` | Die eingerichtete Webseite wird mit dem ausgelieferten Theme dargestellt. |
| `/_admin` | Das Root-Konto öffnet die Workbench mit jedem Panel: Inhalt, Struktur und System. |
| `/_admin#templates` | Das Templates-Panel, der sectionbasierte Template Builder (Alpha). |

Prüfe außerdem jede Sprache und Route, die Navigation sowie verwendete Formulare. Speichere testweise einen Text und ein Bild. Öffne im Templates-Panel ein `page-*.tpl`, ändere zunächst nichts und prüfe, ob seine obersten Sections ohne Warnung erkannt werden.

Newsletter und Suche sind Features, keine Module des Assistenten, und ein Checkout bringt keines mit: Kopiere das Verzeichnis eines Features aus dem Katalog [dapeio/nino-features](https://github.com/dapeio/nino-features) nach `features/`, wenn das Projekt es braucht, schalte es im Panel **Features** der Workbench (Gruppe System) ein und lade die Workbench danach neu, damit sein Panel erscheint. Siehe [Features](features.de.md).

Der letzte Schritt setzt das Recovery-Passwort und sperrt den Assistenten. Entferne anschließend `_admin/install/` aus der produktiven Auslieferung; alles, was er kopiert hat, bleibt dort liegen, wo er es geschrieben hat. Die korrekte Reihenfolge und weitere Sicherheitsprüfungen stehen im [Deployment-Handbuch](deployment.de.md#der-assistent-nach-der-einrichtung).

## Danach weiterarbeiten

- [Grundkonzepte](concepts.de.md) erklärt Architektur, Datenfluss und Aufgabentrennung.
- [Entwickler-Handbuch](development.de.md) vertieft Kernel, APIs, Callbacks und eigene Module.
- [Einrichtungsassistent](setup.de.md) dokumentiert alle Optionen und Schreibvorgänge.
- [`/_admin`-Workbench](_admin.de.md) führt durch jedes Panel, die Konten und die Recovery-Seite.
- Der **Template-Baukasten** – Seitentemplates aus ganzen Abschnitten – ist ein Feature aus dem Katalog [dapeio/nino-features](https://github.com/dapeio/nino-features); sein [Handbuch](https://github.com/dapeio/nino-features/blob/main/features/Templates/docs/templates.de.md) liegt dort ebenfalls.
- [Features](features.de.md) erklärt, wie ein installierbares Feature eingeschaltet, konfiguriert und aktualisiert wird.
- [Deployment](deployment.de.md) führt durch Webserver-Konfiguration, Sicherheit, Backups und Go-live.
