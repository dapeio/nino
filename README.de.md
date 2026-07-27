# Nino

*[English](README.md)*

**Nino** ist ein PHP-Microframework für kleine und mittlere Websites. Es arbeitet vollständig dateibasiert und bietet alle essenziellen Funktionen ohne externe Pakete oder Abhängigkeiten.
Der Nino.php-Kernel bündelt in einer einzigen Datei mit rund 3.800 Zeilen PHP-Code alle notwendigen Methoden, um mehrsprachige Websites mit Templating und dateibasierter Inhaltsverwaltung auszugeben.
Texte und wiederkehrende Inhalte (wie Posts/Nodes) werden in PHP-Array-Dateien gespeichert, Templates liegen in HTML-ähnlichen .tpl-Dateien. Ein Callback-, Textfill- und Shortcode-System stellt eine kompakte, aber dynamisch flexible und leistungsstarke Template-Engine bereit - die vollständige Callback-Referenz steht in `docs/modules.md`.
Darstellung und Logik sind immer strikt voneinander getrennt, und ein granulares Rechtesystem (`docs/developer.md`, Abschnitt "Permission system") erlaubt es, ein Admin-Konto auf genau die benötigten Module zu beschränken, statt Alles-oder-nichts-Zugriff zu vergeben.
Die optionalen Pakete `_dev` (für Entwickler) und `_admin` (für Administratoren) ergänzen das Frontend um eine einfache grafische Verwaltungsanwendung - beide sind vollständig optional und lassen sich bei Bedarf komplett entfernen.
Für das Frontend stehen vollständige, moderne Start-Themes sowie umfangreiche JavaScript- und CSS-Tools zur Verfügung - auch hier vollständig ohne Abhängigkeiten.

## Core-Features von Nino.php
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