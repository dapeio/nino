# 11. CSS und JavaScript einbinden

[Übersicht](README.md) · zurück: [10. Eine Aktion im Bereich: löschen](10-action.md) · weiter: [12. Auf dem Dashboard erscheinen](12-dashboard.md)

**Ziel:** Das Feature bringt ein Stylesheet und ein Skript für die Webseite mit. Die Liste der Treffen bekommt ein Aussehen, das dem Design des Projekts folgt.

## Wie Nino CSS und JavaScript ausliefert

Nino fasst alle Stylesheets zu einer Datei zusammen und alle Skripte zu einer zweiten. So eine Datei heißt **Bundle**. Der Browser lädt nie eine Datei aus `features/`.

Das Stylesheet-Bundle hat eine feste Reihenfolge:

```text
_nino/Nino.css              die Vorgaben von Nino
Features und Module         ihre Stylesheets
private/assets/theme.css    das Design des Projekts
private/assets/style.css    eigene Regeln des Projekts
```

Was später kommt, gewinnt bei gleich genauen Selektoren. Das Projekt kann also jede Regel Deines Features überstimmen.

## Schritte

### 1. Die Dateien im Manifest nennen

Füge in `features/Meetups/feature.php` vor dem Kommentar über `'settings'` ein:

```php features/Meetups/feature.php
	// Into the style bundle and the script bundle of the site
	'assets'      => [ '/features/Meetups/assets/meetups.css', '/features/Meetups/assets/meetups.js' ],
```

Die Pfade sind virtuelle Pfade. Die Dateien liegen im Verzeichnis `assets/` des Features. Nino nimmt jede `.css` ins Stylesheet-Bundle und jede `.js` ins Skript-Bundle.

### 2. Das Stylesheet

Lege `features/Meetups/assets/meetups.css` an:

```css features/Meetups/assets/meetups.css
/* The look of the Meetups feature on the site. Every size and colour is a
   custom property of _nino/Nino.css, so the list follows the theme */

.meetups-list {
	margin: var(--space-2) 0;
	padding: 0;
	list-style: none;
}

.meetups-list li {
	padding: var(--space-1) 0;
	border-bottom: 1px solid var(--color-border);
}

.meetups-date {
	display: inline-block;
	min-width: 12em;
	color: var(--color-primary);
}

/* Set by meetups.js on a date within the next seven days */
.meetups-date.meetups-is-soon {
	font-weight: bold;
}

.meetups-signup {
	display: grid;
	gap: var(--space-1);
	max-width: 30em;
	margin-top: var(--space-3);
}

.meetups-signup-message:empty {
	display: none;
}
```

Werte wie `var(--space-2)` und `var(--color-primary)` sind **Custom Properties** von Nino. Eine Custom Property ist eine Variable in CSS. Nino legt sie in `_nino/Nino.css` fest, das Design des Projekts setzt sie neu. Benutzt Du sie, folgt Dein Feature dem Design von selbst.

### 3. Das Skript

Lege `features/Meetups/assets/meetups.js` an:

```js features/Meetups/assets/meetups.js
'use strict';

( function() {

	// How many days ahead a date counts as soon
	const DAYS = 7;

	// Runs once the page is ready, also when the script loads after that
	Nino.events.bindCallback( 'ready', function() {

		const now = Date.now();

		document.querySelectorAll('.meetups-date time[datetime]').forEach( function( time ) {

			const ahead = ( new Date( time.getAttribute('datetime') ) ).getTime() - now;

			if( ahead >= 0 && ahead <= DAYS * 86400000 )
				time.parentNode.classList.add('meetups-is-soon');
		} );
	} );

} )();
```

`Nino.events.bindCallback( 'ready', … )` ruft die Funktion auf, sobald die Seite bereit ist. Das Skript markiert Treffen der nächsten sieben Tage. Die Seite funktioniert auch ohne das Skript.

### 4. Neu laden

Lade die Seite `/meetups` neu. Nino baut die Bundles selbst neu, wenn sich eine Datei ändert.

## Zusammenspiel mit dem Design-Feature

Das Feature **Design** aus dem Katalog erzeugt `private/assets/theme.css` aus Einstellungen in der Workbench. Für Dein Feature heißt das:

- Benutze die Custom Properties von Nino für Farben, Abstände und Radien. Dann folgt Dein Feature jedem Design.
- Schreibe keine Regel, die davon abhängt, dass Dein Stylesheet vor oder nach dem Design steht.
- Ein Feature kann dem Design-Feature keine eigenen Regler hinzufügen. Wer das Aussehen Deines Features ändern will, schreibt Regeln in `private/assets/style.css`.

## Prüfen

- Im Quelltext von `/meetups` steht ein Stylesheet `…/.cache/style.css` und ein Skript `…/.cache/script.js`. Beide enthalten jetzt Deine Regeln und Dein Skript. In `style.css` stehen Deine Regeln nach denen von `Nino.css` und vor denen von `theme.css`.
- Die Liste hat Linien zwischen den Treffen. Jedes Datum hat die Hauptfarbe des Designs.
- Lege im Bereich **Elemente** ein Treffen in den nächsten sieben Tagen an. Sein Datum steht fett da. Es trägt die Klasse `meetups-is-soon`.
- Schreibe zur Probe `.meetups-date { color: rgb(200, 0, 0); }` ans Ende von `private/assets/style.css`. Jedes Datum wird rot. Die Regel des Projekts gewinnt. Nimm die Zeile wieder heraus.

## Häufige Fehler

- **Feste Farben und Abstände.** `color: #1a7bb8` passt zu einem Design und zu keinem anderen. Nimm `var(--color-primary)`.
- **Eine Datei direkt verlinken.** `<link href="/features/Meetups/assets/meetups.css">` lädt nichts. Der Webserver liefert aus `features/` nie eine Datei aus.
- **Textfills im CSS.** In CSS und JavaScript ersetzt Nino keine Textfills und keine Shortcodes. Nur `[[/nino/dir]]` und `[[/nino/public]]` werden ersetzt.
- **Ein Skript, ohne das die Seite nicht geht.** Inhalte und Formulare müssen ohne JavaScript funktionieren.

## Nachschlagen

- [Assets](../features.md#assets): die Reihenfolge im Bundle
- [Assets Are Not Templates](../development.md#assets-are-not-templates): Bundles und der Shortcode `[assets]`
- [The Look](../setup.md#the-look): `theme.css` und `style.css`
- [The toolset](../features.md#the-toolset): die Helfer im Browser und die CSS-Namen von Nino
