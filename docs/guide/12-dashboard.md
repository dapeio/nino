# 12. Auf dem Dashboard erscheinen

[Übersicht](README.md) · zurück: [11. CSS und JavaScript einbinden](11-assets.md) · weiter: [13. Testen und veröffentlichen](13-test-publish.md)

**Ziel:** Das Dashboard der Workbench zeigt eine Kachel mit der Zahl der Anmeldungen und oben eine Zeile mit dem nächsten Treffen.

Das **Dashboard** ist die Startseite der Workbench. Ein Feature kann dort an zwei Stellen erscheinen:

- Eine **Kachel** zeigt eine Zahl mit einer Beschriftung. Sie braucht eine Methode in der Klasse des Bereichs.
- Der **Kopf** ist der Platz über den Hinweisen. Ein Feature zeichnet dort eine eigene Fläche. Das braucht einen Callback auf dem Server und eine Anmeldung im Skript.

## Schritte

### 1. Die Kachel

Füge in `features/Meetups/Admin/Admin.php` in die Klasse ein, direkt vor `apiList()`, mit einer Leerzeile danach:

```php features/Meetups/Admin/Admin.php
		// The tile on the Dashboard, for accounts that hold the right of the panel
		public static function summary( array &$appData ): array {
			return [ 'value' => count( self::_signups( $appData ) ), 'label' => '/_admin/meetups/label/tile' ];
		}
```

`value` ist die Zahl, `label` der Textschlüssel der Beschriftung. Das Dashboard fragt `summary()` nur für Konten, die das Recht des Bereichs haben.

### 2. Der Kopf: die Daten vom Server

Füge in `features/Meetups/Meetups.php` am Ende von `init()` ein, nach einer Leerzeile:

```php features/Meetups/Meetups.php
			// The area above the notices of the Dashboard
			\Nino\Callbacks::registerCallback( $appData, '/nino/admin/dashboard/head', [ self::class, 'callbackDashboardHead' ] );
```

Und diese Methode in die Klasse, direkt nach `init()` und vor `adminPanels()`, mit je einer Leerzeile davor und danach:

```php features/Meetups/Meetups.php
		// Called for every account that opens the Dashboard, with the areas so far
		public static function callbackDashboardHead( array &$appData, array &$areas ): array {

			// The callback decides itself who may see its area
			if( \Nino\Auth::checkPermission( $appData, \Nino\Modules\Meetups\Admin::perm() ) === false )
				return $areas;

			$next = \Nino\Elements::queryElements( $appData, '/meetups', [], '*', [], [ 'sort' => 'date' ] );
			self::callbackUpcoming( $appData, $next );

			if( $next !== [] )
				$areas['meetups'] = [ 'title' => (string) ( $next[0]['title'] ?? '' ), 'date' => (string) $next[0]['date'] ];

			return $areas;
		}
```

- Der Callback bekommt die Flächen der anderen Features als `name => daten`. Er fügt seine hinzu und gibt alle zurück.
- Der Name `meetups` ist ein Schlüssel aus Kleinbuchstaben. Die Daten sind ein Array.
- Der Callback läuft für jedes Konto, das das Dashboard öffnet. Er prüft deshalb selbst, ob das Konto die Daten sehen darf.
- `queryElements()` liest Elemente in PHP. `'*'` heißt: in jeder Sprache.

### 3. Der Kopf: die Fläche im Skript

Füge in `features/Meetups/assets/admin.js` vor dem Kommentar `// Asks for meetups/list …` ein:

```js features/Meetups/assets/admin.js
	// The area in the head of the Dashboard. The data is what callbackDashboardHead() answered
	if( typeof Nino.admin.dashboard === 'object' && Nino.admin.dashboard !== null && typeof Nino.admin.dashboard.head === 'object' && Nino.admin.dashboard.head !== null )
		Nino.admin.dashboard.head.add( 'meetups', function( data ) {
			return Nino.adminUi.notice( Nino.adminUi.format( word('label/next'), data.title, data.date ), { href : '#meetups', label : word('nav') } );
		} );
```

- Das Skript fragt zuerst, ob das Dashboard einen Kopf hat.
- `add( 'meetups', … )` meldet die Fläche unter demselben Namen an wie der Callback.
- Die Funktion bekommt die Daten des Callbacks und gibt ein Element zurück, oder `null` für nichts.
- `Nino.adminUi.notice()` setzt den Text als Text ein und hängt einen Link auf den Bereich an.

### 4. Die Worte

Füge in `features/Meetups/text/en_US.php` vor der letzten Zeile `];` ein:

```php features/Meetups/text/en_US.php
	// The words on the Dashboard
	'[[/_admin/meetups/label/tile]]'     => 'Meetup sign-ups',
	'[[/_admin/meetups/label/next]]'     => 'Next meetup: %s on %s.',
```

Und in `text/de_DE.php`:

```php features/Meetups/text/de_DE.php
	// The words on the Dashboard
	'[[/_admin/meetups/label/tile]]'     => 'Anmeldungen zu Treffen',
	'[[/_admin/meetups/label/next]]'     => 'Nächstes Treffen: %s am %s.',
```

`%s` ist ein Platzhalter. `Nino.adminUi.format()` setzt die Werte der Reihe nach ein.

## Prüfen

Lade `/_admin` neu und öffne das **Dashboard**.

- Über den Hinweisen steht das nächste Treffen, zum Beispiel: „Nächstes Treffen: Christmas party am 2026-12-24.“ Dahinter steht der Link „Treffen“. Er öffnet den Bereich.
- Eine der Kacheln heißt „Anmeldungen zu Treffen“ und zeigt die Zahl der Anmeldungen.
- Melde Dich mit einem Konto ohne das Recht `/_admin/meetups/manage` an. Kachel und Zeile fehlen.

## Häufige Fehler

- **Der Callback prüft kein Recht.** Dann sieht jedes Konto die Daten. Das Dashboard prüft für den Kopf nichts.
- **Die Namen passen nicht.** Der Name im Callback und der Name in `add()` müssen gleich sein. Sonst zeichnet das Dashboard nichts.
- **Markup vom Server senden.** Der Server sendet Daten, das Skript setzt sie als Text ein.
- **`Nino.admin.dashboard.head` ohne Abfrage benutzen.** Fehlt der Kopf, bricht Dein ganzes Skript ab, und der Bereich bleibt leer. Frage zuerst, ob es den Kopf gibt.

## Nachschlagen

- [The Head of the Dashboard](../_admin.md#the-head-of-the-dashboard): die Regeln für den Kopf
- [The class](../features.md#the-class): `summary()`, `log()` und was ein Feature auf dem Dashboard lesen darf
- [The panel contract](../recipes/admin-panel.md#the-panel-contract): `summary()` unter den Methoden eines Bereichs
