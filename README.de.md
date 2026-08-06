# Nino

*[English](README.md)*

## **Hallo, ich bin Nino.**
Nino ist ein kompaktes PHP-Framework für Webseiten. Es funktioniert **unabhängig von Paketen**, benötigt **keine Datenbank** und bietet trotzdem alle essenziellen Funktionen für die Entwicklung und den Betrieb moderner Webanwendungen.
Das Framework beinhaltet die vorgegebene Dateistruktur, einen **Kernel** zur dynamischen Generierung und Anzeige der Seite (_nino) und eine **optionale** grafische Oberfläche für den Entwickler (_admin), den Betreiber (_editor) sowie einen Einrichtungsassistenten für die Erstinstallation (_install).

## _nino
Der Kernel (_nino) umfasst PHP/JS/CSS-Dateien für das Front- und Backend. Er bündelt den gesamten PHP-Code in nur **einer Datei mit 5.000 Zeilen** und beinhaltet alle notwendigen Methoden, zur Darstellung mehrsprachiger Webseiten - inklusive einem vollständigen Backend, dateibasierter Inhaltsverwaltung, Templating und einem umfangreichen Werkzeugkasten mit weiteren Frontend- und Backendtools.
Texte und wiederkehrende Inhalte der Webseite (wie Beiträge, Leistungen, Neuigkeiten, ..) liegen in **PHP-Dateien**, Templates werden **HTML-ähnlich** angelegt. Der Nino-Kernel kümmert sich vollständig um den Request (Locale/Auth/Route), rendert die Templates und liefert das Ergebnis sauber aus.
Alle Daten des laufenden PHP-Lifecycle werden in einem **einzigen Array** durch ein **Callback-System** gereicht. Dadurch sind Anpassungen und Weiterentwicklungen einfach und **ohne Eingriff im Kernel** möglich.
Neben dem Kernel \Nino stehen bereits die wichtigsten Funktionen einer modernen Webseite (Elemente, Newsletter, Bilduploads, Formulare, Localepicker, Navigationen, u.v.m) als **optionale Module** in \Nino\Modules zur Verfügung - inklusive aller notwendigen CSS- und JS-Codes. Eigene Module können durch das Callback-System ganz einfach entwickelt und integriert werden.

### Die Kernel-Features von Nino.php
- HTTP Request-/Response Handling
- Systemweite Callbacks für flexible Eingriffe
- Dateiverwaltung (Lesen, Schreiben, Caching)
- Verwaltung mehrsprachiger Elemente (wiederkehrende Inhalte und Beiträge)
- Template- und HTML-Rendering mit mehrsprachigen Textfills und Shortcodes
- Benutzerauthentifizierung (inkl. Timeout-Schutz)
- Bild-Upload
- Systemweites Locale-Handling
- Einfaches Mailing
- Fehler-Logging
- Config-Handling
- Modulverwaltung für zusätzliche Funktionen

### Die optionalen Nino-Module
- CSS/JS-Asset-Verwaltung (Rendering und Minifizierung)
- Locale-Picker
- Mehrsprachige JavaScript-Texte
- Navigationsmenüs (inkl. Burger-Menü)
- CSRF-Schutz
- HTML-Formulare
- Newsletter (Double-Opt-in-Anmeldung + Abmeldelink)

## _admin / _editor / _install
Der Nino-Core _nino kümmert sich **ausschließlich um die Darstellung der Seite**. Parallel ergänzen _admin (für Entwickler) und _editor (für Administratoren) das Frontend als grafische Verwaltungstools, und _install bietet einen grafischen, schrittweisen Einrichtungsassistenten für einen frischen Checkout.
Alle drei sind **vollständig optional** und lassen sich bei Bedarf komplett entfernen. Sie ermöglichen eine effektive und schnelle Entwicklung und bieten im Anschluss den Betreibern und Administratoren eine einfache und angenehme Pflege der laufenden Daten - **ganz ohne technisches Knowhow**.
Durch ein durchdachtes **Rechtesystem** erhalten Administrator-Accounts nur den Zugriff, den sie brauchen. Kompaktes Logging, ein integriertes Backup und ein sauberes Sicherheitskonzept runden das Toolset für den Alltag ab.

### Features der _install UI
- Umgebungsprüfung: PHP-Version, benötigte Extensions, Datei-/Ordnerrechte
- Sprachen/Module/Seiten wählen und Startinhalt (Routen, Templates, Texte) aus einer Library zusammensetzen - `_install/library/`, siehe `docs/_install.de.md`
- Theme aus einem Vorschau-Raster wählen - Stylesheet, Webfonts und alles Weitere werden kopiert und eingebunden
- Gesammeltes Befüllen der immer vorhandenen "Basis"-Textinhalte
- Anlegen des/der ersten _editor-Accounts
- Setzen des echten _admin-Passworts (zugleich die Selbstsperre des Assistenten)

### Features der _templates UI
- Beliebige `templates/page-*.tpl` als visuellen Baustein-Baum laden - erkannt an den eigenen CSS-Klassen, ohne Migration und ohne Marker-Attribute
- Baustein-Library als ein Verzeichnis pro Baustein (`_templates/library/`), dieselbe Manifest-Konvention wie `_install`
- Grid-Breiten und Abstände maßstäblich gezeichnet, alles andere als beschriftetes Kästchen, siehe `docs/_templates.de.md`
- Byte-genauer Round-Trip: Ein unverändertes Template zu speichern ändert nichts

### Features der _admin UI
- Erstellen/Bearbeiten/Löschen von Elements-Typen
- Erstellen/Bearbeiten/Löschen von Textfills
- Scan von undefinierten Textfills
- Anlegen von bearbeitbaren Image-Slots für Adminstratoren
- Erstellen/Bearbeiten/Löschen von Administratoren
- Granulares Rechtesystem für Admin-Konten
- Backup-Wiederherstellung
- Bearbeitung der wichtigsten Config-Werten

### Features der _editor UI
- Erstellen/Bearbeiten/Löschen von Elementen (mehrsprachig)
- Bearbeiten von Textfills (mehrsprachig)
- Upload/Austausch der festgelegten Image-Slots
- Passwort-/E-Mail-Änderung von Administrator-Accounts
- Einsicht in Formular-Anfragen
- Einsicht/Löschung und Export von Newsletter-Anmeldungen
- Einsicht in eigene Logs

*Alle Features sind über das Rechtesystem pro Account regelbar*

## Schnellstart

```
php -S 127.0.0.1:8000 router.php
```

Benötigt **PHP 8.4+** mit der `gd`-Erweiterung (Bildzuschnitt/-skalierung) und
`phar.ini`-Unterstützung für `PharData` (wird für die automatischen Backups
in `_editor` benötigt, nicht durch `phar.readonly` eingeschränkt). **Kein
weiteres Setup nötig** - `config.php` im Projektwurzelverzeichnis enthält die
Konfiguration der Website und wird bei jedem Request neu eingelesen. Das
nötige Router-Skript für `_editor`/`_admin`, die Webserver-Konfiguration und
den vollständigen Weg bis zum Go-Live beschreibt **[docs/setup.md](docs/setup.md)**
(Englisch).

`text/`, `templates/` und `elements/` werden **leer** ausgeliefert - es gibt
keinen Startinhalt, bis der Setup-Schritt von `/_install` welchen aus seiner
Library zusammensetzt, oder Sie ihn von Hand anlegen. Frischer Checkout?
Unter `/_install` steht ein grafischer Assistent für die Ersteinrichtung
bereit (Umgebungsprüfung, Content-Setup, Basis-Texte im Bulk, Admins,
_admin-Passwort) - siehe **[docs/_install.de.md](docs/_install.de.md)**. Er
funktioniert nur, bis ein echtes `/_admin`-Passwort gesetzt ist, und lässt sich
genauso gut von Hand durchführen.

## Struktur

```
index.php        Haupt-Einstiegspunkt der Website
config.php        Site-Konfiguration: Routen, Module, Sprachen, Formular-Einstellungen

_nino/           Kernel - Nino.php (Backend), Nino.js/Nino.css/Nino.ui.js (Frontend-Core)
_editor/           Admin-Dashboard - Inhaltseditoren (Werte), Nutzer, Backups, Aktivitätslog
_admin/             Entwickler-Werkzeuge - Element-/Text-/Bild-"Schema"-Editoren, Konfigurationseditor, Wiederherstellung
_install/         Einrichtungsassistent - Prüfungen, Content-Setup (library/), Basis-Texte im
                  Bulk, Admins, _admin-Passwort
_templates/       Template-Builder - visueller Editor für templates/*.tpl, Baustein-Library
                  (library/), abgesichert über das Passwort von _admin

elements/         Inhalte der Element-Typen (eine .php-Datei pro Typ) - standardmäßig leer
text/             Texte je Sprache + global.php (inkl. Design-Tokens) + blacklist.php - standardmäßig leer
templates/        .tpl-Seiten-/Sektions-Templates - standardmäßig leer
assets/           Projekt-eigenes style.css/script.js
images/           Hochgeladene Bilder (admin-verwaltet, bei Bedarf generiert)
docs/             _editor.md, _admin.md, _install.md, _templates.md, design.md, development.md,
                  und je ein .de.md-Gegenstück dazu
tests/            Abhängigkeitsfreie Smoke-Tests (php tests/*.php)
data/             Laufzeitdaten (Newsletter-Abonnenten, Formular-Einsendungen,
                  Logs) - entstehen bei Bedarf, nie in git getrackt
```

## Dokumentation

- **[docs/development.de.md](docs/development.de.md)**
- **[docs/development.md](docs/development.md)** (English)
Alle Informationen für den **Backend-Entwickler**.
Aufbau des Kernels, das AppData-Konzept, das Callback-System, die Init → Request → Output-Pipeline, Templates/Shortcodes/Textfills, das Rechtesystem, die Entwicklung eigener Module. Die Architektur von `_editor`/`_admin`, Testkonventionen.
---
- **[docs/design.de.md](docs/design.de.md)**
- **[docs/design.md](docs/design.md)** (English)
Alle Informationen für den **Frontend-Designer und -Entwickler**. Der Ablauf von HTML-Rendering, die Architektur/Naming-Conventions des integrierten CSS-Frameworks, die Entwicklung eigener Frontend-Elemente und Shortcodes.
---
- **[docs/_editor.de.md](docs/_editor.de.md)**
- **[docs/_editor.md](docs/_editor.md)** (English)
Das **Benutzerhandbuch** für die _editor Administrator- und Benutzeroberfläche.
---
- **[docs/_admin.de.md](docs/_admin.de.md)**
- **[docs/_admin.md](docs/_admin.md)** (English)
Das **Benutzerhandbuch** für die _admin Entwickleroberfläche.
---
- **[docs/_install.de.md](docs/_install.de.md)**
- **[docs/_install.md](docs/_install.md)** (English)
Das **Benutzerhandbuch** für den _install Einrichtungsassistenten.
---
- **[docs/_templates.de.md](docs/_templates.de.md)**
- **[docs/_templates.md](docs/_templates.md)** (English)
Das **Benutzerhandbuch** für den _templates Template-Builder.

## Tests
```
php tests/kernel-smoke.php
php tests/admin-smoke.php
php tests/editor-smoke.php
php tests/install-smoke.php
php tests/templates-smoke.php
php tests/concurrency-smoke.php
```

Jede Datei ist ein **eigenständiges Skript** (kein PHPUnit), das gegen ein
isoliertes Sandbox-Verzeichnis läuft und eine Pass/Fail-Zusammenfassung
ausgibt. CI führt alle fünf bei jedem Push aus.

## Lizenz
[MIT](LICENSE)
