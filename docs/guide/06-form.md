# 6. Ein Formular für Besucher

[Übersicht](README.md) · zurück: [5. Daten halten und in einer Schleife ausgeben](05-data.md) · weiter: [7. Eine Route, die Anmeldungen speichert und drosselt](07-route.md)

**Ziel:** Auf der Seite `/meetups` steht ein Formular, mit dem sich Besucher zu einem Treffen anmelden. Dieses Tutorial zeichnet das Formular. Das nächste verarbeitet die Anmeldung.

## Schritte

### 1. Die Komponente im Ordner

Das Formular wird eine zweite Komponente, im Ordner `features/Meetups/components/meetups-signup/`. Lege `component.php` an:

```php features/Meetups/components/meetups-signup/component.php
<?php
declare(strict_types=1);

// The sign-up form has no value of its own: no source, which is what a schema says without one
return [
	'group'   => 'meetups',
	'label'   => [ 'en_US' => 'Meetup sign-up', 'de_DE' => 'Anmeldung zu einem Treffen' ],
	'in'      => [ 'page', 'flow' ],
	'preview' => 'block',
];
```

Die Komponente hat keinen Wert und keine Attribute. Der Aufruf ist nur `[meetups-signup]`. Eine Vorlage `template.tpl` legst Du nicht an: Die Methode in Schritt 4 liest ihre Vorlage selbst, weil sie html+ ist, die sie nach dem Einsetzen noch zeichnen lässt. Eine Komponente ohne Vorlage braucht eine Methode, und die hat sie.

Setze die Version auf `0.4.0`:

```php features/Meetups/feature.php
	'version'     => '0.4.0',
```

### 2. Die Vorlage des Formulars

Lege `features/Meetups/templates/meetups-signup.tpl` an:

```html features/Meetups/templates/meetups-signup.tpl
<form class="nino-form [[class]]" method="post" action="[[/nino/dir]]/meetups" data-form-texts="/feature/meetups/signup/">
	[csrf]
	<input type="text" name="website" value="" tabindex="-1" autocomplete="off" aria-hidden="true" class="nino-form-trap">
	<label for="meetups-signup-meetup">[[/feature/meetups/signup/meetup]]</label>
	<select id="meetups-signup-meetup" name="meetup" required>
		[elements /meetups sort="date" callback="/meetups/upcoming"]<option value="[[.slug]]">[[title]]</option>[/elements]
	</select>
	<label for="meetups-signup-name">[[/feature/meetups/signup/name]]</label>
	<input type="text" id="meetups-signup-name" name="name" maxlength="80" autocomplete="name" required>
	<button type="submit" class="nino-form-submit">[[/feature/meetups/signup/submit]]</button>
	<p class="nino-form-message meetups-signup-message">[[message]]</p>
</form>
```

- `[csrf]` setzt ein verstecktes Feld mit dem Token der Sitzung ein. Ohne dieses Feld lehnt Nino jede Anfrage des Formulars ab.
- `[[/nino/dir]]` ist das Verzeichnis, unter dem das Projekt läuft. Setze es vor jede Adresse.
- Das Feld `website` ist eine Falle für Bots. Menschen sehen es nicht. Die Klasse `nino-form-trap` versteckt es.
- `class="nino-form"` übergibt das Formular dem Skript von Nino und dem Stylesheet des Moduls Form (`form.css`), das Felder, Meldung und Knopf zeichnet; `nino-form-submit` ist die Klasse des Knopfes. `[[class]]` ist der Name der Komponente, `meetups-signup`. Es sendet ohne Neuladen ab, prüft die Pflichtfelder, markiert das falsche Feld und sagt in der Sprache der Seite, was fehlt. Eigenes JavaScript brauchst Du nicht.
- Das Formular trägt kein `novalidate`: Das Skript setzt es selbst und prüft in der Sprache der Seite. Ohne JavaScript prüft der Browser `required` und die Feldtypen in seiner eigenen Sprache.
- `data-form-texts` nennt den Anfang der Schlüssel, unter denen die Sätze des Skripts liegen. Der Schlüssel endet mit `/`. Der nächste Schritt legt sie an.
- Das erste `<p>` des Formulars ist die Meldung: Das Skript schreibt den Satz hinein und macht den Absatz zur Live-Region. Der erste `<button>` ist der Knopf, den es während der Anfrage sperrt.
- Die Auswahl der Treffen ist dieselbe Schleife wie in Tutorial 5. `[[.slug]]` ist der Name des Elements, zum Beispiel `christmas`.
- `[[class]]` und `[[message]]` füllt PHP. Die anderen Platzhalter sind Textfills.

Die Vorlage eines Features darf Textfills und Shortcodes enthalten. Nino liest die Ausgabe einer Komponente aber nicht noch einmal: Was die Methode zurückgibt, ist fertig, und ein `[csrf]` darin bliebe als Text im Formular stehen. Die Komponente zeichnet ihre Vorlage deshalb selbst (Schritt 4).

### 3. Die Worte

Diese Worte gibt das Feature selbst aus, nicht eine Vorlage des Projekts. Sie liegen deshalb unter `/feature/<schlüssel>/…`. Die vier Schlüssel `required`, `invalid`, `success` und `error` sind die Sätze, die das Skript spricht: `required`, wenn ein Pflichtfeld leer ist, `invalid`, wenn die Auswahl oder die Eingabe abgelehnt wird (Status `400`), `success` bei Status `200` und `error` bei jedem anderen Status. Ein Schlüssel, den Du nicht anlegst, zeigt nichts an. `invalid` gilt auch für ein `400`, wenn das Formular kein Feld für eine E-Mail-Adresse hat. Ein Formular mit einem solchen Feld legt auch `email` an. Füge in `features/Meetups/install/text/en_US.php` vor der letzten Zeile `];` ein:

```php features/Meetups/install/text/en_US.php
	'[[/feature/meetups/signup/meetup]]'     => 'Meetup',
	'[[/feature/meetups/signup/name]]'       => 'Your name',
	'[[/feature/meetups/signup/submit]]'     => 'Sign up',
	'[[/feature/meetups/signup/required]]'   => 'Please enter your name.',
	'[[/feature/meetups/signup/invalid]]'    => 'Please pick a meetup and enter your name.',
	'[[/feature/meetups/signup/success]]'    => 'Thank you. You are signed up.',
	'[[/feature/meetups/signup/error]]'      => 'That did not work. Please try again later.',
```

Und in `install/text/de_DE.php`:

```php features/Meetups/install/text/de_DE.php
	'[[/feature/meetups/signup/meetup]]'     => 'Treffen',
	'[[/feature/meetups/signup/name]]'       => 'Dein Name',
	'[[/feature/meetups/signup/submit]]'     => 'Anmelden',
	'[[/feature/meetups/signup/required]]'   => 'Bitte gib Deinen Namen ein.',
	'[[/feature/meetups/signup/invalid]]'    => 'Bitte wähle ein Treffen und gib Deinen Namen ein.',
	'[[/feature/meetups/signup/success]]'    => 'Danke. Du bist angemeldet.',
	'[[/feature/meetups/signup/error]]'      => 'Das hat nicht geklappt. Bitte versuche es später noch einmal.',
```

### 4. Die Komponente zeichnen

Füge in `features/Meetups/Meetups.php` diese Methode in die Klasse ein, direkt nach `init()`, mit je einer Leerzeile davor und danach:

```php features/Meetups/Meetups.php
		// [meetups-signup]
		public static function componentMeetupsSignup( array &$appData, array $args, string $content, array $context ): string {

			$state = $appData['./meetups/signup'] ?? '';

			// One of three fixed words picks the text key. Nothing of the request is written
			$message = in_array( $state, [ 'success', 'invalid', 'error' ], true ) === true ? '[[/feature/meetups/signup/'. $state. ']]' : '';

			// $args['class'] is the name of the component: the register made it, and nobody wrote it
			$source = str_replace(
				[ '[[class]]', '[[message]]' ],
				[ $args['class'], $message ],
				\Nino\Feature::template( $appData, self::TEMPLATES, 'meetups-signup' )
			);

			// The template is html+: [csrf], [elements] and the textfills are drawn here, as the page draws them
			return \Nino\Html::renderSource( $appData, $source, \Nino\Html::context( $appData ) );
		}
```

- Die Methode heißt nach der Komponente: `meetups-signup` wird `componentMeetupsSignup()`. Sie nimmt dieselben vier Argumente wie die Methode aus Tutorial 3.
- Die Vorlage ist HTML+. Die Methode setzt zuerst die Platzhalter ein und lässt das Ergebnis dann von `\Nino\Html::renderSource()` zeichnen. `\Nino\Html::context()` sagt, wo der Aufruf auf der Seite steht, damit die Vorlage dort so wirkt, als stünde sie in der Seite.
- Was Du vor dem Zeichnen einsetzt, wird mitgelesen. Setze deshalb nur Sicheres ein: `$args['class']` besteht aus dem Namen der Komponente und festen Wörtern, und die Meldung ist einer von drei festen Schlüsseln. Würdest Du einen Wert der Anfrage einsetzen, schriebest Du jedes `[` darin als `&#91;`, wie `\Nino\Feature::classes()` es tut.
- `$appData['./meetups/signup']` ist ein Wert, der nur für diese Anfrage gilt. Schlüssel mit `./` am Anfang speichert Nino nie. Tutorial 7 setzt den Wert.
- Der Satz im Formular ist ein Textfill. Die Methode wählt seinen Schlüssel aus drei festen Worten. Sie schreibt nichts aus der Anfrage ins HTML. Der Satz ist für einen Besucher ohne JavaScript: Das Skript schreibt seine Sätze selbst in die Meldung.

### 5. Die Worte der Seite mitgeben

Das Skript liest seine Sätze aus dem Text, den die Seite im Kopf trägt, und dort stehen nur die Schlüssel, die jemand dafür freigibt. Ersetze in `features/Meetups/Meetups.php` die Methode `init()` samt ihrem Kommentar:

```php features/Meetups/Meetups.php
		// Called at every start of a request while the feature is on
		public static function init( array &$appData ): void {
			\Nino\Callbacks::registerCallback( $appData, '/meetups/upcoming', [ self::class, 'callbackUpcoming' ] );

			// The words the sign-up form's script says are public: they stand in the source of the page
			\Nino\Modules\Jstext::publish( $appData, [ '/feature/meetups/signup/' ] );
		}
```

`Jstext::publish()` gibt den Anfang eines Schlüssels frei. Gib nur Sätze frei, die jeder Besucher lesen darf.

### 6. Das Formular auf die Seite setzen

Schreibe `[meetups-signup]` in beide Fassungen der Vorlage: in `features/Meetups/install/templates/page-meetups.tpl` und in die Kopie des Projekts `private/templates/page-meetups.tpl`. Die Zeile steht nach `</ul>`, mit einer Leerzeile dazwischen:

```html features/Meetups/install/templates/page-meetups.tpl
			</ul>

			[meetups-signup]
```

Klicke im Bereich **Features** bei „Treffen“ auf **Auf 0.4.0 aktualisieren**. Das Update fügt die neuen Worte ins Projekt ein.

## Prüfen

Öffne `/meetups`. Unter der Liste steht das Formular mit einer Auswahl der kommenden Treffen, einem Feld für den Namen und dem Knopf „Anmelden“. Der Quelltext zeigt:

```html
<form class="nino-form meetups-signup" method="post" action="/meetups" data-form-texts="/feature/meetups/signup/">
	<input type="hidden" name="_csrf" value="…">
```

Der Wert von `_csrf` ist eine lange Zeichenfolge. In `private/text/de_DE.php` stehen die sieben neuen Schlüssel.

Sende das Formular ab, erst ohne Namen: Unter dem Knopf steht „Bitte gib Deinen Namen ein.“, das Feld ist markiert, und der Cursor steht darin. Sende es dann mit einem Namen ab. Die Antwort ist der Status `404`, und das Skript sagt „Das hat nicht geklappt. …“. Das ist richtig: Die Route für `POST /meetups` gibt es noch nicht. Sie kommt in Tutorial 7.

## Häufige Fehler

- **`[csrf]` fehlt im Formular oder steht darin als Text.** Jede Anfrage endet später mit dem Status `403`. Steht `[csrf]` im Quelltext der Seite als Text, hat die Methode ihre Vorlage nicht mit `renderSource()` gezeichnet.
- **`data-form-texts` fehlt.** Das Skript spricht dann die Sätze des Kontaktformulars. Auch ein Schlüssel, der nicht freigegeben ist (Schritt 5), zeigt nichts an.
- **Ein Feld ohne `label`.** Jedes Feld braucht eine Beschriftung, die mit `for` auf seine `id` zeigt.
- **Die Adresse ohne `[[/nino/dir]]`.** Das Formular geht dann ins Leere, sobald das Projekt in einem Unterverzeichnis läuft.

## Nachschlagen

- [Components in folders](../features.md#components-in-folders): Komponenten ohne Quelle
- [CSRF](../development.md#csrf): wo Nino das Token liest
- [A form of a feature](../development.md#a-form-of-a-feature): was das Skript voraussetzt und was die Route selbst tun muss
- [Forms](../development.md#forms): das Kontaktformular von Nino und die Klassen für Formulare
- [The key grammar](../development.md#the-key-grammar): wann ein Wort unter `/feature/` liegt
