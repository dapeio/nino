# 3. Eine Komponente erstellen

[Übersicht](README.md) · zurück: [2. Einen Shortcode erstellen](02-shortcode.md) · weiter: [4. Eine Seite mit Texten in zwei Sprachen](04-texts.md)

**Ziel:** Aus dem Shortcode `[meetup-date]` wird eine Komponente: ein Shortcode mit Schema, dessen Wert aus einem Text, einem Textschlüssel oder dem Feld eines Elements kommt.

## Warum eine Komponente

Von einem Shortcode kennt Nino nur den Namen. Eine **Komponente** nennt auch ihre Quelle und ihre Attribute. Das steht in ihrem **Schema**: die Quelle des Werts und jedes Attribut mit Typ und Vorgabe. Das bringt drei Dinge:

- Nino prüft die Attribute und setzt Vorgaben ein.
- Nino holt den Wert und maskiert ihn, bevor Deine Methode läuft.
- Ein Werkzeug wie der Builder aus dem Katalog kann die Komponente anbieten.

## Schritte

### 1. Das Schema ins Manifest

Ersetze `features/Meetups/feature.php`:

```php features/Meetups/feature.php
<?php
// What Nino reads about the feature. The class is not named here:
// features/Meetups/ can only serve \Nino\Modules\Meetups
return [
	'key'         => 'meetups',
	'name'        => [ 'en_US' => 'Meetups', 'de_DE' => 'Treffen' ],
	'description' => [ 'en_US' => 'The meetups of a club.', 'de_DE' => 'Die Treffen eines Vereins.' ],
	'category'    => 'content',
	'version'     => '0.1.0',
	'nino'        => '^1.12',
	// A shortcode with a schema. The class renders it in componentMeetupDate()
	'components'  => [
		'meetup-date' => [
			'label'      => [ 'en_US' => 'Meetup date', 'de_DE' => 'Datum eines Treffens' ],
			'source'     => 'text',
			'preview'    => 'text',
			'draws'      => 'span .meetups-date',
			'in'         => [ 'section', 'card' ],
			'attributes' => [
				'time'  => [ 'type' => 'string', 'default' => '', 'label' => [ 'en_US' => 'Time (HH:MM)', 'de_DE' => 'Uhrzeit (HH:MM)' ] ],
				'label' => [ 'type' => 'string', 'default' => '', 'label' => [ 'en_US' => 'Label', 'de_DE' => 'Bezeichnung' ] ],
				'long'  => [ 'type' => 'bool', 'default' => false, 'label' => [ 'en_US' => 'Month as a word', 'de_DE' => 'Monat als Wort' ] ],
			],
		],
	],
];
```

- `source` ist die Quelle des Werts. `text` heißt: Der Wert ist ein Text.
- `attributes` nennt jedes Attribut mit `type` und `default`. Ohne `default` lehnt Nino das Manifest ab.
- `label`, `preview`, `draws` und `in` sind für Werkzeuge: der Name, die Art der Vorschau, das gezeichnete Element und die Orte, an denen die Komponente stehen darf.

### 2. Die Methode umbenennen

Nino sucht die Methode nach dem Namen der Komponente: `meetup-date` wird `componentMeetupDate()`. Die Anmeldung in `init()` entfällt, denn das Manifest meldet die Komponente an. Ersetze `features/Meetups/Meetups.php`:

```php features/Meetups/Meetups.php
<?php
declare(strict_types=1);

namespace Nino\Modules {

	class Meetups {

		public const string TEMPLATES = '/features/Meetups/templates';

		// [meetup-date text="2026-12-24" time="19:30" label="Christmas party" long="yes" class="highlight"]
		public static function componentMeetupDate( array &$appData, array $args ): string {

			// The value is escaped already. A date has no character that escaping changes
			$date = $args['value'];
			$time = $args['time'];

			// A date that is none draws nothing
			if( preg_match( '/^\d{4}-\d{2}-\d{2}$/D', $date ) !== 1 )
				return '';

			// A time that is none is left out
			if( preg_match( '/^\d{2}:\d{2}$/D', $time ) !== 1 )
				$time = '';

			// The date as the language of the page writes it
			if( $time !== '' )
				$text = \Nino\Format::datetime( $appData, $date. ' '. $time );
			else if( $args['long'] === '1' )
				$text = \Nino\Format::dateLong( $appData, $date );
			else
				$text = \Nino\Format::date( $appData, $date );

			// A date the calendar does not have, such as 2026-02-30
			if( $text === null )
				return '';

			$label = trim( $args['label'] );

			return str_replace(
				[ '[[classes]]', '[[iso]]', '[[label]]', '[[text]]' ],
				[
					\Nino\Feature::classes( $args ),
					$time === '' ? $date : $date. 'T'. $time,
					$label === '' ? '' : \Nino\Modules\Components::escape( $label ). ': ',
					\Nino\Modules\Components::escape( $text ),
				],
				\Nino\Feature::template( $appData, self::TEMPLATES, 'meetup-date' )
			);
		}
	}
}
```

Was sich geändert hat:

- `$args['value']` ist der Wert der Quelle. Nino hat ihn schon maskiert. Maskiere ihn nicht noch einmal.
- Jedes Attribut ist da, als Text. Fehlt es im Aufruf, steht die Vorgabe darin. Deshalb entfällt `?? ''`.
- Ein `bool` kommt als `'1'` oder `'0'` an.
- Alles andere maskierst Du weiter selbst, hier `label` und den Text des Datums.

Die Vorlage `templates/meetup-date.tpl` bleibt, wie sie ist.

### 3. Die Aufrufe anpassen

Das erste Argument einer Komponente ist jetzt die Quelle, kein fester Wert mehr. Es gibt drei Formen:

| Aufruf | Der Wert ist |
|---|---|
| `[meetup-date text="2026-12-24"]` | der feste Text |
| `[meetup-date /template/page-home/next/date]` | der Text dieses Textschlüssels |
| `[meetup-date date]` | das Feld `date` des Elements, in einer Schleife von Elementen |

Ersetze in `private/templates/page-home.tpl` die Zeilen aus Tutorial 2:

```html
<p>Next meeting: [meetup-date text="2026-12-24"]</p>
<p>[meetup-date text="2026-12-24" time="19:30" label="Christmas party" class="highlight"]</p>
<p>[meetup-date text="2027-01-09" long="yes"]</p>
<p>[meetup-date text="2027-03-01" label="<b>Bold</b>" colour="red"]</p>
```

Du musst das Feature nicht neu einschalten. Nino liest das Manifest bei jeder Anfrage.

## Prüfen

Der Quelltext der Startseite zeigt auf einer deutschen Seite:

```html
<p>Next meeting: <span class="meetups-date"><time datetime="2026-12-24">24.12.2026</time></span></p>
<p><span class="meetups-date highlight">Christmas party: <time datetime="2026-12-24T19:30">24.12.2026, 19:30</time></span></p>
<p><span class="meetups-date"><time datetime="2027-01-09">9. Januar 2027</time></span></p>
<p><span class="meetups-date">&lt;b&gt;Bold&lt;/b&gt;: <time datetime="2027-03-01">01.03.2027</time></span></p>
```

Das Attribut `colour` steht nicht im Schema. Nino lässt es weg, und die Komponente wird trotzdem gezeichnet.

## Häufige Fehler

- **Der alte Aufruf `[meetup-date 2026-12-24]` zeigt nichts mehr.** Das erste Argument ist jetzt eine Quelle. Ein fester Wert steht in `text="…"`.
- **Ein Attribut ohne `default`.** Nino lehnt dann das ganze Manifest ab und lädt das Feature nicht. Der Bereich Features zeigt es weiter an, mit dem Grund der Ablehnung. Derselbe Grund steht in `private/data/logs.<Jahr>-<Monat>.php`, siehe [Beim Entwickeln](README.md#beim-entwickeln). Ein Syntaxfehler oder eine Ausnahme in `feature.php` lehnt es ebenso ab. Dann nennt der Bereich Art, Meldung und Zeile des Fehlers. War das Feature eingeschaltet, steht es unter **Aktiv** mit dem Hinweis, dass es unvollständig läuft, und ein Backup hält an, bis Du das Manifest repariert hast.
- **Doppelt anmelden.** Melde eine Komponente entweder im Manifest an oder in `init()`, nie beides. Trägt ein Name schon als Shortcode im Register und kommt er als Komponente dazu (oder umgekehrt), schreibt Nino eine Warnung ins Log. Sie nennt beide Klassen, die ihn anmelden.
- **`$args['value']` noch einmal maskieren.** Aus `&` wird dann `&amp;amp;`.

## Nachschlagen

- [Components and stacks](../features.md#components-and-stacks): Komponenten im Manifest
- [A component from a feature](../recipes/component.md#3-a-component-from-a-feature): das Rezept
- [Components](../development.md#components): das ganze Schema, alle Quellen und Typen
