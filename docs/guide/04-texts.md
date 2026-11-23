# 4. Eine Seite mit Texten in zwei Sprachen

[Übersicht](README.md) · zurück: [3. Eine Komponente erstellen](03-component.md) · weiter: [5. Daten halten und in einer Schleife ausgeben](05-data.md)

**Ziel:** Das Feature bringt eine eigene Seite `/meetups` mit. Ihre Worte liegen als Textschlüssel im Projekt, auf Deutsch und Englisch, und die Redaktion kann sie ändern.

## Die Installationseinheit

Ein Feature liegt in `features/`. Vorlagen und Texte einer Seite gehören aber dem Projekt, denn das Projekt will sie ändern. Die **Installationseinheit** bringt sie dorthin. Das ist das Verzeichnis `install/` im Feature.

Nino wendet die Einheit bei jedem Aktivieren und bei jedem Update an. Dabei gilt immer: **nur hinzufügen** (englisch „add-only“). Eine Datei, eine Route oder ein Text, den das Projekt schon hat, bleibt unverändert.

## Schritte

### 1. Das Manifest der Einheit

Lege `features/Meetups/install/manifest.php` an:

```php features/Meetups/install/manifest.php
<?php
// What the project gets when the feature is switched on or updated.
// Nothing here replaces a file, a route or a word the project has
return [
	'routes'    => [
		'GET://meetups' => [ 'uri' => '/meetups', 'body' => '[template /templates/page-meetups]' ],
	],
	'templates' => [ 'page-meetups.tpl' ],
];
```

- `routes` trägt die Route in `config.php` ein. `uri` ist der feste innere Name der Seite, `body` ihre Vorlage.
- `templates` kopiert die genannten Dateien aus `install/templates/` nach `private/templates/`.

### 2. Die Vorlage der Seite

Lege `features/Meetups/install/templates/page-meetups.tpl` an:

```html features/Meetups/install/templates/page-meetups.tpl
<!-- nino:template-name Meetups -->
[template /templates/html-header]

[section-wrap id="intro"]
	[section-row]
		[section-col]
			[section-title ~title tag="h1"]
			[text ~text]
			[meetup-date text="2026-12-24" time="19:30"]
		[/section-col]
	[/section-row]
[/section-wrap]

[template /templates/html-footer]
```

- Die erste Zeile gibt der Vorlage ihren Namen in der Workbench.
- `html-header` und `html-footer` sind die Rahmen jeder Seite des Projekts.
- `[section-wrap]`, `[section-row]`, `[section-col]`, `[section-title]` und `[text]` sind Komponenten des Features **Basis**: ein Abschnitt, eine Zeile, eine Spalte, eine Überschrift und ein Text. Basis liegt im Katalog, nicht im Kernel: Es muss im Panel Features installiert und eingeschaltet werden - der Einrichtungsassistent schreibt eine schlichte HTML-Seite und schaltet kein Feature ein ([The Look](../setup.md#the-look)). Fehlt es oder ist es aus, sind das Namen, die niemand anmeldet: Sie zeichnen nichts.
- Die Worte stehen nicht in der Vorlage. `~title` ist ein Verweis auf einen Textschlüssel: Nino bildet ihn aus dem Namen der Vorlage, der `id` des Abschnitts und dem Namen, hier `/template/page-meetups/intro/title`. Der nächste Schritt legt die Worte an.

### 3. Die Worte

Ein Textschlüssel hat vier Teile. Die Worte einer Vorlage liegen unter `/template/<Dateiname ohne .tpl>/<teil>/<name>`. Die Vorlage `page-meetups.tpl` liest also `/template/page-meetups/…`.

Lege `features/Meetups/install/text/en_US.php` an:

```php features/Meetups/install/text/en_US.php
<?php
declare(strict_types=1);

// The words the site says, merged into the project's text/en_US.php.
// A key the project has stays as it is
return [
	'[[/template/page-meetups/intro/title]]' => 'Meetups',
	'[[/template/page-meetups/intro/text]]'  => 'Here is when our club meets.',

	// What the page is called in the menu, in the title of the browser and in its description
	'[[/_nino/webpage/meetups/name]]'        => 'Meetups',
	'[[/_nino/webpage/meetups/title]]'       => 'Meetups of the club',
	'[[/_nino/webpage/meetups/description]]' => 'When the club meets.',
];
```

Und `features/Meetups/install/text/de_DE.php`:

```php features/Meetups/install/text/de_DE.php
<?php
declare(strict_types=1);

// The same keys in German, merged into the project's text/de_DE.php
return [
	'[[/template/page-meetups/intro/title]]' => 'Treffen',
	'[[/template/page-meetups/intro/text]]'  => 'Hier trifft sich unser Verein.',

	'[[/_nino/webpage/meetups/name]]'        => 'Treffen',
	'[[/_nino/webpage/meetups/title]]'       => 'Treffen des Vereins',
	'[[/_nino/webpage/meetups/description]]' => 'Wann sich der Verein trifft.',
];
```

Die drei Schlüssel unter `/_nino/webpage/meetups/` sind die Ausnahme von der Regel mit den vier Teilen. Sie gehören dem System: Es liest Name, Titel und Beschreibung einer Seite unter der `uri` ihrer Route. Der Name steht im Menü, der Titel im Reiter des Browsers, die Beschreibung im `<meta>` der Seite. Fehlen sie, steht im Reiter der rohe Schlüssel. Hat das Projekt unter `GET://meetups` schon eine Route mit einer anderen `uri`, ist das eine andere Seite. Dann sind die Schlüssel falsch benannt. Trage Name und Titel in diesem Fall im Bereich **Routen** ein.

Liefere in jeder Sprache dieselben Schlüssel. Hat das Projekt eine Sprache, für die Du keine Datei hast, legt Nino die Schlüssel dort leer an. Die Seite zeigt dann den Text der Hauptsprache.

### 4. Die Version erhöhen und aktualisieren

Ändere in `features/Meetups/feature.php` die Version:

```php features/Meetups/feature.php
	'version'     => '0.2.0',
```

Öffne den Bereich **Features** und klicke auf „Treffen“. Dort steht jetzt „Version 0.2.0 – installiert als 0.1.0“. Klicke **Auf 0.2.0 aktualisieren**. Nino wendet dabei die Einheit an.

## Prüfen

Öffne `/meetups`. Die Seite zeigt die Überschrift „Treffen“, den Satz „Hier trifft sich unser Verein.“ und das Datum. Im Reiter des Browsers steht „Treffen des Vereins“. Auf Englisch steht dort „Meetups“, „Here is when our club meets.“ und „Meetups of the club“. Zeigt die Seite nur den Rahmen, ist das Feature **Basis** aus: Schalte es im Bereich **Features** ein.

Der Quelltext der deutschen Seite zeigt:

```html
<section id="intro" class="section-wrap">
	<div class="section-row">
		<div class="section-col">
			<h1 class="section-title">Treffen</h1>
			<div class="text"><p>Hier trifft sich unser Verein.</p></div>
			<span class="meetup-date"><time datetime="2026-12-24T19:30">24.12.2026, 19:30</time></span>
		</div>
	</div>
</section>
```

Die Seite steht noch in keinem Menü. Welche Seite in welches Menü kommt, entscheidet das Projekt: Öffne den Bereich **Routen**, klicke auf `/meetups` und hake bei „In Navigationen zeigen“ das Menü „main“ an. Der Eintrag heißt dann „Treffen“.

In `private/` findest Du jetzt:

- `private/templates/page-meetups.tpl`
- in `private/text/de_DE.php` und `en_US.php` die zwei Schlüssel der Vorlage und die drei Schlüssel der Seite, `/_nino/webpage/meetups/name`, `title` und `description`
- in `private/config.php` die Route `GET://meetups`

Prüfe „nur hinzufügen“: Ändere im Bereich **Texte** die Überschrift in „Unsere Treffen“. Lösche die Datei `private/templates/page-meetups.tpl`. Öffne im Bereich **Features** den Reiter **Aktiv**, klicke auf „Treffen“ und dann auf **Inhalte neu anwenden**. Die Meldung nennt, was Nino angelegt hat und was schon da war: die Vorlage ist neu angelegt, die Texte sind schon da. Die Überschrift heißt weiter „Unsere Treffen“. Die gelöschte Vorlage ist wieder da.

## Häufige Fehler

- **Die Einheit ändern und auf das Update warten.** Ein Update ersetzt nichts, was das Projekt schon hat. Änderst Du später `install/templates/page-meetups.tpl`, bleibt die Kopie in `private/templates/` alt. Lösche beim Entwickeln die Kopie in `private/templates/` und klicke im Feature auf **Inhalte neu anwenden**, oder ändere sie von Hand mit.
- **Die Version nicht erhöhen.** Ohne neue Version bietet die Workbench kein Update an. Dann wendest Du die Einheit an, indem Du im Bereich **Features** das Feature öffnest und auf **Inhalte neu anwenden** klickst. Auch das fügt nur hinzu: Eine Datei oder ein Text, den das Projekt schon hat, bleibt unverändert. Elemente eines Typs, den das Projekt hat, kommen nicht zurück.
- **Im Titel des Browsers steht `[[/_nino/webpage/meetups/title]]`.** Der Seite fehlen Name und Titel. Die Schlüssel `/_nino/webpage/<uri>/…` heißen anders als die `uri` der Route, oder die Textdateien der Einheit haben sie nicht. Trage Name und Titel im Bereich **Routen** ein.
- **Ein Schlüssel mit drei oder fünf Teilen.** Ein Textschlüssel hat genau vier Teile aus Kleinbuchstaben, Ziffern und Bindestrichen. Eine Einheit liefert nie einen Schlüssel unter `/project/`.

## Nachschlagen

- [The install unit](../features.md#the-install-unit): was eine Einheit darf
- [Add an installer module package](../recipes/installer-package.md): alle Schlüssel des Manifests der Einheit
- [The key grammar](../development.md#the-key-grammar): die Regeln für Textschlüssel
- [Textfills](../development.md#textfills): wie Nino eine fehlende Sprache ersetzt
- [Routing and Responses](../development.md#routing-and-responses): Routen und Sprachen
