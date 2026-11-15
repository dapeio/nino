# 1. Ein Feature anlegen

[Übersicht](README.md) · weiter: [2. Einen Shortcode erstellen](02-shortcode.md)

**Ziel:** Du legst das Feature „Meetups“ an, schaltest es ein und siehst seine erste Ausgabe auf der Startseite.

Das Beispiel sind die Treffen eines Vereins: eine Liste der Treffen, zu denen sich Besucher anmelden. Alle dreizehn Tutorials bauen an diesem einen Feature. Es heißt „Meetups“.

## Schritte

### 1. Das Verzeichnis

Lege `features/Meetups/` an. Der Name des Verzeichnisses ist der Name der Klasse. Er beginnt mit einem Großbuchstaben und enthält nur Buchstaben und Ziffern.

Am Ende dieses Tutorials sieht das Feature so aus:

```text
features/Meetups/
├── feature.php               das Manifest
├── Meetups.php               die Klasse \Nino\Modules\Meetups
└── templates/
    └── meetup-date.tpl       das Markup
```

### 2. Das Manifest

Das Manifest sagt Nino, was das Feature ist. Lege `features/Meetups/feature.php` an:

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
];
```

- `key` ist der Schlüssel des Features: Kleinbuchstaben, Ziffern, Bindestriche.
- `name` und `description` zeigt der Bereich **Features**. Sie stehen im Manifest, weil Nino sie auch für ein ausgeschaltetes Feature braucht.
- `version` hat drei Zahlen: `major.minor.patch`.
- `nino` nennt den Kernel, für den Du schreibst. `^1.12` heißt: ab 1.12, unter 2.0.

### 3. Die Klasse

Lege `features/Meetups/Meetups.php` an:

```php features/Meetups/Meetups.php
<?php
declare(strict_types=1);

namespace Nino\Modules {

	class Meetups {

		public const string TEMPLATES = '/features/Meetups/templates';

		// Called at every start of a request while the feature is on
		public static function init( array &$appData ): void {
			\Nino\Feature::shortcode( $appData, 'meetup-date', [ self::class, 'doShortcode' ] );
		}

		// [meetup-date date="2026-12-24"]
		public static function doShortcode( array &$appData, array $args ): string {

			$date = (string) ( $args['date'] ?? '' );

			// Only a date of this exact shape is written, so nothing needs escaping
			if( preg_match( '/^\d{4}-\d{2}-\d{2}$/D', $date ) !== 1 )
				return '';

			return str_replace(
				[ '[[iso]]', '[[text]]' ],
				[ $date, $date ],
				\Nino\Feature::template( $appData, self::TEMPLATES, 'meetup-date' )
			);
		}
	}
}
```

- Nino ruft `init()` bei jeder Anfrage auf, solange das Feature eingeschaltet ist. `init()` meldet nur Shortcodes, Routen und Callbacks an. Es gibt nichts aus.
- `\Nino\Feature::shortcode()` meldet den Shortcode `[meetup-date]` an.
- `doShortcode()` bekommt die Argumente des Shortcodes. `$args['date']` ist das Argument mit dem Namen `date`.
- `\Nino\Feature::template()` liest die Vorlage des Features. PHP ersetzt darin die Platzhalter `[[iso]]` und `[[text]]`.

### 4. Die Vorlage

Das Markup steht nicht in PHP. Lege `features/Meetups/templates/meetup-date.tpl` an:

```html features/Meetups/templates/meetup-date.tpl
<time class="meetups-date" datetime="[[iso]]">[[text]]</time>
```

### 5. Einschalten

Öffne `/_admin` und dort den Bereich **Features**. Wähle den Reiter **Inaktiv**. Dort steht „Treffen“, der deutsche Name aus dem Manifest. Klicke **Aktivieren**.

Nino trägt die Klasse jetzt in `private/config.php` unter `/nino/modules` ein und merkt sich die Version unter `/nino/features`.

### 6. Den Shortcode benutzen

Öffne `private/templates/page-home.tpl`. Das ist die Vorlage der Startseite. Schreibe diese Zeile vor die letzte Zeile `[template /templates/html-footer]`:

```html
<p>Next meeting: [meetup-date date="2026-12-24"]</p>
```

## Prüfen

Öffne die Startseite und sieh in den Quelltext. Dort steht:

```html
<p>Next meeting: <time class="meetups-date" datetime="2026-12-24">2026-12-24</time></p>
```

Schreibe zur Probe `[meetup-date date="morgen"]`. Die Ausgabe ist leer, denn `morgen` ist kein Datum.

## Häufige Fehler

- **Das Feature steht nicht im Bereich Features.** Das Verzeichnis heißt `meetups` statt `Meetups`, oder die Datei `Meetups.php` fehlt. Nino überspringt das Manifest dann ganz. Den Grund schreibt Nino als Warnung in `private/data/logs.<Jahr>-<Monat>.php`, siehe [Beim Entwickeln](README.md#beim-entwickeln).
- **Der Name ist vergeben.** Ein Feature darf nicht heißen wie ein Modul von Nino, zum Beispiel `Elements` oder `Users`.
- **Die Zeile zeigt kein Datum.** Das Feature ist nicht eingeschaltet. Ein Name, den kein Feature anmeldet, zeichnet nichts, und auch nichts von dem, was zwischen seinen Marken steht. Das Log nennt den Namen einmal je Anfrage.
- **`init()` gibt etwas aus.** Ein `echo` in `init()` zerstört die Antwort. `init()` meldet nur Shortcodes, Routen und Callbacks an.

## Nachschlagen

- [What a feature is](../features.md#what-a-feature-is) und [The manifest](../features.md#the-manifest): alle Felder des Manifests
- [The class](../features.md#the-class): die Methoden, die Nino aufruft
- [The toolset](../features.md#the-toolset): die Helfer von `\Nino\Feature`
- [Features in der Workbench](../_admin.md#features): Einschalten, Ausschalten, Entfernen
