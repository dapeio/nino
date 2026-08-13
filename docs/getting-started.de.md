# Erste Schritte mit Nino

**Sprache:** [English](getting-started.md) · Deutsch

**Stand:** 8. August 2026 · **Nino-Version:** 0.11.0-beta.1

Dieses Handbuch führt auf dem kürzesten Weg von einem frischen Checkout zu einer lokal laufenden Nino-Webseite. Falls du stattdessen jedes Feld und jeden Schreibvorgang des Assistenten nachschlagen möchtest, lies die [`/_install`-Referenz](_install.de.md); technische Hintergründe stehen in den [Grundkonzepten](concepts.de.md).

**Weitere Links:**
[README](../README.de.md) · [Grundkonzepte](concepts.de.md) · [Entwickler-Handbuch](development.de.md) · [Erste Schritte](getting-started.de.md) · [`/_install`-Referenz](_install.de.md) · [`/_admin`-Bedienung](_admin.de.md) · [`/_templates`-Bedienung](_templates.de.md) · [`/_editor`-Bedienung](_editor.de.md) · [Deployment](deployment.de.md) · [Security Policy](https://github.com/dapeio/nino/blob/main/SECURITY.md) · [Changelog](https://github.com/dapeio/nino/blob/main/CHANGELOG.md)

**Wichtig:** Ein frischer Checkout enthält Kernel, Oberflächen und die Installations-Library, aber noch keinen vollständigen Projektstand. `/_install` erzeugt und befüllt die benötigten Projektverzeichnisse; erst danach läuft die Webseite.

## Voraussetzungen

Für die lokale Einrichtung werden PHP 8.4 oder neuer, die von Nino geprüften PHP-Erweiterungen und Schreibrechte in der Projektwurzel benötigt. Git ist erforderlich, wenn das Projekt direkt aus dem Repository ausgecheckt wird.

Der erste Installationsschritt prüft Version, Erweiterungen und Schreibrechte. Fehlende Verzeichnisse wie `templates/`, `text/`, `elements/` oder `images/` sind zu diesem Zeitpunkt erwartbar – PHP muss sie lediglich anlegen dürfen.

> **Sicherheit:** Führe die Einrichtung lokal oder in einer anderweitig geschützten Umgebung aus. Bis zum Abschluss besitzt `/_install` keinen individuellen Zugangsschutz und das echte Passwort für `/_admin` ist noch nicht gesetzt.

## Projekt auschecken und starten

```bash
git clone https://github.com/dapeio/nino.git meine-webseite
cd meine-webseite
php -S 127.0.0.1:8000 router.php
```

Öffne anschließend <http://127.0.0.1:8000/_install>. `router.php` bildet das lokale Routing ab; für den Produktivbetrieb ist eine eigene Webserver-Konfiguration erforderlich.

## Die sieben Schritte

Solange der Assistent nicht abgeschlossen ist, kannst du zu früheren Schritten zurückkehren und Einstellungen erneut anwenden. Was dabei ersetzt, ergänzt oder erhalten wird, beschreibt die [`/_install`-Referenz](_install.de.md#navigation-und-speichern).

| Schritt | Entscheidung |
|---|---|
| [1. Umgebung](_install.de.md#1-umgebung) | Sind PHP, Erweiterungen und Schreibrechte einsatzbereit? |
| [2. Setup](_install.de.md#2-setup) | Welche Sprachen und funktionalen Module benötigt das Projekt? |
| [3. Themes](_install.de.md#3-themes) | Welcher visuelle Ausgangspunkt soll kopiert werden? |
| [4. Routes](_install.de.md#4-routes) | Welche ersten Seiten, öffentlichen Pfade und Metadaten werden angelegt? |
| [5. Persönliche Angaben](_install.de.md#5-persönliche-angaben) | Welche zentralen Unternehmens- und Webseitenwerte stehen als Textfills bereit? |
| [6. Editor-Zugang](_install.de.md#6-zugänge-für-_editor) | Welches erste Konto erhält vollständigen Zugriff auf `/_editor`? |
| [7. Abschluss](_install.de.md#7-abschluss) | Welches getrennte Passwort schützt `/_admin` sowie `/_templates` und sperrt den Installer? |

Der Assistent löst Abhängigkeiten zwischen gewählten Modulen sowie den verwendeten Seitenvorlagen automatisch auf. Das Theme ist ein Ausgangspunkt für die anschließende Frontend-Entwicklung; nach dem Abschluss ist kein späterer Theme-Wechsel über `/_install` vorgesehen.

Das erste Editor-Konto besitzt vollständige Rechte. Weitere Konten werden später über `/_admin` angelegt oder gelöscht; bestehende Nutzer verwalten ihre Daten und – mit entsprechender Berechtigung – freigegebene Rechte in `/_editor`.

## Ergebnis prüfen

Öffne nach dem Abschluss:

| Adresse | Erwartetes Ergebnis |
|---|---|
| `/` | Die eingerichtete Webseite wird mit dem gewählten Theme ausgeliefert. |
| `/_editor` | Das erste Nutzerkonto kann Inhalte pflegen. |
| `/_admin` | Das getrennte technische Passwort öffnet die vollständige Projektverwaltung. |
| `/_templates` | Dasselbe technische Passwort öffnet den sectionbasierten Template Builder (Alpha). |

Prüfe außerdem jede Sprache und Route, die Navigation sowie verwendete Formulare. Speichere testweise einen Text und ein Bild in `/_editor`. Falls du `/_templates` einsetzen möchtest, öffne zusätzlich ein `page-*.tpl`, ändere zunächst nichts und prüfe, ob seine obersten Sections ohne Warnung erkannt werden.

Der letzte Installationsschritt ersetzt den mitgelieferten `_admin`-Passworthash und sperrt `/_install`. Entferne anschließend `_install/` aus der produktiven Auslieferung; die korrekte Reihenfolge und weitere Sicherheitsprüfungen stehen im [Deployment-Handbuch](deployment.de.md#_install-nach-der-einrichtung).

## Danach weiterarbeiten

- [Grundkonzepte](concepts.de.md) erklärt Architektur, Datenfluss und Aufgabentrennung.
- [Entwickler-Handbuch](development.de.md) vertieft Kernel, APIs, Callbacks und eigene Module.
- [`/_install`-Referenz](_install.de.md) dokumentiert alle Optionen und Schreibvorgänge.
- [`/_admin`-Bedienung](_admin.de.md) führt durch die vollständige Projektverwaltung.
- [`/_templates`-Bedienung](_templates.de.md) erklärt den sectionbasierten Template Builder im Alpha-Status.
- [`/_editor`-Bedienung](_editor.de.md) erklärt die anschließende, berechtigungsgesteuerte Inhaltspflege.
- [Deployment](deployment.de.md) führt durch Webserver-Konfiguration, Sicherheit, Backups und Go-live.
