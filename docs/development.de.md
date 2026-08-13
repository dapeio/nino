# Nino — Entwickler-Handbuch

**Sprache:** [English](development.md) · Deutsch

**Stand:** 8. August 2026 · **Nino-Version:** 0.11.0-beta.1

Dieses Handbuch beschreibt die technische Arbeit mit Nino – vom Einstiegspunkt über Routing und Rendering bis zu eigenen Modulen, dauerhaften Daten und Tests. Falls du stattdessen zuerst die Architektur kennenlernen oder ein frisches Projekt einrichten möchtest, lies die [Grundkonzepte](concepts.de.md) beziehungsweise [Erste Schritte](getting-started.de.md).

**Weitere Links:**
[README](../README.de.md) · [Grundkonzepte](concepts.de.md) · [Entwickler-Handbuch](development.de.md) · [Erste Schritte](getting-started.de.md) · [`/_install`-Referenz](_install.de.md) · [`/_admin`-Bedienung](_admin.de.md) · [`/_templates`-Bedienung](_templates.de.md) · [`/_editor`-Bedienung](_editor.de.md) · [Deployment](deployment.de.md) · [Security Policy](https://github.com/dapeio/nino/blob/main/SECURITY.md) · [Changelog](https://github.com/dapeio/nino/blob/main/CHANGELOG.md)

**Entwicklerprofil:** Für einfache Webseiten reichen solide Kenntnisse in HTML, CSS und JavaScript sowie PHP-Grundlagen. Templates bestehen aus HTML+, also HTML mit Textfills und Shortcodes. Erst eigene Anwendungslogik, externe Schnittstellen oder neue Module verlangen tieferes PHP-Wissen. Ein fertiges Projekt kann anschließend weitgehend über `/_admin`, `/_templates` und `/_editor` gepflegt werden.

## Einstiegspunkt und Laufzeitmodell

Jeder öffentliche Request durchläuft drei Aufrufe:

```php
$appData = \Nino\init();
$request = \Nino\request( $appData, $_SERVER );
\Nino\output( $appData, $request );
```

Diese wenigen Zeilen bilden die gesamte Laufzeit ab:

1. `init()` baut `$appData` auf, startet die Session und initialisiert Kernel sowie Module.
2. `request()` normalisiert den Request, löst die Route auf und rendert die Response über Kernel und registrierte Callbacks.
3. `output()` sendet Status, Header und Body und beendet das Script.

Es gibt keine nachgelagerte Teardown-Phase. Alles, was vor dem Ende dauerhaft erhalten bleiben soll, muss vorher gezielt ins Dateisystem geschrieben werden.

### `$appData` und `$request`

Nino trennt den Zustand der Anwendung vom Zustand eines einzelnen HTTP-Vorgangs:

- `$appData` enthält Konfiguration, registrierte Callbacks, Caches, den aktuellen Nutzer und weitere Laufzeitdaten.
- `$request` enthält den normalisierten Eingang und die dazu entstehende Response.

Beide Arrays werden als Referenz weitergereicht. Dadurch bleibt sichtbar, welche Methode Daten liest oder verändert; ein versteckter Container oder globaler Service-Locator ist nicht nötig.

In `$appData` gilt folgende Konvention:

- Schlüssel unter `/…` gehören zum stabilen Konfigurations- und Datenraum, zum Beispiel `/nino/http/routes`.
- Schlüssel unter `./…` gelten nur für den aktuellen PHP-Lebenszyklus, zum Beispiel `./nino/locales/current`.

Diese Schreibweise entscheidet nicht automatisch über Persistenz. Auch ein `/…`-Wert wird erst dauerhaft, wenn ihn eine passende Schreibmethode explizit speichert.

Der relevante Teil von `$request` sieht vereinfacht so aus:

```php
[
    '/nino/http/request' => [
        'method' => 'GET',
        'uri'    => '/contact',
        'query'  => [],
        'header' => [],
        'body'   => '',
        'ip'     => '127.0.0.1',
    ],
    '/nino/http/response' => [
        'uri'        => '/contact',
        'locale'     => 'de_DE',
        'header'     => [],
        'body'       => '',
        'statusCode' => 200,
    ],
]
```

### Konfiguration außerhalb des Document-Roots

Ohne Override liest Nino `config.php` aus `private/`. Um den vollständigen privaten Verzeichnisbaum oder nur diese Datei außerhalb des öffentlich erreichbaren Verzeichnisses abzulegen, wird der Pfad vor dem Laden des Kernels definiert:

```php
define( 'NINO_CONTENT_DIR', '/var/www/private/nino-example' );
// Oder, um nur config.php zu verschieben:
// define( 'NINO_CONFIG_DIR', '/var/www/private/nino-example' );
require_once __DIR__. '/_nino/Nino.php';
```

Verwende `NINO_CONTENT_DIR` für den vollständigen privaten Baum und `NINO_CONFIG_DIR` nur für eine getrennte `config.php`. Jedes ausdrücklich konfigurierte Ziel muss existieren und beschreibbar sein; ein ungültiger Pfad wird nicht stillschweigend ersetzt.

## Der Request-/Response-Lebenszyklus im Detail

### 1. `\Nino\init()`

`init()` führt die Kernkomponenten in einer festen Reihenfolge aus:

```php
AppData::prepare( $appData );
AppData::prepareSession( $appData );
Runtime::init( $appData );
Filesystem::init( $appData );
AppData::init( $appData );
Locales::init( $appData );
Csrf::init( $appData );
Auth::init( $appData );
Modules::callModules( $appData, 'init' );
```

Die Reihenfolge ist Teil des Laufzeitvertrags:

- `AppData::prepare()` legt die internen Laufzeitbereiche von `$appData` an.
- `AppData::prepareSession()` stellt die Session-Konfiguration bereit, bevor PHP die Session startet.
- `Runtime::init()` richtet die PHP-Fehlerbehandlung ein und startet beziehungsweise übernimmt die Session.
- `Filesystem::init()` bestimmt Projekt- und Konfigurationspfad und initialisiert den Datei-Cache.
- `AppData::init()` lädt `config.php` in `$appData`.
- `Locales`, `Csrf` und `Auth` bestimmen Sprache, CSRF-Zustand und aktuellen Nutzer.
- Erst danach initialisiert `Modules::callModules()` die unter `/nino/modules` registrierten Module.

Ein Modul kann sich daher auf die Kernfunktionen und die geladene Konfiguration verlassen. Umgekehrt darf die Grundinitialisierung nicht von einem optionalen Modul abhängen.

### 2. `\Nino\request()`

Die Verarbeitung eines Requests besteht aus vier Schritten:

```php
Http::request( $appData, $request );
Http::response( $appData, $request );
Locales::response( $appData, $request );
Html::addFills( $appData, [ /* Laufzeitwerte */ ], '*' );
Html::response( $appData, $request );
```

`Http::request()` liest nicht direkt in beliebige Projektvariablen, sondern normalisiert Methode, URI, Query, Header, Body, Basic-Auth-Daten und Client-IP unter `/nino/http/request`. Gleichzeitig entsteht eine Response mit leerem Body, Status `200` und den voreingestellten Sicherheitsheadern.

`Http::response()` sucht unter `/nino/http/routes` nach einer passenden Route, übernimmt deren Werte in die vorbereitete Response und führt anschließend die globalen und routenspezifischen Response-Callbacks aus. `Locales::response()` übernimmt die durch die Route aufgelöste Sprache. Danach ergänzt Nino die Laufzeit-Textfills wie Request-URI, Response-URI, Locale, aktuellen Nutzer, `/nino/dir` und das aktuelle Jahr.

`Html::response()` rendert den Body nur, wenn er ein String ist. Arrays und andere strukturierte Werte bleiben unverändert und werden später als JSON ausgegeben.

### 3. `\Nino\output()`

`Http::output()` finalisiert die Antwort:

- Nicht-String-Bodies werden JSON-kodiert und erhalten einen passenden `Content-Type`.
- Projekt- und Standardheader werden zusammengeführt.
- Statuscode und Header werden gesendet.
- Bei `HEAD` wird der Body nicht ausgegeben.
- Danach endet das Script mit `exit`.

> **Keine direkte Ausgabe während der Laufzeit:** `echo`, `header()` und `http_response_code()` brechen das grundlegende Konzept von Nino und dürfen nicht im regulären Request-/Response-Lebenszyklus verwendet werden. Sie umgehen den gemeinsamen Response-Pfad und können Header, JSON-Antworten und Tests beschädigen. Ändere stattdessen `/nino/http/response`.

## Routing und Responses

Routen liegen unter `/nino/http/routes` in `config.php`. Der öffentliche Schlüssel besteht aus Methode, Doppelpunkt und URI:

```php
'/nino/http/routes' => [
    'GET://' => [
        'uri'  => '/home',
        'body' => '[template /templates/home]',
    ],
    'GET://contact' => [
        'uri'    => '/contact',
        'locale' => 'de_DE',
        'body'   => '[template /templates/contact]',
    ],
    'POST://api/example' => [
        'uri'  => '/api/example',
        'csrf' => true,
    ],
]
```

Der Array-Schlüssel beschreibt die von außen angefragte Route. Das Feld `uri` ist ihre interne Identität. Diese Trennung ist nützlich, wenn mehrere öffentliche URLs auf dasselbe Verhalten zeigen oder eine lokalisierte Route intern stabil bleiben soll.

Eine Route kann unter anderem folgende Felder liefern:

| Feld | Bedeutung |
| --- | --- |
| `uri` | interne Identität der Response |
| `body` | String für HTML oder strukturierter Wert für JSON |
| `statusCode` | HTTP-Statuscode |
| `header` | zusätzliche Response-Header |
| `locale` | für diese Route aufgelöste Sprache |
| `csrf` | CSRF-Prüfung für diese Route ausdrücklich steuern |

Kann Nino keine Route auflösen, wird `GET://404` verwendet. Fehlt auch diese Route, entsteht eine minimale `404`-Response.

Wildcard-Routen enden auf `/*`. Bei einem Request auf `/blog/entry` sucht Nino nach einer fehlenden exakten Route schrittweise auch in übergeordneten Pfaden, beispielsweise `GET://blog/*`. Die interne `uri` der gefundenen Route bleibt dabei der feste Anker für Callbacks und Rendering.

### Beispiel: Eine Response mit einem Callback verändern

```php
\Nino\Callbacks::registerCallback(
    $appData,
    '/nino/http/response/GET://api/example',
    static function( array &$appData, array &$request ): void {
        \Nino\Http::ok( $request, [
            'version' => \Nino\VERSION,
            'status'  => 'ready',
        ] );
    }
);
```

`Http::ok()` setzt den Body einer erfolgreichen Response. `Http::fail()` setzt Statuscode und ein einheitliches `error`-Feld. Beide verändern den übergebenen `$request` direkt und sind besonders für JSON-Routen lesbarer als das manuelle Setzen aller Felder.

Routenspezifische Callbacks verwenden die **interne Response-URI**:

```text
/nino/http/response/<METHOD>:/<response-uri>
```

Für `GET` und die interne URI `/contact` lautet der Name also `/nino/http/response/GET://contact`. Er wird erst nach dem allgemeinen Callback `/nino/http/response` ausgeführt.

## Callbacks: der gemeinsame Erweiterungsmechanismus

Kernel, Module und Projektcode kommunizieren über benannte Callbacks:

```php
\Nino\Callbacks::registerCallback(
    array &$appData,
    string $name,
    mixed $callback,
    int $prio = 5
): void;

\Nino\Callbacks::doCallbacks(
    array &$appData,
    string $name,
    mixed &$args = null
): mixed;
```

Prioritäten reichen von `0` bis `9`; kleinere Werte laufen zuerst. Ungültige Werte werden auf `5` gesetzt. Jeder Handler erhält `$appData` und `$args` als Referenz:

```php
static function( array &$appData, mixed &$args ): mixed {
    // lesen, verändern oder einen neuen Wert zurückgeben
    return $args;
}
```

Ein Rückgabewert ungleich `null` ersetzt `$args` für den nächsten Handler. `null` lässt den bereits per Referenz veränderten Wert bestehen.

Wichtig ist die genaue Semantik: Die Callback-Kette besitzt keinen allgemeinen Abbruchwert. Auch nach `false` laufen weitere Handler. `false` wirkt nur dort als Veto, wo der aufrufende Code dieses Ergebnis ausdrücklich prüft, beispielsweise vor bestimmten Element-Schreibvorgängen. Sicherheitslogik wie der CSRF-Schutz setzt deshalb einen eindeutigen Zustand in `$request`, statt auf einen vermeintlichen Abbruch der Kette zu vertrauen.

Callback-Pfade unter `/nino/*` sind dem Kernel und den mitgelieferten Modulen vorbehalten. Für projektspezifische Events eignet sich ein eigener Namensraum:

```php
\Nino\Callbacks::doCallbacks( $appData, '/project/catalog/import', $rows );
```

So bleibt klar, welche Events zum Kernel gehören und welche Teil des Projekts sind.

## Dauerhafte Daten und konkurrierende Schreibzugriffe

Nino speichert alle Inhalte im Dateisystem. Das ist einfach zu sichern und zu übertragen, macht aber eine klare Struktur und kontrollierte Schreibvorgänge besonders wichtig.

### Konfiguration gezielt speichern

Welche Inhalte in `config.php`, `text/`, `elements/`, `templates/` und `data/` gehören, zeigt der Abschnitt [Dauerhafte Projektdaten](concepts.de.md#dauerhafte-projektdaten). Für die Entwicklung ist vor allem wichtig, dass geladene Werte nicht automatisch zurückgeschrieben werden.

`config.php` wird beim Start in `$appData` geladen. Ausgewählte Top-Level-Schlüssel lassen sich gezielt speichern:

```php
\Nino\AppData::writeContentData( $appData, [
    '/nino/http/routes',
    '/nino/locales',
] );
```

Die Methode liest den aktuellen Dateistand erneut, übernimmt nur die angegebenen Schlüssel und schreibt anschließend atomar. Auth-Sessions werden zusätzlich über einen Drei-Wege-Abgleich zusammengeführt, damit parallele Logins oder Logouts nicht unbemerkt den jeweils anderen Stand überschreiben.

### `Filesystem`

`Filesystem` kapselt Pfadauflösung, Serialisierung, Cache, Sperren und atomare Schreibvorgänge:

- `.php`-Dateien werden als `<?php return …;` gespeichert und per `include` gelesen.
- `.json`-Dateien werden JSON-kodiert und -dekodiert.
- Lesezugriffe werden anhand von Änderungszeit und Dateigröße gecacht.
- Schreibvorgänge erzeugen zunächst eine temporäre Datei im Zielverzeichnis und ersetzen das Ziel anschließend per `rename()`.
- Sperren liegen als Sidecar-Dateien unter `/data/.locks`; ihr Name wird aus dem Zielpfad abgeleitet.
- Pfade mit `..` werden als zusätzliche Schutzschicht abgewiesen.

Für eine einfache, vollständige Ersetzung genügt `putFileContent()`:

```php
\Nino\Filesystem::putFileContent(
    $appData,
    '/data/example.php',
    [ 'updated' => time() ]
);
```

Bei einem Read-modify-write-Vorgang sollte immer `mutate()` verwendet werden:

```php
\Nino\Filesystem::mutate(
    $appData,
    '/data/counter.php',
    static function( array $current ): array {
        $current['value'] = (int) ( $current['value'] ?? 0 ) + 1;
        return $current;
    },
    [ 'value' => 0 ]
);
```

`mutate()` sperrt die Datei, verwirft einen möglicherweise veralteten Cache-Eintrag, liest den aktuellen Stand, führt den Callback aus und schreibt atomar zurück. Gibt der Callback `null` zurück, wird der Schreibvorgang verworfen.

Das manuelle Muster „lesen, verändern, schreiben“ ist bei parallelen Requests unsicher: Zwei Prozesse können denselben Ausgangsstand lesen und der zuletzt Schreibende verliert die Änderung des anderen.

## Rendering: von HTML+ zu HTML

Die zentrale Methode ist:

```php
$html = \Nino\Html::renderHtml( $appData, $html );
```

Sie führt drei Verarbeitungsschritte aus:

1. Textfills ersetzen.
2. Shortcodes auflösen.
3. Callbacks unter `/nino/html/render` ausführen.

### Textfills

Textfills sind Platzhalter mit doppelten eckigen Klammern:

```html
<title>[[/webpage/meta/title]]</title>
<p>[[/contact/intro]]</p>
```

Nino kombiniert dabei:

1. globale Werte aus `/text/global.php`,
2. Werte der aktuellen Locale aus `/text/<locale>.php`,
3. während der Laufzeit mit `Html::addFills()` ergänzte Werte.

Fills können weitere Fills enthalten. Nino wiederholt die Ersetzung deshalb, bis sich der vollständige String nicht mehr verändert, höchstens jedoch zehn Durchläufe. Damit sind kontrollierte Verschachtelungen möglich, ohne dass ein zyklischer Fill die Laufzeit endlos blockiert.

Laufzeitwerte lassen sich gezielt ergänzen:

```php
\Nino\Html::addFills( $appData, [
    '/project/catalog/count' => 42,
], '*' );
```

Der dritte Parameter bezeichnet den Sprachbereich. `'*'` steht für sprachunabhängige Werte.

### Shortcodes

Shortcodes binden Verhalten und strukturierte Inhalte in Templates ein:

```html
[template /templates/header]

[element /team/ada]
    <article>
        <h2>[[name]]</h2>
        <p>[[description]]</p>
    </article>
[/element]
```

Ein Shortcode kann positionale und benannte Argumente sowie umschlossenen Inhalt besitzen:

```text
[example first limit="3"]Inhalt[/example]
```

Der Handler erhält `first` als `$args[0]`, `3` als `$args['limit']` und den Inhalt als `$args['content']`. Registriert wird er mit:

```php
\Nino\Html::addShortcode( $appData, 'example', 'Project\\Example::shortcode' );
```

Die Ausgabe eines Shortcodes wird erneut durch `renderHtml()` geschickt. Deshalb können Templates, Textfills und Shortcodes ineinander verschachtelt werden. Die maximale Render-Tiefe beträgt 20 Ebenen; danach stoppt Nino die weitere Rekursion.

### Elemente im Template

Die Shortcodes `[element]` und `[elements]` laden strukturierte Inhalte. Innerhalb ihres Blocks werden Felder mit `[[field]]` angesprochen; `[[.id]]` enthält die interne Element-ID.

```html
[elements /services limit="6" query="featured=1"]
    <article id="service-[[.id]]">
        <h2>[[title]]</h2>
        <p>[[description]]</p>
    </article>
[/elements]
```

Normale Feldwerte werden HTML-kodiert. Ein im Modell mit `html => true` freigegebenes Feld darf nur eine begrenzte, bereinigte Menge an Inline-HTML enthalten. Der Schutz findet bewusst im Elements-Modul statt: Element-Platzhalter sind lokale Daten des jeweiligen Blocks und nicht Teil des globalen Textfill-Raums.

### Assets sind keine Templates

Der Assets-Shortcode bündelt und zwischenspeichert CSS oder JavaScript:

```php
'/nino/html/assets' => [
    '/.cache/site.min.css' => [
        '/assets/reset.css',
        '/assets/site.css',
    ],
    '/.cache/site.min.js' => [
        '/assets/site.js',
    ],
],
```

Die jeweiligen Zielnamen werden anschließend als Shortcodes eingebunden:

```html
[assets /.cache/site.min.css]
[assets /.cache/site.min.js]
```

Nur wenn der Zielname auf `.min` endet, wird zusätzlich minifiziert. Der Cache berücksichtigt Pfad, Größe und Änderungszeit der Quelldateien.

Assets durchlaufen absichtlich **nicht** die vollständige HTML+-Engine. Ersetzt wird lediglich der sichere Verzeichnispfad `[[/nino/dir]]`. Dadurch können redaktionelle Textfills oder Shortcodes nicht unbeabsichtigt ausführbaren CSS- oder JavaScript-Code erzeugen.

### Abschließende HTML-Callbacks

Nach Fills und Shortcodes läuft der Kernel-Callback `/nino/html/render`. Die registrierten Methoden und Funktionen erhalten den fertigen String und können ihn final verändern:

```php
\Nino\Callbacks::registerCallback(
    $appData,
    '/nino/html/render',
    static function( array &$appData, string &$html ): string {
        return str_replace( '<html>', '<html data-project="example">', $html );
    },
    8
);
```

Dieser Hook eignet sich für klar begrenzte, globale Nachbearbeitung. Projektlogik und Inhaltsabfragen gehören weiterhin in Module oder Shortcodes.

## Wichtige Kernel-APIs

Die folgende Übersicht ist eine Arbeitsreferenz, keine vollständige Auflistung jeder internen Methode.

| Klasse | Wichtige öffentliche Aufgaben |
| --- | --- |
| `AppData` | Grundzustand vorbereiten, `config.php` laden, ausgewählte Schlüssel mit `writeContentData()` speichern |
| `Auth` | Login, Logout, Nutzerverwaltung, Session-Widerruf und Berechtigungsprüfung |
| `Callbacks` | Callbacks registrieren und ausführen |
| `Csrf` | Token lesen/rotieren und Requests prüfen |
| `Filesystem` | Dateien lesen/schreiben, Pfade auflösen, sperren und atomar mutieren |
| `Backup` | verschlüsselte Backup-Manifeste verarbeiten |
| `RotatingLog` | datierte Protokolldateien nach Aufbewahrungsfrist bereinigen |
| `Elements` | einzelne Elemente laden sowie Typen und Elemente abfragen, anlegen, ändern und löschen |
| `Html` | Fills und Shortcodes registrieren, HTML+ rendern und erlaubtes Inline-HTML bereinigen |
| `Http` | Requests normalisieren, Routen auflösen, Responses erzeugen und ausgeben |
| `Images` | Uploads verarbeiten, Varianten verwalten und URLs erzeugen |
| `Locales` | aktuelle, native und verfügbare Sprachen verwalten |
| `Text` | Textdefinitionen lesen, sperren und als Batch speichern |
| `Mail` | E-Mails über die Projektkonfiguration versenden |
| `Modules` | freigegebene Module laden und initialisieren |
| `Runtime` | Session- und Fehlerbehandlung bereitstellen |

Verwende die öffentlichen Methoden statt interner, mit `_` beginnender Implementierungen. So bleibt Projektcode von Details wie Cache-Invalidierung, Dateiformat und Session-Rotation entkoppelt.

## Integrierte Module

Module werden in `/nino/modules` aktiviert. Die Reihenfolge des Arrays ist relevant, wenn mehrere Module Callbacks derselben Priorität registrieren.

| Modul | Integration | Wichtige Eigenschaften |
| --- | --- | --- |
| `Assets` | `[assets …]` | bündelt, zwischenspeichert und optional minifiziert CSS/JS |
| `Csrf` | `[csrf]` | rendert ein verstecktes Token-Feld; der Kernschutz selbst ist immer aktiv |
| `Elements` | `[element …]`, `[elements …]` | lädt typisierte Inhalte; Listen unterstützen `limit`, Query und optionalen Callback |
| `Form` | `POST://.form` | validiert Kontaktformulare, nutzt Honeypot und Rate-Limit, versendet Mails und protokolliert erfolgreiche Einsendungen |
| `Images` | `[image …]` | erzeugt ein escaped `<img>` aus einem Bildslot oder einer URI |
| `Jstext` | `[jstext]` | stellt Textwerte als sicher kodiertes JSON mit CSP-Nonce bereit |
| `Localepicker` | `[localepicker …]` | wechselt Locale über Query und Redirect |
| `Navigation` | `[navigation …]` | rendert Navigationen aus einer kompakten Zeilensyntax |
| `Newsletter` | POST/GET unter `/.newsletter` | Double-Opt-in, Bestätigung und Abmeldung ohne öffentliche Adressauskunft |
| `Template` | `[template /path/name]` | lädt den Rohinhalt einer `.tpl`-Datei; die gemeinsame Render-Pipeline verarbeitet ihn weiter |

Einige Details sind absichtlich defensiv gestaltet:

- Das Formular begrenzt Eingaben, schützt Schreibvorgänge und verwirft alte Protokollmonate.
- Die öffentliche Newsletter-Anmeldung antwortet unabhängig davon gleich, ob eine Adresse neu oder bereits bekannt ist. Das erschwert die Abfrage fremder Adressen.
- `Jstext` verwendet JSON-Hex-Escaping und ergänzt die Content-Security-Policy um einen zufälligen Nonce.

## Ein eigenes Modul entwickeln

### Verzeichnis und Autoloading

Ninos Autoloader bildet Namespaces direkt auf Verzeichnisse ab. Für die Klasse `Project\Catalog\Catalog` wird beispielsweise folgende Datei erwartet:

```text
/_nino/Project/Catalog/Catalog/Catalog.php
```

Der Basename der Klasse und der PHP-Datei müssen übereinstimmen. Klassenpfade werden auf erlaubte Zeichen begrenzt; dynamisch zusammengesetzte oder benutzerkontrollierte Klassennamen gehören trotzdem nicht in die Modulliste.

### Beispiel: Minimales Modul

```php
<?php

namespace Project\Catalog;

class Catalog {

    public static function init( array &$appData ): void {
        \Nino\Html::addShortcode(
            $appData,
            'catalog-count',
            self::class. '::shortcodeCount'
        );

        \Nino\Callbacks::registerCallback(
            $appData,
            '/nino/http/response/GET://api/catalog',
            self::class. '::responseCatalog'
        );
    }

    public static function shortcodeCount( array &$appData, array &$args ): string {
        $rows = \Nino\Filesystem::getFileContent(
            $appData,
            '/data/catalog.php',
            []
        );

        return (string) count( $rows );
    }

    public static function responseCatalog( array &$appData, array &$request ): void {
        \Nino\Http::ok( $request, [
            'items' => \Nino\Filesystem::getFileContent(
                $appData,
                '/data/catalog.php',
                []
            ),
        ] );
    }
}
```

Anschließend wird die Klasse in `config.php` freigegeben:

```php
return [
    '/nino/modules' => [
        // integrierte Module …
        '\\Project\\Catalog\\Catalog',
    ],

    '/nino/http/routes' => [
        // bestehende Routen …
        'GET://api/catalog' => [
            'uri' => '/api/catalog',
        ],
    ],
];
```

Ein gutes Modul hält sich an vier Regeln:

1. `init()` registriert Verhalten, führt aber keine Ausgabe aus.
2. HTTP-Handler verändern die zentrale Response oder verwenden `Http::ok()`/`Http::fail()`.
3. Templating bleibt in Shortcodes und `.tpl`-Dateien; PHP gibt keine Seitenfragmente ungeplant per `echo` aus.
4. Veränderliche Dateien werden bei Read-modify-write mit `Filesystem::mutate()` geschützt.

### Eigene Schreiboperationen absichern

Bei schreibenden Routen ist CSRF standardmäßig aktiv. Ein bewusstes `csrf => false` ist nur für Endpunkte sinnvoll, die einen anderen überprüfbaren Authentisierungsmechanismus besitzen, beispielsweise signierte Webhooks. Die Ausnahme gehört an die Route und sollte im Code begründet werden.

Zusätzlich sollte der Handler:

- Methode und erwartetes Eingabeformat eng begrenzen,
- Eingabelängen vor aufwendiger Verarbeitung beschränken,
- Berechtigungen mit `Auth::checkPermission()` prüfen,
- keine internen Fehlermeldungen oder Dateipfade an Clients zurückgeben,
- und für sensible Aktionen ein projektbezogenes Rate-Limit vorsehen.

## Separate Einstiegspunkte

`/_admin`, `/_templates`, `/_editor` und `/_install` verwenden denselben Kernel wie das Frontend, besitzen aber jeweils eine eigene `index.php`. Nach `\Nino\init()` initialisiert der Einstiegspunkt seinen Bereich und übergibt anschließend wieder an den gemeinsamen Request-/Response-Lebenszyklus.

```php
$appData = \Nino\init();
\Nino\Admin\Admin::init( $appData );
\Nino\Templates\Templates::init( $appData );
$request = \Nino\request( $appData, $_SERVER );
\Nino\output( $appData, $request );
```

Das Beispiel zeigt den Einstiegspunkt von `/_templates`; die übrigen Bereiche initialisieren entsprechend ihre eigenen Klassen. Die Bereiche sind keine regulären Module aus `/nino/modules`:

- `/_install` erzeugt den ersten Projektstand und sperrt sich anschließend;
- `/_admin` bietet vollständigen Zugriff auf technische Struktur sowie Texte und Elemente;
- `/_templates` legt `page-*.tpl` an, setzt sie aus vollständigen HTML- und Template-Sections zusammen und befüllt native Inhalte schnell;
- `/_editor` pflegt Inhalte und Betriebsdaten innerhalb der Kontoberechtigungen.

`/_templates` bindet die Admin-Authentifizierung ein und teilt Passwort, Sperrstatus und Sitzung mit `/_admin`. Wird `_admin/` aus einer Auslieferung entfernt, steht deshalb auch der Builder nicht mehr zur Verfügung. Das geplante `/_themes` ist noch kein Einstiegspunkt im aktuellen Quellstand.

## Fehlerbehandlung und Protokolle

`Runtime` registriert einen gemeinsamen Handler für PHP-Fehler und Exceptions. Bewusst ausgelöste Hinweise, Warnungen und Deprecation-Meldungen können protokolliert werden, ohne den Request zu beenden. Exceptions, Engine-Fehler und `E_USER_ERROR` führen zu einer `500`-Antwort.

Das Verhalten wird in `config.php` gesteuert:

```php
'/nino/error/log'     => true,
'/nino/error/display' => false,
```

Bei aktivem Logging schreibt Nino monatliche Dateien unter `/data/logs.<Y-m>.php` und entfernt Einträge außerhalb der dreimonatigen Aufbewahrungsfrist. Die Anzeige sollte nur in einer geschützten Entwicklungsumgebung aktiviert werden. Selbst dort blendet der Backtrace Funktionsargumente aus, damit Passwörter, Session-Tokens und Request-Header nicht versehentlich auf der Fehlerseite erscheinen.

Fehlerprotokollierung ist kein Ersatz für kontrollierte Rückgabewerte: Ein erwartbarer fachlicher Fehler sollte als passende `4xx`-Response behandelt werden. Der globale Handler ist für unerwartete technische Zustände vorgesehen.

## Sicherheitsmodell für Entwickler

Sicherheit entsteht in Nino aus wenigen zentralen Regeln, die für Projektcode weiter gelten.

### CSRF

Nicht-lesende Methoden werden standardmäßig geprüft. Als sicher gelten `GET`, `HEAD` und `OPTIONS`. Das Token kann aus einem Formularfeld `_csrf`, dem Header `X-CSRF-Token` oder einem JSON-Body gelesen werden.

Der Shortcode

```html
[csrf]
```

erzeugt das versteckte Formularfeld. Der eigentliche Schutz gehört jedoch zum Kernel und bleibt auch dann aktiv, wenn das optionale Rendering-Modul nicht verwendet wird.

### Sessions und Login

Nino startet Sessions im Strict Mode. Session-Cookies sind `HttpOnly`, verwenden `SameSite=Lax` und werden über HTTPS als `Secure` gesetzt. Hinter einem TLS-terminierenden Proxy lässt sich das Secure-Flag mit `/nino/session/force-secure-cookie` erzwingen. Ein erfolgreicher Login erneuert die Session-ID und den CSRF-Token.

Die Authentifizierung schützt zusätzlich durch:

- einen Dummy-Passworthash gegen messbare Unterschiede bei unbekannten Nutzern,
- Fehlversuchsgrenzen pro Konto und IP,
- automatisches Rehashing veralteter Passworthashes,
- zufällige Session-Tokens mit begrenzter Laufzeit,
- und die Möglichkeit, alle Sessions eines Nutzers zu widerrufen.

### Response-Header

Jede Response startet mit zentralen Sicherheitsheadern, darunter:

- `Strict-Transport-Security`,
- `Content-Security-Policy`,
- `X-Frame-Options: SAMEORIGIN`,
- `X-Content-Type-Options: nosniff`.

Projektcode darf diese Header gezielt erweitern. Er sollte sie nicht pauschal ersetzen oder abschwächen, nur um eine unsaubere Inline-Integration zum Laufen zu bringen.

### Ausgaben und Uploads

- Elementfelder werden HTML-kodiert oder bei ausdrücklich erlaubtem HTML bereinigt.
- `Jstext` überträgt Daten JSON-kodiert und CSP-gebunden in JavaScript.
- Der Newsletter verrät nicht, ob eine E-Mail-Adresse bereits existiert.
- Bildverarbeitung begrenzt Uploads auf 8 MiB und Quelldateien auf 20 Millionen Pixel, bevor speicherintensive Verarbeitung beginnt.
- PHP-Datendateien in öffentlich erreichbaren Verzeichnissen erhalten Schutzstubs oder liefern ausschließlich Werte zurück.

Diese Vorkehrungen nehmen Projektcode nicht die Verantwortung ab. Daten aus Request, Dateien oder externen APIs bleiben unvertrauenswürdig, bis sie für ihren konkreten Zielkontext validiert und sicher ausgegeben wurden.

## Tests und Änderungsworkflow

Nino verwendet eigenständige Smoke-Tests ohne PHPUnit. Jeder Test erstellt ein isoliertes temporäres Projekt und prüft einen anderen Bereich:

| Test | Schwerpunkt |
| --- | --- |
| `tests/kernel-smoke.php` | Kernel, Routing, Rendering, Auth, Filesystem und Module |
| `tests/editor-smoke.php` | Editor-Routen, Rechte, Backups, Protokolle und Inhaltsoperationen |
| `tests/admin-smoke.php` | Admin-Authentifizierung und technische Verwaltungsfunktionen |
| `tests/install-smoke.php` | Installationsschritte, erzeugte Struktur und Selbstsperre |
| `tests/templates-smoke.php` | Section-Komposition, Template-Includes, verlustfreie Seitenrahmen, native Schnellbefüllung und Speicherkonflikte |
| `tests/*-js-smoke.js` | browsernahe Logik der Verwaltungsoberflächen und des Template-Builders |
| `tests/concurrency-smoke.php` | parallele und atomare Schreibvorgänge |

Lokal werden sie einzeln ausgeführt:

```bash
php tests/kernel-smoke.php
php tests/editor-smoke.php
php tests/admin-smoke.php
php tests/install-smoke.php
php tests/templates-smoke.php
for test in tests/*-js-smoke.js; do node "$test"; done
php tests/concurrency-smoke.php
```

Die GitHub-Actions-Pipeline verwendet PHP 8.4, führt diese PHP- und JavaScript-Smoke-Tests aus und ergänzt sie um Syntaxchecks über alle PHP- und JavaScript-Dateien.

Für Änderungen am Kernel oder an einem Modul empfiehlt sich dieser Ablauf:

1. Verhalten zunächst im passenden Smoke-Test reproduzieren oder spezifizieren.
2. Die kleinstmögliche Änderung implementieren.
3. Alle PHP- und JavaScript-Smoke-Tests sowie die Syntaxchecks ausführen.
4. Bei Änderungen an Routen, Dateien oder Callbacks auch die entsprechende Dokumentation anpassen.
5. Sicherheitsrelevante Ausnahmen wie `csrf => false`, gelockertes HTML oder zusätzliche CSP-Quellen ausdrücklich begründen.

Vermeide Assertions, die nur interne Zwischenschritte festschreiben. Ein guter Smoke-Test prüft den sichtbaren Vertrag: Response, gespeicherte Daten, Berechtigung, Sperrverhalten oder erzeugte Projektstruktur.

## Callback-Referenz

Die folgende Tabelle nennt die wichtigsten vom Kernel und den integrierten Modulen verwendeten Hooks. Zusätzliche, bereichsspezifische Hooks können direkt im jeweiligen Quelltext gefunden werden.

| Callback | Argument | Zweck |
| --- | --- | --- |
| `/nino/http/request` | kompletter `$request` | normalisierten Request vor dem Routing ergänzen |
| `/nino/http/response` | kompletter `$request` | globale Bearbeitung jeder Response; hier greift unter anderem CSRF |
| `/nino/http/response/<METHOD>:/<uri>` | kompletter `$request` | Verhalten einer aufgelösten Route |
| `/nino/html/shortcode/<name>` | Shortcode-Argumente | Handler eines registrierten Shortcodes |
| `/nino/html/render` | HTML-String | letzte globale Nachbearbeitung des gerenderten HTML |
| `/nino/shortcodes/assets/output/<extension>` | Link- oder Script-Template | HTML des Assets-Shortcodes für einen Dateityp anpassen |
| `/nino/auth/login` | Nutzerdaten | auf einen erfolgreichen Login reagieren |
| `/nino/auth/logout` | Nutzerdaten | auf einen Logout reagieren |
| `/nino/auth/user/{insert\|update\|delete}` | Nutzerdaten | Änderungen an Nutzerkonten ergänzen |
| `/nino/elements<type-uri>/insert` | Daten des Elementtyps | Einfügen in einen Typ prüfen oder per `false` verwerfen |
| `/nino/elements<type-uri>/update` | Daten des Elementtyps | Änderung in einem Typ prüfen oder per `false` verwerfen |
| `/nino/elements/delete<type-uri>` | Daten des Elementtyps | Löschen aus einem Typ prüfen oder per `false` verwerfen |
| `/nino/elements<type-uri>/update/uri` | Elementdaten | auf eine Änderung der Element-URI reagieren |

Callback-Namen sind einfache Strings. Behandle die etablierten Namen und Argumentformen trotzdem wie eine API: Eine Umbenennung oder ein geänderter Argumenttyp kann jedes registrierte Modul betreffen.

## Weiterführende Handbücher

- [Konzepte](concepts.de.md) erklärt die Kernsäulen und den technischen Gesamtzusammenhang.
- [Erste Schritte](getting-started.de.md) führt vom Checkout zum eingerichteten Projekt.
- [_install-Referenz](_install.de.md) dokumentiert alle Installationsschritte und Schreibregeln.
- [`/_admin`-Bedienung](_admin.de.md) beschreibt den vollständigen technischen und inhaltlichen Zugriff.
- [`/_templates`-Bedienung](_templates.de.md) erklärt den strukturellen Template-Builder im Alpha-Status.
- [`/_editor`-Bedienung](_editor.de.md) erklärt die redaktionelle Arbeit und das Berechtigungsmodell.
- [Deployment](deployment.de.md) beschreibt Webserver, Sicherheit und Go-live.
- [Security Policy](https://github.com/dapeio/nino/blob/main/SECURITY.md) erklärt den Umgang mit Sicherheitsmeldungen.
