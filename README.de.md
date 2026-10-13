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

Jeder frische Checkout wird über den Assistenten eingerichtet – das, was `/_admin` zeigt, bis er abgeschlossen ist. Er prüft die Umgebung, führt durch die Sprachen (und die Module, die ein Projekt selbst hinzugefügt hat, falls es welche gibt), übernimmt das Theme der Base-Einheit samt der benötigten Assets, legt erste Seiten und Basisinformationen an, erstellt die ersten Entwicklerkonten und setzt das Recovery-Passwort. Danach sperrt er sich selbst aus, und `_admin/install/` kann aus einer Produktivauslieferung entfernt werden.

#### Der Builder – ein Feature

Der Builder setzt ein Seitentemplate aus Sections, Zeilen, Spalten und Komponenten zusammen und schreibt es als die `page-*.tpl`-Datei zurück, die er gelesen hat: Die Datei ist die Wahrheit, und was der Builder nicht liest, lässt er Byte für Byte stehen. Sein Panel steht in der Gruppe Features der Leiste und ist ein Workspace-Panel – die Leiste klappt zu ihren Symbolen zusammen, und die Vorschau, die zugleich der Editor ist und an jedem Rahmen Werkzeuge trägt, nimmt die ganze Breite ein. Eine Komponente ist ein Shortcode mit Schema; das Kernel-Modul `Components` ist das Register, das der Builder liest, und Features melden ihre eigenen in ihrem Manifest an.

## Was Nino mitbringt

* mehrsprachiges Routing sowie mehrsprachige Texte und Inhalte
* eigenes Template-System mit Shortcodes und klarer Trennung von HTML und PHP
* eine Workbench für Entwickler und Redakteure, mit Rollen, Recovery und einem optionalen Builder für `.tpl`-Dateien, einem Feature aus dem Katalog
* dateibasiertes Content-Modell für Textfills und wiederkehrende Elemente
* ein festes Theme, Asset-Bundling und Frontend-Basiskomponenten
* Formulare, Navigation, Sprachauswahl und Bildverarbeitung
* Impressum und Datenschutzerklärung als bearbeitbarer Startinhalt, ein Element je Abschnitt, auf Deutsch und Englisch, mit den Angaben der Firma als Platzhalter
* installierbare Features mit Manifest, Einstellungen und versionierten Updates, eingeschaltet in der Workbench – Newsletter und Suche darunter, aus dem Katalog [dapeio/nino-features](https://github.com/dapeio/nino-features)
* Benutzer, granulare Rechte, Login-Schutz und Aktivitätenprotokolle
* automatische, verschlüsselte Backups und Wiederherstellung
* integriertes Callback-System für eigene Module und Integrationen

**Wichtig:** Impressum und Datenschutzerklärung, die Nino mitliefert, sind ein Ausgangspunkt und keine Rechtsberatung. Die Texte sind auf keine bestimmte Website zugeschnitten und nicht rechtlich geprüft. Der Betreiber einer Website ist dafür verantwortlich, sie vor der Veröffentlichung fachkundig prüfen zu lassen und anzupassen: an das, was die Website tatsächlich verarbeitet, an seine Rechtsform und an weitere Pflichtangaben wie Registereintrag, Umsatzsteuer-Identifikationsnummer oder eine für journalistische Inhalte verantwortliche Person. Für Richtigkeit, Vollständigkeit und Aktualität der Texte übernimmt das Projekt keine Gewähr.

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

Nino benötigt **PHP 8.4 oder neuer** mit den Erweiterungen `gd`, `mbstring` und `session` sowie der von `Phar` bereitgestellten Klasse `PharData`. Es wird ohne Paketmanager oder Build-Schritt gestartet:

```bash
git clone https://github.com/dapeio/nino.git my-website
cd my-website
php -S 127.0.0.1:8000 router.php
```

Öffne anschließend <http://127.0.0.1:8000/_admin>. `router.php` ist das Routing des eingebauten PHP-Servers und nur für die lokale Arbeit; ein Webserver braucht die Konfiguration aus dem [Deployment](docs/deployment.md#webroot-and-routing) (englisch).

Ein frischer Checkout ist noch kein Projekt. Der Einrichtungsassistent, den `/_admin` zeigt, bis er abgeschlossen ist, legt `private/` und `public/` mit allem Inhalt an. Er hat keinen eigenen Zugriffsschutz, und noch existiert kein Konto; führe ihn deshalb lokal oder in einer anderen geschützten Umgebung aus. Solange er nicht abgeschlossen ist, kannst Du zu einem früheren Schritt zurückkehren und ihn erneut anwenden. Die sechs Schritte, jeder mit seiner Referenz im Handbuch [Setup Wizard](docs/setup.md) (englisch):

| Schritt | Entscheidung |
|---|---|
| [1. Environment](docs/setup.md#1-environment) | Sind PHP, Erweiterungen und Schreibrechte einsatzbereit? |
| [2. Languages](docs/setup.md#2-languages) | Welche Sprachen braucht das Projekt – und welche Module, falls das Projekt eigene mitbringt? |
| [3. Routes](docs/setup.md#3-routes) | Welche ersten Seiten, öffentlichen Pfade und Metadaten werden angelegt? |
| [4. Personal Information](docs/setup.md#4-personal-information) | Welche zentralen Firmen- und Webseitenwerte stehen als Textfills bereit? |
| [5. Accounts](docs/setup.md#5-accounts) | Welche Entwicklerkonten melden sich mit vollem Zugriff an der Workbench an? |
| [6. Finish](docs/setup.md#6-finish) | Welches Recovery-Passwort öffnet `/_admin/recovery.php`, wenn die Konten kaputt sind – und sperrt den Assistenten? |

Das Erscheinungsbild gehört nicht zu seinen Fragen: Die Base-Einheit liefert ein Theme und die beiden Rahmen-Templates, gegen die es gezeichnet ist ([The Look](docs/setup.md#the-look)). Redakteurskonten entstehen später im Panel Users der Workbench.

Prüfe danach das Ergebnis. `/` liefert die eingerichtete Webseite, und `/_admin` öffnet die Workbench mit jedem Panel für das Root-Konto. Sieh Dir jede Sprache und Route, die Navigation und die Formulare an und speichere zur Probe einen Text und ein Bild. Ist der Assistent abgeschlossen, entferne `_admin/install/` aus einer Produktivauslieferung; alles, was er kopiert hat, bleibt dort, wo er es geschrieben hat. Reihenfolge und Prüfungen stehen unter [Verify the Result and Remove the Wizard](docs/setup.md#verify-the-result-and-remove-the-wizard).

## Philosophie und Technik

Nino hält seine Architektur bewusst klein: Ein zentrales `$appData`-Array trägt den Anwendungszustand, `$request` bleibt für HTTP-Anfrage und -Antwort zuständig, und Callbacks verbinden Kernel, Module und Templates.

## Dokumentation

Die Handbücher und die Rezepte sind englisch. Der Agent-Leitfaden und die Rezepte sind der Arbeitstext dessen, der Nino erweitert.

**Handbücher**

* **[Developer Manual](docs/development.md):** wie Nino läuft – Anfrage, Routing, Callbacks, Rendering, Module und Sicherheit
* **[Setup Wizard](docs/setup.md):** die sechs Schritte, was sie schreiben, und das Library-Format
* **[`/_admin` Workbench](docs/_admin.md):** Rollen und Rechte, Recovery und Fehlersuche
* **[Features](docs/features.md):** installierbare Pakete – Panel, Manifest, Lebenszyklus und Katalog
* **[Deployment](docs/deployment.md):** Webserver, Schreibrechte, Produktionseinstellungen, Tests und Go-live
* **[Agent-Leitfaden](AGENTS.md):** die Regeln, die jede Änderung einhält, und das Verfahren, nach dem sie entsteht

**Rezepte** ([Übersicht](docs/recipes/README.md))

* **[Add a panel to the workbench](docs/recipes/admin-panel.md):** ein neuer Bildschirm in `/_admin`
* **[Add a runtime module](docs/recipes/runtime-module.md):** PHP-Verhalten beim Start, ein Shortcode, ein Endpunkt
* **[Add a component](docs/recipes/component.md):** ein Shortcode mit Schema oder ein Stack, der Elemente durchläuft
* **[Add an installer module package](docs/recipes/installer-package.md):** ein Modul, das der Assistent auswählen kann
* **[Define Element types](docs/recipes/element-types.md):** wiederkehrende strukturierte Inhalte
* **[Package a feature](docs/recipes/feature.md):** ein Modul als ein Verzeichnis mit Manifest, Einstellungen und Version

**Anderswo**

* **[Builder](https://github.com/dapeio/nino-features/tree/main/features/Builder):** Seitentemplates aus Sections, Spalten und Komponenten zusammensetzen – ein Feature aus dem Katalog [dapeio/nino-features](https://github.com/dapeio/nino-features), nach `features/` kopiert und im Panel Features eingeschaltet; sein Handbuch ist das der `feature.php`
* **[Feature-Katalog](https://github.com/dapeio/nino-features):** die Features, die Nino veröffentlicht – Newsletter und Suche darunter –, installiert aus dem Panel Features oder durch Kopieren eines Verzeichnisses nach `features/`
* **[Security Policy](https://github.com/dapeio/nino/blob/main/SECURITY.md):** Sicherheitsmeldungen und unterstützte Versionen
* **[Changelog](history/CHANGELOG.md):** das Archiv der Änderungen bis 1.7.0; danach ist die Geschichte das Git-Log (englisch)

## Status und Sicherheit

Kernel, Frontend, Workbench und Projektgrundlage von Nino sind **veröffentlicht** – 1.3.0 ist das erste Release. Ein Feature trägt seinen eigenen Reifegrad, den das Panel Features als Badge neben dem Namen zeigt.

Sicherheitskorrekturen landen direkt auf `main`; eine separate LTS-Version gibt es noch nicht.

Sicherheitsprobleme sollten nicht als öffentliches Issue gemeldet werden. Kontaktdaten und unterstützter Versionsstand stehen in der [Security Policy](https://github.com/dapeio/nino/blob/main/SECURITY.md).

## Lizenz

[MIT](https://github.com/dapeio/nino/blob/main/LICENSE). Die Icons der Workbench stammen von
[Lucide](https://lucide.dev) (ISC, die aus Feather übernommenen zusätzlich MIT) und die Schrift, die die Base-Einheit
ausliefert, ist [Open Sans](https://github.com/googlefonts/opensans) (SIL Open Font License 1.1); ihre Lizenzhinweise stehen in
[THIRD-PARTY-NOTICES.md](https://github.com/dapeio/nino/blob/main/THIRD-PARTY-NOTICES.md).
