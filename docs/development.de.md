# Nino — Entwickler-Handbuch

**Sprache:** [English](development.md) · Deutsch

**Stand:** 22. September 2026 · **Nino-Version:** 1.3.1

Dieses Handbuch beschreibt die technische Arbeit mit Nino – vom Einstiegspunkt über Routing und Rendering bis zu eigenen Modulen, dauerhaften Daten und Tests. Falls du stattdessen zuerst die Architektur kennenlernen oder ein frisches Projekt einrichten möchtest, lies die [Grundkonzepte](concepts.de.md) beziehungsweise [Erste Schritte](getting-started.de.md).

**Weitere Links:**
[README](../README.de.md) · [Grundkonzepte](concepts.de.md) · [Entwickler-Handbuch](development.de.md) · [Rezepte](recipes/README.md) · [Erste Schritte](getting-started.de.md) · [Einrichtungsassistent](setup.de.md) · [`/_admin`-Workbench](_admin.de.md) · [Features](features.de.md) · [Deployment](deployment.de.md) · [Security Policy](https://github.com/dapeio/nino/blob/main/SECURITY.md) · [Changelog](https://github.com/dapeio/nino/blob/main/CHANGELOG.md)

**Entwicklerprofil:** Für einfache Webseiten reichen solide Kenntnisse in HTML, CSS und JavaScript sowie PHP-Grundlagen. Templates bestehen aus HTML+, also HTML mit Textfills und Shortcodes. Erst eigene Anwendungslogik, externe Schnittstellen oder neue Module verlangen tieferes PHP-Wissen. Ein fertiges Projekt kann anschließend weitgehend in der Workbench `/_admin` gepflegt werden.

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
define( 'NINO_PRIVATE_DIR', '/var/www/private/nino-example' );
// Oder, um nur config.php zu verschieben:
// define( 'NINO_CONFIG_DIR', '/var/www/private/nino-example' );
require_once __DIR__. '/_nino/Nino.php';
```

Verwende `NINO_PRIVATE_DIR` für den vollständigen privaten Baum und `NINO_CONFIG_DIR` nur für eine getrennte `config.php`. Jedes ausdrücklich konfigurierte Ziel muss existieren und beschreibbar sein; ein ungültiger Pfad wird nicht stillschweigend ersetzt. Jeder Einstiegspunkt startet den Kernel für sich – `index.php`, `_admin/index.php` und `_admin/recovery.php` –, also werden die verwendeten Konstanten in allen dreien mit denselben Werten definiert: Eine nur in der `index.php` der Site gesetzte lässt den Workbench auf dem Standardpfad, wo er keine `config.php` findet und den Setup-Assistenten anbietet.

Projekteigene PHP-Klassen verwenden einen getrennten Quellcode-Root. Standard
ist `app/` im Projektverzeichnis. Liegen diese Klassen an einer anderen Stelle,
wird `NINO_APP_DIR` vor dem Laden des Kernels als absoluter Verzeichnispfad
definiert. Der Root wird als Ganzes ersetzt. Installierte Features haben einen
eigenen Root, `features/`, den `NINO_FEATURES_DIR` auf dieselbe Weise verlegt
– und ebenso als Ganzes ersetzt: Ein Projekt, das ihn woandershin zeigen
lässt, nimmt seine Features mit, oder der Kernel überspringt ein Modul, das er
nicht mehr laden kann, ohne ein Wort.

```php
define( 'NINO_APP_DIR', '/var/www/nino-example-app' );
define( 'NINO_FEATURES_DIR', '/var/www/nino-example-features' );
require_once __DIR__. '/_nino/Nino.php';
```

Das ändert ausschließlich die beiden Quellcode-Roots. Projektdaten werden
dadurch nicht verschoben, und der Ladeort von Klassen im kerneigenen Namespace
`Nino\` – Ninos eigene Module eingeschlossen – bleibt unverändert.

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
Html::init( $appData );
Auth::init( $appData );
Modules::callModules( $appData, 'init' );
```

Die Reihenfolge ist Teil des Laufzeitvertrags:

- `AppData::prepare()` legt die internen Laufzeitbereiche von `$appData` an.
- `AppData::prepareSession()` stellt die Session-Konfiguration bereit – und die beiden `/nino/error/`-Schalter, damit ein Fehler in diesem Fenster protokolliert werden kann –, bevor PHP die Session startet.
- `Runtime::init()` richtet die PHP-Fehlerbehandlung ein und startet beziehungsweise übernimmt die Session.
- `Filesystem::init()` bestimmt Projekt- und Konfigurationspfad und initialisiert den Datei-Cache.
- `AppData::init()` lädt `config.php` in `$appData`.
- `Locales`, `Csrf` und `Auth` bestimmen Sprache, CSRF-Zustand und aktuellen Nutzer; `Html::init()` dazwischen registriert den einen Shortcode, den der Kernel selbst besitzt: `[json]`, den die Basis-Templates in ihrem schema.org-Block verwenden, ganz gleich welche Module ein Projekt hat.
- Erst danach initialisiert `Modules::callModules()` die unter `/nino/modules` registrierten Module.

Ein Modul kann sich daher auf die Kernfunktionen und die geladene Konfiguration verlassen. Umgekehrt darf die Grundinitialisierung nicht von einem optionalen Modul abhängen.

### 2. `\Nino\request()`

Die Verarbeitung eines Requests besteht aus diesen Schritten:

```php
Http::request( $appData, $request );
Html::addFills( $appData, [ /* Werte, die nur von $appData abhängen */ ], '*' );
Http::response( $appData, $request );
Locales::response( $appData, $request );
Html::addFills( $appData, [ /* Werte, die den Request brauchen */ ], '*' );
Html::response( $appData, $request );
```

`Http::request()` liest nicht direkt in beliebige Projektvariablen, sondern normalisiert Methode, URI, Query, Header, Body, Basic-Auth-Daten und Client-IP unter `/nino/http/request`. Gleichzeitig entsteht eine Response mit leerem Body, Status `200` und den voreingestellten Sicherheitsheadern.

Die Laufzeit-Textfills werden in zwei Durchgängen ergänzt, und die Trennung ist wesentlich. `/nino/dir`, `/nino/public` und `/nino/date/year` hängen nur von `$appData` ab und werden deshalb **vor** `Http::response()` registriert: Ein Response-Callback, der ein Template rendert, ist ein realer Aufrufer — `Modules\Form` und `Modules\Newsletter` bauen ihre HTML-Mails genau in diesem Fenster, und ein danach registrierter Fill erreichte sie als das Literal `[[/nino/public]]`.

`Http::response()` sucht unter `/nino/http/routes` nach einer passenden Route, übernimmt deren Werte in die vorbereitete Response und führt anschließend die globalen und routenspezifischen Response-Callbacks aus. `Locales::response()` übernimmt die durch die Route aufgelöste Sprache. Erst danach ergänzt Nino die Textfills, die den aufgelösten Request brauchen: Request-URI, Response-URI, Locale und aktuellen Nutzer.

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
| `maintenance` | `false` hält die Route in der Wartung erreichbar: `Modules\Maintenance` lässt eine Response mit diesem Feld in Ruhe. Die Routen von `Modules\Legal` und die Login-Route von `Auth` tragen es; das Panel Routen behält es beim Speichern einer Route, wie jedes Feld, das es nicht bearbeitet. Ein Besucher kann es nicht setzen |

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

`Http::ok()` setzt den Body einer erfolgreichen Response. `Http::fail()` setzt Statuscode und ein einheitliches `error`-Feld – die englische Meldung für Entwickler oder ein Log – und optional einen stabilen `code`, seine `params` und das `field`, für das der Wert abgelehnt wurde; die Workbench macht daraus einen Satz in der Oberflächensprache. Beide verändern den übergebenen `$request` direkt und sind besonders für JSON-Routen lesbarer als das manuelle Setzen aller Felder.

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
$written = \Nino\AppData::writeContentData( $appData, [
    '/nino/http/routes',
    '/nino/locales',
] );
```

Die Methode liest den aktuellen Dateistand erneut, übernimmt nur die angegebenen Schlüssel und schreibt anschließend atomar. Sie gibt `false` zurück, wenn `config.php` nicht gesperrt oder geschrieben werden konnte; unter Ninos eigenem Error-Handler beendet dieser Fall die Anfrage bereits mit einem 500, der Rückgabewert zählt also dort, wo ein Handler weiterläuft, etwa in den Tests. Auth-Sessions werden zusätzlich über einen Drei-Wege-Abgleich zusammengeführt, damit parallele Logins oder Logouts nicht unbemerkt den jeweils anderen Stand überschreiben.

### `Filesystem`

`Filesystem` kapselt Pfadauflösung, Serialisierung, Cache, Sperren und atomare Schreibvorgänge:

- `.php`-Dateien werden als `<?php return …;` gespeichert und per `include` gelesen.
- `.json`-Dateien werden JSON-kodiert und -dekodiert; eine, die sich nicht dekodieren lässt, antwortet mit dem Standardwert – wie eine Datei, die es nicht gibt.
- Lesezugriffe werden anhand von Änderungszeit und Dateigröße gecacht.
- Schreibvorgänge erzeugen zunächst eine temporäre Datei im Zielverzeichnis und ersetzen das Ziel anschließend per `rename()`.
- Sperren liegen als Sidecar-Dateien unter `/data/.locks`; ihr Name wird aus dem Zielpfad abgeleitet.
- Pfade mit `..` oder einem Nullbyte werden als zusätzliche Schutzschicht abgewiesen – von jeder Tür von `Filesystem`, nicht nur von den beiden, die Inhalte lesen und schreiben: `path()` und `url()` antworten mit `''`, `fileExists()`, `lockFile()`, `putFileContent()` und `mutate()` mit `false`, `getFileContent()` mit seinem Vorgabewert, und `forceDir()` legt nichts an. `path()`, `url()` und `forceDir()` protokollieren das, damit eine Aufrufstelle, die ihre Eingabe nicht geprüft hat, auffindbar ist; Lesen, Schreiben, Existenzprüfung und Sperre weisen so still ab wie eine fehlende Datei. Das Nullbyte ist dabei die Hälfte, die mehr als eine zweite Schicht ist: PHPs `mkdir()`, `fopen()`, `rename()` und `glob()` werfen dafür einen `ValueError`, den das vorangestellte `@` nicht unterdrückt – ohne diese Regel war ein Nullbyte dort, wo ein `..` mit `false` geantwortet hätte, ein ungefangener 500.

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
<title>[[/_nino/webpage[[/nino/http/response/uri]]/title]]</title>
<p>[[/template/page-contact/hero/subtitle]]</p>
```

Nino kombiniert dabei:

1. globale Werte aus `/text/global.php`,
2. Werte der aktuellen Locale aus `/text/<locale>.php`,
3. während der Laufzeit mit `Html::addFills()` ergänzte Werte.

Fills können weitere Fills enthalten. Nino wiederholt die Ersetzung deshalb, bis sich der vollständige String nicht mehr verändert, höchstens jedoch zehn Durchläufe. Damit sind kontrollierte Verschachtelungen möglich, ohne dass ein zyklischer Fill die Laufzeit endlos blockiert.

Laufzeitwerte lassen sich gezielt ergänzen:

```php
\Nino\Html::addFills( $appData, [
    '/project/catalog/list/count' => 42,
], '*' );
```

Der dritte Parameter bezeichnet den Sprachbereich. `'*'` steht für sprachunabhängige Werte.

#### Die Schlüssel-Grammatik

Jeder Textschlüssel, den ein Projekt oder eine Unit mitbringt, hat vier Segmente, `/<namensraum>/<kategorie>/<teil>/<name>`, jedes aus kleingeschriebenen Wörtern, die ein Bindestrich verbindet: `[a-z0-9]+(-[a-z0-9]+)*`. Die Wörter sind englisch und ausgeschrieben. Ein Schlüssel nennt weder die Sprache noch, ob er global oder je Sprache gilt, und ein Name ist nie eine Zahl: Eine Liste trägt ihren Index im Teil (`item-1`).

| Namensraum | Was er enthält | Kategorie | Beispiel |
| --- | --- | --- | --- |
| `template` | die Wörter, die genau ein Template liest | der Dateiname des Templates ohne `.tpl`, mit Präfix | `/template/page-services/item-1/title` |
| `template` | die Wörter, die mehrere Templates lesen | immer `common` | `/template/common/form/submit` |
| `project` | Tatsachen, Einstellungen und eigene Inhalte des Projekts | `company`, `website`, `mail` oder ein Name des Projekts | `/project/company/contact/email` |
| `feature` | die Wörter einer Funktion eines Katalog-Features | der `key` aus dem Manifest | `/feature/lightbox/controls/close` |
| `module` | die Wörter eines Kernel-Moduls | das Verzeichnis des Moduls, klein geschrieben | `/module/form/info/required` |

Wem ein Wort gehört, entscheidet diese Reihenfolge; die erste Frage, die mit Ja beantwortet wird, gilt:

1. Ist es eine Tatsache, Einstellung oder ein eigener Inhalt des Projekts, der zu keinem Template gehört – Firma, Website, Postfach, Gestaltung der Mails, Texte der projekteigenen Module? → `/project/<kategorie>/...`
2. Lesen mehrere Templates das Wort, und ist es an keine Funktion gebunden – Feldbeschriftungen, Knopfwörter, die Beschriftung vor einer Telefonnummer? → `/template/common/<teil>/<name>`. Dazu zählt ein Wort, das Code in beliebige Templates mit passendem Markup einsetzt, etwa die Feldbeschriftungen des Kontaktformulars, die `\Nino\Form::DEFAULT_FORM` in jedes Template bringt, das das Formular zeigt.
3. Gehört es zu einer Funktion eines Moduls oder Features – ihr Code gibt es aus, setzt es zur Laufzeit, wählt es aus einer festen Menge aus, oder die Funktion liefert es für Markup mit, das beliebige Projekt-Templates schreiben? → `/module/<verzeichnis>/...` oder `/feature/<key>/...`. Daran ändert sich nichts, wenn ein Projekt-Template es ebenfalls liest oder das Projekt das Template austauscht.
4. Sonst gehört das Wort dem einen Template, das es liest, in der Kategorie seines Dateinamens: `/template/<dateiname ohne .tpl>/...`. Das gilt auch für die Templates, die eine Unit mitliefert.

Die Regel gilt beim Anlegen eines Schlüssels. Nichts zieht ihn später um: Liest ein zweites Template ein Wort einer fremden Kategorie, bleibt es, wo es ist. Der Scan im Tab Textschlüssel nennt so einen Fall; wer das Wort teilen will, legt es unter `common` neu an und ändert die Lesestellen.

Der Teil ist der Block, in dem das Wort steht: `intro`, `hero`, `item-<n>`, `form`, `navigation`, `action`, `summary`, `outro`, `label` – oder ein Wort, das den Block benennt (`welcome`, `pager`, `banner`). Der Name ist das Ding selbst, möglichst `title`, `text`, `button` oder `label`. Hat ein Eintrag einer Liste ein Wort, steht seine Kennung im Namen (`/feature/modeswitch/mode/dark`); hat er mehrere, steht sie im Teil (`item-1`, `category-necessary`).

Eine Beschriftung vor einer Angabe und die Angabe selbst sind zwei Dinge. `/template/common/label/phone` ist das Wort „Telefon“; die Nummer ist `/project/company/contact/phone`, und jedes Template liest sie von dort und von nirgends sonst.

Die Kategorie eines Templates ist sein Dateiname ohne `.tpl`: `page-home.tpl` trägt `/template/page-home/...`, `html-footer.tpl` trägt `/template/html-footer/...`; nichts wird abgeschnitten oder abgeleitet. Ein Name, der kein Wort eines Schlüssels ist – ein Punkt, ein Großbuchstabe, ein Unterstrich, ein Schrägstrich –, gibt dem Template keine Kategorie: Es trägt keine eigenen Schlüssel und darf alle anderen lesen (`page-home.de_DE.tpl`, `.demo-catalogue.tpl`). Nur Dateien direkt in `templates/` haben eine. `\Nino\Modules\Template::category()` nennt sie für einen Dateinamen oder ein Template, wie ein Shortcode oder ein Routen-Body es angibt (`/templates/page-home`); die Regel steht nur dort.

Zur Laufzeit zusammengesetzt werden darf ein Schlüssel nur in diesen Formen: `[[/_nino/webpage[[/nino/http/response/uri]]/<name>]]` und `[[/_nino/locale/[[locale]]/name]]` in einem Template, dazu ein Schlüssel mit vier Segmenten, in dem ein Platzhalter einen ganzen Namen oder die Kennung eines Listen-Teils ersetzt (`/feature/posts/navigation/` + `prev`, `/feature/consent/category-` + `necessary` + `/name`). Welche Wörter dabei herauskommen, sieht kein Scan; wer einen Schlüssel zusammensetzt, prüft die gerenderten Wörter im eigenen Smoke-Test.

Drei Dinge sind kein Textschlüssel. Die Laufzeit-Fills des Kernels liegen unter `/nino/` und stehen nie in einer Textdatei (`[[/nino/dir]]`, `[[/nino/public]]`, `[[/nino/date/year]]`, `[[/nino/http/response/uri]]`, ...; `\Nino\Html::runtimeFillKeys()` nennt sie). Ein lokaler Platzhalter hat keinen führenden Schrägstrich (`[[name]]` in einer Elements-Schleife oder einem Mail-Template, `[[.rel]]`). Konfiguration wie `/nino/jstext/keys` oder `/project/catalog` in `config.php` ist ein anderer Speicher, auch wo sie gleich aussieht.

Zwei Formen gehören dem System, nicht dem Projekt: `/_nino/webpage<uri>/<name|title|description|uri>`, die Seitenangaben nach der Element-URI (sie darf Schrägstriche und Punkte enthalten), und `/_nino/locale/<code>/name`, der Name einer Sprache. Den ersten schreiben der Assistent und das Panel Routen – für die Seiten, die Features zur Laufzeit routen, im Abschnitt „Routen aus Features und Modulen“ des Panels –, den zweiten die Localepicker-Unit und das Panel Sprache; es gibt einer Sprache, die es anlegt, ihren Code als Namen. `/_admin/...` sind die Wörter der Workbench und bleiben, wie sie sind.

Der Server hält die Grammatik ein, wo Menschen Schlüssel anlegen: Der Tab Textschlüssel legt nur Schlüssel an und benennt nur zu Schlüsseln um, die ihr folgen (`\Nino\Text::isGrammarKey()`), und benennt keinen Schlüssel unter `/_nino/` oder `/_admin/` um. Schlüssel, die ein Projekt schon hat, bleiben unberührt und bearbeitbar. `tests/keys-smoke.php` prüft alles, was Nino ausliefert, findet Reste alter Formen und stellt sicher, dass jeder Schlüssel, den ein ausgeliefertes Template liest, existiert.

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
[elements /services sort="-date" limit="6" query="featured=1"]
    <article id="service-[[.id]]">
        <h2>[[title]]</h2>
        <p>[[description]]</p>
    </article>
[/elements]
```

`query` filtert – `key=value`, mehrere mit `&` verbunden, `%` als Platzhalter an einem der Enden. `sort` ordnet nach einem Feld: `sort="title"` aufsteigend, `sort="-date"` absteigend, `sort="category,-date"` nach dem ersten und, wo das gleich ist, dem zweiten; zwei Zahlen vergleichen sich als Zahlen, alles andere natürlich und ohne Rücksicht auf Groß- und Kleinschreibung („Item 9“ vor „Item 10“), und ein Element ohne das Feld kommt in beiden Richtungen zuletzt. `offset` und `limit` schneiden ein Fenster aus der sortierten Liste. Ein `callback` läuft dazwischen: Er sieht die sortierte Liste und darf streichen oder umordnen, und `offset` und `limit` gelten für das, was er durchgelassen hat – eine Seite ist eine Seite davon. In PHP ist dasselbe `\Nino\Elements::queryElements( $appData, $typeUri, $query, $locale, $return, $options )` mit `sort`, `offset` und `limit` unter `$options`, und `\Nino\Elements::sortElements( $elements, $sort )` ordnet eine Liste, die du schon hast. `\Nino\Elements::prevElement( $appData, $elementUri, $locale, $return, $options )` und `nextElement()` liefern das Element vor und nach einem in dieser Liste – in der Reihenfolge des Typs oder mit `sort` und `query` unter `$options` – aufgelöst für `$locale` wie `getElement()`, und `$return` hinter beiden Enden; das „Zurück“ und „Weiter“ einer Detailseite läuft die Liste entlang, aus der ein Besucher kam.

Normale Feldwerte werden HTML-kodiert. Ein im Modell mit `html => true` freigegebenes Feld darf nur eine begrenzte, bereinigte Menge an Inline-HTML enthalten: `strong`, `em`, `span`, `code` und `a`. `blocks => true` daneben fügt Absätze und Listen (`p`, `ul`, `ol`, `li`) und das `<br>` darin hinzu; `breaks => true` an einem Feld *ohne* `html` behält die Zeilenumbrüche seines einfachen Textes, maskiert, als `<br>`. Das alles ist `\Nino\Html::fieldValue( $value, $field )`, das `[element]` und `[elements]` aufrufen und das ein Feature, das ein Feld selbst zeichnet, aufrufen sollte, statt eine Kopie zu pflegen; `\Nino\Html::sanitizeHtml( $html, $format )` kennt `inline`, `lines` und `blocks`, und `\Nino\Html::detectFormat()` liest das weiteste Format, das ein Wert enthält. Gib dem Element, das einen `blocks`-Wert hält, die Klasse `nino-richtext`: Der Reset am Anfang von `Nino.css` nimmt jeden Absatzabstand und jedes Listenzeichen weg, die Klasse holt sie zurück. Der Schutz findet bewusst im Elements-Modul statt: Element-Platzhalter sind lokale Daten des jeweiligen Blocks und nicht Teil des globalen Textfill-Raums.

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

Assets durchlaufen absichtlich **nicht** die vollständige HTML+-Engine. Ersetzt werden lediglich die beiden sicheren Verzeichnis-Fills: `[[/nino/dir]]` und `[[/nino/public]]`. Dadurch können redaktionelle Textfills oder Shortcodes nicht unbeabsichtigt ausführbaren CSS- oder JavaScript-Code erzeugen. Beide werden gebraucht – `Nino.ui.js` greift für ein Formular ohne eigene `action` auf `[[/nino/dir]]/.form` zurück, und die `theme.css` der Basis-Einheit adressiert ihre drei Webfonts über `[[/nino/public]]`.

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
| `Catalogue` | den signierten Feature-Katalog laden und prüfen, sagen, was er diesem Kernel anbietet, und ein Archiv unter `features/` installieren |
| `Csrf` | Token lesen/rotieren und Requests prüfen |
| `Filesystem` | Dateien lesen/schreiben, Pfade auflösen, sperren und atomar mutieren |
| `Backup` | verschlüsselte Backup-Manifeste verarbeiten |
| `RotatingLog` | datierte Protokolldateien nach Aufbewahrungsfrist bereinigen |
| `Elements` | einzelne Elemente laden sowie Typen und Elemente abfragen, anlegen, ändern und löschen; `seed()` fügt die Elemente einer Install-Einheit hinzu, nur hinzufügend, und lässt die endgültig gelöschten aus |
| `Features` | die Features unter `features/` finden, ihre Manifeste lesen und prüfen, ihre Einstellungen beantworten und speichern, sie aktivieren und deaktivieren und eine Install-Einheit anwenden – auch die des Assistenten |
| `Fetch` | der eine HTTP-Client des Kernels: ein GET über https mit Timeout und Bytegrenze, vom Katalog benutzt und von sonst nichts |
| `Form` | die Formular-Engine hinter `POST /.form`: welche Formulare ein Projekt definiert, wie eine Einsendung aussehen muss, welches Mail-Paar sie verschickt und welchen Datensatz sie hinterlässt |
| `Html` | Fills und Shortcodes registrieren, HTML+ rendern, erlaubtes Inline-HTML bereinigen und einen Textschlüssel samt verschachtelter Fills auflösen (`resolveTextfill()`) |
| `Http` | Requests normalisieren, Routen auflösen, Responses erzeugen und ausgeben |
| `Images` | Uploads verarbeiten, Varianten verwalten und URLs erzeugen |
| `Locales` | aktuelle, native und verfügbare Sprachen verwalten |
| `Text` | Textdefinitionen lesen, sperren und als Batch speichern |
| `Mail` | E-Mails über die Projektkonfiguration versenden, durch `mail()` oder einen unter `/nino/mail/send` registrierten Transport; ein gescheiterter Aufruf hinterlässt `/data/mail-status.php` (seit, zuletzt, Anzahl – keine Adresse), gelesen von `Mail::failure()`, und das Ergebnis jeder Mail steht in `./nino/mail/results` |
| `Modules` | freigegebene Module laden und initialisieren |
| `Runtime` | Session- und Fehlerbehandlung bereitstellen |

Verwende die öffentlichen Methoden statt interner, mit `_` beginnender Implementierungen. So bleibt Projektcode von Details wie Cache-Invalidierung, Dateiformat und Session-Rotation entkoppelt.

## Integrierte Module

Module werden in `/nino/modules` aktiviert. Die Reihenfolge des Arrays ist relevant, wenn mehrere Module Callbacks derselben Priorität registrieren.

| Modul | Integration | Wichtige Eigenschaften |
| --- | --- | --- |
| `Assets` | `[assets …]` | bündelt, zwischenspeichert und optional minifiziert CSS/JS |
| `Cache` | `/nino/http/response`, Priorität 9; `/nino/http/output`, Priorität 9 | beantwortet, solange `/nino/cache/status` an ist, ein anonymes `GET` aus einer gespeicherten Kopie und legt jede fertige Seite ab, die es ablegen darf, mit der `Content-Security-Policy`, mit der sie gesendet wurde; ein Schreibvorgang über `/_admin` verwirft den gesamten Cache |
| `Csrf` | `[csrf]` | rendert ein verstecktes Token-Feld; der Kernschutz selbst ist immer aktiv |
| `Elements` | `[element …]`, `[elements …]`, `[elementvalues …]` | lädt typisierte Inhalte; Listen unterstützen Query, `sort`, `offset`, `limit` und optionalen Callback, und `[elementvalues]` durchläuft die verschiedenen Werte eines Feldes |
| `Form` | `POST://.form` | besitzt den einen Formular-Endpunkt und reicht jede Einsendung an `\Nino\Form` weiter – siehe [Formulare](#formulare) |
| `Images` | `[image …]` | erzeugt ein escaped `<img>` aus einem Bildslot oder einer URI. Sein `alt` ist der für den Slot in der aktuellen Sprache gespeicherte Text, sonst das eigene `alt="…"` des Shortcodes, sonst leer (`alt=""`, dekorativ) – nie die Beschriftung des Slots. Der Text wird escaped und sein `[` als `&#91;` geschrieben, damit er in der nächsten Rendering-Runde keinen Fill und keinen Shortcode öffnen kann. Mit Inhalt – `[image /logo]...[/image]` – wird der Inhalt statt des `<img>` ausgegeben, und nur, wenn der Platz ein Bild hat: `[[src]]` (der Pfad der Datei ab der Wurzel der Seite; `https://[[/project/website/general/url]][[src]]` ist eine absolute Adresse), `[[width]]`, `[[height]]` und `[[alt]]` werden eingesetzt. So fragt ein Meta-Tag oder eine Mail nach der Adresse, ohne leer oder kaputt zu bleiben, wo noch nichts hochgeladen ist. Ein einfaches `[image]` vor dem ersten schließenden `[/image]` eines Templates liest den Text dazwischen als eigenen Inhalt; die Inhaltsform gehört deshalb in ein Template ohne einfaches `[image]` davor, oder das einfache wird als `[image /x][/image]` geschrieben |
| `Jstext` | `[jstext]` | stellt Textwerte als sicher kodiertes JSON mit CSP-Nonce bereit |
| `Legal` | `[legal]`, `[privacy]`, die Routen beider Seiten; `/seo/pages`, `/nino/elements/committed` | Impressum und Datenschutzerklärung als Elemente, in der Sprache des Besuchs gezeichnet und aus der Navigation `legal` verlinkt – siehe [Legal](#legal) unten |
| `Localepicker` | `[localepicker …]` | wechselt Locale über Query und Redirect |
| `Maintenance` | `/nino/http/response`, Priorität 1; `/nino/http/output`, Priorität 9 | beantwortet, solange `/nino/maintenance/status` an ist, jede Seite und jeden Modul-Endpunkt mit 503 und Retry-After-Header, für jeden nicht in der Workbench angemeldeten Besuch – die Anmeldung selbst ausgenommen – und setzt oben auf jede Seite, die ein angemeldetes Konto öffnet, einen Hinweis |
| `Navigation` | `[navigation …]` | rendert Navigationen aus einer kompakten Zeilensyntax |
| `Template` | `[template /path/name]` | lädt den Rohinhalt einer `.tpl`-Datei; die gemeinsame Render-Pipeline verarbeitet ihn weiter |

Jedes Modul der Tabelle liegt in `_nino/Nino/Modules/`: die immer aktiven `Assets`, `Cache`, `Csrf`, `Elements`, `Images`, `Jstext` und `Template` und die fünf, die ein Projekt abschalten kann (`Form`, `Legal`, `Localepicker`, `Maintenance`, `Navigation`). `Form` und `Navigation` bringen ihre Workbench-Panels mit (Anfragen, Navigationen), `Maintenance` ist nichts als ein Schalter: Jedes ist genau dann vorhanden, wenn sein Modul aktiv ist. `Form`, `Legal`, `Navigation` und `Localepicker` sind im Einrichtungsassistenten keine Wahl mehr - der Assistent wendet die `install/`-Einheit jedes einzelnen an und trägt seine Klasse bei jedem Durchlauf in `/nino/modules` ein (`\Nino\Install\Setup::ALWAYS_MODULES`), genau wie er `Maintenance` einträgt, sobald dessen Klasse existiert. Ein Projekt kann jedes der fünf weiterhin von Hand in `/nino/modules` aus- oder einschalten, und `_nino/` bleibt vollständig ersetzbar. Alles jenseits der Tabelle ist ein **Feature** – ein installierbares Paket unter `features/<Name>/` mit einem Manifest `feature.php`, eingeschaltet im Panel Features der Workbench, das sein Panel auf dieselbe Weise mitbringt. Ein Checkout bringt keines mit: Sie kommen aus dem Katalog [dapeio/nino-features](https://github.com/dapeio/nino-features) – `Newsletter` (Double-Opt-in, Bestätigung und Abmeldung unter `/.newsletter`) und `Search` (ein sprachabhängiger Fuzzy-Index über Element-Felder) darunter –, nach `features/` kopiert oder aus dem Panel Features installiert. Siehe [Features](features.de.md), [Panels der Workbench](#panels-der-workbench) und [Verzeichnis und Autoloading](#verzeichnis-und-autoloading) weiter unten.

Einige Details sind absichtlich defensiv gestaltet:

- Das Formular begrenzt Eingaben, schützt Schreibvorgänge und verwirft alte Protokollmonate.
- Die öffentliche Anmeldung des Newsletter-Features aus dem Katalog antwortet unabhängig davon gleich, ob eine Adresse neu oder bereits bekannt ist. Das erschwert die Abfrage fremder Adressen.
- `Jstext` verwendet JSON-Hex-Escaping und ergänzt die Content-Security-Policy um einen zufälligen Nonce.
- `Jstext` trägt nur die Textschlüssel, die dafür veröffentlicht wurden, nicht jeden Fill der Seite. Mitgeliefert sind `/module/form/info/`, `/feature/newsletter/info/` und `/template/common/slider/` – was die öffentlichen Skripte lesen –, die Workbench veröffentlicht `/_admin/`. Wer ein eigenes Skript hat, das einen anderen Schlüssel liest, nennt dessen Präfix in `config.php` unter `/nino/jstext/keys`; ein Modul oder Feature ruft `\Nino\Modules\Jstext::publish( $appData, [ '/project/mine/info/' ] )` in seinem `init()`. **Ein veröffentlichter Schlüssel ist öffentlich**: Er steht im Quelltext jeder Seite, die den Block rendert. Vorher stand dort `/project/mail/address/owner` – das Postfach, an das ein Kontaktformular zustellt – auf jeder Seite.

### Formulare

`Modules\Form` besitzt die Route `POST /.form` und sonst nichts: Was eine Einsendung ist, wie sie aussehen muss, welches Mailpaar sie verschickt und welchen Eintrag sie hinterlässt, ist `\Nino\Form` – und genau diese Trennung erlaubt einem Projekt mehr als ein Formular, ohne dass ein zweiter Endpunkt dieselbe URI beantwortet.

Ein Projekt definiert seine Formulare unter `/nino/form/forms` in der `config.php` – neben seinen Routen und seinen Bildslots, also von Hand editierbar, in jedem Backup enthalten und ohne eigenes Dateiformat. Wer keines definiert, bekommt `\Nino\Form::DEFAULT_FORM`, das Kontaktformular, das Nino immer schon mitgebracht hat, Feld für Feld:

```php
'/nino/form/forms' => [
	[
		'key'						=> 'quote',
		'name'					=> 'Angebotsanfrage',
		'to'						=> 'vertrieb@example.com',	// '' schickt an '[[/project/mail/address/owner]]'
		'subject'				=> '',											// '' nutzt '[[/module/form/subject/owner]]'
		'confirm'				=> true,										// Bestätigung an die erste Adresse, die der Besucher angegeben hat
		'ownerTemplate'	=> '/templates/mail-owner',
		'userTemplate'	=> '/templates/mail-user',
		'fields'				=> [
			[ 'name' => 'email',	'label' => '[[/template/common/form/email]]', 'type' => 'email',		'required' => true ],
			[ 'name' => 'budget',	'label' => 'Budget',								'type' => 'number' ],
			[ 'name' => 'wishes',	'label' => 'Wofür?',								'type' => 'textarea' ],
		],
	],
],
```

Der `type` eines Feldes ist einer aus `\Nino\Form::TYPES` (`text`, `email`, `tel`, `url`, `number`, `textarea`, `select`, `checkbox`, `radio`, `date`; ein `select` und ein `radio` führen ihre `options` mit), sein `label` darf ein Textfill sein, und sein `name` ist eine Kennung aus höchstens 64 Zeichen (ein Buchstabe, dann Buchstaben, Ziffern, `_` oder `-`), die keiner aus `\Nino\Form::RESERVED` ist – die vier Schlüssel, die der Endpunkt selbst aus dem Post liest, und die vier, die ein Eintrag neben den Werten trägt. Eine Definition, von der kein brauchbares Feld übrig bleibt, wird verworfen statt halb gelesen: Ein Formular, das niemand absenden kann, ist besser als eines, das an eine vertippte Adresse schickt.

Was jeder Typ sendet, ist das, was das gemeinsame Skript schickt: eine angehakte `checkbox` ihren Wert, eine nicht angehakte nichts – eine Pflicht-Checkbox muss also angehakt sein; eine `radio`-Gruppe den Wert des angehakten Mitglieds, der eine ihrer `options` sein muss – ein `radio` ohne eine einzige nicht leere Option verlangt eine Antwort, die niemand geben kann, und wird weggelassen, während ein `select` ohne Optionen weiter jeden Wert nimmt; ein `date` ist `Y-m-d`, ein Tag, den es gibt. Ein Wert in der falschen Form ist ein `400`.

`\Nino\Form::normalize()` repariert eine Definition stillschweigend. Ein Schreiber, der eine von einem Menschen übernimmt, fragt zuerst `\Nino\Form::problems( $entry )`: je ein `[ 'field' => Index | null, 'code' => ... ]` für jeden Fall, in dem `normalize()` etwas weglassen oder ersetzen würde (die Codes sind `key`, `field`, `name`, `reserved`, `duplicate`, `type`, `options`, `fields`, `to`, `ownerTemplate` und `userTemplate`; der Index ist der Schlüssel in der gesendeten `fields`-Liste, `null` bei einem Problem der Definition selbst), `[]` für eine saubere. Beide antworten aus einer Routine und können deshalb nicht auseinanderlaufen.

Nur `[[fields]]` bringt jedes Feld in eine Mail – die ganze Einsendung als Tabelle, was immer das Formular fragt. Die Templates `mail-owner` und `mail-user`, mit denen eine Neuinstallation startet, enthalten es; ein Projekt, das früher installiert hat, kopiert es von Hand in seine eigenen Templates. Ein Template, das nur `[[name]]`, `[[email]]`, `[[subject]]` (das Feld `cat`) und `[[message]]` füllt, wie die früheren, schickt von einem Feld mit anderem Namen nichts mit.

Zwei weitere Schlüssel stehen daneben. `/nino/form/retention` ist die Zahl der Monate, die Einsendungen auf der Platte bleiben (1 bis 60, ohne Angabe `\Nino\Form::RETENTION_MONTHS`), und `/nino/form/store` auf `false` heißt: Die Mail geht raus und es wird gar nichts geschrieben – eine Seite, die ihre Anfragen beantwortet und keine Kopie behält, hat weniger zu schützen, und das Panel Anfragen bleibt dann leer, weil es nichts zu zeigen gibt.

Ein Projekt, das die Aufbewahrung oder die Bestätigungsmail ändert, ändert, was seine Datenschutzerklärung sagen muss: Der Abschnitt „Kontaktformular“ der Datenschutzerklärung (Typ `privacy`, Element `contact`) nennt die Frist, nach der Einsendungen gelöscht werden, und die Bestätigung, die der Besuch bekommt. Wer `/nino/form/retention` oder `/nino/form/store` setzt oder keine Bestätigung schickt, passt diesen Abschnitt daran an – siehe [Legal](#legal).

Das Markup gehört dem Projekt: `page-contact.tpl` trägt ein von Hand geschriebenes `<form class="nino-form">`, das das gemeinsame Skript in `Nino.ui.js` steuert. Ein Projekt mit mehreren Formularen schreibt jedes Markup genauso – oder installiert das [Forms-Feature](https://github.com/dapeio/nino-features/blob/main/features/Forms/README.md) aus dem Katalog, das einen Shortcode `[form]` mitbringt, der eines aus seiner Definition zeichnet, dazu einen Builder für die Definitionen und eine Reihe Spam-Wächter.

**Eine Einsendung abweisen** braucht keinen eigenen Callback-Namen. Ein Modul oder Feature, das eine abweisen will, registriert sich auf demselben Route-Callback vor dem Modul – `\Nino\Callbacks::registerCallback( $appData, '/nino/http/response/POST://.form', ..., 1 )` – und hinterlässt einen Status; `\Nino\Form::handle()` sieht einen Status, der nicht 200 ist, und kehrt zurück, ohne etwas zu verschicken oder zu schreiben. `\Nino\Csrf::init()` macht genau das, und deshalb hat der Endpunkt keine eigene CSRF-Prüfung:

```php
\Nino\Callbacks::registerCallback( $appData, '/nino/http/response/POST://.form', static function( array &$appData, array &$request ): void {

	if( deinWaechterLehntAb( $appData ) === true )
		$request['/nino/http/response']['statusCode'] = 418;

}, 1 );
```

418 statt eines eigenen Status je Ablehnung: Das gemeinsame Skript `.nino-form` zeigt für alles, was nicht 200 oder 400 ist, eine einzige allgemeine Meldung – ein Bot erfährt also nie, an welcher Prüfung er gescheitert ist.

Die eigenen Antworten des Endpunkts folgen derselben Regel. Eine Einsendung, die die Mail-Drossel je IP abweist, ist ein `429`, weder verschickt noch gespeichert – der Besucher sieht die allgemeine Meldung und versucht es später noch einmal, und eine gedrosselte Flut wird nicht zu ungedrosseltem Plattenwachstum. Eine Einsendung, deren Mail an den Inhaber kein Transport genommen hat, wird mit `500` beantwortet – die allgemeine Meldung auf der Seite, damit der Besucher weiß, dass sie nicht angekommen ist – und, wo das Projekt eine Kopie behält, trotzdem gespeichert, denn die Anfrage steht im Panel Anfragen; das Dashboard sagt, woran es liegt. Nur die Mail an den Inhaber zählt: Eine Bestätigung an den Besucher, die nicht zugestellt werden konnte, während die Mail an den Inhaber hinausging, wird mit `200` beantwortet.

### Legal

**Wichtig:** Impressum und Datenschutzerklärung, die Nino mitliefert, sind ein Ausgangspunkt und keine Rechtsberatung. Die Texte sind auf keine bestimmte Website zugeschnitten und nicht rechtlich geprüft. Der Betreiber einer Website ist dafür verantwortlich, sie vor der Veröffentlichung fachkundig prüfen zu lassen und anzupassen: an das, was die Website tatsächlich verarbeitet, an seine Rechtsform und an weitere Pflichtangaben wie Registereintrag, Umsatzsteuer-Identifikationsnummer oder eine für journalistische Inhalte verantwortliche Person. Für Richtigkeit, Vollständigkeit und Aktualität der Texte übernimmt das Projekt keine Gewähr.

`Modules\Legal` bringt Impressum und Datenschutzerklärung als **Elemente**: zwei Typen, `legal` (das Impressum) und `privacy` (die Datenschutzerklärung), ein Element je Abschnitt, jedes mit Titel und Text je Sprache und den Feldern `order` und `hidden`. Der Einrichtungsassistent wendet die `install/`-Einheit bei jedem Durchlauf an (`\Nino\Install\Setup::ALWAYS_MODULES`) und füllt die beiden Typen mit einem neu geschriebenen Starttext auf Deutsch und Englisch. Danach wird nichts davon ersetzt: Elemente sind der Inhalt der Redakteure, ein zweiter Durchlauf ergänzt also, was fehlt, und lässt jeden Abschnitt, wie er ist.

| Teil | Aufgabe |
| --- | --- |
| `[legal]`, `[privacy]` | zeichnen die Abschnitte eines Typs in der Sprache des Besuchs, nach `order` geordnet, ohne die mit `hidden`; ein Abschnitt ohne Titel und Text in dieser Sprache erscheint in der nativen Sprache (oder der ersten, die einen hat) und trägt `lang=""` |
| `\Nino\Modules\Legal::render( $appData, $type )` | was die beiden Shortcodes aufrufen; Titel und Text werden zuerst über das Modell des Feldes bereinigt (`\Nino\Html::fieldValue()`) |
| `\Nino\Modules\Legal::placeholders( $appData, $html )` | ersetzt `#/project/company/contact/email#` durch das, was dieser Textschlüssel in der aktuellen Sprache sagt |
| `\Nino\Modules\Legal::url( $appData, $page, $locale = '' )` | die Adresse von `imprint` oder `privacy`, mit Verzeichnis-Präfix, je Sprache, mit Rückfall auf die native |
| `\Nino\Modules\Legal::addLocale( $appData, $locale )` | was eine später angelegte Sprache bekommt: Seitenangaben, Feldbeschriftungen und Hinweise der Typen, die Namen der Seiten und die Fassungen dieser Sprache für die Abschnitte, die das Modul und die aktiven Features mitbringen; das Panel Sprache ruft sie auf |
| `\Nino\Modules\Legal::contributions( $appData, $featureKey, $locale = '' )` | die Abschnitte, die die Einheit eines Features mitbringt, für den Dialog beim Deaktivieren und Entfernen |
| `\Nino\Modules\Legal::check( $appData )`, `notices( $appData )` | was an der Einrichtung nicht stimmt, für das Dashboard; wird nie von einem Seitenaufruf aufgerufen |

**Seiten und Routen.** Das Modul routet die beiden Seiten selbst, in `init()`: eine Route je Sprache und Seite, mit dem Pfad der Sprache aus `/nino/legal/paths` in `config.php` (die Einheit liefert `/impressum` und `/datenschutz` für `de_DE`, `/imprint` und `/privacy` für `en_US`), mit `locale` und `'maintenance' => false`, damit die Seiten in der Wartung erreichbar bleiben. Eine Sprache ohne eigenen Pfad bekommt einen abgeleiteten, `/fr-fr/impressum`; ein Pfad, der ungültig oder von einer anderen Route belegt ist, wird ausgelassen und von `check()` gemeldet, statt etwas zu überschreiben. Beide Routen einer Seite teilen eine Element-URI, `/legal/imprint` und `/legal/privacy`, die der Assistent und das Panel Routen für diese Seiten freihalten. Die Seitenangaben stehen unter `/_nino/webpage<uri>/{name,title,description}`.

**Platzhalter.** Ein Text nennt eine Angabe der Website durch einen Platzhalter, statt sie auszuschreiben: `#/project/company/contact/email#`. Ersetzt werden nur Schlüssel unter `\Nino\Modules\Legal::PREFIXES` (`/project/company/` und `/project/website/general/`) in der viersegmentigen Form der Schlüsselgrammatik, nur in Text und erst, nachdem der Text bereinigt wurde; ein Wert kann also nichts als Text sein: Tags und Entities entfallen, der Rest wird escaped, und jede eckige Klammer wird zur Entity. Ein Schlüssel ohne Wert bleibt, wie er geschrieben ist, damit man ihn sieht. Die Präfixliste ist eine Konstante des Kernels und absichtlich keine Einstellung.

**Die Navigation `legal`.** Der Assistent legt eine dritte Navigation an, `legal`, mit den beiden Seiten als ersten Einträgen; der Fuß der Basis-Rahmen gibt sie mit `[navigation nav="legal" id="legal__nav"][/navigation]` aus. Der Assistent legt sie nur an, solange das Projekt keine hat; ein zweiter Durchlauf lässt die Einträge, wie die Redakteure sie gesetzt haben. Ihre Einträge sind Element-URIs: Eine Seite mit einer Route je Sprache ist ein Eintrag.

**Features bringen eigene Abschnitte mit.** Ein Feature, das personenbezogene Daten verarbeitet, nennt im Schlüssel `elements` seiner Install-Einheit eine Datei (siehe [Features](features.de.md#die-install-einheit)). Die Aktivierung fügt die Abschnitte dieser Datei mit `\Nino\Elements::seed()` hinzu – nur hinzufügend, nie ein vorhandenes Element oder eine vorhandene Sprachfassung ersetzend. Das Deaktivieren löscht sie nicht: Dashboard und Dialog sagen, dass der Text stehen bleibt. Ein Abschnitt, der endgültig – in allen Sprachen – gelöscht wird, wird in `/nino/elements/removed` in `config.php` vermerkt (vom Listener auf `/nino/elements/committed`), damit ihn das nächste Update des Features nicht zurückbringt; legt man das Element von Hand neu an, entfällt der Eintrag.

**Ein Abschnitt lässt sich ändern.** `\Nino\Modules\Legal::SECTION`, `/nino/legal/section`, ist der Callback, durch den der Text eines Abschnitts geht, bevor er gezeichnet wird: Er bekommt `[ 'type', 'id', 'html' ]` und darf `html` ergänzen. Das Feature Consent setzt so die Schaltfläche für seine Einstellungen in seinen Abschnitt.

**Einen gelöschten Typ zurückkopieren.** Ein gelöschter Typ ist kein Abschnitt, und das Dashboard nennt die Datei der Einheit, die man nach `private/elements/<typ>.php` zurückkopiert: `_nino/Nino/Modules/Legal/install/elements/<typ>.php`.

**Abschalten.** Ein Projekt kann `\Nino\Modules\Legal` von Hand aus `/nino/modules` nehmen. Die beiden Shortcodes, die Routen und die Hinweise im Dashboard entfallen dann, die Elemente bleiben, wo sie sind. Nichts im Kernel braucht das Modul: Das Panel Sprache, das Panel Routen und das Dashboard fragen nach der Klasse, bevor sie sie benutzen.

**Was das Modul über Besucher speichert.** Nichts: kein Cookie, keine Anfrage an Dritte. Wer einen Abschnitt liest, sieht seinen Text und sonst nichts.

### Suchindex für Elements

`Modules\Search` ist ein Feature und kein Teil des Checkouts: Es kommt aus dem
Katalog [dapeio/nino-features](https://github.com/dapeio/nino-features), wird
nach `features/Search/` kopiert und im Panel Features der Workbench
eingeschaltet. Es führt einen kleinen sprachabhängigen Fuzzy-Index über
konfigurierte Felder flacher Elementtypen – definiert unter
`/nino/elements/index` in der `config.php`, abgelegt als je eine abgeleitete
Datei pro Typ unter `data/`, neu aufgebaut durch **Suchindex erstellen** in
seinem Panel Suche und nach jedem bestätigten Schreiben eines Elements – und
beantwortet `\Nino\Modules\Search::getElements( $appData, $type, $query )` mit
den passenden Elements der aktuellen Sprache in Trefferreihenfolge. Die
Indexkonfiguration, die Regeln der Rangfolge, der Lebenszyklus der
abgeleiteten Dateien und die API stehen in der eigenen README des Features im
Katalog: [features/Search/README.md](https://github.com/dapeio/nino-features/blob/main/features/Search/README.md).

## Ein eigenes Modul entwickeln

### Verzeichnis und Autoloading

Ninos Autoloader bildet Namespaces direkt auf Verzeichnisse ab. Für die
projekteigene Klasse `Project\Catalog\Catalog` wird standardmäßig folgende Datei
erwartet:

```text
/app/Project/Catalog/Catalog/Catalog.php
```

Die Auflösungsregeln sind bewusst asymmetrisch:

| Klassen-Namespace | Such-Roots |
| --- | --- |
| `Nino\Modules\...` | zuerst `_nino/`, dann `_admin/`, dann `features/` (`NINO_FEATURES_DIR`, falls definiert), dann `app/` (`NINO_APP_DIR`, falls definiert) |
| jeder andere `Nino\...` | ausschließlich `_nino/` |
| alle anderen Namespaces | `NINO_APP_DIR`, falls definiert, sonst `app/` |

Der Namespace `Nino\` gehört dem Kernel und kann aus dem Application-Root des
Projekts nicht überschrieben werden – mit einer bewussten Öffnung:
`Nino\Modules\*` ist kein Verzeichnis, sondern eine zusammengeführte Sicht
über vier Roots. Die Laufzeitmodule, die Nino mitbringt, liegen in `_nino/` –
die immer aktiven und die optionalen, die ein Projekt in `/nino/modules` ein-
oder ausschaltet (`Form`, `Navigation`, `Localepicker`,
`Maintenance`); die eigenen Ansichten der Workbench in `_admin/Nino/Modules/`
(`Dashboard`, `Elements`, `Text`, `Images`, `Logs`, `Routes`, `Users`,
`Language`, `Backups`, `Config`, `Features`); die Features, die ein Projekt
unter `features/` (oder `NINO_FEATURES_DIR`) installiert, je ein Verzeichnis
mit einem Manifest `feature.php` – die Features des Katalogs
kommen so an, ein Checkout bringt keines mit; und der Application-Root, wo der
eigene Code des Projekts liegt.
Unter dem Features-Root ist das Präfix `Nino/Modules/` das Verzeichnis selbst:
`features/Newsletter/Newsletter.php` ist `\Nino\Modules\Newsletter`, nicht
`features/Nino/Modules/Newsletter/`. Die Reihenfolge sagt, was ein Root dem
anderen antun darf: `_nino/` zuerst, damit ein ausgeliefertes Modul nie
verdeckt werden kann; `_admin/` vor `features/`, damit ein Feature keine
Workbench-Ansicht ersetzen kann; `features/` vor `app/`, damit ein Projekt
kein installiertes Feature ersetzen kann, indem es eine Datei neben seine
eigenen Module legt; `app/` zuletzt und damit nur ergänzend.

Aufgelöst wird der vollständige relative Pfad, nicht das erste Segment – ein
Modulname darf deshalb Klassen in mehreren Roots halten:
`\Nino\Modules\Elements` ist das Laufzeitmodul des Kernels in `_nino/`,
`\Nino\Modules\Elements\Admin` die Workbench-Ansicht dazu in `_admin/` –
zwei Hälften eines Moduls, jede dort, wo sie hingehört. Die eigenen Module
eines Projekts behalten ihren eigenen Namespace unter `app/`.

Innerhalb des gewählten Roots wird der vollständige Klassenname zum
Verzeichnispfad und der Klassen-Basename noch einmal als Dateiname angehängt.
Der Basename der Klasse und der PHP-Datei müssen daher übereinstimmen.
Klassenpfade werden auf erlaubte Zeichen begrenzt; dynamisch zusammengesetzte
oder benutzerkontrollierte Klassennamen gehören trotzdem nicht in die
Modulliste.

Der Kernel folgt demselben Aufbau. `_nino/Nino.php` enthält nur die
Boot-Funktionen `\Nino\init()`, `request()` und `output()` sowie den Autoloader;
jede Kernel-Klasse liegt in einer eigenen Datei unterhalb von `_nino/Nino/` –
`\Nino\Filesystem` in `_nino/Nino/Filesystem/Filesystem.php`, `\Nino\Auth` in
`_nino/Nino/Auth/Auth.php` und so weiter – und wird bei der ersten Verwendung
geladen.

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

### Panels der Workbench

Ein Modul kann der Workbench eine eigene Ansicht mitbringen. `/_admin` baut Navigation, Inhaltsbereiche, Bundles und Textfills aus einer Panel-Registry (`\Nino\Admin\Panels`), und ein Modul tritt ihr bei, indem es eine Frage beantwortet:

```php
public static function adminPanels( array &$appData ): array {
    return [ \Project\Catalog\Admin::class ];
}
```

Der Kernel fragt jedes aktive Modul in der Reihenfolge von `/nino/modules` (`\Nino\Modules::collect()`); die Ansicht existiert also genau so lange wie das Modul.

Die eigenen Ansichten der Workbench sind dasselbe in einem anderen Root: `_admin` hält die Hülle – Login, Leiste, Flächen, Registry, Bundles – und sonst nichts, und jede Ansicht darin ist ein Modul unter `_admin/Nino/Modules/<Name>/`, aufgebaut genau wie die obigen: `Admin/Admin.php` ist das Panel, `<Tab>/<Tab>.php` ein Tab davon, `assets/` seine Skripte und sein Stylesheet, `text/` seine Worte. `\Nino\Admin\Admin::modules()` liest das Verzeichnis statt einer Liste; eine Ansicht kommt also durch ein Verzeichnis hinzu und geht durch Löschen wieder – ohne das Verzeichnis ist `/_admin` ein Login und eine leere Leiste. Ein Panel ist eine Klasse mit zwei erforderlichen und einer Handvoll optionaler statischer Methoden, ohne Interface und ohne Basisklasse:

| Methode | Rückgabe |
| --- | --- |
| `actions()` | `[ 'catalog/list' => [ Class::class, 'apiList' ], ... ]` – vom `POST`-Handler der Workbench verteilt |
| `nav()` | `[ uri, label, weight = 50, group = 'content' ]` – die URI ist ein Slug und benennt Link, Inhaltsbereich und JS-Namensraum (`Nino.admin.<uri>`); ein Label, das mit `/` beginnt, ist ein Textfill-Schlüssel, alles andere wörtlicher Text – jedes ausgelieferte Panel nutzt einen Schlüssel, ein Modul liefert dafür seine `text/<locale>.php` mit; die Gruppe ist `content`, `structure`, `features` oder `system` – außer ein Panel, das ein Feature mitbringt (seine Klassendatei liegt unterhalb von `\Nino\Features::dir()`): das landet immer in `features`, gleich was es selbst nennt, und `features` von außerhalb zu nennen wird wie eine unbekannte Gruppe abgelehnt |
| `perm()` | die Berechtigung, die den Link zeigt und die Aktionen schützt – per Konvention `/_admin/<uri>/manage`; auf dem Tab Nutzerrollen des Panels Nutzer automatisch als Kästchen angeboten und Teil der Rolle **Editor**, die der Assistent schreibt, wenn die Gruppe `content` ist |
| `panes()` | Mount-Ids innerhalb des Inhaltsbereichs, Standard `[ '<uri>-list' ]` |
| `template()` | statt Mount-Ids: ein `.tpl`, das ganz in den Inhaltsbereich gerendert wird, projektrelativ und ohne Endung – für ein Panel, das seine Bereiche selbst anordnet |
| `layout()` | `'page'` (Standard: eine Spalte Inhalt in Lesebreite) oder `'workspace'` (die ganze Breite, die Leiste zu ihren Symbolen eingeklappt) |
| `head()` | `false` öffnet den Inhaltsbereich allein mit seinem Bildschirm; jedes andere Panel öffnet mit dem Kopf, der es benennt – das Label als `<h2>` des Bildschirms, die Tab-Leiste daneben, ein Platz für die eigenen Schaltflächen des Panels –, sodass ein Panel keinen eigenen Titel zeichnet. Das Dashboard ist das eine ausgelieferte Panel ohne; ein Panel mit eigenen Tabs legt sie über `Nino.adminUi.panelHead()` dorthin – und wo der Kopf schon die Leiste der Registry trägt, bekommt ein Bildschirm in einem ihrer Tabs seine eigene Leiste oben in der Fläche dieses Tabs, die der Registry bleibt unberührt |
| `icon()` | ein Inline-`<svg>` für die Leiste; ein Panel ohne eines zeigt bei eingeklappter Leiste den Anfangsbuchstaben seines Labels |
| `tabs()` | weitere Panel-Klassen, die als Tabs der Fläche dieses Panels erscheinen – jede ein vollständiges Panel mit eigenem `perm()`, Script und Hash-Präfix, in der Leiste nach dem Gewicht ihres `nav()` geordnet; `tab()` benennt den eigenen Tab dieses Panels, wenn das Nav-Label nicht passt. Die eigenen Module der Workbench tun das: Elementtypen unter Elemente, Textschlüssel unter Texte, Bildplätze unter Bilder, Nutzerrollen, Anmeldeschutz und Recovery-Passwort unter Nutzer, Übersetzungen unter Sprache. Das Panel eines Features bekommt einen weiteren, ohne ihn zu nennen: Erklärt das Feature Einstellungen, ergänzt die Registry einen Tab **Einstellungen** (`\Nino\Modules\Features\Settings`, Berechtigung `/_admin/features/manage`) in seiner Fläche |
| `assets()` | projektrelative `.js`-/`.css`-Dateien, nach den eigenen der Workbench nach `/_admin/.cache/` gebündelt |
| `text()` | ein Verzeichnis mit `<locale>.php`-Textfill-Dateien, die mit den eigenen der Workbench zusammengeführt werden |
| `summary( &$appData )` | eine Dashboard-Kachel `[ 'value' => ..., 'label' => ... ]` |
| `log( $action, $data )` | die Protokollzeile einer abgeschlossenen Aktion, `''` für keine |
| `scopes( &$appData )` | die Einzelrechte, die das Panel kennt, für die drei Listen des Rollen-Tabs: `[ { scope, door, label, areas: [ { id, label, perm?, actions: [ { id, label, perm, fields?: [ { id, label, perm } ] } ] } ] } ]` - `scope` das Präfix, das `Admin::isScoped()` liest, `door` das Recht des Panels, jedes `perm` in der Form eines Rechts und unterhalb von `scope`; was das nicht ist, lässt `Users\Admin::scopeOptions()` weg. Ein Panel ohne diese Methode bietet keine an |

Ein Modul benennt seine Dateien von dort aus, wo seine Klasse liegt, damit sie mit ihm umziehen:

```php
public static function assets(): array {
    return [ \Nino\Admin\Panels::relative( dirname( __DIR__ ). '/assets/admin.js' ) ];
}
```

Jede Aktionsmethode schützt sich selbst mit `\Nino\Admin\Admin::guardPerm( $appData, $request, self::MANAGE_PERM )`, das ohne Konto mit `401` und ohne Berechtigung mit `403` antwortet. Die Panels der Workbench selbst werden zuerst zusammengeführt; eine URI oder ein Aktionsname, den ein solches Panel bereits besitzt, geht nie an ein Modul. Die mitgelieferten Module sind die Referenz: `_nino/Nino/Modules/Form/Admin/Admin.php` ist eines mit Textfills und Dashboard-Kachel, und das kleinste vollständige Panel im Checkout ist die Fixture `tests/fixtures/features/Sample/Admin/Admin.php` – im Katalog ist `features/Search/Admin/Admin.php` das kleinste veröffentlichte und `features/Templates/Admin/Admin.php` eines mit eigenem Template und Workspace-Layout. Das [Panel-Rezept](recipes/admin-panel.md) des KI-Leitfadens führt durch ein vollständiges Panel samt Frontend.

Ein Modul, das eigene Dateien unter `data/` führt, registriert in `init()` den Callback `'/nino/admin/restore'`; das Panel Backups ruft ihn mit dem entpackten Backup und dem aktiven Datenverzeichnis auf, und das Modul führt zusammen, was ihm gehört (`Newsletter::callbackRestore()` im Newsletter-Feature des Katalogs). Ein Verzeichnis `install/` neben der Klassendatei – `manifest.php`, `templates/`, `text/` – macht ein Kernel- oder Projektmodul schließlich im Einrichtungsassistenten wählbar; siehe das [Library-Format](setup.de.md#library-format). Ein Feature trägt dieselbe Einheit, und `\Nino\Features::activate()` wendet sie an – ohne etwas zu überschreiben, das das Projekt hat –, wenn das Feature im Panel Features eingeschaltet wird; Manifest, Einstellungen und Lebenszyklus stehen unter [Features](features.de.md).

### Eigene Schreiboperationen absichern

Bei schreibenden Routen ist CSRF standardmäßig aktiv. Ein bewusstes `csrf => false` ist nur für Endpunkte sinnvoll, die einen anderen überprüfbaren Authentisierungsmechanismus besitzen, beispielsweise signierte Webhooks. Die Ausnahme gehört an die Route und sollte im Code begründet werden.

Zusätzlich sollte der Handler:

- Methode und erwartetes Eingabeformat eng begrenzen,
- Eingabelängen vor aufwendiger Verarbeitung beschränken,
- Berechtigungen mit `Auth::checkPermission()` prüfen,
- keine internen Fehlermeldungen oder Dateipfade an Clients zurückgeben,
- und für sensible Aktionen ein projektbezogenes Rate-Limit vorsehen.

## Separate Einstiegspunkte

Die Workbench verwendet denselben Kernel wie das Frontend und besitzt eine eigene `index.php`. Nach `\Nino\init()` initialisiert der Einstiegspunkt die Workbench und übergibt anschließend wieder an den gemeinsamen Request-/Response-Lebenszyklus:

```php
$appData = \Nino\init( true );
\Nino\Admin\Admin::init( $appData );
$request = \Nino\request( $appData, $_SERVER );
\Nino\output( $appData, $request );
```

`init( true )` startet ohne `config.php`, denn bis der Einrichtungsassistent gelaufen ist, gibt es keine. `Admin::init()` entscheidet dann, was die Route ausliefert: den Assistenten (`_admin/install/Install.php`), solange `Admin::isInstalled()` verneint, danach die Anmeldung und die Panels. Der Assistent ist kein Modul aus `/nino/modules`; ein Panel, das als Modul ausgeliefert wird, ist es und kommt wie jedes andere über `adminPanels()`.

`_admin/recovery.php` ist der dritte Einstiegspunkt und startet auf dieselbe Weise: Er prüft das Recovery-Geheimnis (`\Nino\Admin\Recovery`) und bietet eine Wiederherstellung, das Setzen des Passworts eines vorhandenen Kontos und das Anlegen eines Kontos mit Vollzugriff, sonst nichts. Die Workbench selbst fragt an einer Stelle danach, im Tab Recovery-Passwort von Nutzer (`Recovery::change()`).

## Fehlerbehandlung und Protokolle

`Runtime` registriert einen gemeinsamen Handler für PHP-Fehler und Exceptions. Bewusst ausgelöste Hinweise, Warnungen und Deprecation-Meldungen können protokolliert werden, ohne den Request zu beenden – ebenso eine Deprecation, die die Engine auslöst: Sie sagt, dass ein künftiges PHP etwas anders machen wird, nicht dass an diesem Request etwas falsch ist. Exceptions, alle übrigen Engine-Fehler und `E_USER_ERROR` führen zu einer `500`-Antwort. Das Protokoll eines Monats behält seine neuesten 1000 Einträge: Die Datei ist ein Array, das bei jedem Eintrag vollständig neu geschrieben wird – eine Vorlage, die pro Aufruf einen Hinweis auslöst, ließe sie sonst mit dem Verkehr mitwachsen.

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

Eine Session wird gestartet, wenn etwas in sie schreibt, nicht bei jeder Anfrage: beim Erzeugen eines CSRF-Tokens (also beim Rendern eines Formulars oder beim Prüfen eines Posts), beim Anmelden und wenn ein Besucher eine Sprache wählt. Eine Seite, die nichts davon tut, antwortet ohne Session, ohne `PHPSESSID`-Cookie und ohne Sessiondatei – was sowohl für den Plattenplatz zählt, den ein Crawler füllt, als auch dafür, was ein Cookie-Banner erklären muss. `\Nino\Runtime::startSession()` ist der Aufruf, der eine startet; jeder Schreibzugriff geht durch ihn, und auf der CLI, wo es nichts zu starten gibt, antwortet er `false`. `false` antwortet er auch, wenn PHP eine Session gar nicht starten kann – etwa bei einem unbrauchbaren `session.save_path`: Der Grund, den PHP genannt hat, landet im Fehlerprotokoll, und die Anfrage wird ohne Session beantwortet, statt zu einem 500 zu werden, den nichts erklärt.

Die Authentifizierung schützt zusätzlich durch:

- einen Dummy-Passworthash gegen messbare Unterschiede bei unbekannten Nutzern,
- Fehlversuchsgrenzen pro Konto und IP – die IP ist die, die `\Nino\Http::getClientIp()` auflöst; hinter einem Reverse Proxy muss also `/nino/http/proxies` gesetzt sein, sonst teilt sich das ganze Internet einen Eimer,
- automatisches Rehashing veralteter Passworthashes,
- zufällige Session-Tokens mit begrenzter Laufzeit,
- und die Möglichkeit, alle Sessions eines Nutzers zu widerrufen.

### Die Adresse des Besuchers

`\Nino\Http::getClientIp( $appData )` beantwortet, von wem eine Anfrage kommt. Das ist die TCP-Gegenstelle (`REMOTE_ADDR`) – außer sie ist selbst ein Reverse Proxy, der unter `/nino/http/proxies` steht; dann ist es der rechteste `X-Forwarded-For`-Sprung, der nicht selbst einer dieser Proxies ist.

Der Schlüssel ist wichtig, weil jede Regel je IP genau diesen einen Wert liest: der IP-Eimer der Anmeldedrossel, die Sendegrenze von `\Nino\Mail`, das Rate-Limit eines Formulars und die Adresse, unter der eine Session in der Workbench steht. (Eine Session ist nicht an eine Adresse gebunden – ihr Token ist der Schlüssel, unter dem sie liegt.) Hinter einem Proxy ohne gesetzten Schlüssel ist `REMOTE_ADDR` für jeden einzelnen Besucher der Proxy, alle teilen sich also einen Eimer – fünf Formularabsendungen von irgendwem halten dann die Mail jedes Besuchers für den Rest des Fensters auf.

```php
'/nino/http/proxies' => [
    '198.51.100.7',       // ein Proxy
    '10.0.0.0/8',         // oder ein ganzer Bereich
    '2001:db8::/32',      // v4 und v6, exakt oder CIDR
],
```

Leer, und das ist die Vorgabe, heißt „kein Proxy“: `X-Forwarded-For` wird vollständig ignoriert. Tragen Sie nur Proxies ein, die Sie tatsächlich betreiben oder bezahlen – den Header kann jeder Client schreiben, und eine Adresse in dieser Liste, die kein Proxy ist, verschenkt genau diese Fälschung. Alles links des rechtesten nicht vertrauten Sprungs wird nie gelesen, denn ein weiterleitender Proxy hängt an den Header an, statt zu prüfen, was schon darin steht. Das Panel Konfiguration bearbeitet dieselbe Liste und weist eine Zeile zurück, die weder Adresse noch CIDR-Bereich ist.

### Response-Header

Jede Response startet mit zentralen Sicherheitsheadern, darunter:

- `Strict-Transport-Security`,
- `Content-Security-Policy`,
- `X-Frame-Options: SAMEORIGIN`,
- `X-Content-Type-Options: nosniff`,
- `Cache-Control: no-store`.

Der letzte ist Ninos eigene Antwort darauf, ob eine Response gespeichert werden darf. Sie kam bisher von PHP, als Nebenwirkung der Session, die bei jeder Anfrage gestartet wurde; da eine Anfrage ohne Session-Inhalt nun keine mehr startet, steht der Header in `\Nino\Http`. Ein Projekt, das seine öffentlichen Seiten von Browsern und Proxies cachen lassen will, ändert diesen einen Wert – und nimmt in Kauf, dass ein Besucher eine Seite so lange einen Render hinter einer Änderung sieht, wie er es sagt.

Projektcode darf diese Header gezielt erweitern. Er sollte sie nicht pauschal ersetzen oder abschwächen, nur um eine unsaubere Inline-Integration zum Laufen zu bringen.

### Ausgaben und Uploads

- Elementfelder werden HTML-kodiert oder bei ausdrücklich erlaubtem HTML bereinigt.
- `Jstext` überträgt Daten JSON-kodiert und CSP-gebunden in JavaScript.
- Das Newsletter-Feature des Katalogs verrät nicht, ob eine E-Mail-Adresse bereits existiert.
- Bildverarbeitung begrenzt Uploads auf 8 MiB und Quelldateien auf 20 Millionen Pixel, bevor speicherintensive Verarbeitung beginnt. `\Nino\Images::limits()` beantwortet das alles für ein Formular zum Nennen: die Grenze des Kernels, was PHP durch eine Anfrage lässt (das Kleinere aus `upload_max_filesize` und `post_max_size`, 0 wo es keine gibt) und die Pixel. `\Nino\Images::reject( $bytes )` sagt, warum der Kernel Bytes ablehnen würde – `image_too_large`, `image_type` oder `image_too_many_pixels`, jeweils mit ihren Params – und ist das, womit die Upload-Endpunkte der Workbench antworten.
- Jedes abgeleitete Bild wird als WEBP geschrieben, wo GD eines schreiben kann: verlustfrei, wo vorher PNG stand – damit geht nichts verloren und ein Alphakanal kommt mit –, verlustbehaftet, wo vorher JPEG stand. Gemessen an einer 1600×1000-Quelle: 1 KB gegenüber 24 KB als PNG bei Strichgrafik, 151 KB gegenüber 199 KB als JPEG beim Foto. Ein GD ohne WEBP oder `'/nino/images/webp' => false` in der `config.php` schreibt weiter PNG und JPEG; die mitgelieferte `.htaccess` deklariert den Typ für Hosts, deren eigene `mime.types` älter ist.
- PHP-Datendateien in öffentlich erreichbaren Verzeichnissen erhalten Schutzstubs oder liefern ausschließlich Werte zurück.

Diese Vorkehrungen nehmen Projektcode nicht die Verantwortung ab. Daten aus Request, Dateien oder externen APIs bleiben unvertrauenswürdig, bis sie für ihren konkreten Zielkontext validiert und sicher ausgegeben wurden.

## Tests und Änderungsworkflow

Nino verwendet eigenständige Smoke-Tests ohne PHPUnit. Jeder Test erstellt ein isoliertes temporäres Projekt und prüft einen anderen Bereich:

| Test | Schwerpunkt |
| --- | --- |
| `tests/kernel-smoke.php` | Kernel, Routing, Rendering, Auth, Filesystem und Module |
| `tests/features-smoke.php` | der Feature-Vertrag gegen `tests/fixtures/features/`: Erkennung, Manifestprüfung, Versions-Constraints, jeder Settings-Typ, Aktivierung mit nur ergänzend angewendeter Einheit, Updates über den Upgrade-Haken, Deaktivierung und die ausgelieferten Manifeste |
| `tests/catalogue-smoke.php` | der Katalog: der https-Client hinter einem Stub, die abgetrennte Signatur, was ein Katalogdokument sagen muss, was er diesem Kernel anbietet, eine Installation und ein Update aus im Test gebauten Archivbytes und jede Abweisung auf dem Weg – ein feindliches Archiv darunter |
| `features/<Name>/tests/<key>-smoke.php` | der eigene Test eines Features, der mit ihm reist – `features/Search/tests/search-smoke.php` des Katalogs etwa prüft Aktivierung, Index-Lebenszyklus, Fuzzy-Rangfolge, Sprachen und den Admin-Neuaufbau. In einem Checkout leer, denn der bringt kein Feature mit |
| `tests/admin-smoke.php` | die Shell des Workbench und seine Inhalts-Panels: Text-Blacklist und HTML-Sanitizer, Element- und Bildoperationen |
| `tests/admin-system-smoke.php` | die Struktur- und System-Panels: Session-Gate, Konten, Rollen und Rechte, Elementtypen, Backups und Wiederherstellung, das Aktivitätsprotokoll und ein Rendern jedes Panels in jeder Oberflächensprache |
| `tests/install-smoke.php` | Installationsschritte, erzeugte Struktur und Selbstsperre |
| `tests/legal-smoke.php` | das Modul Legal: die beiden Typen und ihre Abschnitte in jeder Sprache, die Shortcodes und Platzhalter (und was ein Wert nicht kann), die Routen je Sprache mit abgeleiteten und gemeinsamen Pfaden, die Navigation `legal`, `addLocale()`, `check()` und die Abschnitte, die die Einheit eines Features mitbringt, nur hinzufügend und mit dem Grabstein eines gelöschten |
| `tests/keys-smoke.php` | die Textschlüssel-Grammatik und alles, was Nino ihr nach ausliefert: die Fragmente der Einheiten, jeder Schlüssel, den ein ausgeliefertes Template liest, die Schlüssel-Literale im Code und dass keine alte Schlüsselform übrig ist |
| `tests/*-js-smoke.js` | browsernahe Logik der Verwaltungsoberflächen |
| `tests/concurrency-smoke.php` | parallele und atomare Schreibvorgänge |

Lokal werden sie einzeln ausgeführt:

```bash
php tests/kernel-smoke.php
php tests/admin-smoke.php
php tests/admin-system-smoke.php
php tests/install-smoke.php
php tests/features-smoke.php
php tests/catalogue-smoke.php
php tests/keys-smoke.php
php tests/legal-smoke.php
for test in features/*/tests/*-smoke.php; do [ -e "$test" ] || continue; php "$test" || exit 1; done
for test in tests/*-js-smoke.js; do node "$test"; done
php tests/concurrency-smoke.php
```

`tests/harness.php` ist der gemeinsame Bootstrap: Er lädt den Kernel und die Shell der Workbench und stellt `check()`, `ninoSandbox()` (ein isoliertes Projektverzeichnis, zwei Sprachen, keine Module), `ninoSandboxDir()`, `ninoWarnings()` (die seit dem letzten Aufruf aufgezeichneten Warnungen, für einen Test, der eine erwartet) und `ninoDone()` bereit. Der Test eines Features lädt ihn aus dem Checkout drei Ebenen über sich oder aus dem, das `NINO_ROOT` nennt – so läuft derselbe Test gegen eine andere Nino-Version.

Neben den Tests läuft statische Analyse. PHPStan liest `phpstan.neon` (Level 5, der Kernel, die Workbench, `app/` und `features/`, ohne die eigenen Tests eines Features); `phpstan-baseline.neon` daneben führt die Funde, die beim Einführen der Prüfung offen waren, sodass nur ein neuer Fund fehlschlägt. Wer einen der gelisteten Funde behebt, entfernt seinen Eintrag; ein neuer Fund wird nie in die Baseline aufgenommen, um ihn zum Schweigen zu bringen. ESLint liest `eslint.config.mjs` und prüft die Browser-Skripte auf undefinierte Namen, ungenutzten Code und `==`; es deklariert die Browser-Globals und den Namensraum `Nino`, sonst nichts. Beides ist keine Abhängigkeit des Produkts: Es gibt keine `composer.json` und keine `package.json`, CI installiert beide Werkzeuge selbst.

```bash
phpstan analyse
npx eslint .
```

Die GitHub-Actions-Pipeline verwendet PHP 8.4 und Node 22, führt die Syntaxchecks über alle PHP- und JavaScript-Dateien aus, dann PHPStan und ESLint, dann diese PHP- und JavaScript-Smoke-Tests. Ein zweiter Job, `features`, klont den Katalog [dapeio/nino-features](https://github.com/dapeio/nino-features) und kopiert jedes seiner Features in den Checkout, prüft dann ihre Manifeste und führt ihre eigenen Tests und PHPStan dagegen aus – die Sperre gegen eine Kernel-Änderung, die ein veröffentlichtes Feature bricht; die CI des Katalogs macht das Umgekehrte gegen Ninos `main` und den jüngsten Tag. `features/*/tests/` selbst ist in einem Checkout leer, weshalb die Schleife oben ein Glob ohne Treffer überspringt.

Für Änderungen am Kernel oder an einem Modul empfiehlt sich dieser Ablauf:

1. Verhalten zunächst im passenden Smoke-Test reproduzieren oder spezifizieren.
2. Die kleinstmögliche Änderung implementieren.
3. Alle PHP- und JavaScript-Smoke-Tests, die Syntaxchecks und die statische Analyse ausführen.
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
| `/nino/http/output` | kompletter `$request`, fertig | der einzige Hook, der den gerenderten Body und den endgültigen Header kurz vor dem Senden in der Hand hat; ein Modul, das die Antwort an das anpasst, was die Seite tatsächlich enthält (eine Quelle der `Content-Security-Policy` für einen Host, den die Seite nutzt), registriert sich hier, denn `/nino/http/response` läuft, bevor der Body existiert. Läuft nach Priorität, und `Modules\Cache` legt die Seite mit Priorität 9 ab; ein Callback mit der Standardpriorität 5 ist also Teil dessen, was gespeichert wird. Er läuft nicht für eine Seite, die der Cache beantwortet, und nicht für die Wartungsseite – beide werden schon in der Response-Phase gesendet; von dem, was ein Callback hier ändert, trägt ein Treffer den Body und die `Content-Security-Policy` (von `Modules\Cache` mit Priorität 9 gespeichert, eine Änderung mit der Standardpriorität 5 ist also darin) und sonst nichts |
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
| `/nino/elements/committed` | `{ operation, type, uri, previousUri, locale }` | Benachrichtigung, nachdem ein Element eingefügt, geändert oder gelöscht und gespeichert wurde; kann den abgeschlossenen Schreibvorgang nicht verwerfen |
| `/nino/legal/section` | `{ type, id, html }` | ein Abschnitt von Impressum oder Datenschutzerklärung wird gleich gezeichnet: Ein Listener darf `html` ergänzen (Consent setzt so seine Schaltfläche in seinen Abschnitt); nur die Abschnitte von `Modules\Legal` |
| `/nino/mail/send` | `{ to, subject, body, replyTo, sender, headers, sent }` | eine Mail anders zustellen als durch `mail()`: Ein Transport, der sie genommen hat, setzt `sent` auf `true` oder `false`, und `mail()` wird übersprungen; ein `sent`, das `null` bleibt, reicht die Mail weiter |
| `/nino/images/render` | `{ mode, bytes, width, height, basePath, source: { width, height, type, orientation }, filename }` | ein hochgeladenes Bild anders rendern als mit gd: Ein Handler, der die Datei geschrieben hat, setzt `filename` auf den Pfad unter `/images/`, `false` weist den Upload ab, `null` reicht ihn weiter |
| `/nino/admin/restore` | `{ dataDir, staging }` | `/_admin` stellt ein Backup wieder her: Ein Modul führt seine eigenen `data/`-Dateien aus der entpackten Kopie in das aktive Verzeichnis zusammen |
| `/nino/admin/action` | `{ action, panel, status, user, data }` | Eine Panel-Aktion in `/_admin` ist gelaufen und hat geantwortet – reine Benachrichtigung, und auch für eine gescheiterte Aktion. Sagt, wer im Werkzeug was getan hat; *was sich geändert hat*, sagen die Kernel-Events darüber. `data` ist die gesendete Nutzlast mit geleerten `pw`, `current` und `currentPassword` auf oberster Ebene – ein Listener sieht, dass ein Passwort gesendet wurde, nie welches. Ein Geheimnis unter einem anderen Schlüssel (die `secret`-Einstellung eines Features in `features/settings`) wird nicht geleert |

`/nino/mail/send` und `/nino/images/render` sind die zwei Hooks, die eine Kernel-Aktion ersetzen, statt auf sie zu reagieren. `\Nino\Mail::send()` löst den ersten nach der Sendegrenze je IP und nach dem Bereinigen jedes Header-Werts aus – mit dem Betreff noch roh, denn wie ein Betreff kodiert wird, ist Sache des Transports – und ruft `mail()` nur, wo kein Handler `sent` gesetzt hat. Ein Modul oder Feature, das über SMTP oder eine API zustellt, registriert sich hier in `init()`; `\Nino\Mail::TRANSPORT` ist der Name.

`\Nino\Images::process()` und `\Nino\Images::fit()` lösen den zweiten **nach den Prüfungen und vor dem Kodieren** aus, und genau darin liegt der Punkt: Die Byte-Grenze, der Pfad, der Bildtyp und die Pixelgrenze sind das, was einen Upload-Endpunkt sicher hält – ein Feature darf sie nicht dadurch abschalten können, dass es sich registriert. Ein Handler bekommt also Bytes, von denen bereits feststeht, dass sie ein dekodierbares Bild vernünftiger Größe sind, dazu `mode` (`crop` bei `process()`, `fit` bei `fit()`), die Zielmaße, den deterministischen `basePath` ohne Endung und Maße, Typ und `orientation` der Quelle. `source.width` und `source.height` sind die Pixel, wie sie gespeichert sind; `orientation` ist der EXIF-Wert 1-8 eines JPEG (bei allem anderen 1), und bei 5-8 wird das Bild auf der Seite liegend angezeigt – ein Handler, der das Bild selbst rendert, muss ihn also anwenden. Eine Nutzlast ohne den Schlüssel, wie ältere Kernel sie schickten, bedeutet 1. Wer das Bild gerendert hat, schreibt es selbst unter `/images/` und setzt `filename` auf den relativen Pfad; ein Pfad, der aus diesem Verzeichnis herausführt, wird ignoriert und gd rendert doch. `\Nino\Images::RENDER` ist der Name. Hier gehört ein umfangreicherer Uploader hin – ein srcset, eine imagick-Pipeline, ein Upload, der ganz woandershin geht – und nicht in eine Kopie der beiden Methoden. WEBP stand früher als Beispiel hier und ist inzwischen die Ausgabe des Kernels selbst, überall wo GD eine schreiben kann.

Callback-Namen sind einfache Strings. Behandle die etablierten Namen und Argumentformen trotzdem wie eine API: Eine Umbenennung oder ein geänderter Argumenttyp kann jedes registrierte Modul betreffen.

### Zwei Erweiterungsflächen, ein Gedanke

Ein Modul erreicht das Framework auf zwei Wegen, und der Unterschied ist nicht
Events gegen Methoden – es ist *Reaktion* gegen *Deklaration*.

**Reaktion** ist die Aufgabe der Callbacks. Etwas ist passiert, wer will, läuft
mit. Die feuernde Seite kennt keinen Listener, mehrere laufen nach Priorität,
und manche dürfen ablehnen (`/nino/elements<typ>/insert` und Geschwister geben
`false` zurück). Jeder Name in der Tabelle oben ist von dieser Art.

**Deklaration** ist die Aufgabe des Panel-Contracts von `/_admin`. Ein Modul
beantwortet `adminPanels()` mit einer Klasse, und diese Klasse *sagt, was sie
ist*: Uri, Label, Gewicht, Gruppe, Berechtigung, Panes, Assets, Texte, Tabs
(siehe `\Nino\Admin\Panels` und Abschnitt 7). Daraus baut das Werkzeug eine
Registry, die es prüfen kann (eine kaputte Uri, eine belegte Uri, ein fehlendes
`actions()`/`nav()`, ein Asset, das es nicht gibt – jeweils mit der Klasse
benannt, die es verursacht hat), nach Gruppe und Gewicht sortieren und aus der
es Rail, Panes, Asset-Bundle, Fills und Rechteliste ableitet. Ein Callback-Bus
kann das nicht, ohne selbst zur Registry zu werden, und er hat die falsche
Grundregel dafür: Bei `doCallbacks()` gewinnt der letzte Schreiber, während in
einer Panel-Registry der *erste* Anspruch auf eine Uri stehen bleiben muss –
sonst könnte ein Modul ein Kernpanel ersetzen, indem es dessen Namen wählt.

Die Grenze lautet also: **Events über die Werkzeuggrenze, Contract darin.**
`/_admin` registriert seine eigenen Routen wie alles andere als Callback, und
`/nino/admin/action` und `/nino/admin/restore` sind die beiden Punkte, an denen
ein Modul auf das reagiert, was die Workbench tut, ohne dort ein Panel zu
besitzen.

## Weiterführende Handbücher

- [Konzepte](concepts.de.md) erklärt die Kernsäulen und den technischen Gesamtzusammenhang.
- [Erste Schritte](getting-started.de.md) führt vom Checkout zum eingerichteten Projekt.
- [Einrichtungsassistent](setup.de.md) dokumentiert alle Installationsschritte und Schreibregeln.
- [`/_admin`-Workbench](_admin.de.md) beschreibt jedes Panel, die Rollen und die Recovery-Seite.
- Der **Template-Baukasten** – Seitentemplates aus ganzen Abschnitten – ist ein Feature aus dem Katalog [dapeio/nino-features](https://github.com/dapeio/nino-features); sein [Handbuch](https://github.com/dapeio/nino-features/blob/main/features/Templates/docs/templates.de.md) liegt dort ebenfalls.
- [Features](features.de.md) erklärt installierbare Features: Manifest, Einstellungen, Aktivierung, Updates und die Tests eines Features.
- [Deployment](deployment.de.md) beschreibt Webserver, Sicherheit und Go-live.
- [Security Policy](https://github.com/dapeio/nino/blob/main/SECURITY.md) erklärt den Umgang mit Sicherheitsmeldungen.
