# 2. Einen Shortcode erstellen

[Übersicht](README.md) · zurück: [1. Ein Feature anlegen](01-feature.md) · weiter: [3. Eine Komponente erstellen](03-component.md)

**Ziel:** Der Shortcode `[meetup-date]` bekommt Attribute, schreibt das Datum in der Sprache der Seite und gibt jeden fremden Wert sicher aus.

## Schritte

### 1. Die Attribute festlegen

Ein Shortcode kennt drei Arten von Argumenten:

| Im Template | In PHP |
|---|---|
| `[meetup-date date="2026-12-24"]` | `$args['date']` – ein Argument mit Namen, sein Wert steht in Anführungszeichen |
| `[meetup-date … long]` | ein bloßes Wort, ein Schalter |
| `[meetup-date /template/page-home/next/date]` | `$args[0]` – die Quelle: ein Wort ohne Namen, das mit `/`, `~` oder `.` beginnt |

Ein Wort ohne Namen ist die Quelle, wenn es mit `/`, `~` oder `.` beginnt. Sonst ist es ein Schalter, und das nur, wenn es als Name eines Attributs taugt: Buchstaben und Ziffern, ein Buchstabe am Anfang. Ein Datum ist weder das eine noch das andere. `[meetup-date 2026-12-24]` ist für Nino keine Marke, sondern Text, und die Seite zeigt es so, wie es dasteht. Deshalb bekommt das Datum den Namen `date`. Tutorial 3 zeigt, wie eine Komponente ihren Wert aus einer Quelle holt.

Der Shortcode soll das können:

```html
[meetup-date date="2026-12-24" time="19:30" label="Christmas party" class="highlight"]
[meetup-date date="2027-01-09" long]
```

### 2. Die Methode

Ersetze in `features/Meetups/Meetups.php` die Methode `doShortcode()` samt ihrem Kommentar:

```php features/Meetups/Meetups.php
		// [meetup-date date="2026-12-24" time="19:30" label="Christmas party" long class="highlight"]
		public static function doShortcode( array &$appData, array $args ): string {

			$date = (string) ( $args['date'] ?? '' );
			$time = (string) ( $args['time'] ?? '' );

			// A date that is none draws nothing
			if( preg_match( '/^\d{4}-\d{2}-\d{2}$/D', $date ) !== 1 )
				return '';

			// A time that is none is left out
			if( preg_match( '/^\d{2}:\d{2}$/D', $time ) !== 1 )
				$time = '';

			// The date as the language of the page writes it
			if( $time !== '' )
				$text = \Nino\Format::datetime( $appData, $date. ' '. $time );
			else if( \Nino\Feature::flag( $args, 'long' ) === true )
				$text = \Nino\Format::dateLong( $appData, $date );
			else
				$text = \Nino\Format::date( $appData, $date );

			// A date the calendar does not have, such as 2026-02-30
			if( $text === null )
				return '';

			$label = trim( (string) ( $args['label'] ?? '' ) );

			return str_replace(
				[ '[[classes]]', '[[iso]]', '[[label]]', '[[text]]' ],
				[
					\Nino\Feature::classes( $args ),
					$time === '' ? $date : $date. 'T'. $time,
					$label === '' ? '' : \Nino\Components::escape( $label ). ': ',
					\Nino\Components::escape( $text ),
				],
				\Nino\Feature::template( $appData, self::TEMPLATES, 'meetup-date' )
			);
		}
```

Was hier neu ist:

- **Prüfen statt vertrauen.** Datum und Uhrzeit müssen ein festes Muster haben. Alles andere wird verworfen.
- **Die Sprache der Seite.** `\Nino\Format::date()`, `dateLong()` und `datetime()` schreiben ein Datum so, wie die Sprache der Seite es schreibt. Für ein Datum, das es nicht gibt, antworten sie `null`.
- **Der Schalter.** `\Nino\Feature::flag()` liest ein bloßes Wort wie `long`. Es versteht auch `long="yes"` und `long="no"`.
- **Die Klasse.** Jeder Shortcode darf `class="…"` tragen. `\Nino\Feature::classes()` gibt die Klassen maskiert zurück, mit einem Leerzeichen davor.
- **Maskieren.** `label` ist freier Text. `\Nino\Components::escape()` maskiert ihn für HTML: `<`, `>`, `&` und die Anführungszeichen, dazu entfernt es Steuerzeichen. Nino liest die Ausgabe eines Shortcodes nicht noch einmal: Was Du zurückgibst, ist fertig, und ein `[` darin öffnet nichts. Anders ist es dort, wo ein Wert in eine Vorlage kommt, die ein Shortcode selbst zeichnet (Tutorial 6): Dort wird mitgelesen, was Du einsetzt, und ein `[` schreibst Du als `&#91;`. `\Nino\Feature::classes()` tut das für die Klassen.

### 3. Die Vorlage

Die Vorlage bekommt zwei neue Platzhalter. Ersetze den Inhalt von `features/Meetups/templates/meetup-date.tpl`:

```html features/Meetups/templates/meetup-date.tpl
<span class="meetups-date[[classes]]">[[label]]<time datetime="[[iso]]">[[text]]</time></span>
```

### 4. Ausprobieren

Ersetze in `private/templates/page-home.tpl` die Zeile aus Tutorial 1 durch diese Zeilen:

```html
<p>Next meeting: [meetup-date date="2026-12-24"]</p>
<p>[meetup-date date="2026-12-24" time="19:30" label="Christmas party" class="highlight"]</p>
<p>[meetup-date date="2027-01-09" long]</p>
<p>[meetup-date date="2027-02-30" label="No such day"]</p>
<p>[meetup-date date="2027-03-01" label="<b>Bold</b>"]</p>
```

## Prüfen

Der Quelltext der Startseite zeigt auf einer deutschen Seite:

```html
<p>Next meeting: <span class="meetups-date"><time datetime="2026-12-24">24.12.2026</time></span></p>
<p><span class="meetups-date highlight">Christmas party: <time datetime="2026-12-24T19:30">24.12.2026, 19:30</time></span></p>
<p><span class="meetups-date"><time datetime="2027-01-09">9. Januar 2027</time></span></p>
<p></p>
<p><span class="meetups-date">&lt;b&gt;Bold&lt;/b&gt;: <time datetime="2027-03-01">01.03.2027</time></span></p>
```

Wechsle die Sprache: Öffne `/?/_nino/localepicker/current=en_US` oder nimm die Sprachwahl im Fuß der Seite. Auf der englischen Seite steht `12/24/2026, 7:30 PM` und `January 9, 2027`. Der 30. Februar gibt nichts aus. Das `<b>` erscheint als Text, nicht als fette Schrift.

## Häufige Fehler

- **Einen Wert unmaskiert ausgeben.** Auch ein Attribut aus einer Vorlage ist fremd: Texte lassen sich in der Workbench ändern, und ein Attribut kann einen Textfill enthalten. Maskiere alles, was Du nicht selbst erzeugt oder streng geprüft hast, mit `\Nino\Components::escape()` und nicht mit `htmlspecialchars()` allein: Ohne `ENT_SUBSTITUTE` löscht ein einziges Byte, das kein UTF-8 ist, den ganzen Wert.
- **Eine Marke, die Nino nicht liest.** Sie steht dann als Text auf der Seite oder fehlt ganz. Eine Marke über zwei Zeilen ist Text, ebenso ein Wort, das weder Quelle noch Name eines Attributs ist (`[meetup-date 2026-12-24]`), und ein Wert in Anführungszeichen, die nie schließen. Ein Wert ohne Anführungszeichen (`time=19:30`) oder in einfachen (`time='19:30'`) fällt weg, und der Shortcode läuft ohne ihn. Ein Name, den niemand anmeldet, zeichnet nichts.
- **Beide Formen eines Namens mischen.** Steht `[meetup-date …]` in einer Datei einmal mit `[/meetup-date]` und einmal ohne, hält Nino das erste für einen Behälter und verschluckt den Rest. Das Blatt schreibst Du dann `[meetup-date … /]`, mit einem Leerzeichen vor dem `/`.
- **Markup in PHP bauen.** `'<span>'. $text. '</span>'` in der Methode ist nicht erlaubt. Das Markup gehört in die Vorlage.

## Nachschlagen

- [HTML+ - the notation](../development.md#html---the-notation): welche Marke Nino liest und was Text bleibt
- [Shortcodes](../development.md#shortcodes): Argumente und Inhalt
- [Drawing a page](../development.md#drawing-a-page): was ein Shortcode zurückgibt, wie er eigenen Quelltext zeichnet
- [Shortcode contract](../recipes/runtime-module.md#shortcode-contract): die Regeln für einen Shortcode
- [Rendering and escaping rules](../../AGENTS.md#rendering-and-escaping-rules): wo was maskiert wird
- [Formatting a value](../development.md#formatting-a-value): `\Nino\Format`
