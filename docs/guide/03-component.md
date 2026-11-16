# 3. Eine Komponente erstellen

[Übersicht](README.md) · zurück: [2. Einen Shortcode erstellen](02-shortcode.md) · weiter: [4. Eine Seite mit Texten in zwei Sprachen](04-texts.md)

**Ziel:** Aus dem Shortcode `[meetup-date]` wird eine Komponente: ein Shortcode mit Schema, dessen Wert aus einem Text, einem Textschlüssel oder dem Feld eines Elements kommt.

## Warum eine Komponente

Von einem Shortcode kennt Nino nur den Namen. Eine **Komponente** nennt auch ihre Quelle, ihre Attribute und ihr Markup. Das steht in ihrem **Schema**: die Quelle des Werts und jedes Attribut mit Typ und Vorgabe. Das bringt vier Dinge:

- Nino prüft die Attribute und setzt Vorgaben ein.
- Nino holt den Wert und maskiert ihn, bevor Deine Methode läuft.
- Das Markup liegt als Vorlage neben dem Schema. Eine Komponente, die nichts berechnet, braucht keine Methode.
- Ein Werkzeug wie der Builder aus dem Katalog kann die Komponente anbieten.

Ein Feature bringt seine Komponenten in Ordnern, nicht im Manifest. Nino liest sie bei jeder Anfrage, solange das Feature eingeschaltet ist.

## Schritte

### 1. Die Ordner

Lege diese Ordner und Dateien an. Die Datei `features/Meetups/templates/meetup-date.tpl` aus Tutorial 1 und 2 löschst Du: Das Markup liegt neben dem Schema.

```text
features/Meetups/
├── feature.php                          das Manifest
├── Meetups.php                          die Klasse \Nino\Modules\Meetups
├── components/
│   └── meetup-date/                     der Name des Ordners ist der Name der Komponente
│       ├── component.php                das Schema
│       └── template.tpl                 das Markup
└── groups/
    └── meetups/
        └── group.php                    die Gruppe, unter der ein Werkzeug die Komponente anbietet
```

Das Manifest bleibt, wie es ist. Es nennt keine Komponente.

### 2. Das Schema

Lege `features/Meetups/components/meetup-date/component.php` an:

```php features/Meetups/components/meetup-date/component.php
<?php
declare(strict_types=1);

// A component says what it takes: where its value comes from and which attributes it has
return [
	'group'      => 'meetups',
	'label'      => [ 'en_US' => 'Meetup date', 'de_DE' => 'Datum eines Treffens' ],
	'in'         => [ 'page', 'flow' ],
	'source'     => 'text',
	'preview'    => 'text',
	'variants'   => [ 'highlight' ],
	'attributes' => [
		'time'  => [ 'type' => 'text', 'default' => '', 'label' => [ 'en_US' => 'Time (HH:MM)', 'de_DE' => 'Uhrzeit (HH:MM)' ] ],
		'label' => [ 'type' => 'string', 'default' => '', 'label' => [ 'en_US' => 'Label', 'de_DE' => 'Bezeichnung' ] ],
		'long'  => [ 'type' => 'bool', 'default' => false, 'label' => [ 'en_US' => 'Month as a word', 'de_DE' => 'Monat als Wort' ] ],
	],
];
```

- `group` ist die Gruppe, unter der ein Werkzeug die Komponente anbietet. Eine Komponente eines Features nennt sie immer. Schritt 4 legt die Gruppe an.
- `source` ist die Quelle des Werts. `text` heißt: Der Wert ist ein Text.
- `attributes` nennt jedes Attribut mit `type` und `default`. Ohne `default` lehnt Nino die Komponente ab.
- `time` hat den Typ `text`: ein zweiter Text eines Aufrufs. Er ist ein Verweis (`~name`, ein Schlüssel mit `/`, ein `.feld`) oder der Text selbst. Tutorial 5 braucht den Verweis. `label` hat den Typ `string`: ein Wert, den Nino maskiert.
- `long` ist ein Schalter. Ein Schalter, der an ist, steht auch als Klasse am Markup: `meetup-date--long`.
- `variants` nennt die Wörter einer offenen Achse. Der Aufruf `variant="highlight"` schreibt die Klasse `meetup-date--variant-highlight`.
- `label`, `preview` und `in` sind für Werkzeuge: der Name, die Art der Vorschau und die Orte, an denen die Komponente stehen darf. `page` ist die Seite selbst, `flow` der Fluss des Inhalts.

### 3. Die Vorlage

Lege `features/Meetups/components/meetup-date/template.tpl` an:

```html features/Meetups/components/meetup-date/template.tpl
<span class="[[class]]">[[label]]<time datetime="[[iso]]">[[text]]</time></span>
```

`[[class]]` ist der Name der Komponente und eine Klasse für jedes Attribut, das eine Klasse ist. Die anderen drei Platzhalter füllt die Methode im nächsten Schritt. Ein Editor beendet die Datei mit einem Zeilenumbruch. Er steht auch in der Ausgabe, hinter dem `</span>`; die Zeilen unter „Prüfen“ lassen ihn weg.

### 4. Die Gruppe

Lege `features/Meetups/groups/meetups/group.php` an:

```php features/Meetups/groups/meetups/group.php
<?php
declare(strict_types=1);

// The group the components of the feature are offered under
return [
	'label' => [ 'en_US' => 'Meetups', 'de_DE' => 'Treffen' ],
];
```

Jede Gruppe gehört genau einem Feature. Ein Feature, das den Namen einer Gruppe nimmt, die ein anderes schon hat, läuft ohne diese Komponenten.

### 5. Die Methode

Nino sucht die Methode nach dem Namen der Komponente: `meetup-date` wird `componentMeetupDate()`. Die Anmeldung in `init()` entfällt, denn Nino meldet die Komponente an. Ersetze `features/Meetups/Meetups.php`:

```php features/Meetups/Meetups.php
<?php
declare(strict_types=1);

namespace Nino\Modules {

	class Meetups {

		public const string TEMPLATES = '/features/Meetups/templates';

		// [meetup-date text="2026-12-24" time="19:30" label="Christmas party" long="yes" variant="highlight"]
		public static function componentMeetupDate( array &$appData, array $args, string $content, array $context ): string {

			// The value and the texts are escaped already. A date has no character that escaping changes
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

			// The template of the component, with the three tokens this method made
			return \Nino\Components::fill( $appData, 'meetup-date', $args, $content, [
				'[[iso]]'   => $time === '' ? $date : $date. 'T'. $time,
				'[[label]]' => $label === '' ? '' : $label. ': ',
				'[[text]]'  => \Nino\Components::escape( $text ),
			] );
		}
	}
}
```

Was die Methode von der aus Tutorial 2 unterscheidet:

- `$args['value']` ist der Wert der Quelle. Nino hat ihn schon maskiert. Maskiere ihn nicht noch einmal.
- Jedes Attribut ist da, als Text. Fehlt es im Aufruf, steht die Vorgabe darin. Deshalb entfällt `?? ''`.
- Ein `bool` kommt als `'1'` oder `'0'` an.
- `label` und `time` haben Typen, die Nino maskiert. Das Datum, das Du zusammensetzt, maskierst Du selbst, mit `\Nino\Components::escape()`.
- Das Markup baut `\Nino\Components::fill()` aus der Vorlage der Komponente. Die Methode gibt ihm nur die Teile, die sie selbst berechnet hat.
- Die Methode nimmt vier Argumente: die `$appData`, die Attribute, den Inhalt zwischen den Marken und den Ort des Aufrufs. Eine Komponente, die nichts davon braucht, schreibt sie trotzdem hin.

### 6. Die Aufrufe anpassen

Der Wert steht nicht im Attribut `date`, sondern in der Quelle der Komponente. Die Quelle ist das erste Wort des Aufrufs, oder ein fester Wert in `text="…"`. Es gibt drei Formen:

| Aufruf | Der Wert ist |
|---|---|
| `[meetup-date text="2026-12-24"]` | der feste Text |
| `[meetup-date /template/page-home/next/date]` | der Text dieses Textschlüssels |
| `[meetup-date .date]` | das Feld `date` des Elements, in einer Schleife von Elementen |

Ersetze in `private/templates/page-home.tpl` die Zeilen aus Tutorial 2:

```html
<p>Next meeting: [meetup-date text="2026-12-24"]</p>
<p>[meetup-date text="2026-12-24" time="19:30" label="Christmas party" variant="highlight"]</p>
<p>[meetup-date text="2027-01-09" long="yes"]</p>
<p>[meetup-date text="2027-03-01" label="<b>Bold</b>" colour="red"]</p>
```

Du musst das Feature nicht neu einschalten. Nino liest die Ordner bei jeder Anfrage neu, sobald sich eine Datei ändert.

## Prüfen

Der Quelltext der Startseite zeigt auf einer deutschen Seite:

```html
<p>Next meeting: <span class="meetup-date"><time datetime="2026-12-24">24.12.2026</time></span></p>
<p><span class="meetup-date meetup-date--variant-highlight">Christmas party: <time datetime="2026-12-24T19:30">24.12.2026, 19:30</time></span></p>
<p><span class="meetup-date meetup-date--long"><time datetime="2027-01-09">9. Januar 2027</time></span></p>
<p><span class="meetup-date">&lt;b&gt;Bold&lt;/b&gt;: <time datetime="2027-03-01">01.03.2027</time></span></p>
```

Das Attribut `colour` steht nicht im Schema. Nino lässt es weg, und die Komponente wird trotzdem gezeichnet.

## Häufige Fehler

- **Der Aufruf aus Tutorial 2, `[meetup-date date="2026-12-24"]`, zeichnet nichts.** Die Komponente holt ihren Wert aus der Quelle, und ein fester Wert steht in `text="…"`. Das Attribut `date` kennt das Schema nicht, Nino lässt es weg, und ohne Wert zeichnet die Komponente nichts.
- **Ein Attribut ohne `default`, oder eine Komponente ohne `group`.** Nino lehnt die Komponente ab, und das Feature läuft ohne sie. Der Bereich Features zeigt den Grund an. Derselbe Grund steht einmal je Anfrage in `private/data/logs.<Jahr>-<Monat>.php`, siehe [Beim Entwickeln](README.md#beim-entwickeln). Ein Syntaxfehler oder eine Ausnahme in `component.php` lehnt die Komponente ebenso ab. Dann nennt der Grund Art, Meldung und Zeile des Fehlers.
- **Der Ordner heißt anders als die Komponente.** Der Name des Ordners unter `components/` ist der Name der Komponente: Kleinbuchstaben, Ziffern und Bindestriche. Ein Ordner, dessen Name kein solcher Name ist, liest Nino nicht.
- **`$args['value']` noch einmal maskieren.** Aus `&` wird dann `&amp;amp;`.

## Nachschlagen

- [Components in folders](../features.md#components-in-folders): Komponenten im Feature
- [A component in a feature](../recipes/component.md#1-a-component-in-a-feature): das Rezept
- [The Register of Components](../development.md#the-register-of-components): das ganze Schema, alle Quellen und Typen
