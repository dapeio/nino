# 7. Eine Route, die Anmeldungen speichert und drosselt

[Übersicht](README.md) · zurück: [6. Ein Formular für Besucher](06-form.md) · weiter: [8. Einstellung, Backup und Datenschutz](08-settings-privacy.md)

**Ziel:** Das Feature bekommt eine eigene Route für `POST /meetups`. Sie prüft die Anmeldung, drosselt die Versuche und speichert in einer Datei.

## Der Ablauf

1. Das Formular sendet per `POST` an `/meetups`.
2. Der Kernel prüft das CSRF-Token.
3. Ein Callback der Route prüft die Eingabe und speichert.
4. Bei Erfolg leitet er auf `/meetups?signup=ok` weiter. Bei einem Fehler zeigt Nino die Seite noch einmal, mit einem Satz im Formular.

Das Skript von Nino sendet das Formular ab und liest nur den Status der Antwort: `200` ist `success`, `400` ist `invalid`, jeder andere Status ist `error`. Es folgt der Weiterleitung, die Seite `/meetups?signup=ok` antwortet mit `200`. Ohne JavaScript sendet der Browser selbst und zeigt die Seite, die die Route liefert.

## Schritte

### 1. Konstanten

Füge in `features/Meetups/Meetups.php` unter der Konstante `TEMPLATES` ein:

```php features/Meetups/Meetups.php
		public const string SIGNUPS   = '/data/meetups-signups.php';

		// The file never holds more sign-ups than this: the oldest go first
		public const int MAX_SIGNUPS = 500;

		// The tries one visitor has in an hour
		public const int TRIES_PER_HOUR = 10;
```

`/data/meetups-signups.php` ist ein virtueller Pfad. Die Datei liegt in `private/data/`.

### 2. Route und Callbacks anmelden

Ersetze die Methode `init()` samt ihrem Kommentar:

```php features/Meetups/Meetups.php
		// Called at every start of a request while the feature is on
		public static function init( array &$appData ): void {
			\Nino\Callbacks::registerCallback( $appData, '/meetups/upcoming', [ self::class, 'callbackUpcoming' ] );

			// The form posts to the address of the page. The route is added only where the address is free
			if( isset( $appData['/nino/http/routes']['POST://meetups'] ) === false )
				$appData['/nino/http/routes']['POST://meetups'] = [ 'uri' => '/meetups', 'body' => '[template /templates/page-meetups]' ];

			\Nino\Callbacks::registerCallback( $appData, '/nino/http/response/POST://meetups', [ self::class, 'callbackSignup' ] );
			\Nino\Callbacks::registerCallback( $appData, '/nino/http/response/GET://meetups', [ self::class, 'callbackSigned' ] );

			// The words the sign-up form's script says are public: they stand in the source of the page
			\Nino\Modules\Jstext::publish( $appData, [ '/feature/meetups/signup/' ] );
		}
```

- Eine Route, die dem Feature gehört, meldest Du in `init()` an. Sie ist nur da, solange das Feature an ist.
- Die Route hat dieselbe `uri` wie die Seite. So kann ein Fehler die Seite noch einmal zeigen.
- Der Callback einer Route heißt `/nino/http/response/<METHODE>:/<uri>`. Für `POST` und `/meetups` ist das `/nino/http/response/POST://meetups`.

### 3. Die Anmeldung verarbeiten

Füge diese drei Methoden in die Klasse ein, direkt nach `init()` und vor `componentMeetupsSignup()`, mit je einer Leerzeile davor und danach:

```php features/Meetups/Meetups.php
		// POST /meetups: one sign-up
		public static function callbackSignup( array &$appData, array &$request ): void {

			// The kernel has refused a post without a valid CSRF token already
			if( \Nino\Form::blocked( $request ) === true )
				return;

			$posted = \Nino\Form::posted();

			// A field no person sees. Whoever fills it is a bot
			if( ( $posted['website'] ?? '' ) !== '' ) {
				self::_refuse( $appData, $request, 418, 'error' );
				return;
			}

			// Every try counts, also a wrong one
			if( \Nino\Feature::count( $appData, 'meetups-signup-tries', \Nino\Http::getClientIp( $appData ), self::TRIES_PER_HOUR, 3600 ) === false ) {
				self::_refuse( $appData, $request, 429, 'error' );
				return;
			}

			$name   = $posted['name'] ?? '';
			$slug   = $posted['meetup'] ?? '';
			$meetup = preg_match( '/^[A-Za-z0-9][A-Za-z0-9_-]*$/D', $slug ) === 1 ? \Nino\Elements::getElement( $appData, '/meetups/'. $slug ) : false;

			if( $name === '' || mb_strlen( $name ) > 80 || is_array( $meetup ) === false || ( $meetup['date'] ?? '' ) < date( 'Y-m-d' ) ) {
				self::_refuse( $appData, $request, 400, 'invalid' );
				return;
			}

			$entry = [ 'id' => bin2hex( random_bytes( 8 ) ), 'meetup' => $slug, 'name' => $name, 'time' => time() ];

			// Read, change and write under one lock
			$written = \Nino\Filesystem::mutate( $appData, self::SIGNUPS, static function( mixed $state ) use ( $entry ): array {

				$rows   = is_array( $state ) === true ? $state : [];
				$rows[] = $entry;

				return array_slice( $rows, -self::MAX_SIGNUPS );
			}, [] );

			if( $written === false ) {
				self::_refuse( $appData, $request, 500, 'error' );
				return;
			}

			// Send the browser on, so that a reload does not post the form a second time
			\Nino\Http::redirect( $appData, $request, '/meetups?signup=ok' );
		}

		// GET /meetups?signup=ok: where a sign-up that went through arrives
		public static function callbackSigned( array &$appData, array &$request ): void {

			if( ( $request['/nino/http/request']['query']['signup'] ?? '' ) === 'ok' )
				$appData['./meetups/signup'] = 'success';
		}

		// The status of a refused sign-up. The page is drawn once more, and the form says why
		private static function _refuse( array &$appData, array &$request, int $status, string $state ): void {

			$request['/nino/http/response']['statusCode'] = $status;
			$appData['./meetups/signup'] = $state;
		}
```

Die Reihenfolge in `callbackSignup()` ist wichtig:

1. **CSRF.** Der Kernel hat das Token schon geprüft, aber der Callback läuft auch nach einer Ablehnung. `\Nino\Form::blocked()` sagt, ob er abgelehnt hat. Dann kehrt der Callback sofort um. Ohne diese Zeile speichert die Route einen gefälschten Post. Hat keiner der Callbacks einer Route `\Nino\Form::blocked()` gefragt und beantworten sie einen abgelehnten Post mit Erfolg oder Umleitung, verwirft der Kernel die Antwort, antwortet mit der `403` und schreibt eine Warnung ins Log. Ersetzt wird nur die Antwort: Was ein Callback direkt getan hat (Header oder Cookie sofort gesendet, Sitzung, Dateien, Ausgabe), bleibt. Gespeichert hat die Route dann schon: Die Warnung macht den Fehler sichtbar, sie verhindert ihn nicht.
2. **Eingabe lesen.** `\Nino\Form::posted()` liefert nur Texte, gekürzt und ohne Leerraum am Rand.
3. **Drosseln.** `\Nino\Feature::count()` zählt die Versuche je Besucher in einem Zeitfenster, hier 3600 Sekunden. Den Besucher erkennt es an seiner IP-Adresse. Über der Grenze antwortet es `false`. Der Zähler liegt in `private/data/meetups-signup-tries.php`. Die IP-Adresse steht dort nicht lesbar, sondern als Hash: eine Prüfsumme, aus der sich die Adresse nicht zurückrechnen lässt.
4. **Prüfen.** Der Name hat eine Länge, das Treffen gibt es, und es liegt nicht in der Vergangenheit.
5. **Speichern.** `\Nino\Filesystem::mutate()` sperrt die Datei, liest sie, ruft Deine Funktion auf und schreibt das Ergebnis. Die Datei wächst nie über `MAX_SIGNUPS`.
6. **Antworten.** `\Nino\Http::redirect()` leitet weiter. Bei einem Fehler setzt der Callback nur den Status: `400` für eine falsche Eingabe, `429` für zu viele Versuche, `500` für einen Fehler beim Schreiben. Die Falle, die Drossel und die Prüfung sind Sache der Route: Das Skript schickt das Feld `website` leer oder gefüllt mit und prüft nichts davon. Eine Falle oder eine Drossel antwortet mit einem anderen Status als `200` und `400`, und das Skript zeigt dann den allgemeinen Satz `error`, damit ein Bot nicht erfährt, welche Prüfung ihn abgewiesen hat.

Die Route hat einen `body`. Nach einem Fehler setzt Nino deshalb die Seite aus ihrer Vorlage noch einmal zusammen. Die Komponente `[meetups-signup]` aus Tutorial 6 liest dabei `$appData['./meetups/signup']` und zeigt den passenden Satz.

Ein Update ist nicht nötig. Die Einheit hat sich nicht geändert.

## Prüfen

Öffne `/meetups`.

- Wähle ein Treffen, gib einen Namen ein, sende ab. Die Seite bleibt stehen, im Formular steht „Danke. Du bist angemeldet.“, und das Formular ist gesperrt. In `private/data/meetups-signups.php` steht die Anmeldung. Ohne JavaScript landet der Browser auf `/meetups?signup=ok`.
- Sende eine Anmeldung ohne Namen und eine ohne Token. Im Browser prüft das Skript den Namen und sendet nichts; ohne JavaScript hält der Browser das leere Pflichtfeld selbst zurück. Eine Anfrage, die an beidem vorbeigeht, bekommt `400` mit dem Satz im Formular. Nimm `curl`:

```bash
J=$(mktemp)
T=$(curl -s -c $J http://127.0.0.1:8000/meetups | sed -n 's/.*name="_csrf" value="\([0-9a-f]*\)".*/\1/p')
curl -s -b $J -o /dev/null -w "%{http_code}\n" -d "_csrf=$T" -d "meetup=christmas" -d "name=" http://127.0.0.1:8000/meetups
curl -s -b $J -o /dev/null -w "%{http_code}\n" -d "meetup=christmas" -d "name=Ada" http://127.0.0.1:8000/meetups
```

  Die erste Antwort ist `400`, die zweite `403`. Bei `403` wird nichts gespeichert.
- Wiederhole die Zeile mit `name=`, bis `429` kommt. Das ist nach acht Wiederholungen der Fall: Die Anmeldung im Browser und die erste Zeile zählen mit, die Anfrage ohne Token zählt nicht. Der elfte Versuch in einer Stunde ist der erste mit `429`. Im Browser steht dann „Das hat nicht geklappt. Bitte versuche es später noch einmal.“ Das ist der allgemeine Satz für jeden Status außer `200` und `400`.

Lösche `private/data/meetups-signup-tries.php`, um den Zähler zurückzusetzen.

## Häufige Fehler

- **`$_POST` direkt lesen und umwandeln.** `(string) $_POST['name']` bricht mit einem Fehler 500 ab, wenn jemand `name[]=x` sendet. Nimm `\Nino\Form::posted()`.
- **Erst lesen, dann schreiben.** `getFileContent()` und danach `putFileContent()` verliert Anmeldungen, wenn zwei Besucher zugleich senden. Nimm `mutate()`.
- **`'csrf' => false` an der Route.** Das schaltet den Schutz ab. Das ist nur für Routen gedacht, die den Absender anders prüfen, etwa an einer Signatur.
- **`echo` oder `header()` im Callback.** Ein Callback ändert `$request` und gibt nichts selbst aus.

## Nachschlagen

- [State-changing runtime endpoint](../recipes/runtime-module.md#state-changing-runtime-endpoint): die Regeln für eine Route, die etwas speichert
- [Routing and Responses](../development.md#routing-and-responses): Routen, `uri` und die Namen der Callbacks
- [The toolset](../features.md#the-toolset): `count()`, `spent()`, `forget()` und `redirect()`
- [Filesystem](../development.md#filesystem): `mutate()` und `putFileContent()`
- [Failure behavior](../../AGENTS.md#84-failure-behavior): welcher Status wofür
