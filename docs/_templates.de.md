# `/_templates` — Template Builder (Alpha)

**Sprache:** [English](_templates.md) · Deutsch

**Stand:** 22. August 2026 · **Nino-Version:** Unreleased

Der Template Builder ist der schnelle Weg vom `page-*.tpl` zur befüllten Seite. Er behandelt ein Template als geordnete Abfolge vollständiger HTML-Sections und wiederverwendbarer `[template]`-Sections, statt jeden verschachtelten DOM-Knoten zur Bearbeitung anzubieten.

[README](../README.de.md) · [`/_admin`-Bedienung](_admin.de.md) · [`/_templates`-Bedienung](_templates.de.md) · [`/_editor`-Bedienung](_editor.de.md) · [Entwickler-Handbuch](development.de.md)

> **Alpha:** Seitendateien bleiben gewöhnliches HTML+ und sind zur Laufzeit nicht vom Werkzeug abhängig. Preset-Library und Composer-Ablauf können sich noch verändern.

## Aufgabe und Abgrenzung

Nutze `/_templates`, um:

- vorhandene Dateien `templates/page-*.tpl` zu öffnen;
- ein neues `page-*.tpl` mit echtem Dateinamen, Anzeigenamen, Header, Footer und VPA-Standard anzulegen;
- vollständige Sections anhand ihres Aussehens aus einer durchsuchbaren, getaggten visuellen Library einzufügen;
- wiederverwendbare `[template]`-Shortcodes als geordnete Komponenten einzufügen, wenn ein Preset sie erlaubt;
- Header und Footer aus passenden Nicht-Seiten-`.tpl`-Dateien zu wählen oder einen Slot auf **None** zu setzen;
- eine stabile Section-ID zu vergeben;
- den Section-Frame sowie geordnete Komponenten, Style und Data-Bindings jeder vom Preset benannten Area zu konfigurieren;
- alle sichtbaren Content-Bausteine umzusortieren, zu duplizieren oder zu entfernen, während Header und Footer außerhalb des Canvas fix bleiben;
- erzeugte Textfills direkt in der nativen Sprache zu befüllen;
- eine vorhandene Elements-Collection zu wählen oder den empfohlenen Elementtyp des Moduls anzulegen;
- eine einzelne Section bewusst als HTML+ zu bearbeiten, wenn der Composer nicht ausreicht.

Der Template Builder erzeugt keine Routen, bearbeitet nicht den Inhalt eingebundener Header-/Footer-Dateien, übersetzt nicht alle Sprachen und pflegt keine einzelnen Elements-Einträge. Seine isolierten Vorschauen verwenden das aktuelle Projekt-Stylesheet und definierte Beispieldaten, führen aber das JavaScript der fertigen Seite nicht aus. Dafür bleiben `/_admin`, das Frontend und Code zuständig. Der frühere DOM-orientierte Builder wurde durch diesen sectionbasierten Ablauf ersetzt.

## Zugang und Sicherheit

Öffne `https://deine-domain.example/_templates`. Das Werkzeug teilt Passwort, Sperrstatus und Sitzung mit `/_admin`. Seine Kopfleiste trägt die gemeinsame Brücke **Admin / Builder / Theme**; nur vollständig ausgelieferte Werkzeugordner erscheinen, **Builder** kennzeichnet die aktuelle Oberfläche. Das Werkzeug benötigt `_admin/Admin.php`; ohne `/_admin` fehlt auch sein Authentifizierungs-Backend.

Geschrieben werden:

- `templates/page-*.tpl` über **Save template**, **New page template** oder **Delete**; gelöschte Templates lassen sich im Builder nicht wiederherstellen;
- `text/<native-sprache>.php` beim Speichern der nativen Inhalte;
- `elements/<typ>.php`, wenn die automatische Elementtyp-Erstellung bestätigt ist;
- die Projektkonfiguration, wenn eine fehlende Bildplatz-Definition automatisch angelegt wird. Der eigentliche Bild-Upload bleibt in `/_admin`.

Verwende HTTPS, behandle das technische Passwort vertraulich und arbeite mit einem wiederherstellbaren Projektstand. Entferne Entwicklerwerkzeuge aus der Produktivauslieferung, wenn sie dort nicht benötigt werden.

## Hauptablauf

1. Wähle links ein Seitentemplate oder lege es über **New page template** an. Der Dialog fragt den vollständigen Dateinamen, Anzeigenamen, Header, Footer und den VPA-Standard ab.
2. Öffne **Add section**.
3. Durchsuche oder filtere unter **Choose** die große Galerie und wähle ein Preset mit benannten Areas anhand seiner echten Markup-Vorschau. Die Library enthält bewusst nur noch den aktuellen Version-3-Vertrag. Wiederverwendbare `.tpl`-Dateien erscheinen nicht als Pseudo-Sections; ein passendes Preset kann sie als **Template**-Komponente in einer Area anbieten.
4. Wechsle zu **Configure & fill** und vergib eine sprechende ID wie `main-hero` oder `services-overview`. Diese bewusst reduzierte Add-Ansicht enthält Structure, Background, Collection-Auswahl und eine gemeinsame Komponenten-/Datenliste.
5. Ergänze, sortiere oder entferne Komponenten direkt neben ihren ersten Bindings, vergleiche die Live-Vorschau und füge die Section ein. Empfohlene Textschlüssel, ein Elementtyp und Bildplatz-Definitionen können dabei mit angelegt werden.
6. Öffne die Section nach der Prüfung im echten Frontend bei Bedarf über **Edit**. Dort stehen Maße und Abstände sowie die getrennten **Design**- und **Data**-Ansichten jeder Area für die grafische Feinjustierung bereit.
7. Öffne bei Bedarf Bildplätze oder einzelne Elements-Einträge direkt in `/_admin`.
8. Ordne HTML- und Template-Section-Karten und speichere das Seitentemplate.
9. Vervollständige Übersetzungen danach über den vorhandenen JSON-Batch-Ablauf oder `/_admin`.

Die schnelle native Befüllung legt neue Schlüssel in der nativen Projektsprache an und ändert bei sprachabhängigen Schlüsseln nur diese Sprache. Ein bereits globaler Schlüssel bleibt bewusst global. Bestehende Übersetzungs-Buckets werden nie geleert oder überschrieben.

## Seiten- und Section-Einstellungen

**Name**, **Header**, **Footer** und **VPA** stehen gemeinsam in einer beschrifteten Zeile der Template Settings. **Delete** und **Save template** bleiben rechts in der Topbar; **Add section** bleibt auch nach dem Einfügen von Content in der Dokument-Toolbar sichtbar. Die Header-/Footer-Selects zeigen die echten `.tpl`-Dateinamen, listen dem Projekt bekannte Nicht-Seiten-Templates und bieten außerdem **None**. Der ausgewählte Wert wird weiterhin als gewöhnlicher `[template /templates/<name>]`-Shortcode geschrieben; die Controls verhindern nur, dass die Seitenschale mit verschiebbarem Content verwechselt wird. **Delete** entfernt genau die aktuell geladene Revision der Datei nach einer ausdrücklichen Bestätigung; eine Wiederherstellung erfordert Versionsverwaltung oder ein anderes externes Backup.

**VPA** auf Template-Ebene liefert den Standard für Sections mit der Einstellung **Page**. Eine Änderung setzt verwaltete Sections neu zusammen, aktualisiert deren `nino-vpa`-Klasse und bleibt auch in einem noch leeren Template erhalten. **On** oder **Off** an einer einzelnen Section überschreibt den Template-Standard.

Add und Edit zeigen absichtlich unterschiedliche Tiefen derselben Version-3-Metadaten:

| Ansicht | Steuerelemente |
|---|---|
| Add Section | ID, Layout, Background, optionale Hintergrundbild-Einstellungen, Collection-Quelle, Komponentenreihenfolge und erste Data-Bindings |
| Edit Section → Section | ID, Layout, Höhe, Breite, Abstände, Background und optionale Hintergrundbild-Einstellungen |
| Edit Section → Area / Design | visueller Area-Style, Komponentenreihenfolge und Component Styles |
| Edit Section → Area / Data | native Text-/Bild-/Template-Bindings oder eine Elements-Collection mit explizitem Feld-Mapping |

Ein Cover- oder Parallax-Hintergrund bindet genau ein Bild, und die Quelle wird
mitgespeichert: **New image slot** erzeugt `/page-<seite>/<section>/background`
und legt den Platz beim Einfügen an, **Existing image slot** verweist auf einen
vorhandenen, **Fixed value** schreibt die URL direkt in die Section — ein
Projektpfad wie `[[/nino/public]]/images/hero.jpg`, ein gewöhnlicher relativer
Pfad oder eine `https`-URL — und legt gar keinen Bildplatz an. Letzteres ist für
ein Bild gedacht, das das Projekt ohnehin mitbringt und das niemand in `/_admin`
austauschen muss.

Jedes Auswahlfeld mit **Auto** nennt den Wert, auf den Auto gerade auflöst –
`Auto (Dim)`, `Auto (Wide)`, `Auto (100)`. Aufgelöst wird in dieser Reihenfolge:
Layout-Empfehlung, Preset-Empfehlung, sicherer Fallback.

Add blendet Section-Höhe/-Breite/-Margin/-Padding, Area Style und Component Style aus. Die Ansicht soll zuerst eine sinnvolle Section erzeugen, die man im echten Frontend beurteilen kann. Edit behält das vollständige grafische Feinjustierungsmodell; beide Ansichten kompilieren dieselben Metadaten. Das Manifest bestimmt, welche Areas, Komponenten, Styles und Layouts kompatibel sind. HTML+ bleibt der ausdrückliche Weg für freie Quelltextänderungen.

## Quelltext-Sicherheit und HTML+-Escape-Hatch

Beim Laden scannt das Backend oberste `<section>`-Elemente, ohne den umgebenden Quelltext zu serialisieren. Eine bereits vorhandene alleinstehende `[template /templates/<name>]`-Zeile außerhalb einer Section bleibt eine eigene Canvas-Karte; neue wiederverwendbare Includes werden über die Template-Komponente eines Presets gewählt. Markierte Header-/Footer-Shortcodes werden stattdessen zu festen Settings-Slots. Sonstiger Quelltext wird als gesperrtes Raw-Segment ausgeliefert. Beim Speichern gilt:

- jedes Raw-Segment muss bytegenau unverändert sein;
- jedes bearbeitbare HTML-Segment muss genau eine vollständige oberste Section enthalten;
- jedes Template-Segment muss genau einen gültigen alleinstehenden `[template]`-Shortcode enthalten;
- genau je ein Header- und Footer-Marker muss alle Canvas-Bausteine umschließen; für **None** darf der jeweilige Marker bewusst ohne Shortcode stehen;
- doppelte nichtleere Section-IDs werden abgelehnt;
- eine optimistische SHA-256-Revision verhindert das Überschreiben externer Änderungen;
- die fertige Datei wird atomar ersetzt.

Kommentare, verschachtelte Sections sowie sectionähnlicher Text in `script`, `style` oder `textarea` werden nicht zu eigenen Karten.

Die Shell-Slots verwenden inerte Kommentare:

```html
<!-- nino:template-slot header -->
[template /templates/html-header]
```

Neue Seitentemplates enthalten beide Marker. Aus Kompatibilitätsgründen wird ein exakter führender `html-header` und abschließender `html-footer` zunächst im Speicher erkannt; der erste bewusste Speichervorgang ergänzt die Marker. Andere `[template]`-Shortcodes bleiben gewöhnliche Canvas-Bausteine.

Am Dateianfang liegen ebenfalls inerte Template-Metadaten, die nicht im Canvas erscheinen:

```html
<!-- nino:template-name Error 404 -->
<!-- nino:template-vpa off -->
```

Der Name speist Anzeige und Suche in der linken Liste; der zweite Marker persistiert den VPA-Standard. Bestehende Dateien ohne diese Metadaten erhalten einen aus dem Dateinamen abgeleiteten Anzeigenamen und übernehmen, sofern vorhanden, den bisherigen Wert einer verwalteten Section. Beim ersten bewussten Speichern werden beide Marker geschrieben.

Verwaltete Sections tragen einen Kommentar wie:

```html
<!-- nino:section {"preset":"hero-centered","version":1,...} -->
```

Diese Metadaten erlauben das erneute Öffnen der Composer-Einstellungen. Sie sind inertes HTML und erzeugen keine Laufzeitabhängigkeit. Die Wahl von **HTML+** entfernt die Metadaten bewusst beim Übernehmen des eigenen Quelltexts. Die Section gilt danach als codebasiert und kann nicht durch eine spätere Composer- oder Seitenstandard-Änderung überschrieben werden.

## Section-Library

System-Presets liegen in `_templates/library/<preset-key>/`. Der erzeugte HTML+-Quelltext wird in die Seite kopiert; die öffentliche Website liest die Library nicht zur Laufzeit.

Die Library liefert zwei Sorten Preset. Die erste verwaltet ihren Inhalt: alles
Wiederholte liest eine Elements-Collection, jede Textzeile ist ein Textfill.

| Preset | Wofür | Layouts |
|---|---|---|
| Fullscreen image | Hero-Bühne mit per JavaScript gesetzter Bildschirmhöhe | Cover · Parallax |
| Banner — Text over a background image | Ruhiges Vollbild ohne Scroll-Effekt | Text auf dem Bild · Text in einer Karte |
| Content — Flexible section | Titel, Text und Aktion für redaktionelle Inhalte | Einzeln |
| Media / Text — Flexible split | Bild und Text nebeneinander | Bild links · Bild rechts |
| Features — Checklist and image | Häkchenliste neben einem Bild | Bild rechts · Bild links |
| Articles — Responsive grid | Wiederholte Bildkarten | Einzeln (2/3/4 Spalten als Style) |
| Filterable grid — Services or portfolio | Wiederholte Karten hinter einem clientseitigen Kategorie-Filter | Einzeln (2/3/4 Spalten als Style); Filter-Buttons brauchen einen manuellen HTML+-Schritt, siehe unten |
| Process — Numbered steps | Ein Ablauf in Schritten, nummeriert von der Liste selbst | Verbundene Timeline · Gestapelt |
| Pricing — Plan cards | Eine Karte je Paket | Gleichwertig · Mittlere hervorgehoben · Vierspaltig · Vier unter einer breiten Karte · Vier über einer breiten Karte |
| Partners — Logo bar | Ruhige Logo-Reihe | Überschrift darüber · daneben |
| Call to action — Banner | Ein klarer nächster Schritt | Text darüber · Text daneben |
| Insert reusable template | Ein `.tpl`-Include in einer verwalteten Section | Einzeln |

Die zweite Sorte liefert einen fertigen Markup-Block und überlässt den Rest
HTML+ – siehe [Statische Blöcke](#statische-blöcke) weiter unten.

| Preset | Wofür | Layouts |
|---|---|---|
| Table — Static block | Echte Tabelle zwischen Intro und Outro | Schlicht · Gestreift, je mit Demo-Zeilen oder Elements-Schleife |
| List — Static block | Häkchen- oder nummerierte Liste | Häkchen · Nummeriert, je mit Demo-Einträgen oder Elements-Schleife |
| FAQ — Static accordion | Fragen als native `details`, ohne JavaScript | Demo-Fragen · Elements-Schleife |
| Newsletter — Signup form | Das funktionierende Double-Opt-in-Formular | Formular unter dem Intro · Intro daneben |
| Contact — Form | Das Kontaktformular des Projekts | Mittig · Kontaktdaten daneben |

### Statische Blöcke

Alles im Layout-`.tpl`, was kein `[[area:…]]`-Token ist, wird unverändert in die
Section kopiert. Genau das nutzen die statischen Presets: eine **Intro**-Area
mit Titel und Unterzeile, ein fertiger Markup-Block und eine **Outro**-Area, die
leer startet und nichts rendert, bis jemand einen Button oder Hinweis ergänzt.

Der Block selbst ist im Composer nicht editierbar – das ist Absicht. Section
einfügen, dann über **HTML+** öffnen und dort die Zeilen, Fragen oder Felder
ausformen. Sobald eine Quelltextänderung übernommen wird, verliert die Section
ihre Metadaten und gilt als codebasiert; der Builder überschreibt Handarbeit
also nie.

Jede Variante gibt es zweimal:

- **demo** liefert drei Beispielzeilen zum Überschreiben;
- **elements** liefert stattdessen eine von Hand geschriebene Schleife
  `[elements /example-rows limit="10"]`. In HTML+ auf die eigene Collection
  umbiegen – solange die nicht existiert, rendert die Schleife schlicht nichts.

`[[section:id]]` wird auch im statischen Block aufgelöst. Deshalb behält das FAQ
seine `name="faq-<section>"`-Gruppierung und die Formulare behalten eindeutige
Feld-IDs, wenn dasselbe Preset zweimal auf einer Seite steht.

Ein statischer Block kann auch die *verteilten Werte* eines Feldes über eine
Collection schleifen statt der Datensätze selbst – die Button-Reihe, die ein
clientseitiger Kategorie-Filter braucht. `[elementvalues /services
key="category"]…[/elementvalues]` rendert eine Wiederholung je Wert, den das
Feld „category“ trägt (inklusive Nutzungszähler) – das Gegenstück zu
`[elements]`, das Datensätze schleift. **Filterable grid** in der Tabelle
oben ist ein vollständiges Beispiel: ein statischer Filter-Block steht neben
einer gewöhnlichen, im Composer editierbaren Elements-Area, und ein Klick auf
einen Button blendet passende Karten ein oder aus, ohne Seiten-Reload.

Beide Schleifen müssen dieselbe Collection lesen, und ein Preset darf diesen
Slug nicht ausschreiben: Eine neue Area heißt beim Einfügen
`<seite>-<section>-<area>`, und **Edit Section → Data** kann sie später
umbiegen. Das Layout schreibt deshalb
`[elementvalues /[[section:collection:services]] …]` – ein Compile-Token, das
auf die tatsächlich gebundene Collection auflöst. Die Button-Reihe folgt ihr
damit beim ersten Einfügen und nach jedem Umbiegen.

## Manifest v3: benannte Areas

Version 3 ersetzt das universelle Intro/Content/Outro-Modell durch semantische
Slots des Presets. Ein Layout enthält jede deklarierte `[[area:<key>]]` genau
einmal. Der Builder rendert das Token aus der geordneten Komponentenliste der
Area und speichert das vollständige bearbeitbare Modell in einem einzigen
`nino:section`-Kommentar.

**Layout** wird nur verwendet, wenn eine weitere `.tpl` die Komposition
wirklich ändert. 2/3/4 Spalten, Ausrichtung und andere reine Klassenvarianten
gehören in den **Style** einer Area. So bleiben beide Auswahlen eindeutig.

### Vollständiges Collection-Beispiel

Dieses gekürzte Article-Manifest zeigt einen einzelnen Titelbereich, eine
wiederholte Elements-Area und eine optionale Action-Area:

```php
<?php return [
	'name' => 'Articles — Responsive grid',
	'description' => 'Titel, wiederholbare Artikelkarten und optionale Action.',
	'category' => 'Cards',
	'tags' => [ 'articles', 'cards', 'grid' ],
	'version' => 3,
	'recommend' => [
		'layout' => 'default',
		'frame' => [ 'background' => 'alt', 'container' => 'wide' ],
	],
	'layouts' => [
		'default' => [ 'label' => 'Heading, articles and action', 'template' => 'section.tpl' ],
	],
	'areas' => [
		'heading' => [
			'label' => 'Title area', 'source' => 'single',
			'allowed' => [ 'title', 'subtitle', 'description' ],
			'container' => [ 'tag' => 'div', 'class' => 'nino-grid-100 nino-mb-3' ],
			'styles' => [
				'left' => [ 'label' => 'Left', 'class' => 'nino-text-left' ],
				'center' => [ 'label' => 'Centered', 'class' => 'nino-text-center' ],
			],
			'recommend' => [ 'style' => 'center', 'components' => [
				[ 'id' => 'title', 'type' => 'title' ],
				[ 'id' => 'subtitle', 'type' => 'subtitle' ],
			] ],
			'render' => [ 'title' => [ 'tag' => 'h2', 'class' => 'nino-section-title' ] ],
		],
		'articles' => [
			'label' => 'Articles', 'source' => 'elements',
			'allowed' => [ 'image', 'title', 'description', 'button' ],
			'item' => [ 'tag' => 'article', 'class' => 'nino-article nino-article--alt' ],
			'styles' => [
				'two-columns' => [ 'label' => '2 columns', 'class' => 'nino-grid-m-50' ],
				'three-columns' => [ 'label' => '3 columns', 'class' => 'nino-grid-m-33' ],
			],
			'recommend' => [ 'style' => 'three-columns', 'components' => [
				[ 'id' => 'image', 'type' => 'image', 'bindings' => [ 'src' => 'image', 'alt' => 'title' ] ],
				[ 'id' => 'title', 'type' => 'title', 'bindings' => [ 'text' => 'title' ] ],
				[ 'id' => 'copy', 'type' => 'description', 'bindings' => [ 'text' => 'description' ] ],
				[ 'id' => 'action', 'type' => 'button', 'style' => 'primary', 'bindings' => [ 'label' => 'linkLabel', 'href' => 'link' ] ],
			] ],
			'typeTitle' => 'Articles',
			'model' => [
				'title' => [ 'type' => 'string', 'locale' => true, 'required' => true ],
				'description' => [ 'type' => 'string', 'locale' => true, 'html' => true ],
				'linkLabel' => [ 'type' => 'string', 'locale' => true ],
				'link' => [ 'type' => 'string' ],
				'image' => [ 'type' => 'image', 'width' => 1200, 'height' => 800 ],
			],
			'shortcode' => [ 'locale' => '', 'callback' => '', 'limit' => 6, 'query' => '' ],
		],
		'action' => [
			'label' => 'Action area', 'source' => 'single',
			'allowed' => [ 'title', 'description', 'button', 'template' ],
			'recommend' => [ 'components' => [] ],
		],
	],
];
```

`section.tpl` enthält keine komponentenspezifischen Tokens:

```html
[[area:heading]]
[[area:articles]]
[[area:action]]
```

### Layout- und Frame-Beispiel

`fullscreen-image` bietet zwei echte Layout-Templates. Layout-Empfehlungen
können die Frame-Empfehlung des Presets ergänzen; eine explizite Auswahl des
Benutzers gewinnt weiterhin:

```php
'recommend' => [ 'layout' => 'cover', 'frame' => [ 'focus' => '5', 'overlay' => 'medium' ] ],
'layouts' => [
	'cover' => [
		'label' => 'Static cover image', 'template' => 'section-cover.tpl',
		'frame' => [ 'screen' => '100', 'background' => 'cover' ],
	],
	'parallax' => [
		'label' => 'Parallax image', 'template' => 'section-parallax.tpl',
		'frame' => [ 'screen' => '100', 'background' => 'parallax' ],
	],
],
'areas' => [
	'content' => [
		'label' => 'Title content', 'source' => 'single',
		'allowed' => [ 'title', 'subtitle', 'description', 'button', 'template' ],
		'recommend' => [ 'components' => [
			[ 'id' => 'title', 'type' => 'title', 'style' => 'loud' ],
			[ 'id' => 'action', 'type' => 'button', 'style' => 'primary' ],
		] ],
	],
],
```

### Regeln für Areas und Komponenten

- `source` ist `single` oder `elements`. Eine Single-Area kann einen neuen
  nativen Text-/Image-Key erzeugen, einen vorhandenen Textfill verwenden oder
  einen festen Wert direkt in die Section schreiben. Eine Elements-Area
  wählt/erstellt einen Typ; jede Nicht-Bild-Eigenschaft kann unabhängig ein
  Modelfeld, einen vorhandenen gemeinsamen Textfill oder einen festen Wert
  verwenden. Bilder bleiben kompatible Modelfeld-Mappings.
- Kernkomponenten sind `title`, `subtitle`, `description`, `text`, `image`,
  `button`, `price`, `number` und `template`. Jede Textkomponente bietet
  dieselben drei Styles – **Auto**, **Quiet**, **Loud** –, die zu einem
  Modifikator der jeweils getragenen Klasse kompilieren
  (`nino-section-title--loud`, `nino-atf-title--loud`, `nino-article-title--loud`). `allowed` ist eine strikte
  Teilmenge pro Area. Komponenten können sortiert und gelöscht, aber nicht
  verschachtelt werden.
- Jede Komponente braucht eine eindeutige kleingeschriebene Slug-`id`. Sie ist
  der stabile Default-Suffix. Bild `hero-image` erzeugt beispielsweise
  `/page-<page>/<section>/hero-image` und `hero-image-alt`.
- Eine `template`-Komponente bietet gezielt einen wiederverwendbaren `.tpl`-
  Input an. Sie kompiliert zu validiertem `[template /templates/<name>]` und
  kann an jeder Position der Komponentenliste stehen.
- `styles` einer Area sind reine CSS-Auswahlen. `render.<type>` darf sicheren
  Tag/Klasse, Bildmaße oder eine endliche Component-Style-Map überschreiben.
  Tags und Klassen werden vom Compiler erlaubt; Browserdaten liefern weder
  beliebiges Markup noch PHP.
- `container` umschließt eine Single-Area, `item` jede Elements-Wiederholung.
  Responsive Struktur und Full-Bleed bleiben in diesen preseteigenen Klassen
  und Templates.
- `data` deklariert die `data-*`-Attribute eines erzeugten Elements.
- `maxComponents` ist standardmäßig 12 und wird auf 1…20 begrenzt.
- Alle Shortcode-Argumente werden ausgegeben. Seltene Query-/Callback-
  Änderungen bleiben nach dem Ablösen eine HTML+-Aufgabe.

Beim Hinzufügen liegen Komponentenreihenfolge und Data in einer gemeinsamen
kompakten Liste. Nach dem Einfügen trennt **Edit** wieder **Design** für Area
Style, Component Style und Reihenfolge von **Data** für dieselben Bindings.
Gewöhnliche Textfills stehen unter **Content textfills**, Einträge aus
`text/blacklist.php` unter **Technical values** — dort erscheint auch die
`/webpage/<seite>/uri` einer Seite, sodass ein Button auf eine andere Seite
zeigen kann, statt einen festen Pfad zu tragen. Die Blacklist steuert nur die
Sichtbarkeit im normalen Editor; technische Route-URIs bleiben gültige
Template-Bindings und werden beim Einfügen nicht überschrieben.

Die Auswahl jeder Eigenschaft wird als `bindingSources` im Komponenten-Knoten
gespeichert und kann auch als Manifest-Empfehlung angegeben werden:

```php
[
	'id' => 'action',
	'type' => 'button',
	'bindings' => [
		'label' => 'Kontakt',
		'href' => '/webpage/contact/uri',
	],
	'bindingSources' => [
		'label' => 'fixed',
		'href' => 'textfill',
	],
]
```

Für Single-Areas sind `new`, `textfill` und `fixed` gültig (bei Bildern
`new`/`image`). Für Elements-Areas gelten `field`, `textfill` und `fixed`, bei
Bildern nur `field`. Template-Eigenschaften verwenden `template`. Feste Werte
werden escaped, Shortcode-Klammern neutralisiert; feste URLs erlauben nur
gewöhnliche relative URLs oder die Schemes `http`, `https`, `mailto` und `tel`.

Die v3-Metadaten sind die einzige grafische Wahrheitsquelle:

```html
<!-- nino:section {"version":3,"preset":"articles-grid","areas":{…}} -->
```

Der öffentliche Request liest sie nicht. Nur der Builder öffnet damit die
Area-/Komponenten-Konfiguration erneut. HTML+ entfernt den Kommentar und
beendet damit bewusst die grafische Zuständigkeit.


### Data-Attribute

Das gemeinsame Frontend-Script liest seine Parameter aus `data-*`-Attributen:
`nino-autoheight` gleicht die Karten einer `data-autoheight-group` an, `nino-slider`
richtet sich nach `data-slider-width`, `nino-vpa` verzögert um `data-vpa-delay`.
Ein Preset, das eine solche Klasse auf ein erzeugtes Element schreibt, deklariert
die zugehörigen Attribute direkt daneben:

```php
'areas' => [
	'services' => [
		'item' => [ 'tag' => 'article', 'class' => 'nino-article' ],
		'render' => [
			'title' => [
				'tag' => 'h3',
				'class' => 'nino-article-title nino-autoheight',
				'data' => [
					'autoheight-group' => 'services-title-[[section:id]]',
					'autoheight-mobile' => 'skip',
				],
			],
			'button' => [ 'class' => 'nino-modal-trigger', 'data' => [ 'modal-target' => 'contact-modal' ] ],
		],
	],
],
```

Ein `data` auf oberster Ebene gehört zur `<section>`, eines im Layout überschreibt
es je Name, `container` und `item` tragen den Wrapper einer Area bzw. ihre
Wiederholung, `render.<type>` jede Komponente dieses Typs. Namen stehen ohne
`data-`-Präfix (ein geschriebenes Präfix wird akzeptiert und entfernt), Werte sind
kurze einzeilige Literale, und `[[section:id]]` wird durch die Section-ID ersetzt —
zwei Kopien desselben Presets bleiben auf einer Seite unabhängig.
`data-cover-height` bleibt beim Frame. Es gibt dafür kein Editor-Feld:
Data-Attribute sind eine Preset-Entscheidung, alles darüber hinaus bleibt HTML+.

Ein Wert in `container`/`item`/`render.<type>` darf auch `[[feldname]]` sein –
ein Collection-Feld, nicht nur `[[section:id]]`. Beim Kompilieren bleibt das
reiner Text; das gewöhnliche `[elements]`-Rendering, das im selben
wiederholten Element bereits `[[title]]` einsetzt, ersetzt es bei jedem
Request pro Datensatz – ganz ohne Laufzeit-Änderung. **Filterable grid**
nutzt genau das, um jede Karte mit `data-filter-item="[[category]]"` zu
markieren.

Dafür eignet sich nur ein kurzes Feld: Die 240-Zeichen-Grenze gilt dem
geschriebenen `[[category]]`, nicht dem eingesetzten Wert – eine lange
Beschreibung landet also vollständig auf jeder Karte. Ein Rich-Text-Feld
(`'html' => true`) wird rundheraus abgelehnt: Sein Wert wird für
Element-*Inhalt* bereinigt und behält `"` – im Attribut würde er es damit
beenden statt darin zu stehen.

Entscheidend ist das Element, das die Klasse wirklich trägt. `nino-cover` und
`nino-parallex` sitzen auf der `<section>`, `data-cover-width` gehört also in eine
Preset- oder Layout-Map. `nino-vpa` schreibt der Compiler auf die erzeugte
`nino-grid-row`, die keine `data`-Map erreicht — Motion-Timing bleibt Sache des
Layout-`.tpl`. Ein `item` einer Collection ist ein direktes Flex-Kind dieser Row
und wird bereits auf die Höhe seiner Zeile gestreckt; Höhenangleich gehört daher
über `render.<type>` an die Boxen in der Karte — genau so richtet das
Articles-Preset die Call-to-Actions seiner Karten aneinander aus.

Die Cover-Höhe bleibt ein Prozentwert des Viewports. Die Cover-Breite bezieht
sich dagegen auf die tatsächliche Contentbox des umgebenden Elements. Eine
dauerhafte Seitennavigation kann `<main>` damit verschmälern, ohne dass das
Cover um die Navigationsbreite überläuft.

### Versionsvertrag

Die Section Library lädt ausschließlich Manifeste mit explizitem `version => 3`. Eine fehlende, ältere oder unbekannte Version wird ignoriert, statt in eine andere UI geraten zu werden. Bereits erzeugtes HTML bleibt gültiger Runtime-Quelltext und kann über HTML+ weiterbearbeitet werden; zum neuen Einfügen oder grafischen Wiederöffnen eines verwalteten Presets wird jedoch ein gepflegtes V3-Manifest benötigt.

## Aktuelle Grenzen

- Library und Konfiguration rendern erzeugtes Markup mit Beispieldaten. Das Backend aktualisiert zuerst das konfigurierte Bundle `/.cache/style.css` und liefert dessen Inhalt im authentifizierten Library-Payload; der Client bettet ihn in jedes isolierte `srcdoc` ein und benötigt deshalb keinen eigenen Request auf ein öffentliches Dot-Verzeichnis. Script-Tags und Inline-Handler werden entfernt, die CSP sperrt Skripte und Netzwerkaktionen, Formulare können nicht senden und Links werden nicht verfolgt.
- Visuelle Content-Einheiten sind oberste `<section>`-Elemente. Bestehende alleinstehende `[template]`-Zeilen bleiben verlustfrei bearbeitbar; neue Includes werden über eine Area-Komponente eingefügt. Markierte Header-/Footer-Slots liegen in den Template Settings.
- Der Builder kann `page-*.tpl` anlegen; die Zuordnung von Route zu Template bleibt in `/_admin` oder im Code.
- Native Quick-Fills sind einfache Texteingaben. Rich Text, Übersetzungen und Batch-Pflege bleiben in den etablierten Content-Werkzeugen.
- Fehlende Bildplatz-Definitionen lassen sich mit empfohlenen Maßen anlegen; Auswahl und Upload des eigentlichen Bildes bleiben in `/_admin`.
- Eigene Sections werden nie durch erratene Composer-Einstellungen rückwärts interpretiert.

## Fehlersuche

| Problem | Prüfung |
|---|---|
| Seite ist schreibgeschützt | Nach einem nicht geschlossenen oder überzähligen `<section>`-Tag suchen. |
| Speichern meldet externe Änderung | Seite neu laden und Änderung bewusst zusammenführen; das Werkzeug überschreibt sie nicht. |
| Doppelte Section-ID | Jeder Section eine eindeutige sprechende ID geben. |
| Header oder Footer fehlt | Das gewünschte Include unter **Template Settings → Header/Footer** wählen. `html-header`, `html-footer` und **None** sind immer verfügbar. |
| Elements-Collection fehlt | Bei verwalteten Sections rechts anlegen, bei eigenem Code in `/_admin`. |
| Section öffnet nur als HTML+ | Metadaten fehlen, sind ungültig oder verweisen auf ein nicht mehr installiertes Preset. |
| Echte Seite weicht von Live-Vorschau ab | Die Vorschau verwendet aktuelles CSS, aber inerte Beispieldaten und kein Projekt-JavaScript; das echte Frontend bleibt maßgeblich. |
