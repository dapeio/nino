# Hi, ich bin Nino.

**Sprache:** [English](README.md) · Deutsch

**[Live Demo](https://demo.getnino.dev)** ·

## Erstelle Webseiten, keine Programme.

Nino ist eine schlanke PHP-Grundlage für individuelle Webseiten – ohne Datenbank, fremde Laufzeitabhängigkeiten oder unnötige Komplexität. Entwickler behalten die Kontrolle über Frontend, Templates und Funktionen; Redakteure und Betreiber pflegen Inhalte über ein schlankes GUI.
Nino verliert sich nicht in Möglichkeiten – es bringt Inhalte ins Internet.

Das gesamte Projekt ist jederzeit ein lesbarer, mit Git versionierbarer Dateibestand und läuft auf klassischem PHP-Hosting. Die erste Installation ist in wenigen Minuten abgeschlossen und hinterlässt ein funktionierendes Gerüst für die Weiterentwicklung. Entwickler können es mit den notwendigen Tools füllen und über ein flexibles Callback-System eigene Funktionen hinzufügen.
Mehrsprachigkeit, Beiträge und Elemente, Formulare, Newsletter, Benutzerrechte und Backups sind bereits enthalten.

## Warum noch ein CMS?

Es gibt großartige PHP-Lösungen für Webseiten:
Ausgereifte Systeme wie WordPress decken mit Themes, Plugins und ihrer großen Community nahezu alle Anwendungen ab. Diese Vielseitigkeit ist ihre Stärke – und gleichzeitig ihre größte Schwäche.
Technische Frameworks wie Laravel ermöglichen hochkomplexe Anwendungen, benötigen für klassische Webseiten jedoch ein umfangreicheres, Composer-basiertes Umfeld und zusätzliche Frontend-Werkzeuge.
Webseiten aus reinem HTML/CSS/JavaScript sind dagegen sehr schlank, müssen redaktionelle Funktionen aber immer wieder neu lösen.

**Nino setzt dazwischen an:** als praxisnahe Basis für dynamische Webseiten. Entwickler können Projekte schnell anpassen; Betreiber erhalten eine einfache Oberfläche für die grundlegende Inhaltspflege.

## Für wen ist Nino gedacht?

Nino richtet sich in erster Linie an Webentwickler und kleine Agenturen, die individuelle, klassische Webseiten entwickeln und eventuell an Dritte zur Inhaltspflege übergeben.

Für die Umsetzung sind gute HTML/CSS/JavaScript-Kenntnisse und PHP-Grundkenntnisse sinnvoll. Eigene Module, APIs oder Datenbanken lassen sich integrieren, erfordern aber entsprechend mehr PHP-Erfahrung.

## Die Säulen von Nino

### Frontend – simpel, aber individuell

![Beispiel eines mit Nino erstellten Frontends](docs/assets/screenshots/frontend.webp)

Das Frontend entsteht aus einfachen HTML-basierten Templates, Textfills, Shortcodes und wiederkehrenden Elementen. Nino gibt keine fertige Seitenstruktur vor: Das sichtbare Ergebnis bleibt ein individuelles Projekt.

Das mitgelieferte Design-System mit Basiskomponenten und Modulen bietet einen schnellen Ausgangspunkt für klassische Webseiten. Mehrsprachige Inhalte, Responsive Design, Performance und Sicherheit bleiben dabei Teil des Projekts.

### `/_install` – der schnelle Einstieg zum Projekt

![Umgebungsprüfung im Installationsassistenten](docs/assets/screenshots/install.webp)

Jeder frische Checkout wird über `/_install` eingerichtet. Der Assistent prüft die Umgebung, führt durch Sprachen, Module und Theme, übernimmt die benötigten Assets, legt erste Seiten und Basisinformationen an und erstellt die Zugänge für `/_editor` und `/_admin`.

Danach kann der Entwickler direkt mit dem Projekt beginnen.

### `/_admin` – optional: die GUI für Entwickler

![Dashboard der technischen Projektverwaltung](docs/assets/screenshots/admin.webp)

In `/_admin` verwaltet der Entwickler die technische und inhaltliche Struktur der Webseite: Elementtypen, Elemente und Texte, Bilder, Seiten und Routen, Benutzer sowie die Konfiguration.

Die Oberfläche bietet vollständigen Zugriff für Entwicklung, Diagnose und Korrekturen und bleibt deshalb einem separaten technischen Zugang vorbehalten. Alle Änderungen lassen sich alternativ direkt im Dateisystem vornehmen.

### `/_editor` – optional: Inhalte einfach weiterpflegen

![Dashboard der Redaktionsoberfläche](docs/assets/screenshots/editor.webp)

`/_editor` ist die tägliche Oberfläche für Redakteure und Betreiber. Hier werden Texte, wiederkehrende Inhalte und Bilder gepflegt sowie Formulareingänge, Newsletter-Abonnements, Benutzerrechte und Logs verwaltet.

Der Entwickler legt fest, welche Inhalte sichtbar sind und welche Bereiche ein Account tatsächlich benötigt.

### `/_templates` – optional, Alpha

`/_templates` macht aus `page-*.tpl`-Dateien eine übersichtliche Abfolge vollständiger HTML- und `[template]`-Sections. Entwickler können ein Template anlegen, aus 43 kuratierten Section-Presets wählen, sinnvolle IDs vergeben, Content und Layout konfigurieren und Textfills unmittelbar in der nativen Sprache befüllen oder mit einer Elements-Collection verbinden.

Der Template Builder bewahrt normales HTML+. Alleinstehende Template-Shortcodes lassen sich direkt über **Add section** wählen und bleiben verschiebbare Canvas-Bausteine; Header und Footer sind gewöhnliche `[template]`-Shortcodes, werden aber sicher über feste Template Settings verwaltet. Anzeigename und VPA-Standard stehen als inerte Metadaten am Dateianfang. Sonstiger Quelltext bleibt gesperrt und bytegenau erhalten. Für codebasierte Sections gibt es einen bewussten HTML+-Escape-Hatch. Der frühere DOM-orientierte Builder wurde entfernt.

> **Status: Alpha.** Preset-Manifeste und erzeugtes `.tpl`-Markup bleiben lesbar und erweiterbar; Library und Arbeitsablauf können sich noch weiterentwickeln.

## Was Nino mitbringt

* mehrsprachiges Routing sowie mehrsprachige Texte und Inhalte
* eigenes Template-System mit Shortcodes und klarer Trennung von HTML und PHP
* optionaler sectionbasierter Template Builder für `.tpl`-Dateien (Alpha)
* dateibasiertes Content-Modell für Textfills und wiederkehrende Elemente
* Themes, Asset-Bundling und Frontend-Basiskomponenten
* Formulare, Newsletter, Navigation, Sprachauswahl und Bildverarbeitung
* Benutzer, granulare Rechte, Login-Schutz und Aktivitätenprotokolle
* automatische, verschlüsselte Backups und Wiederherstellung
* integriertes Callback-System für eigene Module und Integrationen

## Nino vs. WordPress, Laravel, Kirby und Grav

Keines dieser Systeme ist grundsätzlich besser als die anderen. Sie beginnen lediglich an unterschiedlichen Stellen und setzen andere Schwerpunkte.

*Stand: August 2026*

| System        | Ansatz                                                                       | Technische Grundlage                                           | Besonders passend für                                                                   |
| ------------- | ---------------------------------------------------------------------------- | -------------------------------------------------------------- | --------------------------------------------------------------------------------------- |
| **Nino**      | Kompaktes Webseiten-Framework mit eigener Redaktionsoberfläche               | PHP und Dateisystem; keine Datenbank und keine externen Pakete | Individuelle, mehrsprachige Webseiten mit klarer Übergabe vom Entwickler an Redakteure  |
| **WordPress** | Universelles, inhaltsorientiertes CMS mit großem Theme- und Plugin-Ökosystem | PHP mit MySQL oder MariaDB                                     | Projekte, die von fertigen Erweiterungen, Themes und einer großen Community profitieren |
| **Laravel**   | Full-Stack-Framework für Webanwendungen                                      | Composer-basiertes PHP-Ökosystem                               | Individuelle Anwendungen, komplexe Geschäftslogik und skalierbare Infrastruktur         |
| **Kirby**     | Flexibles Flat-File-CMS mit ausgereiftem Panel und Plugin-Plattform          | Dateibasierte Inhalte und modernes PHP                         | Maßgeschneiderte Webseiten mit etabliertem Flat-File-Ökosystem                          |
| **Grav**      | Open-Source-Flat-File-CMS mit Themes und Plugins                             | Markdown, Twig, Symfony-Komponenten und Package-Manager        | Datei- und Markdown-orientierte Webseiten mit offenem Erweiterungs-Ökosystem            |

Nino entscheidet sich bewusst für einen kleineren Rahmen: **keine universelle Plugin-Welt, kein abstrakter Anwendungsbaukasten und keine Datenbank.**

Dafür bilden der stabile Kernel, die schnelle Installation, die Entwicklerwerkzeuge und die Redaktionsoberfläche einen zusammenhängenden Ablauf, der speziell auf individuelle Webseiten zugeschnitten ist.

Durch den Verzicht auf ein offenes Plugin-System und fremde Laufzeitpakete reduziert Nino seine Angriffsfläche sowie Update- und Lieferkettenrisiken. Weniger fremder Code und weniger voneinander abhängige Versionsstände machen den installierten Codebestand überschaubarer und leichter prüfbar.

**Das ersetzt keine sichere Entwicklung, verringert jedoch den Update- und Pflegeaufwand deutlich.**

## Schnellstart

Nino benötigt **PHP 8.4 oder neuer** mit der `gd`-Erweiterung und `phar.ini`-Unterstützung für `PharData`. Es wird ohne Paketmanager oder Build-Schritt gestartet:

```bash
git clone https://github.com/dapeio/nino.git
cd nino
php -S 127.0.0.1:8000 router.php
```

Öffne anschließend http://127.0.0.1:8000/_install.

Der Assistent ist für einen frischen Checkout notwendig und erzeugt den ersten lauffähigen Projektstand.

Der vollständige Ablauf steht unter **[Erste Schritte](docs/getting-started.de.md)**. Alle Optionen und Schreibvorgänge erklärt das **[`/_install`-Referenzhandbuch](docs/_install.de.md)**.

## Projektstruktur

```text
index.php        Haupt-Einstiegspunkt der Webseite
config.php       Site-Konfiguration

_nino/           Kernel und Frontend-Core
app/             Projekteigene PHP-Klassen und Laufzeitmodule
_editor/         Inhaltseditor, Nutzer, Backups, Aktivitätslog
_admin/          Entwickler-Werkzeuge und Wiederherstellung
_install/        Einrichtungsassistent
_templates/      Sectionbasierter Template Builder

elements/        Element-Typen
templates/       Seiten- und Sektions-Templates
text/            Texte je Sprache und globale Einstellungen
assets/          Projekt-eigenes CSS und JavaScript
images/          Hochgeladene Bilder
docs/            Dokumentation
data/            Laufzeitdaten, nicht in Git getrackt
```

## Tests

Jede Datei ist ein eigenständiges Skript und läuft gegen ein isoliertes Sandbox-Verzeichnis:

```bash
php tests/kernel-smoke.php
php tests/admin-smoke.php
php tests/editor-smoke.php
php tests/install-smoke.php
php tests/templates-smoke.php
php tests/concurrency-smoke.php
```

## Philosophie und Technik

Nino hält seine Architektur bewusst klein: Ein zentrales `$appData`-Array trägt den Anwendungszustand, `$request` bleibt für HTTP-Anfrage und -Antwort zuständig, und Callbacks verbinden Kernel, Module und Templates.

## Weitere Dokumente:

* **[Grundkonzepte](docs/concepts.de.md):** Architektur, Datenfluss und Aufgabentrennung
* **[Entwickler-Handbuch](docs/development.de.md):** Laufzeitverträge, APIs, Module und Tests
* **[Erste Schritte](docs/getting-started.de.md):** vom Checkout zur eingerichteten Webseite
* **[`/_install`-Referenz](docs/_install.de.md):** Schritte, Schreibregeln und Library-Format
* **[`/_admin`-Bedienung](docs/_admin.de.md):** Projektstruktur, Inhalte, Konten, Konfiguration und Wiederherstellung
* **[`/_templates`-Bedienung](docs/_templates.de.md):** Seitentemplates aus vollständigen HTML- und Template-Sections zusammensetzen
* **[`/_editor`-Bedienung](docs/_editor.de.md):** Texte, Elemente, Bilder, Anfragen und Newsletter
* **[`/_theme`-Bedienung](docs/_theme.de.md):** Theme, Design, Header und Footer nach der Installation bearbeiten
* **[Deployment](docs/deployment.de.md):** Webserver, Sicherheit, Backups und Go-live
* **[Design-Handbuch](docs/design.de.md):** Frontend, Design-System, CSS und Template-Arbeit **(WIP)**
* **[Security Policy](https://github.com/dapeio/nino/blob/main/SECURITY.md):** Sicherheitsmeldungen und unterstützte Versionen
* **[Changelog](https://github.com/dapeio/nino/blob/main/CHANGELOG.md):** Änderungen zwischen den Versionen

## Status und Sicherheit

Nino befindet sich als Gesamtprojekt derzeit in der **Beta-Phase**. Einzelne optionale Werkzeuge besitzen einen eigenen, niedrigeren Reifegrad:

| Bereich                                          | Status                                    |
| ------------------------------------------------ | ----------------------------------------- |
| Kernel, Frontend und bestehende Projektgrundlage | Beta                                      |
| `/_templates`                                    | Alpha                                     |

Sicherheitskorrekturen landen direkt auf `main`; eine separate LTS-Version gibt es noch nicht.

Sicherheitsprobleme sollten nicht als öffentliches Issue gemeldet werden. Kontaktdaten und unterstützter Versionsstand stehen in der [Security Policy](https://github.com/dapeio/nino/blob/main/SECURITY.md).

## Lizenz

[MIT](https://github.com/dapeio/nino/blob/main/LICENSE)
