# 6. Ein Formular für Besucher

[Übersicht](README.md) · zurück: [5. Daten halten und in einer Schleife ausgeben](05-data.md) · weiter: [7. Eine Route, die Anmeldungen speichert und drosselt](07-route.md)

**Ziel:** Auf der Seite `/meetups` steht ein Formular, mit dem sich Besucher zu einem Treffen anmelden. Dieses Tutorial zeichnet das Formular. Das nächste verarbeitet die Anmeldung.

## Schritte

### 1. Die Komponente im Manifest

Das Formular wird eine zweite Komponente. Füge in `features/Meetups/feature.php` unter `components` einen Eintrag ein, nach dem Eintrag `meetup-date`:

```php features/Meetups/feature.php
		// The sign-up form. It has no value of its own, so its source is none
		'meetups-signup' => [
			'label'      => [ 'en_US' => 'Meetup sign-up', 'de_DE' => 'Anmeldung zu einem Treffen' ],
			'source'     => 'none',
			'preview'    => 'block',
			'draws'      => 'form .meetups-signup',
			'in'         => [ 'section' ],
			'attributes' => [],
		],
```

`'source' => 'none'` heißt: Die Komponente hat keinen Wert. Der Aufruf ist nur `[meetups-signup]`.

Setze die Version auf `0.4.0`:

```php features/Meetups/feature.php
	'version'     => '0.4.0',
```

### 2. Die Vorlage des Formulars

Lege `features/Meetups/templates/meetups-signup.tpl` an:

```html features/Meetups/templates/meetups-signup.tpl
<form class="meetups-signup[[classes]]" method="post" action="[[/nino/dir]]/meetups">
	[csrf]
	<input type="text" name="website" value="" tabindex="-1" autocomplete="off" aria-hidden="true" class="nino-form-trap">
	<label for="meetups-signup-meetup">[[/feature/meetups/signup/meetup]]</label>
	<select id="meetups-signup-meetup" name="meetup" class="nino-form-select" required>
		[elements /meetups sort="date" callback="/meetups/upcoming"]<option value="[[.slug]]">[[title]]</option>[/elements]
	</select>
	<label for="meetups-signup-name">[[/feature/meetups/signup/name]]</label>
	<input type="text" id="meetups-signup-name" name="name" class="nino-form-input" maxlength="80" autocomplete="name" required>
	<button type="submit" class="nino-btn nino-btn--primary">[[/feature/meetups/signup/submit]]</button>
	<p class="meetups-signup-message" role="status">[[message]]</p>
</form>
```

- `[csrf]` setzt ein verstecktes Feld mit dem Token der Sitzung ein. Ohne dieses Feld lehnt Nino jede Anfrage des Formulars ab.
- `[[/nino/dir]]` ist das Verzeichnis, unter dem das Projekt läuft. Setze es vor jede Adresse.
- Das Feld `website` ist eine Falle für Bots. Menschen sehen es nicht. Die Klasse `nino-form-trap` versteckt es.
- Die Auswahl der Treffen ist dieselbe Schleife wie in Tutorial 5. `[[.slug]]` ist der Name des Elements, zum Beispiel `christmas`.
- `[[message]]` füllt PHP. Die anderen Platzhalter sind Textfills.

Die Vorlage eines Features darf Textfills und Shortcodes enthalten. Nino liest die Ausgabe einer Komponente noch einmal und ersetzt auch darin Textfills und Shortcodes.

### 3. Die Worte

Diese Worte gibt das Feature selbst aus, nicht eine Vorlage des Projekts. Sie liegen deshalb unter `/feature/<schlüssel>/…`. Füge in `features/Meetups/install/text/en_US.php` vor der letzten Zeile `];` ein:

```php features/Meetups/install/text/en_US.php
	'[[/feature/meetups/signup/meetup]]'     => 'Meetup',
	'[[/feature/meetups/signup/name]]'       => 'Your name',
	'[[/feature/meetups/signup/submit]]'     => 'Sign up',
	'[[/feature/meetups/signup/done]]'       => 'Thank you. You are signed up.',
	'[[/feature/meetups/signup/invalid]]'    => 'Please pick a meetup and enter your name.',
	'[[/feature/meetups/signup/busy]]'       => 'Too many tries. Please try again in an hour.',
	'[[/feature/meetups/signup/failed]]'     => 'That did not work. Please try again later.',
```

Und in `install/text/de_DE.php`:

```php features/Meetups/install/text/de_DE.php
	'[[/feature/meetups/signup/meetup]]'     => 'Treffen',
	'[[/feature/meetups/signup/name]]'       => 'Dein Name',
	'[[/feature/meetups/signup/submit]]'     => 'Anmelden',
	'[[/feature/meetups/signup/done]]'       => 'Danke. Du bist angemeldet.',
	'[[/feature/meetups/signup/invalid]]'    => 'Bitte wähle ein Treffen und gib Deinen Namen ein.',
	'[[/feature/meetups/signup/busy]]'       => 'Zu viele Versuche. Bitte versuche es in einer Stunde noch einmal.',
	'[[/feature/meetups/signup/failed]]'     => 'Das hat nicht geklappt. Bitte versuche es später noch einmal.',
```

### 4. Die Komponente zeichnen

Füge in `features/Meetups/Meetups.php` diese Methode in die Klasse ein, direkt nach `init()`, mit je einer Leerzeile davor und danach:

```php features/Meetups/Meetups.php
		// [meetups-signup]
		public static function componentMeetupsSignup( array &$appData, array $args ): string {

			$state = $appData['./meetups/signup'] ?? '';

			// One of four fixed words picks the text key. Nothing of the request is written
			$message = in_array( $state, [ 'done', 'invalid', 'busy', 'failed' ], true ) === true ? '[[/feature/meetups/signup/'. $state. ']]' : '';

			return str_replace(
				[ '[[classes]]', '[[message]]' ],
				[ \Nino\Feature::classes( $args ), $message ],
				\Nino\Feature::template( $appData, self::TEMPLATES, 'meetups-signup' )
			);
		}
```

- Die Methode heißt nach der Komponente: `meetups-signup` wird `componentMeetupsSignup()`.
- `$appData['./meetups/signup']` ist ein Wert, der nur für diese Anfrage gilt. Schlüssel mit `./` am Anfang speichert Nino nie. Tutorial 7 setzt den Wert.
- Der Satz im Formular ist ein Textfill. Die Methode wählt seinen Schlüssel aus vier festen Worten. Sie schreibt nichts aus der Anfrage ins HTML.

### 5. Das Formular auf die Seite setzen

Schreibe `[meetups-signup]` in beide Fassungen der Vorlage: in `features/Meetups/install/templates/page-meetups.tpl` und in die Kopie des Projekts `private/templates/page-meetups.tpl`. Die Zeile steht nach `</ul>`, mit einer Leerzeile dazwischen:

```html features/Meetups/install/templates/page-meetups.tpl
			</ul>

			[meetups-signup]
```

Klicke im Bereich **Features** bei „Treffen“ auf **Auf 0.4.0 aktualisieren**. Das Update fügt die neuen Worte ins Projekt ein.

## Prüfen

Öffne `/meetups`. Unter der Liste steht das Formular mit einer Auswahl der kommenden Treffen, einem Feld für den Namen und dem Knopf „Anmelden“. Der Quelltext zeigt:

```html
<form class="meetups-signup" method="post" action="/meetups">
	<input type="hidden" name="_csrf" value="…">
```

Der Wert von `_csrf` ist eine lange Zeichenfolge. In `private/text/de_DE.php` stehen die sieben neuen Schlüssel.

Sende das Formular ab. Die Antwort ist die Seite „nicht gefunden“ mit dem Status `404`. Das ist richtig: Die Route für `POST /meetups` gibt es noch nicht. Sie kommt in Tutorial 7.

## Häufige Fehler

- **`[csrf]` fehlt im Formular.** Jede Anfrage endet später mit dem Status `403`.
- **Die Klasse `nino-form` am Formular.** Das Skript von Nino übernimmt dann das Absenden und zeigt die Sätze des Kontaktformulars. Gib einem eigenen Formular eine eigene Klasse. Die Klassen der Felder wie `nino-form-input` darfst Du benutzen.
- **Ein Feld ohne `label`.** Jedes Feld braucht eine Beschriftung, die mit `for` auf seine `id` zeigt.
- **Die Adresse ohne `[[/nino/dir]]`.** Das Formular geht dann ins Leere, sobald das Projekt in einem Unterverzeichnis läuft.

## Nachschlagen

- [Components and stacks](../features.md#components-and-stacks): Komponenten ohne Quelle
- [CSRF](../development.md#csrf): wo Nino das Token liest
- [Forms](../development.md#forms): das Kontaktformular von Nino und die Klassen für Formulare
- [The key grammar](../development.md#the-key-grammar): wann ein Wort unter `/feature/` liegt
