# 5. Daten halten und in einer Schleife ausgeben

[Übersicht](README.md) · zurück: [4. Eine Seite mit Texten in zwei Sprachen](04-texts.md) · weiter: [6. Ein Formular für Besucher](06-form.md)

**Ziel:** Die Treffen werden Elemente, die die Redaktion in der Workbench pflegt. Die Seite `/meetups` listet alle Treffen ab heute auf.

## Elemente oder eigene Datei?

Nino kennt zwei Orte für Daten:

- **Elemente** sind für Inhalte, die Menschen pflegen. Die Workbench bringt das Formular dafür mit. Du schreibst keinen Code für die Verwaltung.
- **Eine Datei unter `private/data/`** ist für Daten, die das System selbst schreibt. Das kommt in [Tutorial 7](07-route.md).

Treffen pflegt die Redaktion. Sie werden Elemente.

## Schritte

### 1. Der Elementtyp

Ein **Elementtyp** ist eine Datei, die die Felder festlegt. Lege `features/Meetups/install/meetups.php` an:

```php features/Meetups/install/meetups.php
<?php
declare(strict_types=1);

// The element type "meetups", copied to the project's elements/meetups.php
// where it has none. It holds no element: the editors add the dates
return [
	'title' => 'Meetups',
	'model' => [
		'title' => [ 'type' => 'string', 'locale' => true, 'required' => true, 'maxlength' => 120 ],
		'date'  => [ 'type' => 'date', 'required' => true ],
		'time'  => [ 'type' => 'string', 'maxlength' => 5, 'label' => [ 'en_US' => 'Time (HH:MM)', 'de_DE' => 'Uhrzeit (HH:MM)' ] ],
		'place' => [ 'type' => 'string', 'maxlength' => 120, 'label' => [ 'en_US' => 'Place', 'de_DE' => 'Ort' ] ],
	],
	'*' => [
		'*' => [],
	],
];
```

- `model` nennt die Felder. `type` ist der Typ des Felds.
- `'locale' => true` heißt: Das Feld hat je Sprache einen eigenen Wert. Die anderen Felder gelten für alle Sprachen.
- `required` macht ein Feld zur Pflicht.
- Ein `date` speichert Nino als Text der Form `2026-12-24`.
- `label` ist die Beschriftung des Felds im Formular der Workbench, je Sprache. Die Workbench nimmt ihre eigene Sprache. Ohne `label` zeigt das Formular das Wort der Workbench für den Namen (`title` ist „Titel“, `date` „Datum“) und sonst den Namen selbst.

### 2. Den Typ in die Einheit aufnehmen

Ersetze `features/Meetups/install/manifest.php`:

```php features/Meetups/install/manifest.php
<?php
// What the project gets when the feature is switched on or updated.
// Nothing here replaces a file, a route or a word the project has
return [
	'routes'       => [
		'GET://meetups' => [ 'uri' => '/meetups', 'body' => '[template /templates/page-meetups]' ],
	],
	'templates'    => [ 'page-meetups.tpl', 'meetups-none.tpl' ],
	'elementTypes' => [ 'meetups.php' ],
];
```

`elementTypes` kopiert die Datei nach `private/elements/meetups.php`, wenn das Projekt dort noch keine hat.

### 3. Die Schleife in der Vorlage

Der Shortcode `[elements]` wiederholt seinen Inhalt für jedes Element. Ersetze in `features/Meetups/install/templates/page-meetups.tpl` die Zeile `<p>[meetup-date …]</p>` durch:

```html features/Meetups/install/templates/page-meetups.tpl
			<ul class="meetups-list">
			[elements /meetups sort="date" callback="/meetups/upcoming" empty="meetups-none"]
				<li[[.lang]]>[meetup-date text="[[date]]" time="[[time]]"] <strong>[[title]]</strong> [[place]]</li>
			[/elements]
			</ul>
```

- `/meetups` ist der Typ, `sort="date"` sortiert nach dem Feld `date`.
- `[[title]]`, `[[date]]`, `[[time]]` und `[[place]]` sind die Felder des Elements. Nino maskiert sie.
- `[[.lang]]` setzt ein `lang`-Attribut, wenn der Text aus einer anderen Sprache stammt.
- `callback` nennt einen Callback, der die Liste verändern darf. Den schreibst Du im nächsten Schritt.
- `empty` nennt die Vorlage, die auf der Seite steht, wenn die Schleife kein Element findet. Das ist `private/templates/meetups-none.tpl`, gelesen wie ein `[template]`. Ohne das Attribut bleibt eine leere Liste leer. Das Attribut gilt für `[elements]`, nicht für Stapel (Stacks).

Die Vorlage für die leere Liste ist eine Datei mehr in der Einheit. Lege `features/Meetups/install/templates/meetups-none.tpl` an:

```html features/Meetups/install/templates/meetups-none.tpl
<li class="meetups-none">[[/template/meetups-none/list/empty]]</li>
```

Das Wort dazu gehört zu den Texten der Einheit. Füge in `features/Meetups/install/text/en_US.php` eine Zeile hinzu:

```php features/Meetups/install/text/en_US.php
	'[[/template/meetups-none/list/empty]]'  => 'No dates yet.',
```

Und in `features/Meetups/install/text/de_DE.php`:

```php features/Meetups/install/text/de_DE.php
	'[[/template/meetups-none/list/empty]]'  => 'Noch keine Termine.',
```

Das Projekt hat schon eine Kopie dieser Vorlage. Ein Update ersetzt sie nicht. Lösche deshalb `private/templates/page-meetups.tpl`. Das Update bringt die neue Fassung.

### 4. Nur Treffen ab heute

Ein Callback ist eine Funktion, die Nino unter einem Namen aufruft. Füge in `features/Meetups/Meetups.php` in die Klasse ein, zwischen der Konstante `TEMPLATES` und dem Kommentar von `componentMeetupDate()`, mit je einer Leerzeile davor und danach:

```php features/Meetups/Meetups.php
		// Called at every start of a request while the feature is on
		public static function init( array &$appData ): void {
			\Nino\Callbacks::registerCallback( $appData, '/meetups/upcoming', [ self::class, 'callbackUpcoming' ] );
		}

		// What [elements ... callback="/meetups/upcoming"] calls with its list: the dates from today on stay
		public static function callbackUpcoming( array &$appData, array &$elements ): void {

			$today = date( 'Y-m-d' );

			$elements = array_values( array_filter( $elements, static fn( mixed $element ): bool =>
				is_array( $element ) === true && is_string( $element['date'] ?? null ) === true && $element['date'] >= $today
			) );
		}
```

- `registerCallback()` meldet die Methode unter dem Namen `/meetups/upcoming` an. Namen unter `/nino/` gehören Nino. Nimm für eigene Callbacks den Schlüssel des Features.
- Ein Callback bekommt `$appData` und sein Argument per Referenz. Hier ist das Argument die Liste der Elemente.
- Jedes Datum ist ein Text der Form `2026-12-24`. So ein Text lässt sich mit `>=` vergleichen.

### 5. Aktualisieren

Setze in `feature.php` die Version auf `0.3.0`:

```php features/Meetups/feature.php
	'version'     => '0.3.0',
```

Klicke im Bereich **Features** bei „Treffen“ auf **Auf 0.3.0 aktualisieren**.

### 6. Treffen anlegen

Öffne den Bereich **Elemente**. Dort steht der neue Typ „Meetups“. Klicke ihn an und dann **Neues Element**. Lege drei Treffen an:

| Uri | Datum | Uhrzeit | Ort | Titel |
|---|---|---|---|---|
| `new-year` | ein Tag in der Vergangenheit | `11:00` | Clubhouse | New Year reception |
| `christmas` | ein Tag in der Zukunft | `19:30` | Clubhouse | Christmas party |
| `spring-hike` | ein späterer Tag | – | – | Spring hike `<b>!</b>` |

Das Formular beschriftet die Felder mit dem `label` aus dem Modell, bei `date` und `title` mit dem Wort der Workbench. Die Auswahl oben im Formular wählt die Sprache, in der Du den Titel schreibst.

## Prüfen

Öffne `/meetups`. Die Liste zeigt zwei Treffen, das frühere zuerst. Das vergangene Treffen fehlt. Der Quelltext zeigt zum Beispiel:

```html
<li><span class="meetups-date"><time datetime="2026-12-24T19:30">24.12.2026, 19:30</time></span> <strong>Christmas party</strong> Clubhouse</li>
<li><span class="meetups-date"><time datetime="2027-03-06">06.03.2027</time></span> <strong>Spring hike &lt;b&gt;!&lt;/b&gt;</strong> </li>
```

Lösche zur Probe die beiden Treffen in der Zukunft. Die Liste zeigt dann „Noch keine Termine.“, auf Englisch „No dates yet.“. Lege sie danach wieder an.

Das `<b>` im Titel erscheint als Text. Hast Du den Titel nur in einer Sprache geschrieben, zeigt die andere Sprache denselben Titel und setzt `lang="…"` an das `<li>`.

## Häufige Fehler

- **Die Liste ist leer, ohne „Noch keine Termine.“.** Die alte Kopie von `page-meetups.tpl` liegt noch in `private/templates/` und hat das Attribut `empty` nicht. Ohne es bleibt eine leere Liste leer.
- **`[[title]]` außerhalb der Schleife.** Ein Feld ohne `/` am Anfang gilt nur zwischen `[elements]` und `[/elements]`.
- **Ein Feld selbst maskieren.** Nino maskiert die Felder in der Schleife schon. Ein Feld mit HTML braucht `'html' => true` im Modell.
- **Erst lesen, dann schreiben.** Das verliert Daten, wenn zwei Anfragen zugleich kommen. Elemente schreibt Nino für Dich. Für eigene Dateien gibt es `\Nino\Filesystem::mutate()`, siehe Tutorial 7.

## Nachschlagen

- [Define Element types](../recipes/element-types.md): alle Feldtypen und Eigenschaften
- [Elements in Templates](../development.md#elements-in-templates): `query`, `sort`, `limit`, `offset`, `callback`
- [Callbacks](../development.md#callbacks-the-common-extension-mechanism): Namen, Reihenfolge, Rückgabe
- [Persistent Data](../development.md#persistent-data-and-concurrent-write-access): Dateien sicher schreiben
