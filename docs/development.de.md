# Nino — Entwickler-Handbuch
*[English](development.md)*

**Links:**
[README](../README.de.md) · [Design-Handbuch](design.de.md) · [_editor-Handbuch](_editor.de.md) · [_admin-Handbuch](_admin.de.md) · [_install-Handbuch](_install.de.md) · [_templates-Handbuch](_templates.de.md) · [Security Policy](../SECURITY.md) · [Changelog](../CHANGELOG.md)

## Übersicht
Nino verzichtet in der Entwicklung bewusst auf ein Installations-Tools oder eine rein UI-basierte Arbeitsweise. Entwickler benötigen daher Grundkenntnisse in PHP und fortgeschrittene Kenntnisse in HTML/CSS/Javascript um eine Webseite vollständig umsetzen zu können. Die Administration und der Betrieb eines fertigen Projekts ist dann jedoch ohne technisches Knowhow möglich.

Für die Entwicklung einer einfachen Webseite mit statischen und dynamischen Inhalten sind wenig Eingriffe in PHP notwendig. Templates werden in HTML+ *(HTML + [[/text/fills]] + [shortcodes])* angelegt. Die Kernel-Konfiguration, globale und lokale Texte *(Textfills)* und Daten mit typisierten Vorgaben *(Elemente)* werden in PHP-Arrays gespeichert. Diese können über das grafische _admin Interface erstellt und anschließend über _editor gepflegt werden. Weitere Assets (CSS/JS/Images) werden klassisch angelegt.
Bei fortgeschrittener Modulentwicklung für erweiterte Funktionen, wie zum Beispiel die Integration einer API oder die Einbindung von Datenbanken, sind fortgeschrittene PHP-Kenntnisse notwendig.

## Grundlagen
### Philosophie
Nino baut auf eine Reihe von Grundlagen auf, die sich in vielen Entscheidungen wiederfinden:
- **Keine Abhängigkeiten.** Kein Libraries, keine Paketverwaltung, keine Datenbanktreiber. Die gesamte Webseite existiert rein im Dateisystem.
- **So einfach wie möglich** Kein aufwändiger Synthax, keine Objekte, keine fremden Libraries mit anderen Konzepten. Jede Entscheidung soll zu Gunsten Performance und Simplification getroffen werden.
- **Der gesamte Zustand lebt in einem Array.** Alle Daten eines PHP-Lifecycle liegen in einem Array (`$appData`) und werden bei jeder Methode/Funktion als Referenz weitergereicht. Keine Objekte - nur Klassen und Methoden. Ähnlich dem Linux-Pipe-Konzept übernimmt jede Klasse/Methode seine Aufgabe und ändert/ergänzt `$appData`.
- **Logik und Templates sind strikt getrennt. Immer.**
  `.php`-Dateien geben kein HTML aus;
  `.tpl`-Dateien beinhalten keine PHP-Logik.
  Diese Trennung macht manche Wege etwas länger - aber schafft Struktur und schützt auch bei großen Projekten die Transparenz.

### Grundlegender Ablauf
Der Ablauf eines PHP-Livecycle lässt sich stark vereinfacht in 3 Schritten zusammenfassen:
1. Die `index.php` initiiert über Nino ein neues `$appData-Array` auf Basis der `config.php`. Der init-Prozess lädt alle Kernel-Klassen und alle in `config.php` freigegebenen Module und ermöglicht ihnen das Registrieren von Callbacks.
2. Der aktuelle Request wird über `index.php`mit den in der `config.php` hinterlegten Routen *(METHOD://uri => ..)* abgeglichen. Eine Response mit body/header/statusCode wird in der `appData` erstellt - das betroffene Template der Route wird als body gerendert. Abhängig der Sprache werden die globalen und lokalen Textfills aus `/text` geladen und ersetzt. Alle im Template vorhandenen `[shortcodes]` *(Templates, Elements, etc.)* mit Ihrer Callbacks werden ausgeführt. Module können bei Ausführung der Kernel-Callbacks die Response verändern.
3. index.php lässt die Response dann von Nino ausgeben.

### Das `$appData`-Konzept
Ein entscheidender Teil der Nino-Philosophie ist der Single-Point-of-Data.
Fast jede Funktion im Kernel nimmt `array &$appData` als ersten Parameter. Es gibt keinen DI-Container, keine Service-Objekte/Singletons, keine Globals jenseits von `$appData` selbst. Das assoziatives Array trägt alle Werte des laufenden PHP-Lifecycle.
Das Array wird zu Beginn über `$appData = \Nino\init();` in der index.php initiiert. Auf Basis der `config.php` werden persistente Variablen aufgenommen *(z.B. [/nino/http/routes] )*. Temporäre Variablen im Livecycle beginnen mit einem Punkt. *(z.B. [./nino/locales/current])*. Diese können einfach gesetzt werden und verfallen nach dem Script-Ende.
Daraus resultiert:
  Es gibt genau ein `$appData` pro Request. Es wird in `init()` frisch gebaut und beim Prozessende verworfen. Persistente Variablen müssen in die `config.php`, Textinhalte in globale oder lokale Textfiles in `/text`, Templates in `/templates`, Daten nach wiederkehrenden Mustern als Elemente in `/elements` geschrieben werden.
Der Zugriff zum laufenden `appData-Array` erfolgt nur über Callbacks. Diese werden bei Modulen in der init()-Methode registriert.

### Callbacks: der eine Hook-Mechanismus
Die Kommunikation und Dynamik zwischen Nino und seinen Modulen erfolgt nur über Callback-Event-Hooks. Jedes Modul kann globale Callbacks registrieren und ausführen.

```php
\Nino\Callbacks::registerCallback( array &$appData, string $name, mixed $callback, int $prio = 5 ): void;
\Nino\Callbacks::doCallbacks( array &$appData, string $name, mixed &$args = null ): mixed;
```

`$name` ist dabei ein einfacher String-Key — nach der Konvention (`/nino/http/response`,
`/nino/http/response/GET://_editor`, `/nino/html/shortcode/template`). Die festgelegten Callbacks im Kernel werden ebenfalls nach diesem Muster registriert und ausgeführt. Jedes Modul kann Kernel-Callbacks nutzen oder Eigene registrieren.
`$prio` ist `0`–`9`, niedriger läuft zuerst

`doCallbacks()` ruft jeden auf `$name`registrierten Callback in Prioritätsreihenfolge auf und reicht `$args` durch jeden hindurch.
Dieser Mechanismus wird im Kernel genutzt für:
Route-Dispatch (`/nino/http/response/...`),
Shortcode-Dispatch (`/nino/html/shortcode/...`)
und kann als ein allgemeiner Projekt-Erweiterungspunkt verwendet werden (`/project/my/callback`).

*(Alle Kernel-Callbacks sind am Ende dieser Docs aufgelistet.)*

## Der request/response-Lifecycle im Details
Jeder Request durchläuft dieselben drei Top-Level-Aufrufe im Kernel.

```php
$appData = \Nino\init();
$request = \Nino\request( $appData, $_SERVER );
\Nino\output( $appData, $request );
```

`$appData` wird durch alle drei per Referenz gereicht — `init()` baut es,
`request()` mutiert es beim Auflösen der Antwort, `output()` bildet die Antwort ab.

### 1. `\Nino\init()` — `$appData` bauen, Module initiieren

```php
$appData = [ './nino/uid' => dirname(__DIR__) ];
AppData::prepare( $appData );      // leere Laufzeit-Keys vorbereiten
Runtime::init( $appData );         // PHP-Session starten/fortsetzen
Filesystem::init( $appData );      // Datei-Lese-Cache aufsetzen
AppData::init( $appData );         // /config.php lesen, in $appData mergen
Locales::init( $appData );         // aktuelle Locale des Requests auflösen
Auth::init( $appData );            // aktuellen Session-Nutzer auflösen, falls vorhanden
Modules::callModules( $appData, 'init' );
```

Die letzte Zeile `Modules::callModules()` nimmt `config.php`s `/nino/modules`-Array,
lädt alle enthaltenen Klassen-Namen und ruft `$className::init( &$appData )` auf.

Einzige Ausnahme:
`_editor/Editor.php` und `_admin/Admin.php` sind **keine** Module in dieser
Liste — sie sind eigenständige Einstiegspunkte, die ihr eigenes
`::init()` explizit aufrufen (siehe "Die `_editor`/`_admin`-Add-ons" unten),
da ein Request an `/_editor` nie über `config.php`/`index.php` der
Haupt-Site läuft.

### 2. `\Nino\request()` — Route auflösen, Request/Response-Callbacks ausführen

```php
function request( array &$appData, array $request ): array {
    Http::request( $appData, $request );      // Method/Uri/Query/Header/Body parsen,
                                                // Response-Header mit den
                                                // Security-Header-Defaults vorbelegen (s.u.)
    Locales::request( $appData, $request );   // Locale für diesen Request festlegen
    Http::response( $appData, $request );      // Route auflösen, DANN Callbacks laufen lassen
    Html::addFills( $appData, [ /* uri, locale, aktueller Nutzer, ... */ ], '*' );
    Html::response( $appData, $request );      // falls body ein String ist, rendern
    return $request;
}
```

`Http::request()` sammelt und säubert alle Werte des Requests und hinterlegt sie als `$request['/nino/http/request']`. Es erstellt eine praktisch leere `$request['/nino/http/response']`mit den Security-Header-Defaults des Frameworks
*( Content-Security-Policy, Strict-Transport-Security, X-Frame-Options etc.)*, einem leeren Body und Statuscode 200.
Ein Modul kann nun den vorhandenen Header erweitern, den Statuscode ändern und den Body füllen. Dies wird später `Http::output()` ausgegeben.

`Http::response()` ist dann für das tatsächliche Routing verantwortlich:

```php
$routeData = self::requestRoute( $appData, $request['/nino/http/request']['uri'], $request['/nino/http/request']['method'] )
    ?? self::requestRoute( $appData, '/404', 'GET' )
    ?? [ '.uri' => '/404', 'statusCode' => 404 ];

// Die eigenen Header-Felder einer Route *erweitern* den vorbelegten
// Response-Header, statt das gesamte Array zu ersetzen
if( isset( $routeData['header'] ) === true )
    $routeData['header'] = array_merge( $request['/nino/http/response']['header'], $routeData['header'] );

$request['/nino/http/response'] = array_merge( $request['/nino/http/response'], $routeData );

Callbacks::doCallbacks( $appData, '/nino/http/response', $request );
Callbacks::doCallbacks( $appData, '/nino/http/response/'. $request['/nino/http/request']['method']. ':/'. $request['/nino/http/response']['uri'], $request );
```

Zwei Callback-Dispatches passieren hier - der Unterschied ist in der
Praxis wichtig:

- `/nino/http/response` feuert für **jeden einzelnen Request**, egal
  welche Route.   *( z.B.`Csrf` )*
- `/nino/http/response/<METHOD>://<URI>` feuert nur für die **exakte**
  Route - und zwar geankert an der aufgelösten *Response*-Uri
  (`$request['/nino/http/response']['uri']`), nicht an der rohen
  Request-Uri: nach Routing/Locale-Rewrite können beide voneinander
  abweichen. So kann ein besonderes Verhalten bei bestimmten Seiten
  oder POST/API-Mechanismen umgesetzt werden. *( z.B. `Auth`
  Login/Logout )*

Nach diesen Callbacks ist`$request['/nino/http/response']['body']` entweder ein reiner HTML-String für `\Nino\Html::renderHtml()` oder wird, z.B. für eine JSON-API-Antwort,  später in `Http::output()` zu JSON kodiert.

### 3. `\Nino\output()` — senden

```php
function output( array &$appData, array $request ): void {
    Http::output( $appData, $request );
}
```

`Http::output()` kodiert nun Nicht-String-Body als JSON und merged die
Default-Response-Keys zum Header
(`array_merge( self::$_defaultResponse['header'],
self::filterHeaderFields( $request[...]['header'] ) )` 
Der Kernel sendet den Statuscode, echot den Body und macht `exit`.
Danach ist das Script zu Ende — es gibt keine "Teardown"-Phase.

**Wichtig:** Eine Ausgabe während der Runtime *(`echo '...';`, `header('Location: ...')`, etc.)* bricht die gesamte Architektur und ist ein Bug. `Http::output()`s eigener `http_response_code()`-Aufruf läuft bewusst am Ende und setzt den Statuscode zurück. Änderungen von Statuscode, Header-Location, etc. müssen direkt in `$request['/nino/http/response']` erfolgen.

## Dauerhafte Daten
### Überblick
Für den Umgang mit persistenten Daten stellt Nino drei unterschiedliche Konzepte bereit. Alle werden im Dateisystem gespeichert und können über den Kernel, _admin und _editor gelesen und geschrieben werden. Sie unterscheiden sich in der Struktur und damit verbunden in der Anwendung.

#### 1. `config.php` - der Platz für globale key/value-Werte
Hier sitzt die Quelle für Startwerte der `$appData`: Routen, Module, Locales, Nutzer, Asset-Bundles, Error-/Session-Flags. Hier werden alle persistente
Änderungen der Kernel- und Modul-Konfiguration gespeichert - auch für zusätzliche Module. `$appData` wird jedoch niemals automatisch geschrieben. Dies erfolgt gezielt über:
`AppData::writeContentData( $appData, array $keys )`
Die Datei wird hier neu eingelesen und gemeinsam mit den *übergebenen* Keys gemerged. Die Integration von fremden Werten in _admin ist noch nicht umgesetzt.
#### 2.  `elements/*.php`- der Platz für typisierte Werte
Hier können Daten nach festgelegten Modellen abgelegt und gelesen werden *(z.B. Beiträge, Mitarbeiter, Rezensionen, Neuigkeiten, etc.)*.
Für jedes Element wird ein Typ-Modell mit Felder definiert *(z.B. title/string/200 chars/multilocale, price/float/€-Suffix, product/image/800x600, ..etc.)* die anschließend über _editor mit Elementen befüllt werden können. Die Felder/Variablen können unterschiedliche Typen haben *(Strings, Integer, DateTime, Boolean - auch Images und Float)*, ein- oder mehrsprachig sein oder mit zusätzlichen Regeln definiert werden *(max-length, suffix, etc.)*. Das Typ-Modell wird zusammen mit den Elementen in einer Datei gespeichert *(z.B. `elements/services.php`)*.
Jeder Typ hat eine eindeutige Type-URI *(z.B. `/services`)*, einen globalen Titel und sein Model mit der Variablen-Definition.
Jedes Element hat eine eindeutige URI *(z.B. `/webdesign` - somit `/services/webdesign`)* und -abhängig des Models- globale und lokale Werte. 
Für das komfortable Erstellen/Bearbeiten von Typen steht in _admin eine grafische Oberfläche bereit. Eine präzise Dokumentation für die manuelle Bearbeitung der Datei folgt.
**Der Zugriff** erfolgt über den integrierten Template-Shortcode `z.B. [elements /services]<h5>[title]</h5>[/elements]` oder die Kernel Klasse `\Nino\Elements::getElement( $appData, ..), etc.`
Das Erstellen/Bearbeiten/Löschen von Elementen (nicht Typen) -inklusive Image-Upload- erfolgt (ebenfalls grafisch) über `_editor`.
#### 3. `text/*.php` - der Platz für globale/lokale Textinhalte
Hier liegen alle sprachunabhängigen *(text/global.php)* und sprachabhängigen Texte *(`text/de_DE.php`, `text/en_US.php`, ...). Der Aufbau ist ebenfalls key/value - richtet sich in der Integration jedoch gezielt an Sprachbausteine in Templates. Die globalen und -zur aktuellen Sprache passenden- lokalen Texte werden automatisch bei Start geladen und bei `\Nino\Html:renderHtml()` ersetzt. Textfills sind im Regelfall nach der Konvention `[[/category/type/name]]` angelegt und werden genau in dieser Form  im Template ersetzt. Verschachtelungen *(z.B. [[/webpage[[/nino/http/response/uri]]/title]])*sind ebenso möglich und werden vollständig aufgelöst. Das Festlegen der möglichen Text-Keys erfolgt in _admin. Das Befüllen der Text-Werte in _editor.

## Basic Templating (Shortcodes, Textfills)

Das Herzstück der Template-Engine sitzt in`Html::renderHtml( $appData, $html )`. Diese Methode wird automatisch beim Laden von Templates ausgeführt und macht immer 3 Dinge:

```php
$html = self::_renderFills( $appData, $html );       // [[key]] -> Wert
$html = self::_renderShortcodes( $appData, $html );  // [name args]content[/name] -> Rückgabewert des Callbacks
$html = Callbacks::doCallbacks( $appData, '/nino/html/render', $html );
```

### 1. Textfills
Hier werden alle globalen und lokalen Textfill-Arrays zusammengefasst und mit  `str_replace( [ [[key1]], [[key2]], .. ], [ val1, val2, .. ] )`ersetzt.
***Wichtig:** Textfills können -zusätzlich zum Inhalt der .`php`-Dateien- über `Html::addFills()` während der Laufzeit erstellt werden.*
Die Ersetzungsschleife läuft, bis sich die Anzahl der
`[[` nicht mehr ändert — der *Wert* eines Fills kann also selbst ein
weiteres `[[key]]` enthalten.
**!** Javascript und CSS werden bei Asset-Rendering über den Kernel ebenfalls mit Textfills gerendert.

### 2. Shortcodes
Alle registrierten Shortcodes mit dem Muster (`[shortcodename arg1 arg2="value"]content[/shortcodename]`, oder
selbstschließend `[shortcodename arg1]`) werden hier von einem Regex erfasst. Shortcodes werden davor über`Html::addShortcode()` registriert und führen bei jedem Treffer über `_doShortcode()`den angegebenen Callback aus. Die private statische Funktion..

1. ..parst `arg1`, `arg2="value"` in ein gemischtes positionales/keyed
   `$args`-Array (ein blankes Wort wird ein positionaler Eintrag,
   `key="value"` wird `$args['key']`).
2. ..legt jeden umschlossenen Inhalt in `$args['content']`
3. ..dispatcht an den registrierten Handler des Shortcodes über
   `Callbacks::doCallbacks( $appData, '/nino/html/shortcode/'. $name, $args )`.
   und
4. **..ruft `renderHtml()` rekursiv -unabhängig des Rückgabewerts- erneut auf.** Dieser Mechanismus lässt Shortcode-Verschachtelungen zu.
*( z.B. gibt`[template x]`den Rohinhalt von `x.tpl` zuerst unverarbeitet zurück. Erst der rekursive Aufruf von `renderHtml` löst weitere Textfills/Shortcodes in diesem Template auf.
**Potential für Endlosschleifen:** ohne Vorsicht würde `[template x]` innerhalb von `x.tpl`zu einer Endlosschleife führen. Aus diesem Grund ist der Shortcode`Template`abgeändert:

```php
public static function doShortcode( array &$appData, array $args ): string {
    $html = Filesystem::getFileContent( $appData, ( $args[0] ?? '' ). '.tpl', '' );
    return Callbacks::doCallbacks( $appData, '/nino/html/render', $html );
}
```

Er ruft direkt den tiefer liegenden `doCallbacks( '/nino/html/render', ... )` auf statt der vollen `renderHtml()` - der eine zusätzliche rekursive Render-Durchlauf, den `_doShortcode()` danach ohnehin immer anhängt, ist damit die einzige weitere Verarbeitung, die der eingebundene Template-Inhalt bekommt. Das Include selbst löst so nie einen zweiten, verschachtelten vollen Render-Durchlauf aus.

Der Kernel Shortcode`[element]` ist ein gutes Beispiel für die Nutzung von`content` und `Args` in einem Callback.
`[element /services/consulting]<h2>[[title]]</h2>[/element]` lädt die Daten des Elements und ersetzt anschließend `[[title]]` *innerhalb des umschlossenen Inhalts* mit den entsprechenden Feldwerten des Elements. Die Uri wird dabei positional übergeben (`$args[0]`), nicht als benanntes `uri="..."`-Argument.
### 3. Callbacks `'/nino/html/render'`
Alle zusätzlich registrierten Callbacks auf das Event `'/nino/html/render'` erhalten den HTML-String zur Modifikation.
Das Ergebnis wird anschließend zurückgegeben.

## Nino Klassen
Diese Liste ist keine vollständige Referenz.
Jede Klasse und jedes Modul verfügt darüber hinaus über weitere `public static`Methoden, die in der Entwicklung für eigene Projekte jedoch keine führende Rolle spielen und deshalb hier fehlen. Nicht jede `\Nino`-Kernklasse hat allerdings überhaupt eine `init(...)`Methode - nur `AppData`, `Auth`, `Filesystem`, `Locales` und `Runtime` haben eine; `Callbacks`, `Elements`, `Html`, `Http`, `Images`, `Mail` und `Modules` hatten nie eine. Jede `\Nino\Modules`-Klasse dagegen hat immer eine (so wird sie über `config.php`s `/nino/modules`-Liste initiiert) - sie fehlt unten nur, weil sie nie direkt aufgerufen werden muss. Die hier aufgelisteten Methoden dienen direkt bei der PHP-Entwicklung mit Nino.

### `\Nino` Kernel
Diese Klassen werden automatisch initialisiert und sind verbindlicher Teil jedes Projekts. Sie dienen vorwiegend der gezielten Modifikation des `$appData`-Arrays und funktionieren meistens als alleinstehende Helfer-Klassen und -Methoden.
#### `AppData`
Verwaltet das zentrale appData-Array: Laden von `config.php` beim Boot und selektives Zurückschreiben einzelner Top-Level-Keys.
`writeContentData( array &$appData, array $keys )`
Schreibt nur die übergebenen Top-Level-Keys zurück in `config.php` - liest die Datei vorher frisch ein, um parallele Schreibzugriffe nicht zu überschreiben.

#### `Auth`
Benutzer-Authentifizierung: Session-Login/-Logout, Benutzerverwaltung und das granulare Berechtigungssystem für Admin-Accounts.
`loginUser( array &$appData, string $username, string $pw )`
Prüft Zugangsdaten, rotiert Session-ID/CSRF-Token, rehash't bei Bedarf das Passwort und setzt den eingeloggten User.
`logoutUser( array &$appData )`
Beendet die aktuelle Session, rotiert das CSRF-Token und entfernt den Client aus den bekannten Sessions des Users.
`getCurrentUser( array &$appData )`
Liefert die Daten des aktuell eingeloggten Users oder `false`.
`getUser( array &$appData, string $username )`
Liefert die Userdaten zu einer E-Mail-Adresse oder `false`.
`insertUser( array &$appData, string $username, string $pw, array $perms = [] )`
Legt einen neuen User mit gehashtem Passwort an.
`deleteUser( array &$appData, string $username )`
Löscht einen User.
`updateUser( array &$appData, string $username, string $newUsername, string $pw = '' )`
Ändert E-Mail und/oder Passwort eines Users, hält bei Selbst-Umbenennung die aktuelle Session gültig.
`logoutAllSessions( array &$appData, string $username )`
Beendet alle aktiven Sessions eines Users ("überall abmelden"), inkl. der aktuellen, falls betroffen.
`checkPermission( array &$appData, string $perm, string $username = '' )`
Prüft rekursiv (inkl. Wildcard-Parent-Perms), ob ein User eine bestimmte Berechtigung besitzt.

#### `Callbacks`
Generischer Callback-Mechanismus: beliebige Codestellen registrieren sich unter einem String-Key, andere Stellen feuern diesen Key gezielt ab.
`registerCallback( array &$appData, string $name, mixed $callback, int $prio = 5 )`
Registriert eine Callable unter einem Namen (mit Priorität) in appData.
`doCallbacks( array &$appData, string $name, mixed &$args = null )`
Führt alle unter einem Namen registrierten Callbacks der Reihe nach aus und reicht ihren Rückgabewert als neue `$args` weiter.

#### `Csrf`
CSRF-Schutz für jeden nicht-sicheren (`POST`/`PUT`/`DELETE`/`PATCH`) Request, per Session-Token - erforderlich und immer aktiv, anders als die optionalen `\Nino\Modules\*`-Klassen weiter unten. `\Nino\Modules\Csrf` ergänzt lediglich den `[csrf]`-Shortcode obendrauf.
`getToken( array &$appData )`
Gibt das aktuelle CSRF-Token der Session zurück, legt bei Bedarf eines an.
`rotateToken( array &$appData )`
Ersetzt das Token durch ein neues (z. B. nach Login/Logout).

#### `Filesystem`
Zentrale Datei-Ein-/Ausgabe mit In-Request-Cache, Locking und automatischer `.php`-/`.json`-(De-)Serialisierung.
`getFileContent( array &$appData, string $filename, mixed $default = false )`
Liest eine Datei gecacht (mtime-invalidiert); `.php`-Dateien werden included, `.json` dekodiert.
`putFileContent( array &$appData, string $filename, mixed $content, bool $nolock = false, bool $append = false )`
Schreibt Inhalt in eine Datei (inkl. Locking, Opcache-Invalidierung); serialisiert Arrays passend zur Endung.
`lockFile( array &$appData, string $filename )`
Öffnet eine Datei exklusiv gesperrt (`flock LOCK_EX`).
`unlockFile( array &$appData, string $filename )`
Gibt eine zuvor gesperrte Datei wieder frei.
`fileExists( array &$appData, string $filename )`
Prüft, ob eine Datei existiert.
`forceDir( array &$appData, string $dirpath )`
Legt ein Verzeichnis an, falls es noch nicht existiert.
`getPath( array &$appData )`
Liefert den absoluten Dateisystempfad des Projekts.
`getDir( array &$appData )`
Liefert das konfigurierte URL-Verzeichnis der Seite.
`copyDir( string $source, string $dest )`
Kopiert Dateien/Ordner rekursiv.
`removeDir( string $target )`
Löscht Dateien/Ordner rekursiv.

#### `Elements`
Dateibasierte mehrsprachige Inhalte (vergleichbar mit Posts/Nodes): CRUD auf Elementen und Element-Typen.
`getElement( array &$appData, string $uri, string $locale = '', mixed $return = false )`
Liefert ein einzelnes Element in einer Locale.
`queryElements( array &$appData, string $typeUri, array $query, string $locale = '', mixed $return = false )`
Durchsucht alle Elemente eines Typs per Key/Wert-Query (inkl. `%Wildcard%`) über eine oder alle Locales.
`updateElement( array &$appData, string $uri, array $data, string $locale = '' )`
Aktualisiert ein bestehendes Element.
`insertElement( array &$appData, string $uri, array $data, string $locale = '' )`
Legt ein neues Element an, sofern die Uri noch frei ist.
`deleteElement( array &$appData, string $uri, string $locale = '' )`
Löscht ein Element (optional in allen Locales via `*`).
`insertElementType( array &$appData, string $typeUri, array $model )`
Legt einen neuen Element-Typ inkl. Feld-Modell und Default-Werten an.

#### `Html`
Rendering-Pipeline für HTML: Textfills, Shortcodes, Asset-Listen und HTML-Sanitizing.
`renderHtml( array &$appData, string $html )`
Ersetzt Textfills und Shortcodes in einem HTML-String.
`renderTextfill( array &$appData, string $fill )`
Löst einen einzelnen Textfill-Key in der aktuellen Sprache auf.
`addAsset( array &$appData, string $library, string $assetfile )`
Trägt eine Asset-Datei in eine benannte Asset-Bibliothek ein.
`addShortcode( array &$appData, string $shortcode, mixed $callback )`
Registriert einen neuen Shortcode-Namen mit Callback.
`addFills( array &$appData, array $fills, string $locale = '' )`
Fügt Textfills für eine Locale (oder `*`) hinzu.
`getAssets( array &$appData, string $library )`
Liefert alle Asset-Dateien einer Bibliothek.
`getFills( array &$appData )`
Liefert alle aktuell gültigen Textfills (global + Locale + Request-Fills), gemerged.
`containsHtml( string $value )`
Prüft, ob ein Wert eines der erlaubten Inline-Tags (`strong`/`em`/`span`/`a`) enthält.
`sanitizeHtml( string $html )`
Bereinigt rohes HTML auf eine Whitelist erlaubter Inline-Tags und sichere Href-Schemes.

#### `Http`
Http-Request-/Response-Zyklus: Routing, Sicherheits-Header, Ausgabe.
`output( array &$appData, array &$request )`
Sendet Header, Statuscode und Body aus und beendet die Ausführung.
`requestRoute( array &$appData, string $uri, string $method )`
Sucht eine registrierte Route zu Methode+Uri, inkl. Wildcard-Fallback auf übergeordnete Pfade (`/*`).
`findRouteUri( array &$appData, string $responseUri, string $locale )`
Sucht den Routen-Key, dessen Uri (in einer Locale) einem gegebenen Response-Uri entspricht - für Locale-Redirects.
`getRequest( array &$appData, int $offset = 0 )`
Liefert einen früheren Request aus dem Request-Verlauf.
`getClientIp()`
Liefert die tatsächliche TCP-Peer-Adresse des Clients (`REMOTE_ADDR`, keine spoofbaren Header).
`filterHeaderFields( array $headerArray )`
Filtert ein rohes Header-Array auf eine Whitelist bekannter HTTP-Header.

#### `Images`
Bild-Uploads: Validierung, zentriertes Crop/Resize und Verwaltung der entwicklerdefinierten Image-Slots.
`process( array &$appData, string $bytes, int $targetWidth, int $targetHeight, string $basePath )`
Validiert rohe Upload-Bytes, croppt/skaliert sie zentriert auf Zielmaße und speichert sie deterministisch (jpeg oder png).
`delete( array &$appData, string $filename )`
Löscht eine zuvor per `process()` gespeicherte Bilddatei.
`getUrl( array &$appData, string $filename )`
Baut die öffentliche URL zu einer gespeicherten Bilddatei.
`getSlots( array &$appData )`
Liefert alle entwicklerdefinierten Image-Slots.
`getSlot( array &$appData, string $uri )`
Liefert die Definition eines einzelnen Slots.
`setSlotFilename( array &$appData, string $uri, string $filename )`
Trägt für einen bestehenden Slot einen neuen Dateinamen ein und persistiert ihn in `config.php`.

#### `Locales`
Systemweites Locale-Handling inkl. Locale-Wechsel per Query-Parameter und Redirect auf die passende Sprachvariante.
`getCurrentLocale( array &$appData )`
Liefert die aktuell aktive Locale.
`getNativeLocale( array &$appData )`
Liefert die Standard-/Ursprungs-Locale der Seite.
`getAvailableLocales( array &$appData )`
Liefert alle verfügbaren Locales.
`verifyLocale( array &$appData, string $locale )`
Prüft, ob eine Locale verfügbar ist.
`setCurrentLocale( array &$appData, string $locale )`
Setzt (nach Verifikation) die aktuelle Locale und persistiert sie in der Session.

#### `Mail`
Dünner Wrapper um `mail()` mit Content-Type/Reply-To-Header und einem IP-basierten Rate-Limit für alle Versender (Form, Newsletter).
`send( array &$appData, string $to, string $subject, string $body, string $replyTo )`
Verschickt eine HTML-Mail mit Reply-To, sofern die absendende Client-IP ihr Stundenlimit noch nicht erreicht hat.

#### `Modules`
Lädt, initialisiert und spricht die in `config.php` aktivierten optionalen Module an.
`callModules( array &$appData, string $method )`
Ruft eine bestimmte Methode (z. B. `init`) auf jedem aktivierten Modul auf.

#### `Runtime`
PHP-Laufzeitumgebung: Session-Handling und globales Error-/Exception-Handling inkl. Logging.
`getSessionValue( array &$appData, string $key, mixed $return = null )`
Liest einen Wert aus der projektspezifischen Session.
`setSessionValue( array &$appData, string $key, mixed $value )`
Schreibt einen Wert in die projektspezifische Session.
`unsetSessionValue( array &$appData, string $key )`
Entfernt einen Wert aus der Session.
`handleError()`
Globaler Error-/Exception-Handler: loggt (falls konfiguriert) nach `/data/logs.<Jahr-Monat>.php` und zeigt den Fehler an oder bricht mit 500 ab.

### `\Nino\Modules`
Diese Kernel-Module sind optional und erweitern den -meistens Frontend- Funktionsumfang von Nino-Projekten. Sie sind genau wie ein externes Modul aufgebaut (siehe „Eigene Module entwickeln" weiter unten) und liegen unter `_nino/Nino/Modules/`, ein Verzeichnis pro Modul, das bei Bedarf nachgeladen wird - genau wie ein projekteigenes Modul.
Die Aktivierung erfolgt über das `/nino/modules`Array in der `config.php`.
#### `Assets`
CSS/JS-Asset-Verwaltung: bündelt, cached und (bei `.min`-Dateinamen) minifiziert mehrere Quelldateien zu einer Ausgabedatei.
**Shortcode:**
`[assets /pfad/datei.css]`
Bindet die für diese Bibliothek per `addAsset()` gesammelten Dateien gebündelt (und bei `.min`-Dateinamen minifiziert) als `<link>`- oder `<script>`-Tag ein.

#### `Csrf`
Ergänzt den `[csrf]`-Shortcode obendrauf auf die erforderliche Kernel-Klasse `\Nino\Csrf` (siehe oben) - der Schutz selbst ist aktiv, unabhängig davon, ob dieses Modul aktiviert ist.
**Shortcode:**
`[csrf]`
Rendert ein verstecktes `_csrf`-Inputfeld mit dem aktuellen Session-Token - gehört in jedes Formular, das serverseitig per Csrf-Callback geprüft wird.

#### `Elements`
Shortcodes zum Einbinden einzelner oder mehrerer Elemente in Templates.
**Shortcode:**
`[element /typ/uri locale="xx" callback="..."]...[[key]]...[/element]`
Rendert den Inhalt einmal für ein einzelnes Element; `[[key]]`-Platzhalter im Inhalt werden durch die Feldwerte des Elements ersetzt. `locale`/`callback` optional.
`[elements /typ limit="N" query="key=value" locale="xx" callback="..."]...[[key]]...[/elements]`
Wiederholt den Inhalt für jedes Element eines Typs (optional gefiltert per `query`, begrenzt per `limit`) - `[[key]]`-Platzhalter werden je Treffer neu ersetzt.

#### `Form`
Kontaktformular: Validierung, Versand der Betreiber-/Bestätigungsmail und Ablage jeder Einsendung für `_editor`.
**Javascript:**
`Nino.http.sendRequest( '/.form', 'POST', function( xhr ) { ... }, { name: '...', email: '...', message: '...', cat: '...', location: '' } )`
Sendet das Kontaktformular an `/.form`. `location` muss leer bleiben (Honeypot). Erfolg/Fehler ist nur am `xhr.status` erkennbar - der Response-Body bleibt leer: 200 gesendet, 400 Pflichtfeld fehlt/ungültige E-Mail, 418 Honeypot gefüllt, 403 CSRF ungültig/fehlt.

#### `Images`
Shortcode zum Einbinden eines entwicklerdefinierten Image-Slots.
**Shortcode:**
`[image slotUri alt="..."]`
Rendert ein `<img>`-Tag für einen entwicklerdefinierten Image-Slot, sofern dafür bereits ein Bild hochgeladen wurde - sonst bleibt es leer.

#### `Jstext`
Stellt die aktuellen Textfills als JSON auch dem Frontend-JavaScript zur Verfügung.
**Shortcode:**
`[jstext]`
Rendert ein `<script>`-Tag, das alle aktuellen Textfills als `NinoJstext`-JS-Objekt bereitstellt (CSP-Nonce-abgesichert).
**Javascript:**
`Nino.content.getText( key )`
Liest einen vom `[jstext]`-Shortcode bereitgestellten Text (`window.NinoJstext`) aus, z. B. `Nino.content.getText('/form/info/success')`. Liefert `''`, falls der Key nicht existiert.

#### `Localepicker`
Fertiger Sprachumschalter als Shortcode inkl. Redirect-Handling.
**Shortcode:**
`[localepicker callback="..."]`
Rendert die Sprachauswahl-UI mit allen verfügbaren Locales, aktuelle Locale als aktiver Eintrag markiert. `callback` optional.

#### `Navigation`
Einfacher Renderer für Navigationsmenüs (Burger- oder Standard-Variante) aus einer zeilenbasierten Mini-Syntax.
**Shortcode:**
`[navigation id="..." class="..." burger]uri:Titel:Attribute[/navigation]`
Baut aus zeilenweisen `uri:titel:attribute`-Einträgen im Inhalt eine `<ul>`-Navigation; das Flag `burger` (ohne `=`) rendert die Burger-Menü- statt der Standard-Variante.

#### `Newsletter`
Double-Opt-in-Newsletter-Anmeldung unter `/.newsletter`: Signup, Bestätigung und Self-Service-Abmeldung.
`getUnsubscribeLink( array &$appData, string $email )`
Baut die absolute Abmelde-URL für eine (angemeldete oder noch ausstehende) E-Mail-Adresse.
**Javascript:**
`Nino.http.sendRequest( '/.newsletter', 'POST', function( xhr ) { ... }, { email: '...', location: '' } )`
Meldet eine E-Mail-Adresse zum Newsletter an. `location` muss leer bleiben (Honeypot). Bei 200 liefert `xhr.responseJSON.status` `'new'` (Bestätigungsmail gerade verschickt) oder `'existing'` (bereits angemeldet/pending, keine neue Mail); 400 fehlende/ungültige E-Mail, 418 Honeypot gefüllt, 403 CSRF ungültig/fehlt. Bestätigung und Abmeldung laufen **nicht** über JS, sondern über den in der Mail verschickten Link (`GET /.newsletter?confirm=<token>` bzw. `?unsubscribe=<token>`, siehe `getUnsubscribeLink()`).

#### `Template`
Shortcode zum Einbinden eines weiteren `.tpl`-Templates.
**Shortcode:**
`[template /pfad/datei]`
Bindet eine weitere `.tpl`-Datei (ohne Endung) ein und rendert sie.

## Eigene Module entwickeln
Eine reguläre PHP-Klasse wird zu einem Nino-Modul, sobald sie über die statische Methode `init( array &$appData ):void` verfügt. Wenn die Klasse dann mit vollqualifiziertem Namen in `config.php`s `/nino/modules`
eingetragen wird, ruft `\Nino\init(..)`die statische `init(..)`mit der aktuellen `$appData` auf und erlaubt so die Modifikation der `$appData` und das Registrieren von Callbacks und Shortcodes.
Das Registrieren in `config.php` erfolgt nach dem Muster:

```php
'/nino/modules' => [
    '\\Nino\\Modules\\Assets',
    // ...
    '\\MyProject\\Modules\\Foo',
],
```

Jede Klasse, die dieser Konvention folgt, wird beim ersten Zugriff
automatisch geladen - egal ob durch `callModules()`, durch
`\Nino\init(..)` oder durch einen direkten Aufruf von irgendwo sonst:
`_nino/Nino.php` registriert dafür einen `spl_autoload_register()`-
Callback, der aus dem Namespace der angefragten Klasse einen Dateipfad
ableitet:

```php
$relativePath = str_replace( '\\', '/', $className );
$filename = __DIR__ /* _nino/ */ . '/' . $relativePath . '/' . basename( $relativePath ) . '.php';
```

Für `\MyProject\Modules\Foo` (oder ohne führenden Backslash
`MyProject\Modules\Foo` — beides meint dieselbe Klasse, PHP entfernt
den führenden Backslash, bevor ein Autoloader den Namen überhaupt zu
sehen bekommt) löst das zu `_nino/MyProject/Modules/Foo/Foo.php` auf —
das Verzeichnis heißt wie der vollständige Namespace-Pfad, und die
Datei darin wiederholt den kurzen Klassennamen. Jedes eingebaute
`\Nino\Modules\*`-Modul liegt bereits genau unter diesem Pfad in
`_nino/Nino/Modules/` und wird also genauso automatisch geladen -
`config.php`s `/nino/modules`-Liste steuert nur, welche Module über
`callModules()` eine Methode aufgerufen bekommen, nie welche Klassen
überhaupt referenziert werden können.

Das eigene Modul nach der Coding-Philosophie von Nino kann so aussehen:
### Beispiel

```php
<?php
declare(strict_types=1);

namespace MyProject\Modules {

    class LoginLog {

        public static function init( array &$appData ): void {
            \Nino\Callbacks::registerCallback( $appData, '/nino/auth/login', [ self::class, 'onLogin' ] );
            \Nino\Html::addShortcode( $appData, 'lastlogin', [ self::class, 'doShortcode' ] );
        }

        public static function onLogin( array &$appData, mixed $args ): mixed {
            \Nino\Filesystem::putFileContent( $appData, '/data/last-login.php', [
                'mail' => $args['mail'] ?? '', 'time' => time(),
            ] );
            return $args;
        }

        public static function doShortcode( array &$appData, array $args ): string {
            $last = \Nino\Filesystem::getFileContent( $appData, '/data/last-login.php', [] );
            return $last['mail'] ?? '';
        }
    }
}
```

***Der langfristige Traum ist der Aufbau einer kleinen Nino-Community. Durch das modulare Konzept können wir uns gegenseitig Arbeit abnehmen und gekapselte Module mit globalen Aufgaben teilen.
Ich freue mich über jedes Feedback auf GitHub oder mail@dape.io.***

## Die `_editor`/`_admin`-Add-ons

Für die Entwicklung und Pflege einer Webseite liegen in `_admin/`und `_editor/`grafische Frontend-Tools. Beide Seiten haben eigenständige Bootstrap-Skripte, die unabhängig der `/index.php` Route funktionieren. Die Anwendungen sind somit entkoppelt und können jederzeit entfernt werden ohne die Anzeige der eigentlichen Webseite zu beeinträchtigen .
Der Webserver muss so konfiguriert sein, dass er Anfragen an `/_editor`,`/_admin`an die entsprechende Index weiterleitet. Beim lokalen Testen mit PHPs eingebautem Server muss das Router-Skript diesen Fall explizit routen.

Die zwei Anwendungen sind ähnlich aufgebaut, mit eindeutigen Unterschieden:

**_admin** dient einem (!) Entwickler. Der Login erfolgt über eine hartcodierte `PASSWORD_HASH`-Konstante, der fest in der Datei definiert wird. Die Funktionen vereinfachen die Entwicklung der Seite und reduzieren das Bearbeiten von .PHP-Array Dateien. Das strukturelle Schadenspotential ist jedoch groß.

**_editor** dient den Administratoren/Betreibern der Seite. Es können *(über _admin)* mehrere Admin-Accounts erstellt werden, die über unterschiedliche Rechte verfügen. Durch das Backupsystem besteht eine grundlegende Absicherung.


**`_admin` ist bewusst von `config.php` entkoppelt**
Der Login und die Nutzung von `_admin` ist unabhängig der `config.php`möglich. So kann ein Backup wiederhergestellt werden, selbst wenn `config.php`beschädigt ist.

### Das Backup
Nach der Entwicklung kann über `config.php` das Backup über `/nino/editor/backups` aktiviert werden. Damit wird jeden Tag bei einem `_editor`-Login ein Backup erstellt. Die Funktion`Admin\Backup::maybeRun()` tar+gzipt jede Datei, die das Admin-Panel zur Laufzeit beschreiben kann,
  verschlüsselt sie (AES-256-GCM) und schreibt sie unter einem
  einmaligen Zufalls-Verzeichnisnamen in `_editor/`. Die Backups rotieren alle 14-Tage. Die Datei selbst hat eine `.php`-Extension mit einem selbst-terminierenden Stub `<?php http_response_code(403); exit; return '<base64-payload>';` — ein direkter HTTP-Request ist somit nicht möglich.
`Dev\Restore` liest `Backup`s eigene Ausgabe unabhängig — entdeckt das
  Backup-Verzeichnis per Dateisystem-Glob-Muster und hält seine eigene Kopie des Entschlüsselungs-Keys in `_admin/.restore-key.php`. Jede Wiederherstellung macht zuerst einen unabhängigen Sicherheits-Snapshot des *aktuellen* Zustands.

Eine Bedienungsanleitung beider Anwendungen liegt hier:
[_editor-Handbuch](_editor.de.md) · [_admin-Handbuch](_admin.de.md)

## Testing

`tests/*.php` sind abhängigkeitsfreie PHP-Skripte (kein PHPUnit) — jedes
`require`t direkt das Kernel-/Backend-Datei(en), die es braucht, und
läuft gegen ein isoliertes Sandbox-Verzeichnis unter
`sys_get_temp_dir()`, nie gegen echte Projektdaten:

```
tests/kernel-smoke.php      Kernel: AppData, Filesystem, Auth, Http, Locales, Images, Csrf, Mail, Newsletter, ...
tests/editor-smoke.php      _editor/Editor.php: Elements, Text, Users, Images, Backup, Logs, Submissions
tests/admin-smoke.php       _admin/Admin.php: ElementTypes, Text, Pages, Images, Users, Restore, Config, Rate-Limiting
tests/install-smoke.php     _install/Install.php: Checks, Setup, Themes, Webpages, PersonalInfos, Admins, Finish
tests/templates-smoke.php   _templates/Templates.php: Library, Parser/Serializer-Round-Trip, Stylesheets, Documents
tests/templates-js-smoke.js _templates/assets/: die Baustein-Abbildung (blocks.js) und Baum-Edits (tree.js)
tests/concurrency-smoke.php Filesystem-Locking bei parallelen Schreibern
```

Ausführen mit `php tests/kernel-smoke.php` etc. — aktuell 914
Assertions insgesamt über die sieben Suites. Sechs davon sind PHP; die
siebte läuft in reinem Node (`node tests/templates-js-smoke.js`), weil
die beiden abgedeckten Frontend-Module bewusst DOM-frei sind und das,
was sie falsch machen können — eine Klasse anders zurückschreiben als
gelesen, ein Insert mit falscher Einrückung — ein Template still
beschädigt. CI führt alle sieben plus `php -l` und `node --check` über
jede Datei aus, siehe `.github/workflows/ci.yml`.

Zwei Dinge, die beim Ergänzen eigener Tests weitergelten sollten:

- Wenn möglich über den echten Einstiegspunkt dispatchen
  (`Admin::handlePost()`, nicht direkt die `apiXxx()` der
  Domain-Klasse), sobald sich das, was getestet wird, auf dieser Ebene
  einhängt (z. B. das Activity-Log) — der direkte Aufruf der
  Domain-Methode umgeht das stillschweigend.
- Eine private Methode (z. B. `Mail::_hit()`) ist trotzdem via
  `ReflectionMethod` testbar — siehe den Mail-Ratelimit-Abschnitt in
  `tests/kernel-smoke.php` für das Muster, inklusive warum
  `invokeArgs()` (nicht `invoke()`) nötig ist, um `$appData` per
  Referenz zu übergeben.

## Verschlüsselung / Stub-Konventionen

Wer eine weitere admin-verwaltete Datei schreibt, die im Webroot liegen
muss, aber nicht per direktem Request lesbar sein soll, folgt dem
etablierten Muster (`Admin\Backup`, `Admin\Logs`, `Dev\Restore`):

1. Ein einmaliger Zufalls-Verzeichnisname
   (`bin2hex(random_bytes(16))`), einmal generiert und persistiert, von
   nirgendwo verlinkt.
2. `.php`-Extension, Inhalt eingepackt als `<?php
   http_response_code(403); exit; return '<payload>';` mit **keinem
   schließenden `?>`-Tag** — die Payload als Base64 innerhalb des
   Single-Quote-String-Literals halten, im selben nie geschlossenen
   PHP-Block wie das `exit()`.
3. Nur verschlüsseln, wenn der Inhalt tatsächlich so sensibel wie ein
   Credential ist (AES-256-GCM, `random_bytes(12)` IV, Standard
   16-Byte-Tag, `$iv . $tag . $cipher` für die Speicherung
   zusammengefügt) — sonst reicht der Stub allein und der
   Key-Management-Aufwand lohnt sich nicht.

## Ein Beispiel: Setup → Go-Live

Der kürzeste reale Weg von einem leeren Checkout zu einer deployten,
funktionierenden Seite:

1. **Routing funktioniert lokal.** `php -S 127.0.0.1:8000 router.php` —
   das mitgelieferte `router.php` dispatcht `/_editor/*`/`/_admin/*` zu
   deren eigenen Einstiegspunkten und lässt `.cache/`/`.demo/` als
   statische Dateien durch; ohne es 404en `/_editor` und `/_admin` auf dem
   eingebauten Server.
2. **Echtes `_admin`-Passwort setzen:** `php _admin/Admin.php <dein
   Passwort>` → den ausgegebenen Hash in `_admin/Admin.php`s
   `PASSWORD_HASH`-Konstante einfügen. Der mitgelieferte Platzhalter
   matcht kein reales Passwort, nichts danach ist also erreichbar,
   bevor das gesetzt ist.
3. **Ersten Admin-Account anlegen** unter `/_admin` → "Nutzer",
   Manager-Checkbox angehakt — das erste echte Schreiben in `config.php`s
   `/nino/auth/user`. 
4. **Die Route** in `config.php`s `/nino/http/routes` definieren (von
   Hand, oder `/_admin` → "Konfiguration"):
   ```php
   'GET://about' => [ 'uri' => '/about', 'body' => '[template /templates/page-about]' ],
   ```
5. **Einen Elementtyp anlegen** unter `/_admin` → "Element Types" für
   jeden wiederkehrenden Inhalt, den die Seite braucht.
6. **Einen Bild-Slot vorbereiten** unter `/_admin` → "Bilder" für alles,
   was das Template später ohne Code-Änderung admin-austauschbar machen
   soll.
7. **Das Template schreiben**, unter Verwendung von Textfills, des
   Elementtyps und des Bild-Slots aus den Schritten oben (siehe
   `docs/design.de.md` für die vollständige Markup-Referenz und die
   Unterscheidung Slot vs. Element-Feld).
8. **`config.php` für Produktion setzen:**
   - `/nino/error/display` → `false`, `/nino/error/log` → `true`
     (die mitgelieferten Defaults seit `0.9.0-beta` — `display` auf
     `true` zu lassen legt rohe PHP-Fehler, inklusive Funktionsargumenten
     via `debug_backtrace()`, direkt in den Response-Body)
   - `/nino/session/force-secure-cookie` → `true`, falls die Site
     hinter einem TLS-terminierenden Reverse-Proxy sitzt (wo
     `$_SERVER['HTTPS']` typischerweise nicht gesetzt ist, selbst bei
     einer HTTPS-Verbindung)
   - über `/nino/editor/backups`/`/nino/editor/logs` entscheiden
9. **Live gehen** — den Webserver auf die Projektwurzel zeigen lassen,
   verifizieren, dass `/_editor` tatsächlich eine Login-Seite rendert
   (kein 404, kein roher PHP-Quellcode), dann `docs/admin.de.md`s
   Go-Live-Checkliste durcharbeiten, inklusive dem Ausführen aller drei
   Test-Suiten gegen die deployte `config.php`.



## Anhang
### Die Kernel Callbacks

| Name | Feuert | Args | Veto-fähig |
|---|---|---|---|
| `/nino/auth/login` | nach erfolgreichem Login | das eingeloggte Nutzer-Array | nein |
| `/nino/auth/logout` | nach Logout | das ausgeloggte Nutzer-Array | nein |
| `/nino/auth/user/insert` | nachdem `Auth::insertUser()` persistiert hat | das neue Nutzer-Array | nein |
| `/nino/auth/user/update` | nachdem `Auth::updateUser()` persistiert hat | das aktualisierte Nutzer-Array | nein |
| `/nino/auth/user/delete` | nachdem `Auth::deleteUser()` persistiert hat | das gelöschte Nutzer-Array | nein |
| `/nino/elements<typeUri>/insert` | bevor ein neues Element dieses Typs geschrieben wird | die vollen Dateidaten dieses Typs | **ja** |
| `/nino/elements<typeUri>/update` | bevor ein bestehendes Element dieses Typs geschrieben wird | die vollen Dateidaten dieses Typs | **ja** |
| `/nino/elements<typeUri>/update/uri` | wenn die eigene Uri eines Elements geändert wird | die geposteten Elementdaten | nein |
| `/nino/elements/delete<typeUri>` | bevor ein Element dieses Typs entfernt wird | die vollen Dateidaten dieses Typs, nach dem Löschen | **ja** |
| die eigenen `callbacks`-Einträge eines Feldes | beim Validieren eines Speicherns, einmal pro gelistetem Callback | die vollen geposteten Elementdaten | **ja** |
| `/nino/http/request` | einmal pro Request, direkt nach dem Parsen von Method/Uri/Headern/Body | das volle `$request`-Array | nein |
| `/nino/http/response` | einmal pro Request, nach dem Routing, für jede Route | das volle `$request`-Array | nein |
| `/nino/http/response/METHOD://uri` | nur für diese exakte Route | das volle `$request`-Array | nein |
| `/nino/html/render` | einmal pro `renderHtml()`-Durchlauf (fills → shortcodes → dies) | der bisherige HTML-String | nein (Rückgabewert ersetzt das HTML) |
| `/nino/html/shortcode/<name>` | so wird jeder Shortcode tatsächlich dispatcht | die geparsten `$args` des Shortcodes | nein (Rückgabewert wird die Shortcode-Ausgabe) |
| `/nino/shortcodes/assets/output/<css\|js>` | wenn `[assets ...]` seinen `<link>`/`<script>`-Wrapper rendert | der Template-String vor den Fills | nein (Rückgabewert ersetzt das Template) *(Name ist ein Überbleibsel der `\Nino\Shortcodes` → `\Nino\Modules`-Umbenennung - die Klasse ist umgezogen, dieser String nicht)*
