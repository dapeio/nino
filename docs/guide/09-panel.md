# 9. Einen Bereich in der Workbench anlegen

[Übersicht](README.md) · zurück: [8. Einstellung, Backup und Datenschutz](08-settings-privacy.md) · weiter: [10. Eine Aktion im Bereich: löschen](10-action.md)

**Ziel:** Das Feature bekommt einen eigenen Bereich in der Workbench. Er hat einen Eintrag im Menü, ein eigenes Recht und zeigt die Anmeldungen in einer Tabelle.

Ein Bereich heißt in Nino „Panel“. Er hat drei Teile: eine PHP-Klasse, ein Skript für den Browser und Worte. Eine **Aktion** ist eine Anfrage des Skripts an den Server, zum Beispiel `meetups/list`.

## Schritte

### 1. Den Bereich anmelden

Füge in `features/Meetups/Meetups.php` diese Methode in die Klasse ein, direkt nach `init()`, mit je einer Leerzeile davor und danach:

```php features/Meetups/Meetups.php
		// The screens the feature brings to the workbench
		public static function adminPanels( array &$appData ): array {
			return [ \Nino\Modules\Meetups\Admin::class ];
		}
```

Nino fragt jedes eingeschaltete Feature nach `adminPanels()`. Der Bereich ist also genau so lange da wie das Feature.

### 2. Die Klasse

Lege `features/Meetups/Admin/Admin.php` an:

```php features/Meetups/Admin/Admin.php
<?php
declare(strict_types=1);

namespace Nino\Modules\Meetups {

	class Admin {

		// The right an account needs to see the panel and to call its actions
		public static function perm(): string {
			return '/_admin/meetups/manage';
		}

		// The entry in the menu: the address, the text key of its label, the position
		public static function nav(): array {
			return [ 'meetups', '/_admin/meetups/nav', 50 ];
		}

		// What the script may ask for, each name starting with the address of the panel
		public static function actions(): array {
			return [ 'meetups/list' => [ self::class, 'apiList' ] ];
		}

		public static function assets(): array {
			return [ \Nino\Admin\Panels::relative( dirname( __DIR__ ). '/assets/admin.js' ) ];
		}

		// The directory of the words of the panel, one file for each language of the workbench
		public static function text(): string {
			return \Nino\Admin\Panels::relative( dirname( __DIR__ ). '/text' );
		}

		public static function apiList( array &$appData, array &$request ): void {

			if( \Nino\Admin\Admin::guardPerm( $appData, $request, self::perm() ) === false )
				return;

			\Nino\Http::ok( $request, [ 'signups' => self::_signups( $appData ) ] );
		}

		// The sign-ups as the table shows them, the newest first
		private static function _signups( array &$appData ): array {

			$stored = \Nino\Filesystem::getFileContent( $appData, \Nino\Modules\Meetups::SIGNUPS, [] );
			$rows   = [];

			foreach( is_array( $stored ) === true ? array_reverse( $stored ) : [] as $row ) {

				if( is_array( $row ) === false || is_string( $row['id'] ?? null ) === false )
					continue;

				// The title in the language of the workbench, else in another language that has one
				$meetup = \Nino\Elements::getElementWithFallback( $appData, '/meetups/'. (string) ( $row['meetup'] ?? '' ), \Nino\Locales::getCurrentLocale( $appData ) );

				$rows[] = [
					'id'     => $row['id'],
					'name'   => (string) ( $row['name'] ?? '' ),
					'meetup' => is_array( $meetup ) === true ? (string) ( $meetup['title'] ?? '' ) : (string) ( $row['meetup'] ?? '' ),
					'when'   => date( 'Y-m-d H:i', (int) ( $row['time'] ?? 0 ) ),
				];
			}

			return $rows;
		}
	}
}
```

- `perm()` ist das Recht. Der Name folgt dem Muster `/_admin/<bereich>/manage`.
- `nav()` nennt die Adresse `meetups`. Aus ihr leitet Nino drei Namen ab: den Menüeintrag, den Anfang jeder Aktion (`meetups/…`) und das Objekt des Skripts, `Nino.admin.meetups`. Der zweite Wert ist der Textschlüssel der Beschriftung.
- `actions()` verbindet den Namen einer Aktion mit einer Methode.
- `apiList()` prüft **zuerst** das Recht. `guardPerm()` antwortet `403`, wenn dem Konto das Recht fehlt. Tu das in jeder Aktion, in den ersten Zeilen.

### 3. Das Skript

Lege `features/Meetups/assets/admin.js` an:

```js features/Meetups/assets/admin.js
( function(wn) {

	wn.Nino.admin = wn.Nino.admin || {};

	// Every word of the panel is a text key of features/Meetups/text/
	const word = function( name ) { return Nino.content.getText( '/_admin/meetups/'+ name ) };

	// Asks for meetups/list when the panel is first shown and draws the answer
	Nino.admin.meetups = Nino.adminUi.panel( {
		uri		: 'meetups',
		mount	: 'meetups-list',
		show	: 'always',
		render	: function( data, panel ) {

			Nino.adminUi.table( {
				mount		: panel.mount(),
				rowKey	: 'id',
				rows		: data.signups,
				labels	: { search : word('label/search'), empty : word('label/empty'), noMatch : word('label/nomatch') },
				columns	: [
					{ key : 'name', label : word('label/name') },
					{ key : 'meetup', label : word('label/meetup') },
					{ key : 'when', label : word('label/when') },
				],
			} );
		},
	} );

} )(window);
```

- `Nino.adminUi.panel()` steuert den Ablauf eines Bereichs. Es fragt `meetups/list`, wenn der Bereich gezeigt wird, und ruft `render()` mit der Antwort auf. Scheitert die Anfrage, zeigt es selbst den Text des Schlüssels `/_admin/meetups/error/load`.
- `mount` ist die Fläche, in die der Bereich zeichnet. Sie heißt `<adresse>-list`.
- `Nino.adminUi.table()` zeichnet eine Tabelle mit Suche, Sortierung und Seiten. Sie setzt jeden Wert als Text ein, nie als HTML.
- Jedes Wort kommt aus `Nino.content.getText()`. Das Skript enthält keinen festen Satz.

### 4. Die Worte

Diese Worte gehören der Workbench. Nino kopiert sie nicht ins Projekt. Lege `features/Meetups/text/en_US.php` an:

```php features/Meetups/text/en_US.php
<?php
declare(strict_types=1);

// The words of the panel in the workbench. They are never copied into the project
return [
	'[[/_admin/meetups/nav]]'            => 'Meetups',
	'[[/_admin/meetups/label/name]]'     => 'Name',
	'[[/_admin/meetups/label/meetup]]'   => 'Meetup',
	'[[/_admin/meetups/label/when]]'     => 'Signed up',
	'[[/_admin/meetups/label/search]]'   => 'Search the sign-ups',
	'[[/_admin/meetups/label/empty]]'    => 'Nobody has signed up yet.',
	'[[/_admin/meetups/label/nomatch]]'  => 'No sign-up matches the search.',
	'[[/_admin/meetups/error/load]]'     => 'The sign-ups could not be loaded.',
];
```

Und `features/Meetups/text/de_DE.php`:

```php features/Meetups/text/de_DE.php
<?php
declare(strict_types=1);

// The same keys in German
return [
	'[[/_admin/meetups/nav]]'            => 'Treffen',
	'[[/_admin/meetups/label/name]]'     => 'Name',
	'[[/_admin/meetups/label/meetup]]'   => 'Treffen',
	'[[/_admin/meetups/label/when]]'     => 'Angemeldet',
	'[[/_admin/meetups/label/search]]'   => 'Anmeldungen durchsuchen',
	'[[/_admin/meetups/label/empty]]'    => 'Noch hat sich niemand angemeldet.',
	'[[/_admin/meetups/label/nomatch]]'  => 'Keine Anmeldung passt zur Suche.',
	'[[/_admin/meetups/error/load]]'     => 'Die Anmeldungen konnten nicht geladen werden.',
];
```

Fehlt die Datei einer Sprache, zeigt die Workbench die englischen Worte.

## Prüfen

Lade `/_admin` neu. Ein Update ist nicht nötig, denn die Einheit hat sich nicht geändert. Siehst Du eine Änderung an einer PHP-Datei nicht, warte zwei Sekunden und lade noch einmal: OPcache prüft eine Datei nur alle zwei Sekunden (`opcache.revalidate_freq = 2`).

- Im Menü steht unter **Features** der Eintrag „Treffen“. Ein Bereich eines Features steht immer in dieser Gruppe.
- Der Bereich zeigt die Anmeldungen aus Tutorial 7 in einer Tabelle: Name, Treffen, Zeitpunkt.
- Neben dem Reiter „Treffen“ steht ein Reiter **Einstellungen**. Nino legt ihn selbst an, weil das Feature Einstellungen hat.
- Öffne **Nutzer**, Reiter **Nutzerrollen**, und dort die Rolle „Editor“. Bei den Rechten steht in der Liste „Features“ der Eintrag „Treffen“. Die Rolle hat das Recht nicht von selbst. Ein Konto mit der Rolle „Editor“ sieht den Bereich erst, wenn Du das Recht vergibst.

## Häufige Fehler

- **Eine Aktion ohne `guardPerm()`.** Dann kann jedes angemeldete Konto sie aufrufen, auch eines ohne das Recht des Bereichs. Die Workbench prüft für eine Aktion nur das Token und die Anmeldung. Das Menü versteckt nur den Eintrag.
- **`getElement()` in der Workbench.** `\Nino\Elements::getElement()` liest genau die Sprache, die Du nennst, und lässt einen Text weg, der dort fehlt. Der Code Deines Bereichs ruft deshalb `getElementWithFallback()`, wie das Beispiel oben: Die Funktion nimmt für ein fehlendes Feld die Sprache, die einen Text hat.
- **Die Adresse ist vergeben.** Adressen wie `elements`, `text`, `users` oder `features` gehören der Workbench. Nino lässt einen zweiten Bereich mit derselben Adresse weg und schreibt eine Warnung.
- **`innerHTML` mit Daten vom Server.** Setze Text mit `textContent` oder mit den Helfern von `Nino.adminUi`.

## Nachschlagen

- [The panel](../features.md#the-panel): der Bereich eines Features
- [The panel contract](../recipes/admin-panel.md#the-panel-contract): alle Methoden der Klasse
- [Frontend skeleton](../recipes/admin-panel.md#frontend-skeleton): `panel()`, Ebenen, Adressen
- [The shared data table](../recipes/admin-panel.md#the-shared-data-table) und [Login, Accounts and Roles](../_admin.md#login-accounts-and-roles): die Tabelle, Rollen und Rechte
