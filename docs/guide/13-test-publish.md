# 13. Testen und veröffentlichen

[Übersicht](README.md) · zurück: [12. Auf dem Dashboard erscheinen](12-dashboard.md)

**Ziel:** Das Feature bekommt einen eigenen Test. Danach weißt Du, auf welchen Wegen ein Feature in ein anderes Projekt kommt und was Du davon selbst tun kannst.

## Teil 1: Der Test

Der Test eines Features liegt im Feature: `features/<Name>/tests/<schlüssel>-smoke.php`. Er ist ein einzelnes PHP-Skript. Er legt ein eigenes Wegwerf-Projekt an, schaltet das Feature darin ein und prüft, was ein Besucher oder ein Konto zu sehen bekommt. Dein echtes Projekt berührt er nicht.

### 1. Das Skript

Lege `features/Meetups/tests/meetups-smoke.php` an:

```php features/Meetups/tests/meetups-smoke.php
<?php
declare(strict_types=1);

// The test of the Meetups feature. It runs against the Nino checkout three
// levels up, or the one NINO_ROOT names, in a project of its own that it
// creates and removes: php features/Meetups/tests/meetups-smoke.php
$root = getenv( 'NINO_ROOT' ) ?: dirname( __DIR__, 3 );
defined( 'NINO_FEATURES_DIR' ) === true || define( 'NINO_FEATURES_DIR', dirname( __DIR__, 2 ) );
require $root. '/tests/harness.php';

$appData = ninoSandbox( 'meetups' );
$appData['/nino/modules'] = [ '\\Nino\\Modules\\Elements', '\\Nino\\Modules\\Csrf' ];
\Nino\AppData::writeContentData( $appData, [ '/nino/modules', '/nino/locales/available', '/nino/locales/native' ] );

check( 'the manifest validates without a warning', is_array( \Nino\Features::manifest( dirname( __DIR__ ) ) ) && ninoWarnings() === [] );
check( 'activation applies the unit: the page, the type and the words', \Nino\Features::activate( $appData, 'meetups' ) === true
	&& is_file( \Nino\Filesystem::path( $appData, '/templates/page-meetups.tpl' ) ) === true
	&& \Nino\Elements::getElementModel( $appData, '/meetups' ) !== []
	&& \Nino\Text::entry( $appData, '/feature/meetups/signup/submit' ) !== null );

// As \Nino\init() does it: the register reads the folders of the features that are on, then the modules run
\Nino\Html::init( $appData );
\Nino\Features::registerComponents( $appData );
\Nino\Modules::callModules( $appData, 'init' );
$appData['./nino/locales/current'] = 'en_US';

// The template of the component ends with a line break, which the markup keeps: the checks look at the markup
check( 'the component draws a date in the language of the page', trim( \Nino\Html::renderHtml( $appData, '[meetup-date text="2026-12-24" long="yes" variant="highlight"]' ) )
	=== '<span class="meetup-date meetup-date--long meetup-date--variant-highlight"><time datetime="2026-12-24">December 24, 2026</time></span>' );
check( 'a date that is none draws nothing', \Nino\Html::renderHtml( $appData, '[meetup-date text="2026-02-30"][meetup-date text="soon"]' ) === '' );
check( 'a label is escaped, once', str_contains( \Nino\Html::renderHtml( $appData, '[meetup-date text="2026-12-24" label="<b>"]' ), '&lt;b&gt;: ' ) );

// One date that is over and one that is ahead
$ahead = date( 'Y-m-d', time() + 30 * 86400 );
$first = \Nino\Elements::insertElement( $appData, '/meetups/past', [ 'title' => 'Over', 'date' => '2020-01-01' ], 'en_US' );
$second = \Nino\Elements::insertElement( $appData, '/meetups/party', [ 'title' => 'Party [csrf]', 'date' => $ahead ], 'en_US' );
$list = \Nino\Html::renderHtml( $appData, '[elements /meetups sort="date" callback="/meetups/upcoming"]<li>[[title]]</li>[/elements]' );
check( 'the loop lists the dates from today on, their titles escaped', $first !== false && $second !== false && $list === '<li>Party &#91;csrf]</li>' );

// One sign-up as the route callback sees it
$signup = static function( array $post ) use ( &$appData ): int {
	$_POST = $post;
	$request = ninoVisit( $appData, '/meetups', 'POST' );
	\Nino\Modules\Meetups::callbackSignup( $appData, $request );
	return $request['/nino/http/response']['statusCode'];
};

check( 'a sign-up is stored and answered with a redirect', $signup( [ 'name' => 'Ada', 'meetup' => 'party' ] ) === 303
	&& array_column( (array) \Nino\Filesystem::getFileContent( $appData, '/data/meetups-signups.php', [] ), 'name' ) === [ 'Ada' ] );
check( 'a sign-up without a name, for a date that is over or with the trap filled is refused',
	$signup( [ 'name' => '', 'meetup' => 'party' ] ) === 400 && $signup( [ 'name' => 'Bob', 'meetup' => 'past' ] ) === 400 && $signup( [ 'name' => 'Bob', 'meetup' => 'party', 'website' => 'x' ] ) === 418 );

$statuses = [];
for( $try = 0; $try < 8; $try++ )
	$statuses[] = $signup( [ 'name' => '', 'meetup' => 'party' ] );
check( 'the eleventh try of one visitor in an hour is throttled', $statuses === [ 400, 400, 400, 400, 400, 400, 400, 429 ] );

// The panel: an account with the right, then one without
ninoUser( $appData, 'dev@example.com' );
[ $status, $body ] = ninoCall( $appData, [ \Nino\Admin\Admin::class, 'handlePost' ], [], 'meetups/list' );
check( 'meetups/list answers the sign-ups', $status === 200 && count( $body['signups'] ) === 1 && $body['signups'][0]['meetup'] === 'Party [csrf]' );
$id = $body['signups'][0]['id'];

[ $status, $body ] = ninoCall( $appData, [ \Nino\Admin\Admin::class, 'handlePost' ], [ 'id' => 'nope' ], 'meetups/remove' );
check( 'meetups/remove refuses an id that is none', $status === 400 );
[ $status, $body ] = ninoCall( $appData, [ \Nino\Admin\Admin::class, 'handlePost' ], [ 'id' => $id ], 'meetups/remove' );
check( 'meetups/remove removes the sign-up', $status === 200 && $body['signups'] === [] );
[ $status, $body ] = ninoCall( $appData, [ \Nino\Admin\Admin::class, 'handlePost' ], [ 'id' => $id ], 'meetups/remove' );
check( 'a sign-up that is gone is said with a code', $status === 404 && $body['code'] === 'meetups_gone' );
foreach( [ 'en_US', 'de_DE' ] as $locale )
	check( "...and the code has its sentence in $locale", ( \Nino\Admin\Admin::textFills( $appData, '/features/Meetups/text', $locale )['[[/_admin/error/meetups_gone]]'] ?? '' ) !== '' );

$areas = [];
check( 'the head of the Dashboard is given the next date', ( \Nino\Modules\Meetups::callbackDashboardHead( $appData, $areas )['meetups']['title'] ?? '' ) === 'Party [csrf]' );

\Nino\Auth::logoutUser( $appData );
ninoUser( $appData, 'editor@example.com', [ '/_admin/text/manage' ] );
$areas = [];
check( 'an account without the right of the panel is refused, and gets no area on the Dashboard', ninoCall( $appData, [ \Nino\Admin\Admin::class, 'handlePost' ], [], 'meetups/list' )[0] === 403
	&& \Nino\Modules\Meetups::callbackDashboardHead( $appData, $areas ) === [] );

ninoDone( $appData );
```

Die Helfer kommen aus `tests/harness.php` von Nino:

| Helfer | Was er tut |
|---|---|
| `ninoSandbox( $name )` | legt ein leeres Wegwerf-Projekt an und gibt dessen `$appData` zurück, mit allen Pfaden und `/nino/dir` (leer: das Projekt läuft an der Wurzel der Domain) |
| `check( $label, $bedingung )` | eine Prüfung, eine Zeile Ausgabe |
| `ninoWarnings()` | die Warnungen seit dem letzten Aufruf |
| `ninoVisit( $appData, $uri, $methode )` | baut eine Anfrage der Webseite |
| `ninoUser( $appData, $mail, $rechte )` | legt ein Konto an und meldet es an |
| `ninoCall( $appData, $ziel, $daten, $aktion )` | eine Aktion der Workbench, Antwort als `[ status, body ]` |
| `ninoDone( $appData )` | Zusammenfassung, Aufräumen, Exit-Status |

Das Wegwerf-Projekt hat die Sprachen `de_DE` und `en_US` und keine Module. Der Test schaltet ein, was er braucht.

### 2. Laufen lassen

```bash
php features/Meetups/tests/meetups-smoke.php
```

Jede Prüfung gibt eine Zeile aus. Der Exit-Status ist 0, wenn alles stimmt. Prüfe außerdem die Syntax jeder Datei und ob das Feature die Regeln des Kernels erfüllt:

```bash
find features/Meetups -name "*.php" -print0 | xargs -0 -n1 php -l
find features/Meetups -name "*.js" -print0 | xargs -0 -n1 node --check
php tests/features-smoke.php
```

### Was ein guter Test prüft

- das, was ein Besucher oder ein Konto sieht: den Status, die Antwort, die gespeicherten Daten,
- den Fehlerfall genauso wie den guten Fall: falsche Eingabe, fehlendes Recht, zu viele Versuche,
- die maskierte Ausgabe eines Werts mit `<` oder `[`.

Ein Test prüft keine Wortlaute einer Seite und ruft keine Methode auf, deren Name mit `_` beginnt.

## Teil 2: Veröffentlichen

### 1. Die Version festlegen

Setze die Version in `feature.php` für die erste Ausgabe:

```php features/Meetups/feature.php
	'version'     => '1.0.0',
```

Erhöhe die Version mit jeder Ausgabe, die ein Projekt als Update bekommen soll.

### 2. Die Wege in ein anderes Projekt

**Von Hand – das kannst Du immer.** Kopiere das Verzeichnis `features/Meetups/` in das Verzeichnis `features/` eines anderen Projekts. Schalte es dort im Bereich **Features** ein. Für ein Update ersetzt Du das Verzeichnis und klickst dort auf **Auf … aktualisieren**.

**Ein eigener Katalog – das kannst Du mit einem eigenen Server.** Ein **Katalog** ist eine Datei `catalogue.json` mit einer Signatur und je einem Archiv pro Feature. Ein Projekt installiert daraus im Bereich **Features**. Du brauchst dafür:

- die Werkzeuge aus dem Katalog-Repository [dapeio/nino-features](https://github.com/dapeio/nino-features), vor allem `bin/build.php`,
- ein eigenes Schlüsselpaar,
- einen Webserver, der die erzeugten Dateien über https ausliefert.

So entsteht der Katalog in einer Kopie des Katalog-Repositorys, in dessen `features/` Dein Feature liegt. `../nino` ist der Pfad zu einem Checkout von Nino, hier im Verzeichnis daneben:

```bash
openssl ecparam -name prime256v1 -genkey -noout -out catalogue-key.pem
openssl ec -in catalogue-key.pem -pubout -out catalogue-key.pub.pem
php bin/build.php ../nino public --base-url https://example.org/features --key catalogue-key.pem
```

Das schreibt `public/catalogue.json`, `public/catalogue.json.sig` und `public/meetups-1.0.0.tar.gz`. Das Archiv enthält das Verzeichnis `Meetups/` ohne `tests/`. Der Befehl baut jedes Feature mit, das in `features/` der Kopie liegt. Lösche dort vorher, was nicht in Deinen Katalog soll. Lade `public/` auf den Server. Ein Projekt liest Deinen Katalog, wenn seine `config.php` zwei Werte nennt: `/nino/catalogue/url` mit der Adresse der `catalogue.json` und `/nino/catalogue/key` mit dem öffentlichen Schlüssel. Ein Projekt liest genau einen Katalog: Deinen oder den offiziellen.

**Der offizielle Katalog – das kann nur sein Besitzer.** Der Katalog von Nino ist mit einem privaten Schlüssel signiert, den nur der Besitzer hat. Jeder Kernel prüft die Signatur. Du kannst dort nichts selbst veröffentlichen. Du kannst Dein Feature dem Besitzer des Repositorys anbieten. Ob und wann er es aufnimmt, entscheidet er. Auch eine **Anwendung** – ein Eintrag im Katalog, der mehrere Features zusammenfasst – legt nur der Besitzer eines Katalogs an.

### 3. Vor der Weitergabe

- Der Test läuft durch.
- Das Manifest hat `name` und `description` auf Deutsch und Englisch.
- Das Manifest dokumentiert das Feature im Schlüssel `manual`: Shortcodes, Routen, Bereich, Callbacks und was die Einheit installiert.
- Ein Feature, das Daten von Personen verarbeitet, bringt seinen Abschnitt der Datenschutzerklärung mit ([Tutorial 8](08-settings-privacy.md)).

## Prüfen

Der Test endet mit:

```text
17 checks, 0 failed
```

Dahinter steht die Laufzeit. Ändere zur Probe in `Meetups.php` die Grenze des Namens von `80` auf `0` und starte den Test noch einmal. Vier Zeilen beginnen jetzt mit `FAIL`, die erste ist „a sign-up is stored and answered with a redirect“. Der Exit-Status ist 1. Nimm die Änderung zurück.

## Häufige Fehler

- **Der Test findet Nino nicht.** Er sucht den Checkout drei Verzeichnisse über sich. Liegt das Feature woanders, nenne den Checkout: `NINO_ROOT=/pfad/zu/nino php …/meetups-smoke.php`.
- **Der Test hängt vom Datum ab.** Rechne Daten aus `time()` aus, statt sie fest zu schreiben. Ein festes Datum liegt irgendwann in der Vergangenheit.
- **Den privaten Schlüssel ins Repository legen.** Der private Schlüssel eines Katalogs gehört an einen sicheren Ort außerhalb jedes Repositorys.

## Nachschlagen

- [The feature's own test](../features.md#the-features-own-test): alle Helfer und der Test eines Skripts
- [Tests and validation](../../AGENTS.md#10-tests-and-validation): was ein Test prüft und was nicht
- [Tests Before Go-Live](../deployment.md#tests-before-go-live): die Befehle der CI
- [The catalogue](../_admin.md#the-catalogue) und [A catalogue of your own](../_admin.md#a-catalogue-of-your-own): Katalog, Signatur, Format
- [Updates and the upgrade hook](../features.md#updates-and-the-upgrade-hook): Daten bei einem Update umstellen
