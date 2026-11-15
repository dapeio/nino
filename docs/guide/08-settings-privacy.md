# 8. Einstellung, Backup und Datenschutz

[Übersicht](README.md) · zurück: [7. Eine Route, die Anmeldungen speichert und drosselt](07-route.md) · weiter: [9. Einen Bereich in der Workbench anlegen](09-panel.md)

**Ziel:** Die Grenze der Drossel wird eine Einstellung in der Workbench. Das Backup sichert die Anmeldungen. Die Datenschutzerklärung bekommt einen Abschnitt für das Feature.

Alle drei gehören zu einem Feature, das Daten von Besuchern speichert.

## Schritte

### 1. Eine Einstellung und die Datei für das Backup

Füge in `features/Meetups/feature.php` nach der Zeile `'nino' => …` ein:

```php features/Meetups/feature.php
	// The file the feature writes. A backup carries what is named here
	'data'        => [ '/data/meetups-signups.php' ],
	// A form control on the feature's screen in the Features panel
	'settings'    => [
		'perHour' => [ 'type' => 'int', 'label' => [ 'en_US' => 'Sign-up tries per visitor', 'de_DE' => 'Anmeldeversuche je Besucher' ], 'min' => 1, 'max' => 100, 'unit' => 'per-hour', 'default' => 10 ],
	],
```

- `data` nennt die Dateien unter `/data/`, die dem Feature gehören. Das Backup der Workbench sichert die hier genannten Dateien.
- `settings` beschreibt Einstellungen. Nino zeichnet das Formular dafür selbst und prüft die Werte. `type`, `min`, `max` und `default` legen fest, was gültig ist.

Setze die Version auf `0.5.0`.

### 2. Die Einstellung lesen

Ersetze in `callbackSignup()` in `features/Meetups/Meetups.php` den Block der Drossel:

```php features/Meetups/Meetups.php
			// Every try counts, also a wrong one
			$limit = (int) \Nino\Features::setting( $appData, 'meetups', 'perHour', 10 );

			if( \Nino\Feature::count( $appData, 'meetups-signup-tries', \Nino\Http::getClientIp( $appData ), $limit, 3600 ) === false ) {
				self::_refuse( $appData, $request, 429, 'busy' );
				return;
			}
```

Lösche die Konstante `TRIES_PER_HOUR` samt ihrem Kommentar und der Leerzeile davor. Sie wird nicht mehr gebraucht.

`\Nino\Features::setting()` bekommt den Schlüssel des Features, den Namen der Einstellung und einen Ersatzwert. Lies eine Einstellung immer so, nie direkt aus `$appData`.

### 3. Der Abschnitt für die Datenschutzerklärung

Ein Feature, das Daten von Personen verarbeitet, muss seinen Abschnitt der Datenschutzerklärung mitbringen. Die Datenschutzerklärung von Nino besteht aus Elementen des Typs `privacy`. Lege `features/Meetups/install/elements/privacy.php` an:

```php features/Meetups/install/elements/privacy.php
<?php
// One section for the privacy policy, added to the type "privacy" of the Legal module.
// It is only added: a section the editors changed or deleted stays as it is
return [
	'*'     => [ 'meetups' => [ 'order' => 850 ] ],
	'en_US' => [ 'meetups' => [
		'title' => 'Signing up for a meetup',
		'text'  => '<p>When you sign up for a meetup, we store the name you enter, the meetup and the time of the sign-up. We use them to plan the meetup. Questions: #/project/company/contact/email#</p>',
	] ],
	'de_DE' => [ 'meetups' => [
		'title' => 'Anmeldung zu einem Treffen',
		'text'  => '<p>Wenn Du Dich zu einem Treffen anmeldest, speichern wir den Namen, den Du eingibst, das Treffen und den Zeitpunkt der Anmeldung. Wir nutzen sie, um das Treffen zu planen. Fragen: #/project/company/contact/email#</p>',
	] ],
];
```

- `order` ist die Position in der Erklärung. Für Features ist der Bereich 800 bis 899 gedacht.
- `#/project/company/contact/email#` ist ein Platzhalter. Nino ersetzt ihn durch die E-Mail-Adresse des Projekts.
- Schreibe nur, was der Code wirklich tut. Der Text ist ein Anfang und keine Rechtsberatung.

Nimm die Datei in die Einheit auf. Ersetze `features/Meetups/install/manifest.php`:

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
	// The feature stores names, so it adds its section to the privacy policy
	'elements'     => [ 'privacy' => 'elements/privacy.php' ],
];
```

### 4. Aktualisieren

Klicke im Bereich **Features** bei „Treffen“ auf **Auf 0.5.0 aktualisieren**.

## Prüfen

- Öffne im Bereich **Features** das Feature „Treffen“. Der Reiter **Einstellungen** zeigt das Feld „Anmeldeversuche je Besucher (pro Stunde)“ mit dem Wert 10. Trage 3 ein und speichere. Der Wert steht jetzt in `private/config.php` unter `/nino/features`. Lösche dann `private/data/meetups-signup-tries.php`, damit der Zähler aus Tutorial 7 leer ist. Sende viermal eine Anmeldung ohne Namen, mit den `curl`-Zeilen aus Tutorial 7. Der vierte Versuch bekommt den Status `429`. Stelle den Wert danach wieder auf 10 und lösche die Datei noch einmal.
- Öffne die Datenschutzerklärung der Seite, `/datenschutz` oder `/privacy`. Dort steht der Abschnitt „Anmeldung zu einem Treffen“ mit der E-Mail-Adresse des Projekts.

## Häufige Fehler

- **Eine Einstellung ohne gültige Vorgabe.** `default` muss selbst zu `type`, `min` und `max` passen. Sonst lehnt Nino das Manifest ab.
- **Den Abschnitt nachträglich ändern wollen.** Auch Elemente fügt Nino nur hinzu. Einen Abschnitt, den die Redaktion geändert oder gelöscht hat, bringt kein Update zurück.
- **Die Datei des Zählers unter `data` nennen.** Der Zähler der Drossel gehört nicht ins Backup. Nenne nur die Dateien mit Inhalt.
- **Ein Geheimnis als `string` speichern.** Für Passwörter und Schlüssel gibt es den Typ `secret`.

## Nachschlagen

- [Settings](../features.md#settings): alle Typen und Regeln
- [Data and restore](../features.md#data-and-restore): Backup und Wiederherstellung eigener Dateien
- [The install unit](../features.md#the-install-unit): der Abschnitt der Datenschutzerklärung und die Bereiche von `order`
- [Legal](../development.md#legal): Platzhalter und Elemente der rechtlichen Texte
