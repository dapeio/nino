# 10. Eine Aktion im Bereich: löschen

[Übersicht](README.md) · zurück: [9. Einen Bereich in der Workbench anlegen](09-panel.md) · weiter: [11. CSS und JavaScript einbinden](11-assets.md)

**Ziel:** Im Bereich „Treffen“ lässt sich eine Anmeldung entfernen. Die Aktion prüft Recht und Eingabe, speichert sicher und meldet einen Fehler in der Sprache der Workbench.

## Wer was prüft

Die Workbench prüft für Dich zwei Dinge, bevor Deine Methode läuft:

- **Das CSRF-Token.** Das Skript sendet jede Aktion über `Nino.adminUi.api.call()`, und die Workbench hängt das Token der Sitzung an. Ohne gültiges Token antwortet der Kernel mit `403`.
- **Die Anmeldung.** Ohne angemeldetes Konto antwortet der Kernel mit `401`.

Das Recht prüft die Workbench nicht. Deine Methode prüft es selbst, in ihrer ersten Zeile, mit `guardPerm()`. Danach prüft sie die Eingabe. Erst dann speichert sie.

## Schritte

### 1. Die Aktion anmelden

Ersetze in `features/Meetups/Admin/Admin.php` die Zeile in `actions()`:

```php features/Meetups/Admin/Admin.php
			return [ 'meetups/list' => [ self::class, 'apiList' ], 'meetups/remove' => [ self::class, 'apiRemove' ] ];
```

### 2. Die Methode

Füge in dieselbe Klasse ein, direkt nach `apiList()`, mit je einer Leerzeile davor und danach:

```php features/Meetups/Admin/Admin.php
		public static function apiRemove( array &$appData, array &$request ): void {

			if( \Nino\Admin\Admin::guardPerm( $appData, $request, self::perm() ) === false )
				return;

			$id = \Nino\Admin\Admin::postString( \Nino\Admin\Admin::postData(), 'id' );

			if( preg_match( '/^[0-9a-f]{16}$/D', $id ) !== 1 ) {
				\Nino\Http::fail( $request, 400, 'the id is no id of a sign-up' );
				return;
			}

			$found = false;

			// Read, change and write under one lock. Null writes nothing
			$written = \Nino\Filesystem::mutate( $appData, \Nino\Modules\Meetups::SIGNUPS, static function( mixed $state ) use ( $id, &$found ): ?array {

				$rows = is_array( $state ) === true ? $state : [];
				$kept = array_values( array_filter( $rows, static fn( mixed $row ): bool => is_array( $row ) === true && ( $row['id'] ?? '' ) !== $id ) );

				$found = count( $kept ) < count( $rows );

				return $found === true ? $kept : null;
			}, [] );

			if( $found === false )
				\Nino\Http::fail( $request, 404, 'no such sign-up', 'meetups_gone' );
			else if( $written === false )
				\Nino\Http::fail( $request, 500, 'could not write the sign-ups' );
			else
				\Nino\Http::ok( $request, [ 'signups' => self::_signups( $appData ) ] );
		}
```

- `postData()` liefert die gesendeten Daten. `postString()` liest daraus einen Wert sicher als Text.
- Die Kennung muss dem Muster entsprechen, das `callbackSignup()` erzeugt. Alles andere ist ein `400`.
- `mutate()` liest, ändert und schreibt unter einer Sperre. Gibt die Funktion `null` zurück, schreibt Nino nichts.
- `\Nino\Http::fail()` setzt den Fehler. Das vierte Argument ist ein **Code**. Ein Code ist ein fester Name für einen Fehler, den ein Mensch auslösen kann. Er beginnt mit der Adresse des Bereichs: `meetups_gone`.
- Melde den Erfolg erst, wenn das Schreiben gelungen ist.

### 3. Das Skript

Ersetze `features/Meetups/assets/admin.js`:

```js features/Meetups/assets/admin.js
( function(wn) {

	wn.Nino.admin = wn.Nino.admin || {};

	// Every word of the panel is a text key of features/Meetups/text/
	const word = function( name ) { return Nino.content.getText( '/_admin/meetups/'+ name ) };

	let table = null;

	const remove = function( row, button ) {

		Nino.adminUi.confirm( 'destructive', {
			title			: word('confirm/remove-title'),
			message		: Nino.adminUi.format( word('confirm/remove'), row.name ),
			yes				: word('confirm/remove-title'),
			no				: word('label/keep'),
			opener		: button,
			onChoose	: function( yes ) {

				if( yes === false )
					return;

				// meetups/remove, with the CSRF token of the session added by the workbench
				Nino.admin.meetups.call( 'remove', { id : row.id }, function( status, response ) {

					if( status !== 200 ) {
						Nino.admin.meetups.fail( Nino.admin.meetups.mount(), status, response, '/_admin/meetups/error/remove' );
						return;
					}

					table.setRows( response.signups );
				} );
			},
		} );
	};

	// Asks for meetups/list when the panel is first shown and draws the answer
	Nino.admin.meetups = Nino.adminUi.panel( {
		uri		: 'meetups',
		mount	: 'meetups-list',
		show	: 'always',
		render	: function( data, panel ) {

			table = Nino.adminUi.table( {
				mount		: panel.mount(),
				rowKey	: 'id',
				rows		: data.signups,
				labels	: { search : word('label/search'), empty : word('label/empty'), noMatch : word('label/nomatch') },
				columns	: [
					{ key : 'name', label : word('label/name') },
					{ key : 'meetup', label : word('label/meetup') },
					{ key : 'when', label : word('label/when') },
					{ key : 'id', label : '', render : function( value, row ) {
						const button = Nino.adminUi.button( word('label/remove'), function() { remove( row, button ) }, { className : 'nino-admin-btn--small' } );
						return button;
					} },
				],
			} );
		},
	} );

} )(window);
```

- `Nino.adminUi.confirm()` stellt eine Rückfrage. `'destructive'` heißt: Die Aktion lässt sich nicht zurücknehmen. Der `title` ist Pflicht und sagt, was mit was geschieht; der Knopf, der weitergeht, trägt dieselben Worte.
- `Nino.admin.meetups.call( 'remove', … )` ruft die Aktion `meetups/remove` auf.
- `fail()` zeigt einen Fehler in der Fläche. Hat die Antwort einen Code, zeigt die Workbench den Satz zu diesem Code.
- Eine Spalte mit `render` zeichnet ein eigenes Element in die Zelle, hier den Knopf.

### 4. Die Worte

Füge in `features/Meetups/text/en_US.php` vor der letzten Zeile `];` ein:

```php features/Meetups/text/en_US.php
	// The words of the action meetups/remove
	'[[/_admin/meetups/label/remove]]'   => 'Remove',
	'[[/_admin/meetups/label/keep]]'     => 'Keep',
	'[[/_admin/meetups/confirm/remove-title]]' => 'Remove sign-up',
	'[[/_admin/meetups/confirm/remove]]' => 'Remove the sign-up of %s?',
	'[[/_admin/meetups/error/remove]]'   => 'The sign-up could not be removed.',
	// The sentence of the code meetups_gone, see Admin::apiRemove()
	'[[/_admin/error/meetups_gone]]'     => 'This sign-up is gone already. Reload the list.',
```

Und in `text/de_DE.php`:

```php features/Meetups/text/de_DE.php
	// The words of the action meetups/remove
	'[[/_admin/meetups/label/remove]]'   => 'Entfernen',
	'[[/_admin/meetups/label/keep]]'     => 'Behalten',
	'[[/_admin/meetups/confirm/remove-title]]' => 'Anmeldung entfernen',
	'[[/_admin/meetups/confirm/remove]]' => 'Die Anmeldung von %s entfernen?',
	'[[/_admin/meetups/error/remove]]'   => 'Die Anmeldung konnte nicht entfernt werden.',
	// The sentence of the code meetups_gone, see Admin::apiRemove()
	'[[/_admin/error/meetups_gone]]'     => 'Diese Anmeldung ist schon weg. Lade die Liste neu.',
```

Der Satz zu einem Code liegt immer unter `/_admin/error/<code>`.

## Prüfen

Lade `/_admin` neu und öffne den Bereich „Treffen“.

- Jede Zeile hat einen Knopf **Entfernen**. Ein Klick fragt nach: „Die Anmeldung von … entfernen?“ Nach der Bestätigung fehlt die Zeile. Die Anmeldung fehlt auch in `private/data/meetups-signups.php`.
- Öffne den Bereich in zwei Fenstern. Entferne dieselbe Anmeldung in beiden. Das zweite Fenster zeigt: „Diese Anmeldung ist schon weg. Lade die Liste neu.“
- Sende die Aktion ohne Token, zum Beispiel aus der Konsole des Browsers:

```js
fetch( location.pathname, { method : 'POST', body : new URLSearchParams( { action : 'meetups/list', data : '{}' } ) } ).then( r => console.log( r.status ) )
```

  Die Konsole zeigt `403`.

## Häufige Fehler

- **Selbst `fetch()` oder `XMLHttpRequest` benutzen.** Dann fehlt das Token. Sende immer über `call()`.
- **Ein Fehler ohne Code.** Die Workbench zeigt dann die englische Meldung für Entwickler mit dem Status davor. Gib jedem Fehler, den ein Mensch auslösen kann, einen Code und einen Satz in jeder Sprache.
- **Der Kennung vertrauen.** Auch ein angemeldetes Konto kann beliebige Daten senden. Prüfe jede Kennung gegen ein festes Muster.
- **Erfolg melden, obwohl das Schreiben scheiterte.** Prüfe das Ergebnis von `mutate()`.

## Nachschlagen

- [Saying what happened](../recipes/admin-panel.md#saying-what-happened): Codes, Sätze, Rückfragen
- [Backend skeleton](../recipes/admin-panel.md#backend-skeleton): eine Aktion Schritt für Schritt
- [Trust, authentication and CSRF](../../AGENTS.md#81-trust-authentication-and-csrf): wem ein Bereich nicht trauen darf
- [Persistence and concurrency rules](../../AGENTS.md#persistence-and-concurrency-rules): `mutate()`
