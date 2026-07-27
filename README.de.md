# Nino

*[English](README.md)*

**Hi, iam Nino.** Nino ist ein PHP-Microframework für Webseiten. Es ist unabhängig von Paketen, funktioniert ohne Datenbank und bietet alle essenziellen Funktionen eines modernen Frameworks.
### _nino
Der Nino PHP-Kernel (_nino) bündelt in einer Datei mit nur 3.800 Zeilen PHP-Code alle notwendigen Methoden, zur Darstellung mehrsprachiger Webseiten - inklusive dateibasierter Inhaltsverwaltung, Templating und umfangreicher Backend- und Frontendtools.
Texte und wiederkehrende Inhalte (wie Posts/Nodes) liegen in PHP-Dateien, Templates werden HTML-ähnlich angelegt. Ein Shortcode-System verbindet Logik und Darstellung zu einer dynamischen, flexiblen und leistungsstarken Template-Engine - der Nino-Core kümmert sich vollständig um Sprache/Benutzer und hochperformanter Auslieferung.
Durch ein klares Single-Point-of-Data-Konzept und einem durchdachten Callback-System steht der einfachen Anpassung durch eigene Module nichts im Weg. Die wichtigsten Tools für moderne Mobile-First Webseiten (Elemente, Newsletter, Bilduploads, Formulare, Localepicker, Navigationen, u.v.m) stehen jedoch bereits als Core-Module bereit - inklusive der notwendigen CSS- und JS-Codes.
### _dev / _admin
Der Nino-Core _nino kümmert sich ausschließlich um die Darstellung der Seite. Parallel ergänzen _dev (für Entwickler) und _admin (für Administratoren) das Frontend als grafische Verwaltungstools.
Beide sind vollständig optional und lassen sich bei Bedarf komplett entfernen. Sie ermöglichen eine effektive und schnelle Entwicklung und bieten den Betreibern und Administratoren eine einfache und angenehme Pflege der laufenden Daten - ganz ohne technischem Knowhow.
Durch ein durchdachtes Rechtesystem erhalten Administrator-Accounts nur den Zugriff, den sie brauchen. Kompaktes Logging, ein integriertes Backup und ein saubere Sicherheitskonzept runden das Toolset für den Alltag ab.

## Die Core-Features von Nino.php
- HTTP-Server (Request-Handling und Ausgabe)
- Benutzerauthentifizierung (inkl. Timeout-Schutz)
- Granulares Rechtesystem für Admin-Konten
- Systemweite Callbacks für flexible Eingriffe
- Dateiverwaltung (Lesen, Schreiben, Caching)
- Elementeverwaltung (dynamische mehrsprachige Inhalte, ähnlich Posts/Nodes)
- Template- und HTML-Rendering mit mehrsprachigen Textfills und Shortcodes
- Bild-Upload
- Systemweites Locale-Handling
- Modulverwaltung für zusätzliche Funktionen

## Optionale Nino.php-Module
- CSS/JS-Asset-Verwaltung (Rendering und Minifizierung)
- Locale-Picker
- Mehrsprachige JavaScript-Texte
- Navigationsmenüs (inkl. Burger-Menü)
- CSRF-Schutz
- HTML-Formulare
- Newsletter (Double-Opt-in-Anmeldung + Abmeldelink)

## Schnellstart

```
php -S 127.0.0.1:8000 router.php
```

Benötigt PHP 8.4+ mit der `gd`-Erweiterung (Bildzuschnitt/-skalierung) und
`phar.ini`-Unterstützung für `PharData` (wird für die automatischen Backups
in `_admin` benötigt, nicht durch `phar.readonly` eingeschränkt). Kein
weiteres Setup nötig - `config.php` im Projektwurzelverzeichnis enthält die
Konfiguration der Website und wird bei jedem Request neu eingelesen. Das
nötige Router-Skript für `_admin`/`_dev`, die Webserver-Konfiguration und
den vollständigen Weg bis zum Go-Live beschreibt **[docs/setup.md](docs/setup.md)**
(Englisch).

## Struktur

```
index.php        Haupt-Einstiegspunkt der Website
config.php        Site-Konfiguration: Routen, Module, Sprachen, Formular-Einstellungen

_nino/           Kernel - Nino.php (Backend), Nino.js/Nino.css/Nino.ui.js (Frontend-Core)
_admin/           Admin-Dashboard - Inhaltseditoren (Werte), Nutzer, Backups, Aktivitätslog
_dev/             Entwickler-Werkzeuge - Element-/Text-/Bild-"Schema"-Editoren, Konfigurationseditor, Wiederherstellung

elements/         Inhalte der Element-Typen (eine .php-Datei pro Typ)
text/             Texte je Sprache + global.php (inkl. Design-Tokens) + blacklist.php
templates/        .tpl-Seiten-/Sektions-Templates
assets/           Projekt-eigenes style.css/script.js
images/           Hochgeladene Bilder (admin-verwaltet, bei Bedarf generiert)
docs/             administrator.md, developer.md, modules.md, setup.md,
                  design-system.md
tests/            Abhängigkeitsfreie Smoke-Tests (php tests/*.php)
data/             Laufzeitdaten (Newsletter-Abonnenten, Formular-Einsendungen,
                  Logs) - entstehen bei Bedarf, nie in git getrackt
```

## Dokumentation

Unsicher, wo anfangen? "Ich will Inhalte pflegen" → administrator.md. "Ich
will verstehen/ändern, wie es funktioniert" → developer.md. "Ich will eine
Seite bauen" → design-system.md. "Ich stehe kurz vor dem Go-Live" →
setup.md.

- **[docs/administrator.md](docs/administrator.md)** - die tägliche
  Inhaltspflege über `/_admin` und `/_dev`: Elemente, Texte, Bilder,
  Nutzer, Berechtigungen, Backups, Wiederherstellung, Aktivitätslog. Keine
  Programmierkenntnisse nötig. (Deutsch)
- **[docs/developer.md](docs/developer.md)** - wie der Kernel aufgebaut
  ist: die init → request → output-Pipeline,
  Templates/Shortcodes/Textfills, Callbacks, das Rechtesystem,
  Architektur von `_admin`/`_dev`, Testkonventionen. (Englisch)
- **[docs/modules.md](docs/modules.md)** - eigene Module schreiben: der
  Callback-Mechanismus, jeder systemweite Hook-Punkt, ein durchgespieltes
  Beispiel. (Englisch)
- **[docs/setup.md](docs/setup.md)** - ein frisches Projekt vom leeren
  Checkout bis zur produktiven, live geschalteten Website führen.
  (Englisch)
- **[docs/design-system.md](docs/design-system.md)** - das eingebaute
  Design-System des Frontends (Sektionen, Buttons, Design-Tokens über
  `[[/ui/...]]`-Fills). (Englisch)

## Tests

```
php tests/kernel-smoke.php
php tests/admin-smoke.php
php tests/dev-smoke.php
```

Jede Datei ist ein eigenständiges Skript (kein PHPUnit), das gegen ein
isoliertes Sandbox-Verzeichnis läuft und eine Pass/Fail-Zusammenfassung
ausgibt. CI führt alle drei bei jedem Push aus.

## Lizenz

[MIT](LICENSE)
