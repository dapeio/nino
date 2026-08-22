# Grundkonzepte

**Sprache:** [English](concepts.md) · Deutsch

**Stand:** 21. August 2026 · **Nino-Version:** 0.11.0-beta.1

Dieses Handbuch erklärt die Architektur von Nino und das Zusammenspiel von Konfiguration, Daten, Templates und Modulen. Falls du stattdessen direkt eine Webseite einrichten möchtest, beginne mit [Erste Schritte](getting-started.de.md); konkrete APIs und Implementierungsdetails stehen im [Entwickler-Handbuch](development.de.md).

**Weitere Links:**
[README](../README.de.md) · [Grundkonzepte](concepts.de.md) · [Entwickler-Handbuch](development.de.md) · [Erste Schritte](getting-started.de.md) · [`/_install`-Referenz](_install.de.md) · [`/_admin`-Bedienung](_admin.de.md) · [`/_templates`-Bedienung](_templates.de.md) · [`/_editor`-Bedienung](_editor.de.md) · [`/_design`-Bedienung](_design.de.md) · [Deployment](deployment.de.md) · [Security Policy](https://github.com/dapeio/nino/blob/main/SECURITY.md) · [Changelog](https://github.com/dapeio/nino/blob/main/CHANGELOG.md)

## Kernsäulen
Nino organisiert eine Webseite mit nur wenigen, aber klar getrennten Bausteinen:

- `config.php` definiert die technische Struktur der Webseite;
- `.php`-Dateien unter `text/` und `elements/` enthalten mehrsprachige redaktionelle Daten;
- `.tpl`-Dateien unter `templates/` liefern das HTML;
- `data/` enthält die Bewegungsdaten des laufenden Betriebs;
- PHP-Module ergänzen den Ablauf über Callbacks.

Die folgenden Pfade beschreiben den eingerichteten Projektstand; vor dem Abschluss von `/_install` existieren sie noch nicht.

Die Architektur folgt vier Grundentscheidungen:

| Säule | Technische Konsequenz |
|---|---|
| **Keine Abhängigkeiten** | Nino benötigt keine externen Laufzeitpakete, keinen Composer-Bootstrap und keine Datenbank. Kernel und mitgelieferte Module gehören direkt zum Projekt; benötigt werden nur PHP und die dokumentierten Erweiterungen. |
| **Dateibasiertes Projekt** | Konfiguration, Texte, Elemente und Templates liegen als lesbare Dateien vor; auch Assets bleiben Teil des Dateisystems. Das Projekt lässt sich gemeinsam übertragen, mit Git versionieren und ohne Datenbankwerkzeug prüfen. |
| **Zentraler Anwendungszustand (`$appData`)** | Der zentrale Anwendungszustand liegt in einem Array. `init()` erzeugt `$appData` einmal; Kernel und Callbacks erhalten es anschließend per Referenz und verändern gezielt ihren jeweiligen Bereich. Die HTTP-Anfrage und die entstehende Antwort bleiben davon getrennt in `$request`. |
| **Trennung von Logik und Darstellung** | `.tpl`-Dateien enthalten HTML, Textfills und Shortcodes, aber kein PHP. Module und Callbacks übernehmen die Logik; Shortcodes bilden die kontrollierte Verbindung zum Template. |

Diese Entscheidungen greifen ineinander: Dateien liefern den dauerhaften Projektstand, `$appData` bündelt den Zustand einer Anfrage und ein fester Ablauf verarbeitet daraus die HTTP-Antwort.

Der dateibasierte Ansatz ist bewusst auf klassische Webseiten und überschaubare strukturierte Inhalte ausgelegt. Große relationale Datenbestände, komplexe Abfragen oder transaktionale Prozesse gehören in eine Datenbank oder einen externen Dienst, den ein projektspezifisches Modul anbinden kann.

## Datenfluss einer Anfrage

Eine HTTP-Anfrage beginnt in `index.php` und durchläuft immer die drei gleichen Schritte:

```php
$appData = \Nino\init();
$request = \Nino\request( $appData, $_SERVER );
\Nino\output( $appData, $request );
```

| Schritt | Aufgabe |
|---|---|
| `init()` | bereitet Laufzeitwerte vor, lädt `config.php`, initialisiert Sprache, Session und Schutzmechanismen und ruft die aktivierten Module auf |
| `request()` | normalisiert die HTTP-Anfrage, löst die Route auf, reicht `$appData` und `$request` durch Callbacks und rendert den Response-Body |
| `output()` | setzt Status und Header, serialisiert bei Bedarf JSON und sendet die Antwort |

`$appData` enthält Konfiguration, Module, Callback-Registry und Laufzeitwerte. `$request` enthält dagegen die normalisierte HTTP-Anfrage unter `/nino/http/request` und die entstehende Antwort unter `/nino/http/response`.

Es gibt keinen zusätzlichen Dependency-Injection-Container. Statt eines Objektgraphen erhalten Kernel-Funktionen und Callbacks `$appData` sowie – soweit erforderlich – `$request` und verändern darin nur ihren jeweiligen Teil des Ablaufs.

## `$appData`: stabile und temporäre Schlüssel

Schlüssel in `$appData` folgen einer Pfadkonvention:

```php
$routes = $appData['/nino/http/routes'] ?? [];
$locale = $appData['./nino/locales/current'] ?? '';
```

| Schreibweise | Bedeutung | Beispiel |
|---|---|---|
| `/…` | stabil benannter Projekt- oder Kernelwert | `/nino/locales/native` |
| `./…` | temporärer Wert der aktuellen Anfrage | `./nino/locales/current` |

Die Konvention unterscheidet stabil benannte Anwendungswerte von ausdrücklich temporären Laufzeitwerten.

**Wichtig:** Auch ein stabil benannter Wert wird nicht automatisch gespeichert. Änderungen an Werten aus `config.php` gelten zunächst nur für den aktuellen Request; erst `\Nino\AppData::writeContentData()` schreibt sie zurück. Textfills und Elemente besitzen eigene Datei-APIs.

## Routing

Routen stehen in `config.php` unter `/nino/http/routes`. Der Array-Key verbindet HTTP-Methode und Browserpfad:

```php
'/nino/http/routes' => [
    'GET://' => [
        'uri'  => '/home',
        'body' => '[template /templates/page-home]',
    ],
],
```

`GET://` bezeichnet die Startseite: `GET` ist die Methode, der zweite Slash die HTTP-URI `/`. Für `/contact` lautet der Schlüssel entsprechend `GET://contact`.

Die Route trennt die öffentliche Adresse von der internen Seitenidentität. Die Startseite ist im Browser unter `/` erreichbar, kann intern aber `/home` heißen. Textschlüssel wie `/webpage/home/title` bleiben dadurch stabil, wenn sich nur der öffentliche Pfad ändert.

Neben `uri` und `body` kann eine Route beispielsweise Statuscode, Header oder eine feste Sprache enthalten. Gibt es keinen Treffer für eine Anfrage, verwendet Nino die konfigurierte Route für `/404`.

## Dauerhafte Projektdaten

Nino trennt persistente Daten nach ihrer Aufgabe:

| Ort im eingerichteten Projekt | Inhalt |
|---|---|
| `config.php` | Routen, Module, Sprachen, Nutzer und technische Optionen |
| `text/global.php` | sprachunabhängige Textfills |
| `text/<locale>.php` | Textfills einer Sprache |
| `elements/*.php` | Typmodelle und Einträge wiederkehrender Inhalte |
| `templates/*.tpl` | HTML-Struktur für Seiten, Komponenten und E-Mails |
| `images/` und `assets/` | redaktionelle Bilder und statische Frontend-Dateien |
| `data/` | Formulareingänge, Newsletter-Daten und weitere Bewegungsdaten, die während des Betriebs geschrieben werden |

Ein Seitentitel gehört damit in `text/`, eine Route in `config.php`, Newsbeiträge, ein Portfolio oder Teammitglieder in `elements/` und die sichtbare HTML-Struktur in `templates/`.

## Templates und Rendering

Der Route-Body aus `config.php` ist zunächst eine einfache Zeichenkette. Vor der Ausgabe wird sie durch die Nino-Template-Engine gerendert. Mit dem zugehörigen Shortcode kann sie auf eine Template-Datei verweisen:

```text
[template /templates/page-home]
```

Die geladene `.tpl`-Datei kann neben HTML auch wieder Shortcodes und Textfills enthalten:

```html
[template /templates/html-header]
<main>
  <h1>[[/webpage/home/title]]</h1>

  [elements /services]
    <article>
      <h2>[[title]]</h2>
      <p>[[description]]</p>
    </article>
  [/elements]
</main>
[template /templates/html-footer]
```

So können mehrere Template-Dateien zu einer komplexen HTML-Struktur zusammengesetzt werden.

Seitentemplates lassen sich über den optionalen [Template Builder `/_templates`](_templates.de.md) aus vollständigen HTML- und `[template]`-Sections zusammensetzen. Er speichert normales, lesbares `.tpl`-Markup und ersetzt nicht die Prüfung der fertigen Webseite im Browser; tiefergehende Strukturarbeit bleibt über HTML+ oder Code möglich.

Nino verarbeitet einen HTML-String bei jedem Rendering-Durchlauf in einer festen Reihenfolge:

1. Textfills werden ersetzt (`[[/webpage/home/title]]`).
2. Registrierte Shortcodes werden ausgeführt (`[template /templates/html-header]`).
3. Registrierte Callbacks unter `/nino/html/render` erhalten das Ergebnis.

**Textfills** sind einzelne globale oder sprachabhängige Werte. Sie eignen sich für Seitentitel, Beschreibungen und andere Textinhalte an einer festen Stelle.

**Elemente** bilden wiederkehrende Inhalte nach einem vordefinierten Typmodell ab, beispielsweise Leistungen, Teammitglieder oder Referenzen. Entwickler definieren die Felder und können sämtliche Einträge über `/_admin` bearbeiten; Redakteure pflegen die für sie freigegebenen Einträge über `/_editor`.

**Shortcodes** verbinden Templates bei Bedarf mit dynamischer Logik. Sie laden beispielsweise ein Template, geben Elemente aus oder rufen eine projektspezifische Funktion auf. Entwickler können eigene Shortcodes registrieren; ihre technische Callback-Signatur beschreibt das Entwickler-Handbuch. Die Ausgabe kann wiederum Textfills und weitere Shortcodes enthalten.

## Callbacks und Module

Callbacks sind der gemeinsame Erweiterungsmechanismus von Nino. Ein benannter Callback-Pfad verdeutlicht, an welcher Stelle eine registrierte Funktion ausgeführt wird. `$appData` und der übergebene Callback-Parameter werden beim Aufruf durch alle registrierten Funktionen gereicht.

Ein Modul bündelt die Callbacks, Shortcodes und Modifikationen eines einzelnen Features. In der Entwicklung sollten Erweiterungen daher sinnvoll auf einzelne Module verteilt werden. Aktivierte Modulklassen stehen in `config.php` unter `/nino/modules` und werden während `init()` aufgerufen. Navigation, Sprachauswahl, Formulare und die Template-Anbindung folgen demselben Prinzip.

Projektspezifische Funktionen verwenden eigene Callback-Pfade und Module,
statt Kerneldateien zu verändern. Ihre PHP-Klassen gehören unter einem eigenen
Projekt-Namespace nach `app/`; die kerneigenen Klassen unter `Nino\` bleiben in
`_nino/`. So bleibt die Erweiterung Teil des konkreten Projekts, `_nino/` kann
ersetzt werden und es entsteht kein zweites Hook- oder Plugin-System.

## `/_admin`, `/_design`, `/_templates` und `/_editor`

Die optionalen Oberflächen besitzen eigene Einstiegspunkte und sind keine Frontend-Module aus `/nino/modules`:

| Oberfläche | Verantwortung |
|---|---|
| [`/_admin`](_admin.de.md) | vollständiger technischer Zugriff auf Struktur, Konfiguration, Texte und Elemente |
| [`/_design`](_design.de.md) | Theme, erzeugte Design-Tokens, Header und Footer nach der Installation |
| [`/_templates`](_templates.de.md) | sectionbasierte Komposition, wiederverwendbare Template-Includes und native Schnellbefüllung von `page-*.tpl` |
| [`/_editor`](_editor.de.md) | tägliche Pflege freigegebener Inhalte, Bilder, Nutzer- und Betriebsdaten |

`/_design` und `/_templates` teilen Passwort und Sitzung mit `/_admin`; `/_editor` besitzt dagegen einzelne Konten und granulare Rechte. Damit liegt die Abgrenzung nicht mehr allein zwischen Struktur und Inhalt, sondern vor allem zwischen vollständigem Entwicklungszugriff und eingeschränkter redaktioneller Arbeit.

## Wo gehört eine Änderung hin?

| Vorhaben | Passender Ort |
|---|---|
| Seitentitel ändern | Textfill in `text/` oder über `/_editor` |
| neues Teammitglied ergänzen | Element über `/_editor` |
| Seite aus vollständigen Sections zusammensetzen | `/_templates` (Alpha) |
| tiefergehende HTML-Struktur ändern | HTML+-Escape-Hatch oder `.tpl`-Datei in `templates/` |
| neue öffentliche URL anlegen | Route in `config.php` beziehungsweise über `/_admin` |
| dynamische Liste ausgeben | Element-Abfrage oder Shortcode mit Callback |
| technische Funktion ergänzen | projektspezifisches Modul |
| Theme, Design, Header oder Footer ändern | `/_design`; Stylesheets für projektspezifische Übersteuerungen jenseits des Katalogs |

## Wie es weitergeht

- [Erste Schritte](getting-started.de.md) führt durch die notwendige Ersteinrichtung.
- [`/_admin`-Bedienung](_admin.de.md) erklärt den vollständigen technischen und inhaltlichen Zugriff.
- [`/_templates`-Bedienung](_templates.de.md) erklärt den strukturellen Template-Builder im Alpha-Status.
- [`/_editor`-Bedienung](_editor.de.md) begleitet die berechtigungsgesteuerte Inhaltspflege.
- [Deployment](deployment.de.md) beschreibt den Weg von der lokalen Webseite in den sicheren Betrieb.
