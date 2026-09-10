# Hi, ich bin Nino.

**Sprache:** [English](README.md) · Deutsch

**[Live Demo](https://demo.getnino.dev)**

## Erstelle Webseiten, keine Programme.

Nino ist eine schlanke PHP-Grundlage für individuelle Webseiten – ohne Datenbank, fremde Laufzeitabhängigkeiten oder unnötige Komplexität. Entwickler behalten die Kontrolle über Frontend, Templates und Funktionen; Redakteure und Betreiber pflegen Inhalte über ein schlankes GUI.
Nino verliert sich nicht in Möglichkeiten – es bringt Inhalte ins Internet.

Das gesamte Projekt ist jederzeit ein lesbarer, mit Git versionierbarer Dateibestand und läuft auf klassischem PHP-Hosting. Die erste Installation ist in wenigen Minuten abgeschlossen und hinterlässt ein funktionierendes Gerüst für die Weiterentwicklung. Entwickler können es mit den notwendigen Tools füllen und über ein flexibles Callback-System eigene Funktionen hinzufügen.
Mehrsprachigkeit, Beiträge und Elemente, Formulare, Benutzerrechte und Backups sind bereits enthalten; Newsletter, Suche und weitere Features kommen aus dem Katalog [dapeio/nino-features](https://github.com/dapeio/nino-features).

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

### `/_admin` – die Workbench

![Textfill-Übersicht in der Nino-Workbench](docs/assets/screenshots/_admin1.webp)

Eine Verwaltungsoberfläche mit einer Anmeldung. Entwickler richten das Projekt hier ein und bauen Struktur und Erscheinungsbild; Redakteure pflegen hier die Inhalte. Jeder Bildschirm ist ein Panel, gruppiert in **Inhalt** (Elemente, Texte, Bilder, Anfragen, Log), **Struktur** (Routen, Navigationen), **Features** (das eigene Panel eines installierten Features, gleich welche Gruppe es selbst nennt) und **System** (Nutzer und Rollen, Sprachen und Übersetzungen, Backups, Konfiguration, Features); die Form der Inhalte – Elementtypen, Textschlüssel, Bildplätze – liegt auf Tabs daneben. Ein Konto hält eine Rolle, eine Rolle eine Berechtigung je Panel oder Tab; der Assistent schreibt Editor und Developer, und ein Panel, das ein Konto nicht verwenden darf, wird nicht gerendert.

Die Workbench bietet vollständigen Zugriff für Entwicklung, Diagnose und Korrekturen und eine schmale, berechtigungsgesteuerte Oberfläche für die tägliche redaktionelle Arbeit. Alle Änderungen lassen sich alternativ direkt im Dateisystem vornehmen. `/_admin/recovery.php` ist der Weg zurück, wenn die Konten selbst kaputt sind.

#### Der Einrichtungsassistent – der schnelle Einstieg zum Projekt

<a href="docs/assets/screenshots/_install1.webp" target="_blank">
  <img src="docs/assets/screenshots/_install1.webp"
       alt="Routenkonfiguration im Nino-Einrichtungsassistenten"
       width="49%">
</a>
<a href="docs/assets/screenshots/_install2.webp" target="_blank">
  <img src="docs/assets/screenshots/_install2.webp"
       alt="Abschlussansicht des Nino-Einrichtungsassistenten"
       width="49%">
</a>

Jeder frische Checkout wird über den Assistenten eingerichtet – das, was `/_admin` zeigt, bis er abgeschlossen ist. Er prüft die Umgebung, führt durch Sprachen und Module, übernimmt das Theme der Base-Einheit samt der benötigten Assets, legt erste Seiten und Basisinformationen an, erstellt die ersten Entwicklerkonten und setzt das Recovery-Passwort. Danach sperrt er sich selbst aus, und `_admin/install/` kann aus einer Produktivauslieferung entfernt werden.

#### Der Template-Baukasten – ein Feature, Alpha

<a href="docs/assets/screenshots/_templates1.webp" target="_blank">
  <img src="docs/assets/screenshots/_templates1.webp"
       alt="Template-Übersicht und Section-Canvas im Nino Template Builder"
       width="31%">
</a>
<a href="docs/assets/screenshots/_templates2.webp" target="_blank">
  <img src="docs/assets/screenshots/_templates2.webp"
       alt="Section-Preset-Bibliothek im Nino Template Builder"
       width="31%">
</a>
<a href="docs/assets/screenshots/_templates3.webp" target="_blank">
  <img src="docs/assets/screenshots/_templates3.webp"
       alt="Konfiguration einer Articles-Section mit Live-Vorschau"
       width="31%">
</a>

Der Template Builder macht aus `page-*.tpl`-Dateien eine übersichtliche Abfolge vollständiger HTML- und `[template]`-Sections. Entwickler können ein Template anlegen, aus verschiedenen Section-Presets wählen, sinnvolle IDs vergeben, Content und Layout konfigurieren und Textfills unmittelbar in der nativen Sprache befüllen oder mit einer Elements-Collection verbinden. Er ist ein Workspace-Panel: Die Leiste klappt zu ihren Symbolen zusammen, und Templateliste, Section-Canvas und Inspektor teilen sich die ganze Breite.

Der Template Builder bewahrt normales HTML+. Alleinstehende Template-Shortcodes lassen sich direkt über **Add section** wählen und bleiben verschiebbare Canvas-Bausteine; Header und Footer sind gewöhnliche `[template]`-Shortcodes, werden aber sicher über feste Template Settings verwaltet. Anzeigename und VPA-Standard stehen als inerte Metadaten am Dateianfang. Sonstiger Quelltext bleibt gesperrt und bytegenau erhalten. Für codebasierte Sections gibt es einen bewussten HTML+-Escape-Hatch.

> **Status: Alpha.** Preset-Manifeste und erzeugtes `.tpl`-Markup bleiben lesbar und erweiterbar; Library und Arbeitsablauf können sich noch weiterentwickeln. Das Panel gehört zum Template-Baukasten-Feature aus dem Katalog [dapeio/nino-features](https://github.com/dapeio/nino-features) und ist da, solange das Feature nach `features/` kopiert und im Panel Features eingeschaltet ist.

## Was Nino mitbringt

* mehrsprachiges Routing sowie mehrsprachige Texte und Inhalte
* eigenes Template-System mit Shortcodes und klarer Trennung von HTML und PHP
* optionaler sectionbasierter Template Builder für `.tpl`-Dateien (Alpha)
* dateibasiertes Content-Modell für Textfills und wiederkehrende Elemente
* ein festes Theme, Asset-Bundling und Frontend-Basiskomponenten
* Formulare, Navigation, Sprachauswahl und Bildverarbeitung
* installierbare Features mit Manifest, Einstellungen und versionierten Updates, eingeschaltet in der Workbench – Newsletter und Suche darunter, aus dem Katalog [dapeio/nino-features](https://github.com/dapeio/nino-features)
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

Nino benötigt **PHP 8.4 oder neuer** mit den Erweiterungen `gd`, `mbstring`, `session` und `json` sowie der von `Phar` bereitgestellten Klasse `PharData`. Es wird ohne Paketmanager oder Build-Schritt gestartet:

```bash
git clone https://github.com/dapeio/nino.git
cd nino
php -S 127.0.0.1:8000 router.php
```

Öffne anschließend <http://127.0.0.1:8000/_admin>.

Der Assistent ist für einen frischen Checkout notwendig und erzeugt den ersten lauffähigen Projektstand.

Der vollständige Ablauf steht unter **[Erste Schritte](docs/getting-started.de.md)**. Alle Optionen und Schreibvorgänge erklärt die Referenz **[Einrichtungsassistent](docs/setup.de.md)**.

## Projektstruktur

Ein Checkout enthält nur Code. Alles unterhalb von `private/` und `public/`
ist der Zustand einer einzelnen Installation, wird vom Assistenten angelegt
und von niemandem getrackt:

```text
index.php        Haupt-Einstiegspunkt der Webseite
router.php       Routing für den eingebauten Server, lokale Entwicklung

_nino/           Kernel und Frontend-Core, eine Klasse je Datei unter _nino/Nino/,
                 mit jedem Modul, das Nino unter _nino/Nino/Modules/ mitbringt:
                 denen, die jedes Projekt braucht, und den optionalen Form,
                 Navigation und Localepicker, die in /nino/modules ein- oder
                 ausgeschaltet werden
app/             Projekteigene PHP-Klassen unter eigenem Namespace
features/        Die Features, die ein Projekt installiert, je ein Verzeichnis
                 mit feature.php-Manifest, aus dem signierten Katalog von
                 github.com/dapeio/nino-features im Panel Features der
                 Workbench installiert oder von Hand kopiert, und dort
                 eingeschaltet - ein Checkout bringt keines mit
_admin/          Die Workbench: nur die Shell, recovery.php, ihre eigenen
                 Ansichten als Module unter _admin/Nino/Modules/ (Dashboard,
                 Elements, Text, Images, Logs, Routes, Users, Language,
                 Backups, Config) und der Einrichtungsassistent mit seiner
                 Bibliothek unter _admin/install/
docs/            Dokumentation, mit den Erweiterungsrezepten unter docs/recipes/

private/         Wird nie ausgeliefert, nur von PHP gelesen - vom Assistenten angelegt
  config.php       Site-Konfiguration
  templates/       Seiten- und Sektions-Templates
  text/            Texte je Sprache und globale Einstellungen
  elements/        Element-Typen
  assets/          Projekt-eigenes CSS und JavaScript, gebündelt nach public/.cache/
  data/            Laufzeitdaten

public/          Alles, was ein Browser direkt lädt - vom Assistenten angelegt
  images/          Hochgeladene Bilder
  fonts/           Webfonts, die das Theme deklariert
  favicon/         Der erzeugte Favicon-Satz
  .cache/          Die CSS- und JS-Bundles, gebaut aus private/assets/
```

## Tests

Jede Datei ist ein eigenständiges Skript und läuft gegen ein isoliertes Sandbox-Verzeichnis:

```bash
php tests/kernel-smoke.php
php tests/admin-smoke.php
php tests/admin-system-smoke.php
php tests/install-smoke.php
php tests/features-smoke.php
php tests/catalogue-smoke.php
for test in features/*/tests/*-smoke.php; do [ -e "$test" ] && php "$test"; done
for test in tests/*-js-smoke.js; do node "$test"; done
php tests/concurrency-smoke.php
```

`tests/harness.php` ist der gemeinsame Bootstrap; der eigene Test eines
Features liegt in dessen Verzeichnis und lädt ihn von dort.

Daneben läuft statische Analyse, ohne Paketmanager: PHPStan liest
`phpstan.neon`, ESLint `eslint.config.mjs`. `phpstan-baseline.neon` führt die
Funde, die beim Einführen der Prüfung offen waren, sodass nur Neues
fehlschlägt. CI führt beide nach den Syntaxchecks aus.

```bash
phpstan analyse
npx eslint .
```

## Philosophie und Technik

Nino hält seine Architektur bewusst klein: Ein zentrales `$appData`-Array trägt den Anwendungszustand, `$request` bleibt für HTTP-Anfrage und -Antwort zuständig, und Callbacks verbinden Kernel, Module und Templates.

## Weitere Dokumente

* **[Grundkonzepte](docs/concepts.de.md):** Architektur, Datenfluss und Aufgabentrennung
* **[Entwickler-Handbuch](docs/development.de.md):** Laufzeitverträge, APIs, Module und Tests
* **[Erweiterungsrezepte](docs/recipes/README.md):** ein Panel, ein Laufzeitmodul, ein Installer-Paket, ein Section-Preset, Templates und Seiten-Units, Elementtypen, ein Feature - Schritt für Schritt (englisch)
* **[Erste Schritte](docs/getting-started.de.md):** vom Checkout zur eingerichteten Webseite
* **[Einrichtungsassistent](docs/setup.de.md):** Schritte, Schreibregeln und Library-Format
* **[`/_admin`-Workbench](docs/_admin.de.md):** jedes Panel, Konten, Rollen, Konfiguration, Backups und Recovery
* **[Template-Baukasten](https://github.com/dapeio/nino-features/blob/main/features/Templates/docs/templates.de.md):** Seitentemplates aus vollständigen HTML- und Template-Sections zusammensetzen – ein Feature aus dem Katalog [dapeio/nino-features](https://github.com/dapeio/nino-features)
* **[Features](docs/features.de.md):** installierbare Pakete – Manifest, Einstellungen, Aktivierung und Updates
* **[Feature-Katalog](https://github.com/dapeio/nino-features):** die Features, die Nino veröffentlicht – Newsletter und Suche darunter –, installiert durch Kopieren eines Verzeichnisses nach `features/`
* **[Deployment](docs/deployment.de.md):** Webserver, Sicherheit, Backups und Go-live
* **[Design-Handbuch](docs/design.de.md):** Frontend, Design-System, CSS und Template-Arbeit **(WIP)**
* **[Security Policy](https://github.com/dapeio/nino/blob/main/SECURITY.md):** Sicherheitsmeldungen und unterstützte Versionen
* **[Changelog](https://github.com/dapeio/nino/blob/main/CHANGELOG.md):** Änderungen zwischen den Versionen

## Status und Sicherheit

Nino befindet sich als Gesamtprojekt derzeit in der **Beta-Phase**. Einzelne optionale Werkzeuge besitzen einen eigenen, niedrigeren Reifegrad:

| Bereich                                          | Status                                    |
| ------------------------------------------------ | ----------------------------------------- |
| Kernel, Frontend, Workbench und bestehende Projektgrundlage | Beta                           |
| Template-Baukasten (Feature aus dem [Katalog](https://github.com/dapeio/nino-features)) | Alpha                                     |

Sicherheitskorrekturen landen direkt auf `main`; eine separate LTS-Version gibt es noch nicht.

Sicherheitsprobleme sollten nicht als öffentliches Issue gemeldet werden. Kontaktdaten und unterstützter Versionsstand stehen in der [Security Policy](https://github.com/dapeio/nino/blob/main/SECURITY.md).

## Lizenz

[MIT](https://github.com/dapeio/nino/blob/main/LICENSE)
