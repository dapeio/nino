# Nino — Template-Builder-Handbuch
*[English](_templates.md)*

**Links:**
[README](../README.de.md) · [Design-Handbuch](design.de.md) · [Entwickler-Handbuch](development.de.md) · [_editor-Handbuch](_editor.de.md) · [_admin-Handbuch](_admin.de.md) · [_install-Handbuch](_install.de.md) · [Security Policy](../SECURITY.md) · [Changelog](../CHANGELOG.md)

Ein grafischer, rein entwicklerseitiger Editor für die `templates/*.tpl`-Dateien des Projekts, erreichbar unter `/_templates`. Optional - eine `.tpl` ist einfaches HTML und lässt sich genauso gut von Hand schreiben (siehe `docs/design.de.md`) - aber er macht aus "welche Grid-Klassen trägt diese Spalte?" ein Formular statt eines Nachschlagens im Design-Handbuch.

## Wo er liegt

In einem eigenen Verzeichnis auf oberster Ebene, `_templates/`, nicht als Modul in `/_admin` - aus demselben Grund, aus dem auch `/_install` eines hat: Er ist ein Entwicklungswerkzeug, ein Projekt darf ihn also nach Abschluss des Designs löschen (`rm -rf _templates`), und `_admin/Admin.php` ist bereits groß genug.

Das Passwort ist allerdings das von `/_admin`. `_templates/index.php` bindet `_admin/Admin.php` ein und nutzt dessen Session-Flag (`./nino/admin/authed`) weiter, statt ein zweites Passwort einzuführen, das synchron gehalten werden müsste - dieselbe Abhängigkeit, die `/_install` schon hat. In der Praxis: Wer in `/_admin` eingeloggt ist, hat auch den Builder offen, und die Kopfzeile von `/_admin` trägt einen Link "Template Builder". Ein Aufruf von `/_templates` ohne Login zeigt das Login-Formular von `_admin`, das nach `/_admin` postet und anschließend hierher zurücklädt.

`_admin` zu löschen deaktiviert damit auch `_templates`. Das ist Absicht - ein unauthentifizierter Template-Editor schreibt Markup in jede Seite der Website.

## Die Grundidee

**Die Identität eines Bausteins ist sein HTML-Tag plus seine CSS-Klassen, und seine Eigenschaften sind genau dieselben CSS-Klassen.**

Es gibt kein separates Datenmodell neben dem Markup, keine JSON-Datei daneben, keine `data-*`-Attribute, die der Builder bräuchte, um eine Datei zu verstehen. `<div class="ui-grid-100 ui-grid-l-50 ui-mb-3">` *ist* eine Grid-Spalte mit Breite 100, Breite-ab-Large 50 und Margin-Bottom 3 - der Builder liest diese drei Einstellungen aus der Klassenliste und schreibt sie dorthin zurück.

Zwei Konsequenzen, die es wert sind, ausgesprochen zu werden:

- **Es funktioniert mit Templates, die älter sind als der Builder.** Jede `.tpl`, die das Projekt bereits hat, öffnet sich mit erkannter Struktur, ohne vorher angefasst oder migriert zu werden.
- **Was er speichert, bleibt von Hand editierbar.** Die Ausgabe enthält nichts, was ein handgeschriebenes Template nicht auch enthielte. Sie können dieselbe Datei danach weiter im Editor bearbeiten, und der Builder versteht sie immer noch.

Die Alternative - ein `data-nino-builder="grid-col"`-Marker an jedem Element - würde die Erkennung trivial eindeutig machen, um den Preis eines zusätzlichen Attributs an praktisch jedem Tag im ausgelieferten Frontend-HTML. Die Abwägung ist andersherum ausgefallen. Ein Baustein mit tatsächlich mehrdeutiger Klassensignatur kann weiterhin einen eigenen `attrs`-Match deklarieren (siehe "Library-Format"); keiner der mitgelieferten Bausteine braucht das.

## Die Oberfläche

Drei Spalten:

- **Templates** (links) - jede `templates/*.tpl` auf der Platte. Ausgegraute Namen sind nicht editierbar (siehe unten).
- **Canvas** (Mitte) - das geöffnete Template als Baum verschachtelter Kästchen. Ein Klick wählt ein Kästchen aus.
- **Settings + Blocks** (rechts) - oben die Eigenschaften des gewählten Bausteins, darunter die Library, gruppiert nach der `category` des jeweiligen Manifests.

Nichts erreicht das Dateisystem vor **Save**. Jede Änderung geht in den Baum im Speicher und zeichnet neu; die Kopfzeile zeigt "unsaved changes" und der Save-Button wird aktiv. Wer das Template wechselt oder die Seite mit ungespeicherter Arbeit verlässt, wird vorher gefragt.

### Was der Canvas zeigt

Bewusst **keine** Vorschau. Nur die beiden Dinge, die sich aus einer Liste von Klassennamen wirklich nicht beurteilen lassen, werden maßstäblich gezeichnet:

- die **Breite einer Grid-Spalte** - ihr Kästchen ist so breit, wie ihre `width`-Einstellung sagt, und eine Grid-Zeile setzt ihre Spalten nebeneinander, genau wie das Markup es tun wird
- **vertikale Abstände** - `ui-mt-*`/`ui-mb-*`/`ui-pt-*`/`ui-pb-*` werden als echtes Margin und Padding gezeichnet

Alles andere ist ein beschriftetes Kästchen: Name des Bausteins, HTML-Tag und eine kurze Vorschau auf Text, Linkziel oder Shortcode-Argumente. Farben, Schriften und echte Typografie sind Sache des Themes (`docs/design.de.md`); sie hier nachzubauen hieße, einen zweiten Renderer zu pflegen, der in jedem Projekt auf andere Weise falsch ist.

**Auch die Verschachtelungstiefe wird gezeichnet** - jede Ebene liegt auf einem etwas kräftigeren Farbton als die darüber, sodass sich die Schichten eines tiefen Grids voneinander abheben statt als eine Wand aus Kästchen zu erscheinen. Der Farbton wird gegen die Tokens des Editor-Themes gemischt (`color-mix()` auf einer pro Kästchen gesetzten Custom Property `--tb-depth`), stimmt also im hellen wie im dunklen Theme, und wird nach sechs Ebenen gedeckelt, statt ins Unlesbare zu laufen.

Eine Spalte, die 100 % anzeigt, zeigt dabei die Wahrheit: `ui-grid-100 ui-grid-l-50` *ist* bis zum `l`-Breakpoint volle Breite. Der Canvas zeichnet die Basisbreite, die Breakpoint-Werte stehen in den Einstellungen.

### Markup, das die Library nicht kennt

Alles ohne passenden Baustein bekommt trotzdem ein Kästchen, beschriftet mit Tag, ID und Klassen (`<div id="hero" class="my-thing">`) und mit gepunktetem Rahmen gezeichnet. Diese sind rein strukturell: Der Builder hat kein Modell dafür, was ihre Eigenschaften bedeuten, bietet also keine Felder dafür an - er versteckt sie aber nie, verwirft sie nie, und ihre Kinder werden ganz normal weiter geparst und angezeigt. Ein erkannter Baustein innerhalb eines nicht erkannten Wrappers funktioniert genau wie überall sonst.

### Der `#id`-Hinweis

Ein Knoten, dessen `id` von einer Regel in den Stylesheets des Projekts adressiert wird, bekommt ein oranges Abzeichen.

Das ist wegen der Grundidee oben wichtig: Der Builder zeigt die CSS-Klassen eines Elements als seine Eigenschaften an, und eine an eine ID gebundene Regel überschreibt diese Klassen auf der echten Seite, ist hier aber völlig unsichtbar. `#hero { padding: 0 }` gewinnt in der Kaskade gegen `ui-pt-4`, der Canvas würde also ein Padding zeichnen, das der Browser nicht rendert. Das Abzeichen sagt "für diesen Knoten ist die Klassenliste nicht die ganze Wahrheit", statt stillschweigend etwas Falsches anzuzeigen.

Der Scan liest jede `.css`-Datei aus allen `/nino/html/assets`-Bundles der `config.php` und sammelt die IDs, die deren Selektoren erwähnen. Es ist ein Selektor-Scan, kein vollwertiger CSS-Parser - ein False Positive kostet ein überflüssiges Abzeichen, und das ist die richtige Richtung, in der er falsch liegen darf.

## Bearbeiten

**Auswählen.** Ein Kästchen anklicken. Der Klick wählt das innerste getroffene Kästchen aus, nicht jeden Vorfahren, durch den er geblubbert ist; ein Klick auf den Canvas-Hintergrund hebt die Auswahl auf. Die Auswahl hängt an der Knoten-ID und übersteht damit das Neuzeichnen nach jeder Änderung.

**Einstellungen.** Der Inspector erzeugt ein Bedienelement pro Einstellung, die das Manifest des Bausteins deklariert - es gibt nirgends bausteinspezifischen Formularcode, und eine Einstellung im Manifest zu ergänzen genügt, um sie editierbar zu machen. Welches Bedienelement erscheint, folgt aus dem Typ: ein Select für `classenum`/`classgroup`/`tag`, eine Checkbox für `classtoggle`/`attrtoggle`, ein Textfeld für `attr` und `text`. Die Breakpoint-Varianten einer responsiven Einstellung erscheinen eingerückt unter ihrem Basis-Bedienelement, sodass fünf Felder namens "Width" als eine Einstellung mit vier Varianten lesbar sind statt als fünf Einstellungen.

Änderungen greifen sofort im Baum und zeichnen den Canvas neu. Ein Textfeld aktualisiert zusätzlich die Vorschau seines Kästchens beim Tippen - ein vollständiges Neuzeichnen pro Tastendruck würde den Fokus aus dem Feld nehmen.

**Einfügen.** Einen Baustein in der Palette anklicken. Wo er landet, folgt aus der Auswahl: *in* ihr, wenn der gewählte Baustein Kinder aufnimmt, *neben* ihr, wenn nicht, und am Ende des Dokuments, wenn nichts ausgewählt ist. Die `block.tpl` des Bausteins wird **serverseitig mit demselben Parser** geparst, den auch Dokumente durchlaufen (`library/parse`) - das Startmarkup eines Bausteins ist damit exakt wie Template-Markup geschrieben, und es gibt keinen zweiten Codepfad, der anderer Meinung darüber sein könnte, was ein Template bedeutet.

Die Library umfasst aktuell **76 Bausteindefinitionen**: 51 einfügbare Einträge decken den `Nino.css`-Katalog ab (Tabellen, Pricing, Accordions, Galerien, Tabs, Modals, Slider, Timelines, Badges, Alerts, Breadcrumbs, Listen, Logoleisten, Video und ihre Bestandteile), während 25 verschachtelte Hilfsbausteine nur Markup wie Tabellenzellen, Tab-Panels und Modal-Schließen-Buttons erkennen und bearbeitbar machen. Diese Helfer setzen `palette => false`: Im Canvas bleiben sie vollständig editierbar, ohne die oberste Palette zu überladen.

**Aktionen.** Die Fußzeile des Inspectors trägt die Aktionen des gewählten Bausteins - nach oben/unten verschieben, duplizieren, entfernen - gefiltert nach dem, was `actions` im Manifest erlaubt. Ein nicht erkanntes Element bekommt nur die strukturellen, denn die brauchen kein Modell davon, was es ist.

**Einrückung.** Jede strukturelle Änderung bringt ihren eigenen Whitespace mit, denn der Baum behält den exakten Text zwischen den Tags (genau das macht den Round-Trip byte-genau). Statt die Einrückung aus einem Tiefenzähler abzuleiten, *kopiert* ein Insert den Whitespace, den sein neuer Nachbar bereits hat - was auch immer die Datei verwendet, Tabs oder Leerzeichen, in welcher Tiefe auch immer. Ein Remove nimmt seine eigene Einrückung mit, damit sich über die Zeit keine Leerzeilen ansammeln. Der einzige Fall ohne Nachbarn zum Kopieren, ein erstes Kind in einem leeren Element, leitet eine Ebene vom Elternteil ab. `tests/templates-js-smoke.js` deckt jeden dieser Fälle ab.

## Welche Templates bearbeitet werden können

Nur `page-*` und `section-*`. Alles andere wird gelistet, öffnet sich aber schreibgeschützt.

Der Grund sind `html-header.tpl` und `html-footer.tpl`: Sie sind **eine Struktur, aufgeteilt auf zwei Dateien**. Der Header öffnet `<head>`, `<header>` und `<main>`; der Footer schließt `</main>` und ergänzt `<footer>`. Keine der beiden ist für sich ein wohlgeformtes Fragment. Ein HTML-Parser, dem man den Header allein gibt, "korrigiert" ihn, indem er das offen gebliebene Tag schließt - und dieses Ergebnis zu speichern würde den Seitenrahmen jeder Seite der Website stillschweigend zerstören. Dasselbe gilt für das Paar `mail-header`/`mail-footer`, und `sitemap-xml.tpl` ist überhaupt kein HTML.

Ein Template kann sich aus einem zweiten Grund schreibgeschützt öffnen: Es hat den Round-Trip nicht bestanden (siehe unten). Das bedeutet, es enthält Markup, für das der Baum keine getreue Repräsentation hat - und der Builder sagt das, statt es umzuformatieren.

Beide Fälle erscheinen als Hinweis oben im Canvas, mit Begründung.

## Die Round-Trip-Garantie

**Ein Template zu öffnen und unverändert zu speichern reproduziert die Datei Byte für Byte.**

Auf dieser Eigenschaft ruht alles andere. Ein Builder, dessen erstes Speichern die Einrückung umformatiert, Attribute umsortiert und `required` zu `required=""` umschreibt, ist einer, den Entwickler nicht mehr öffnen. Der Knotenbaum behält deshalb alles: die Attributreihenfolge (einschließlich der Position von `class` zwischen den übrigen), den exakten Whitespace zwischen den Tags, Kommentare und die ursprüngliche Schreibweise jeder HTML-Entity.

`tests/templates-smoke.php` prüft das gegen jedes `page-*`-Template der Library sowie gegen eine Reihe von Einzelfällen (Boolean-Attribute, Void-Elemente, `&shy;`, nacktes vs. kodiertes Ampersand, Attributreihenfolge, Whitespace). Eine Parser-Änderung, die eines davon bricht, zeigt sich als fehlschlagender Test statt als zerschossene Website.

Etwas Mechanik dahinter, falls Sie den Parser anfassen:

- **Shortcodes sind Struktur, kein Text.** `[elements /services limit="3"]…[/elements]` umschließt HTML, muss also ein Knoten mit Kindern sein. Vor dem Parsen wird jeder Shortcode-Aufruf in ein `<nino-sc name args>`-Platzhalterelement umgeschrieben; der Serializer macht das rückgängig. Damit erledigt PHP 8.4s echter HTML5-Parser (`\Dom\HTMLDocument`) die gesamte eigentliche Arbeit. Ein Shortcode innerhalb eines Tags (in einem Attributwert) bleibt bewusst unangetastet - ein Element mitten in ein Attribut zu injizieren würde das Dokument zerstören.
- **Textfills brauchen überhaupt keine Behandlung.** `[[/key]]` ist gewöhnlicher Text, in Textknoten wie in Attributwerten, verschachtelte wie `[[/webpage[[/nino/http/response/uri]]/title]]` eingeschlossen.
- **Entities werden vor dem Parsen gegen einen Platzhalter getauscht.** Der Parser dekodiert `&shy;` zu einem Weichtrennzeichen, und danach lässt sich nicht mehr erraten, ob in der Quelle `&shy;`, `&#173;` oder ein literales Zeichen stand. Jede Entity wird zwischen zwei Zeichen der Private Use Area (U+E000/U+E001) durch den Baum getragen und führt ihren eigenen Namen mit, sodass das Wiederherstellen ohne Nachschlagetabelle auskommt.

## Library-Format

`_templates/library/` enthält ein Verzeichnis pro Baustein, jeweils mit `manifest.php` und optional `block.tpl` - dieselbe Verzeichnis-plus-Manifest-Konvention, die auch `_install/library` verwendet. Einen Baustein hinzuzufügen ist genau ein neues Verzeichnis; nirgends eine Codeänderung.

```
_templates/library/
  grid-col/
    manifest.php      category, tag, name, match, children, use, settings, actions, palette
    block.tpl         das Markup, das beim Einfügen dieses Bausteins entsteht
  section/
  button/
  …
```

Eine `manifest.php` liefert ein einfaches Array zurück:

| Schlüssel | Bedeutung |
|---|---|
| `category` | Gruppe in der Palette (`Grid`, `Sections`, `Content`, `Media`, `Forms`, `Shortcodes`, …). Frei wählbar - die Palette gruppiert nach dem, was sie vorfindet |
| `tag` | Um welche Art Ding es sich handelt (`wrap`, `title`, `text`, `image`, `link`, `loop`, `include`, `meta`). Wird neben dem Namen angezeigt und ist das, wogegen ein künftiger `children`-Filter prüft |
| `name` | Anzeige in der Palette und als Beschriftung des Kästchens |
| `match` | Wie dieser Baustein in einem bestehenden Template erkannt wird - siehe unten |
| `children` | `[ '*' ]`, wenn der Baustein Kinder aufnimmt, sonst weggelassen |
| `use` | Einzubindende gemeinsame Einstellungsgruppen: `spacing`, `align`, `vpa`. Erspart es, dieselben zwanzig Zeilen in vierzig Manifesten zu wiederholen; die eigenen `settings` eines Bausteins gewinnen bei Namensgleichheit |
| `settings` | Die editierbaren Eigenschaften - siehe unten |
| `actions` | Welche Aktionen der Baustein anbietet. Standard: `remove`, `duplicate`, `moveup`, `movedown` |
| `palette` | `false` für einen verschachtelten Helfer, der nur der Erkennung dient. Er beschriftet Markup und liefert im Canvas weiterhin Einstellungen/Aktionen, wird aber nicht als eigener Top-Level-Insert angeboten. Standard: `true` |
| `html` | Inline-Startmarkup, für einen Einzeiler, der keine eigene `block.tpl` wert ist |

### `match`

```php
'match' => [
    'tag'        => 'div',                    // oder 'tags' => [ 'h2', 'h3', 'h4' ]
    'classes'    => [ 'ui-grid-row' ],        // alle davon müssen vorhanden sein
    'classesAny' => [ 'ui-grid-25', … ],      // mindestens eine davon
    'attrs'      => [ 'name' => 'elements' ], // exakte Werte (Shortcode-Bausteine)
    'not'        => [ 'ui-atf-title' ],       // schließt den Knoten aus
],
```

Jeder Baustein, dessen `match` ein Knoten erfüllt, wird danach bewertet, wie spezifisch dieser Match war - eine Pflichtklasse oder ein Attribut zählt 10, `classesAny` zählt 5, ein reiner Tag-Match zählt 1 - und der höchste Wert gewinnt. Genau das erlaubt es, "Section Title" (`h3.ui-section-title`) und das generische "Heading" (jedes `h1`-`h6`) nebeneinander zu haben, ohne dass das generische je das spezifische verschluckt.

### `settings`

Sieben Typen, alle eine Zwei-Wege-Abbildung zwischen einem Formularfeld und der Klassenliste, den Attributen, dem Tag oder dem Text des Knotens:

| Typ | Bildet ab auf | Deklaration |
|---|---|---|
| `classenum` | einen Wert aus einer Liste, über ein printf-Muster | `'pattern' => 'ui-grid-%s'`, `'values' => [ '25', '50', … ]`, optional `'bpPattern' => 'ui-grid-%b-%s'` + `'breakpoints' => [ 's','m','l','xl' ]` |
| `classgroup` | eine Klasse aus einer expliziten Liste (Varianten ohne gemeinsames Muster) | `'options' => [ '' => 'Default', 'ui-btn--primary' => 'Primary', … ]` |
| `classtoggle` | eine einzelne Klasse, an oder aus | `'class' => 'ui-section--fullwidth'` |
| `attr` | ein einfaches HTML-Attribut | `'attr' => 'href'`, optional `'values' => [ … ]` für eine feste Auswahl |
| `attrtoggle` | ein boolesches HTML-Attribut, vorhanden oder nicht vorhanden | `'attr' => 'required'`; erscheint als Checkbox und wird ohne Wert serialisiert (`required`, nicht `required=""`) |
| `tag` | den Elementnamen selbst (`h2` vs. `h3`) - die einzige Eigenschaft, die weder Klasse noch Attribut ist | `'values' => [ 'h2', 'h3', 'h4' ]` |
| `text` | den eigenen direkten Textinhalt des Knotens, Textfills eingeschlossen | — |

Ein responsives `classenum` liefert zusätzlich zur Basis einen Wert pro Breakpoint, unter den Schlüsseln `width@m`, `width@l` und so weiter.

**Die Abbildung selbst läuft clientseitig**, in `_templates/assets/blocks.js`, und nur dort. Der Server liefert die Deklarationen (`Library`) und schiebt Tags, Attribute und eine geordnete Klassenliste hin und her (`Parser`/`Serializer`), interpretiert aber nie eine Klasse. Genau eine Implementierung der Abbildung bedeutet, dass nichts eine Klasse auf die eine Art lesen und auf eine andere zurückschreiben kann - und das hält die Round-Trip-Garantie auch über eine Bearbeitung hinweg aufrecht.

Eine Einstellung zu ändern ersetzt ihre Klasse **an dem Index, an dem sie bereits stand**, statt sie zu entfernen und hinten anzuhängen. Eine Eigenschaft zu bearbeiten sortiert also nie den Rest des `class`-Attributs um.

## Speichern

`documents/save` serialisiert den übermittelten Baum und schreibt ihn atomar (temporäre Datei + `rename()`, wie beim Passwort-Rewrite von `/_install`) - ein halb geschriebenes Seitentemplate ist eine kaputte Website.

Vor dem Schreiben verweigert es:

- ein Template außerhalb von `page-*`/`section-*` (403)
- einen leeren Baum (400) - ein Versehen, nie eine Absicht
- jedes Tag außerhalb der Allowlist (400), allen voran `<script>`, dessen Inhalt kein Markup ist und niemals umformatiert oder neu escaped werden darf, als wäre er welches
- jedes `on*`-Eventhandler-Attribut (400)

Dieser Bereich liegt hinter dem Passwort von `_admin`, auf derselben Vertrauensstufe wie dessen Config-Modul, das `config.php` als rohes JSON bearbeitet. "Vertrauenswürdig" und "wohlgeformt" sind allerdings zwei verschiedene Fragen, und ein Baum mit einem Tag, das der Builder nie erzeugt hätte, ist ein Bug, den man ablehnen sollte, statt ihn auf die Platte zu schreiben.

Einen eigenen Backup-Schritt gibt es nicht - `/templates` ist git-verwalteter Projektquellcode, und die automatischen Backups von `_editor` decken es bereits ab.

## Tests

Zwei Suites, weil der Builder auf zwei Sprachen verteilt ist:

- `php tests/templates-smoke.php` - der Loader der 76 Definitionen, der `Parser`/`Serializer`-Round-Trip jedes einfügbaren Bausteins (zusätzlich zu jedem ausgelieferten Seitentemplate), der `#id`-Scan und `documents/list`/`load`/`save` inklusive aller Verweigerungen.
- `node tests/templates-js-smoke.js` - die Baustein-Abbildung (`blocks.js`) und die Baum-Änderungen (`tree.js`). Beide Module sind bewusst DOM-frei, und genau das lässt sie in reinem Node gegen einen zweizeiligen `window`-Stub laufen - kein Testframework, kein Browser. Die Testumgebung reicht ihnen ein `document`, das bei jedem Zugriff über die zwei von ihrem IIFE dereferenzierten Eigenschaften hinaus wirft; ein Modul, das anfinge das DOM zu benutzen, ließe die Suite scheitern, statt still untestbar zu werden.

Diese beiden Dateien enthalten die Teile, die ein Template still beschädigen können: Eine Einstellung, die anders zurückgeschrieben als gelesen wird, sortiert Klassenattribute um, und ein Insert mit falsch behandeltem Whitespace macht die Datei bei jeder Änderung ein Stück krummer. Was keine der Suites abdeckt, ist das Rendering selbst (`canvas.js`, `inspector.js`), das DOM-gebunden ist.

## Fahrplan

Ausgeliefert (Patch 1-3):

- die App `/_templates`, ihre über `_admin` abgesicherte Route und der Link aus `/_admin`
- `Parser`/`Serializer` mit der byte-genauen Round-Trip-Garantie
- das Bausteinformat `library/<key>`, sein Loader und 76 Definitionen für den `Nino.css`-Komponentenkatalog; reine Erkennungshelfer können aus der Palette ausgeblendet bleiben
- sieben generierte Einstellungstypen einschließlich `attrtoggle` für native boolesche Attribute wie `required`, `open`, `controls` und `allowfullscreen`
- der `#id`-Scan samt Hinweis
- `documents/list`/`load`/`save` sowie `library/blocks`/`parse` inklusive aller oben genannten Verweigerungen
- der Canvas mit Tiefen-Einfärbung und Auswahl, die Palette, der generierte Einstellungs-Inspector, Einfügen/Verschieben/Duplizieren/Entfernen und Speichern mit Warnung bei ungespeicherten Änderungen

Noch ausstehend:

- eine "Neues Template"-Aktion und das Herauslösen von `section-*` aus einer bestehenden Auswahl
