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

<section class="nino-section">
	<div class="nino-grid-row">
		<div class="nino-grid-100">

			<h1 class="nino-section-title">[[/template/page-meetups/intro/title]]</h1>
			<p class="nino-section-text">[[/template/page-meetups/intro/text]]</p>

			<p>[meetup-date text="2026-12-24" time="19:30"]</p>

		</div>
	</div>
</section>

[template /templates/html-footer]
```

- Die erste Zeile gibt der Vorlage ihren Namen in der Workbench.
- `html-header` und `html-footer` sind die Rahmen jeder Seite des Projekts.
- Die Worte stehen als Textfills da, nicht als Text.

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
];
```

Liefere in jeder Sprache dieselben Schlüssel. Hat das Projekt eine Sprache, für die Du keine Datei hast, legt Nino die Schlüssel dort leer an. Die Seite zeigt dann den Text der Hauptsprache.

### 4. Die Version erhöhen und aktualisieren

Ändere in `features/Meetups/feature.php` die Version:

```php features/Meetups/feature.php
	'version'     => '0.2.0',
```

Öffne den Bereich **Features** und klicke auf „Treffen“. Dort steht jetzt „Version 0.2.0 – installiert als 0.1.0“. Klicke **Auf 0.2.0 aktualisieren**. Nino wendet dabei die Einheit an.

### 5. Der Seite einen Namen geben

Der Name und der Titel einer Seite sind Texte des Systems. Eine Einheit kann sie nicht liefern. Du trägst sie in der Workbench ein:

Öffne den Bereich **Routen** und klicke auf `/meetups`. Trage unter **Inhalt** für jede Sprache Name und Titel ein: für Deutsch beide Male „Treffen“, für Englisch beide Male „Meetups“. Der Name steht im Menü, der Titel im Reiter des Browsers. Wähle „In der Navigation „main“ zeigen“, wenn die Seite im Menü stehen soll. Klicke **Speichern**.

## Prüfen

Öffne `/meetups`. Die Seite zeigt die Überschrift „Treffen“, den Satz „Hier trifft sich unser Verein.“ und das Datum. Auf Englisch steht dort „Meetups“ und „Here is when our club meets.“.

In `private/` findest Du jetzt:

- `private/templates/page-meetups.tpl`
- in `private/text/de_DE.php` und `en_US.php` die zwei Schlüssel
- in `private/config.php` die Route `GET://meetups`

Prüfe „nur hinzufügen“: Ändere im Bereich **Texte** die Überschrift in „Unsere Treffen“. Lösche die Datei `private/templates/page-meetups.tpl`. Schalte das Feature aus (**Deaktivieren**) und wieder ein (**Aktivieren**). Die Überschrift heißt weiter „Unsere Treffen“. Die gelöschte Vorlage ist wieder da.

## Häufige Fehler

- **Die Einheit ändern und auf das Update warten.** Ein Update ersetzt nichts, was das Projekt schon hat. Änderst Du später `install/templates/page-meetups.tpl`, bleibt die Kopie in `private/templates/` alt. Lösche beim Entwickeln die Kopie, oder ändere sie von Hand mit.
- **Die Version nicht erhöhen.** Ohne neue Version bietet die Workbench kein Update an. Dann wendest Du die Einheit an, indem Du das Feature aus- und wieder einschaltest.
- **Im Titel des Browsers steht `[[/_nino/webpage/meetups/title]]`.** Der Seite fehlen Name und Titel. Trage sie im Bereich **Routen** ein (Schritt 5).
- **Ein Schlüssel mit drei oder fünf Teilen.** Ein Textschlüssel hat genau vier Teile aus Kleinbuchstaben, Ziffern und Bindestrichen. Eine Einheit liefert nie einen Schlüssel unter `/project/`.

## Nachschlagen

- [The install unit](../features.md#the-install-unit): was eine Einheit darf
- [Add an installer module package](../recipes/installer-package.md): alle Schlüssel des Manifests der Einheit
- [The key grammar](../development.md#the-key-grammar): die Regeln für Textschlüssel
- [Textfills](../development.md#textfills): wie Nino eine fehlende Sprache ersetzt
- [Routing and Responses](../development.md#routing-and-responses): Routen und Sprachen
