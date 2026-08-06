# Nino — Design-Handbuch
*[English](design.md)*

**Links:**
[README](../README.de.md) · [Entwickler-Handbuch](development.de.md) · [Admin-Handbuch](_editor.de.md) · [_admin-Handbuch](_admin.de.md) · [_install-Handbuch](_install.de.md) · [_templates-Handbuch](_templates.de.md) · [Security Policy](../SECURITY.md) · [Changelog](../CHANGELOG.md)

## Einführung

Alles, was auf einer Nino-Seite sichtbar wird, entsteht aus drei kombinierbaren Mechanismen - Textfills, Shortcodes, Elemente - plus einem festen Satz an CSS-Klassen.
`docs/development.de.md` beschreibt diese Mechanismen aus Entwickler-Sicht (Klassen, Callbacks, Datenfluss).
Hier betrachten wir es aus der Sicht des Frontend-Designs.

### Das Grundkonzept des Nino Designsystem
Nino arbeitet mit reinem HTML, das mit drei Mechanismen ergänzt wird. Logik ist bewusst vom Design getrennt. Dynamische Abbildung wird durch die zusätzlichen Mechanismen möglich:
#### Textfills
Jede Nino Webseite hat eine Textsammlung nach dem Muster
`[[key]] => value`.
Diese wird in **globale Werte** (in allen Sprachen gleich) und **lokale Werte** (für jede Sprache unterschiedlich) aufgeteilt. Diese Textsammlung können Entwickler und Administratoren jederzeit über `_admin`und `/_editor` bearbeiten.
Der Entwickler legt dafür Textbausteine in `_admin` an - über `_editor`können sie dann -abhängig der Einstellung- global oder für alle erlaubten Sprachen befüllt werden.
Textfills können **beliebig lang** sein. Sie sollten jedoch **keinen HTML-Code** beinhalten.
Beim Templating kann man so **sprachrelevante und wiederkehrende Texte** mit einem Fill eintragen, der bei jedem Rendering mit der entsprechenden Client-Locale ersetzt wird.

Die Konvention dahinter ist - statt:
`<h2 class="ui-atf-title">Herzlich Willkommen auf: www.muster.de.</h2>`
schreibt man:
`<h2 class="ui-atf-title">[[/page-home/atf/title]]: [[/company/website]]</h2>`
Eine **Basis** an globalen und lokalen Textfills ist bereits im **Grund-Repo** vorhanden. Weitere Textfills können Entwickler/Designer nach Ihrem Gefühl **frei festlegen und erstellen**.
Es gibt jedoch eine kleine Zahl von Nino reservierter Textfills:

**Textfills - Kernel / Webseite**
| Fill | z.B. | Wert / definiert in | Verwendung |
|---|---|---|---|
| `[[/nino/http/request/uri]]` | `/kontakt` | von Nino berechnet | tatsächlich aufgerufene URI |
| `[[/nino/http/response/uri]]` | `/contact` | von Nino berechnet | intern aufgelöste Routen-URI |
| `[[/nino/http/response/locale]]` | `de_DE` | von Nino berechnet | aufgelöste Locale der Response |
| `[[/nino/auth/user]]` | `changeme@domain.com` | von Nino berechnet | E-Mail des eingeloggten Nutzers, sonst `''` |
| `[[/nino/dir]]` | `/www/nino` | von Nino berechnet | Verzeichnis-Präfix der Installation |
| `[[/date/year]]` | `2026` | von Nino berechnet | aktuelles Jahr |
| `[[/website/lang]]` | `de` | `text/{locale}.php` | `<html lang="...">` |
| `[[/website/charset]]` | `UTF-8` | `text/global.php` | `<meta charset>`/Content-Type (Header, Mail) |
| `[[/website/author]]` | `Max Mustermann` | `text/global.php` | `<meta name="author">`, Impressum |
| `[[/website/host]]` | `Meinhost Gbr` | `text/global.php` | Hosting-Angabe im Impressum |
| `[[/website/url]]` | `www.max-mustermann.com` | `text/global.php` | Basis-Domain für alle absoluten URLs (canonical, og:url, sitemap.xml, llms.txt, Mail-Betreffs) |

#### Textfills - Individuelle Webpage
Jede Webpage *(z.B. `/kontakt`)* benötigt außerdem eine Reihe von festgelegten lokalen (!) Textfills um im vollen Funktionsumfang integriert werden zu können. Diese werden in der Nino Navigation und im Default HTML-Head verwendet.

**Achtung:
Für Textinhalte der Seite (z.B. `hero-title`) empfiehlt sich die Konvention
`[[/page-<uri>/category/element]]`
z.B. `[[/page-home/hero/title]]`**

| Fill | Beispiel | Wert / definiert in | Verwendung |
|---|---|---|---|
| `[[/webpage/<uri>/uri]]` | `/contact` | `text/{locale}.php`<br>je Seiten-Key | Routen-URI der Seite (Links, Navigation, sitemap.xml) |
| `[[/webpage/<uri>/name]]` | `Contact us!` | `text/{locale}.php`<br>je Seiten-Key | Linktext (Navigation, Footer, llms.txt) |
| `[[/webpage/<uri>/title]]` | `Contact us - Contact form` | `text/{locale}.php`<br>je Seiten-Key | `<title>`/og:title/twitter:title, via `[[/webpage[[/nino/http/response/uri]]/title]]` |
| `[[/webpage/<uri>/description]]` | `Our contact data and contact form.` | `text/{locale}.php`<br>je Seiten-Key | Meta-/OG-/Twitter-Description, gleiches Verschachtelungsmuster |

Für \<uri> steht immer der `uri`-Key aus `$appData['/nino/http/routes']`
*(Bei `/contact` z.B. `[[/webpage/contact/uri]]`)*

#### Shortcodes
Nino Shortcodes sind die **Verbindungen zwischen Design und Logik**. Über PHP können Entwickler einen Shortcode mit der Konvention
`[myshortcode]`mit einem Callback registrieren - dessen Rückgabe-Wert dann im Template automatisch ersetzt wird.
So wird z.B. aus
`[localepicker]`
beim HTML-Rendering automatisch
`<div class="sc-localepicker-wrap">.....</div>`

Nino stellt bereits eine kleine Palette an Modulen mit Shortcodes bereit.
*(Siehe unten oder im [Entwickler-Handbuch](development.de.md) im Kapitel `\Nino\Modules`)* . Zur Verwendung müssen die Module vom Entwickler in der `config.php`freigeschaltet werden. Details dazu im Handbuch.
Weitere Shortcodes können einfach über PHP-Module ergänzt werden.

#### Elemente
Elemente sind Ninos Lösung für **wiederkehrende Daten** nach einem festen Datenmodell.
Das eignet sich z.B. für Serviceleistungen, Partner, Neuigkeiten, Blogbeiträge, etc. Der Entwickler erstellt über `_admin`einen **Elementtyp** mit einer URI *(z.B. /services)* und den Feldern *(z.B. title, descr, eventdate, price)* . Damit können dann über `_editor` **beliebig viele Elemente** erstellt werden. Jedes Element erhält ebenfalls eine URI *(z.B. /webdesign)* die sich mit der Typ-URI kombiniert *(/services/webdesign)*.

In der Template-Engine stehen zur Abbildung von Elementen zwei Shortcodes bereit:

`[element /services/webdesign]...[/element]`
gibt ein **gezieltes Element** mit seiner URI aus. Der mit dem Shortcode-Tag umschlossene HTML-Code dient als Template zur Abbildung. Die Elementfelder werden darin als **Textfills automatisch ersetzt**. *(z.B. '[[title]]' wird zu 'Webdesign')*

`[elements /services limit="4"..]...[/elements]`
Arbeitet nach dem gleichen Konzept - allerdings werden **mehrere Elemente** mit dem Typ der angegebenen URI angezeigt. Der Syntax zur Steuerung liegt im [Entwickler-Handbuch](development.de.md).
Der umschlossene HTML-Code wird in diesem Fall für **jedes Element** wiederholt und entsprechend gefüllt.

**Empfehlung für eine Nino Entwicklung:** Artikel/Cards werden immer über den `elements`-Shortcode aufgebaut, nie mit im Template hartkodiertem Inhalt - auch nicht auf den Demo-Seiten. Eine Services- und Portfolio-Sections folgt dem ebenso (gestützt auf die Element-Typen `services`/`portfolio`) statt handgeschriebener `<article>`-Blöcke.

### Das Template-Rendering

Die techniche Umsetzung dieser einfachen Template-Engine sitzt in der Kernel Methode `\Nino\Html::renderHtml( $appData, $html )` .
Diese wird bei jedem Laden eines Templates **automatisch ausgeführt** und macht in fester Reihenfolge immer drei Dinge:

1. **Textfills auflösen** - alle `[[key]]`-Platzhalter werden per `str_replace()` gegen den aktuellen Textbestand ersetzt.
2. **Shortcodes ausführen** - alle `[name arg]content[/name]`-Aufrufe werden per Regex erkannt und an den registrierten Callback des jeweiligen Shortcodes weitergereicht.
3. **`/nino/html/render`-Callbacks** - beliebige weitere, projekteigene Callbacks bekommen den fertigen HTML-String zur Modifikation.

Danach wird `renderHtml()` **rekursiv erneut aufgerufen** - unabhängig davon, was der Shortcode zurückgegeben hat - so lange bis alle Inhalte ersetzt sind und sich nichts mehr am HTML-Content verändert.
Es gibt keinen Kompilierschritt und keinen Cache-Bau zur Entwicklungszeit - jede Anfrage rendert live gegen den aktuellen Stand der `.tpl`-, `text/*.php`- und `elements/*.php`-Dateien.

**Wichtig für die Praxis:** Ein Fill oder Shortcode-Name, der nicht existiert, bricht nichts - er bleibt einfach als sichtbarer `[[key]]`- bzw. `[name]`-String im ausgelieferten HTML stehen. Es gibt keinen Fallback und keine Fehlermeldung an dieser Stelle.
Somit kann man im Design mit Platzhalter Textfills `z.B. [[/page-home/hero/title]]` oder `[my_shortcode]` arbeiten und diese auch danach füllen.

#### Template-Dateien (.tpl)
Zur eindeutigen Abgrenzung von `.html `verwendet Nino `.tpl`-Dateien. Diese liegen alle (!) in `/templates` und sind **reine Textdateien mit HTML-Inhalt** - kein PHP, keine Logik, keine eigene Syntax jenseits von Textfills (`[[key]]`) und Shortcodes (`[name]...[/name]`). Eine `.tpl`-Datei wird auf zwei Arten in die Auslieferung eingebunden:

- **1. als Route-Body** - `config.php`s `/nino/http/routes` referenziert eine `.tpl`-Datei direkt über den `Template`-Shortcode, z. B. `'body' => '[template /templates/page-home]'`.
- **2. als Include innerhalb eines anderen Templates** - derselbe `[template ...]`-Shortcode, z. B. `[template /templates/html-header]` am Anfang jeder Seite.

Für die Einbindung andere Template-Dateien innerhalb einer `.tpl` dient der
**Shortcode:**
`[template /pfad/datei]`
Das Beispiel bindet `/pfad/datei.tpl` (ohne Endung angegeben) ein und rendert sie. Aus technischen Gründen ruft  `Template` intern direkt `Callbacks::doCallbacks( $appData, '/nino/html/render', $html )` auf statt eines vollen `renderHtml()`-Durchlaufs.

Jede Seite folgt derselben **Konvention**:

```
[template /templates/html-header]

... Seiteninhalt (Sections) ...

[template /templates/html-footer]
```

`html-header.tpl` öffnet `<head>`/`<header>`/`<main>`, `html-footer.tpl` schließt `</main>` und ergänzt `<footer>`, Cookie-Banner, Preloader und die Asset-/Textfill-Skripte. Siehe "HTML-Aufbau" weiter unten für die vollständige Struktur.

### Wie werden Assets eingebunden

CSS und JS werden **nicht** einzeln per `<link>`/`<script>` eingebunden, sondern über ein in `config.php` definiertes Bundle:

```php
'/nino/html/assets' => [
  '/.cache/style.css' => [ '/_nino/Nino.css', '/assets/style.css' ],
  '/.cache/script.js' => [ '/_nino/Nino.js', '/_nino/Nino.ui.js', '/assets/script.js' ],
],
```

Jeder Schlüssel ist der Pfad der **erzeugten Cache-Datei**, der Wert die Liste der Quelldateien in Einbindungsreihenfolge. `[assets /.cache/style.css]` rendert daraus automatisch das passende `<link>`- oder `<script>`-Tag, abhängig von der Dateiendung. Beim ersten Request wird das Bundle einmal gebaut: Quelldateien werden zusammengefügt, dabei **ebenfalls durch die Textfill-/Shortcode-Rendering-Pipeline geschickt** (deshalb funktioniert `[[/ui/color-primary]]` auch innerhalb einer `.css`-Datei), bei `.min`-Dateinamen minifiziert, und unter dem Cache-Pfad abgelegt - danach liefert jeder weitere Request direkt die Cache-Datei.

**Reihenfolge ist die einzige Regel:** `_nino/Nino.css` muss vor dem projekteigenen Stylesheet stehen, damit dessen `:root`-Overrides in der Kaskade gewinnen (siehe CSS-Abschnitt unten). Es gibt keinen Build-Step, kein Bundler-Tooling, keine Kompilierung - nur diese eine Liste plus Dateisystem-Konkatenation.

Ein eigener Shortcode kann eigenes CSS/JS mitbringen, indem er die passende Datei einfach in dieses Array einträgt - es ist keine separate Registrierung nötig.

## HTML-Aufbau

### Die Nino-Struktur-Konvention

Jede Seite folgt derselben Verschachtelung, sichtbar am Beispiel `templates/.demo-sections.tpl`:

```
[template /templates/html-header]      Öffnet <head>, <header>, <main>

  <section class="ui-section ...">     Eine inhaltliche Sektion
    <div class="ui-grid-row ...">      Flex-Container, max-width + Padding
      <div class="ui-grid-100 ui-grid-m-50 ...">   Eine Spalte
        ... Inhalt (Überschrift, Text, Button, article, ...) ...
      </div>
    </div>
  </section>

  <section class="ui-section ...">     Nächste Sektion, gleiches Muster
    ...
  </section>

[template /templates/html-footer]      Schließt </main>, ergänzt <footer>
```

**Feste Regeln dieser Konvention:**

1. **`<section>` trägt immer `ui-section`** (oder `ui-atf` für einen Above-the-fold Seiteneinstieg) plus optional genau eine Farbvariante (`--dark`/`--black`/`--primary`/`--alt`) und optional `--fullwidth`. Sections werden nie ineinander verschachtelt.
2. **`<div class="ui-grid-row">` ist immer das direkte Kind von `<section>`** - nie Text oder ein `<article>` direkt im `<section>`, außer bei den JS-gesteuerten Vollbild-Varianten (`js-cover`, `js-parallex`), die stattdessen `<img>` + `.js-cover-content`-Wrapper direkt im `<section>` erwarten (siehe CSS-Klassen unten).
3. **Grid-Breiten-Klassen (`ui-grid-100`, `ui-grid-m-50`, ...) sitzen immer auf einem eigenen `<div>`**, nie direkt auf `<article>`. Ein `<article class="ui-article">` steht *innerhalb* eines Grid-Divs und trägt selbst nur `ui-article` und seine Modifier.
4. **Wiederkehrender Inhalt kommt immer über `[elements]`**, nie hartkodiert - siehe oben. Das Grid-Div steht dabei *innerhalb* der `[elements]...[/elements]`-Schleife, damit jedes Element seine eigene Spalte bekommt:
   ```
   <div class="ui-grid-row">
      [elements /demo-services limit="3"]
         <div class="ui-grid-100 ui-grid-m-33">
            <article class="ui-article">
		       <h4 class="ui-article-title">[[title]]</h4>
		       <p class="ui-article-descr">[[description]]</p>
		    </article>
         </div>
      [/elements]
   </div>
   ```
5. **Eine Section, die auf mehreren Seiten identisch vorkommt, kann als eigenes `templates/section-*.tpl`-Partial ausgelagert** und per `[template /templates/section-*]` eingebunden werden - derselbe Include-Mechanismus wie für `html-header`/`html-footer`. `section-contact.tpl` (Adresse + Kontaktformular) ist ein mögliches Beispiel dafür.

### Arbeit mit allen Shortcodes im Template

Ein realistischer Seitenausschnitt, der die Shortcodes aus der Einführung im Zusammenspiel zeigt:

```
[template /templates/html-header]

<section class="ui-atf ui-section--fullwidth js-cover js-cover--dim" data-cover-height="100">
  <img src="[[/nino/dir]]/images/.demo/demo-01.jpg">
  <div class="js-cover-content">
    <div class="ui-grid-row">
      <div class="ui-grid-100">
        <h2 class="ui-atf-title">[[/page-home/hero/title]]</h2>
        <p class="ui-atf-subtitle">[[/page-home/hero/subtitle]]</p>
        <a href="[[/webpage/contact/uri]]" class="ui-btn ui-btn--primary">[[/global/cta]]</a>
      </div>
    </div>
  </div>
</section>

<section class="ui-section">
  <div class="ui-grid-row">
    <div class="ui-grid-100 ui-text-center ui-mb-3">
      <h3 class="ui-section-title">[[/page-home/services/title]]</h3>
    </div>
    [elements /services limit="3"]
    <div class="ui-grid-100 ui-grid-m-33">
      <article class="ui-article">
        [image [[.uri]]/teaser alt="[[title]]"]
        <div class="ui-article-content">
          <h4 class="ui-article-title">[[title]]</h4>
          <p class="ui-article-descr">[[description]]</p>
        </div>
      </article>
    </div>
    [/elements]
  </div>
</section>

[template /templates/section-contact]

[template /templates/html-footer]
```

Diese Reihenfolge - Hero über `js-cover`, dann ein `elements`-Grid, dann ein ausgelagertes Section-Partial - deckt bereits `Elements`, `Images` und `Template` in Kombination mit der Textfill-Syntax ab. `Navigation`, `Localepicker`, `Jstext` und `Csrf` stehen bereits fertig verdrahtet in `html-header.tpl`/`html-footer.tpl` (siehe die Struktur-Konvention oben) und müssen auf einzelnen Seiten normalerweise nicht erneut aufgerufen werden - `Csrf` taucht direkt in einem eigenen Formular wieder auf, siehe das Newsletter-Beispiel im CSS-Abschnitt unten.

## CSS

### `var()`-Handling und das Konzept von `Nino.css`

Alle Design-Werte - Farben, Abstände, Schriftgrößen, Radien - liegen als native CSS Custom Properties in einem einzigen `:root { --token: value; }`-Block ganz am Anfang von `_nino/Nino.css` (Abschnitt "00 Theme").
Der Rest der Datei referenziert **ausschließlich** `var(--token)`, nirgendwo einen hartkodierten Farb- oder Abstandswert. Das hat einen einzigen, aber wichtigen Effekt: ein Projekt muss keine einzige Regel in `Nino.css` verändern, um sein eigenes Erscheinungsbild zu bekommen - es reicht, denselben Token-Namen in einem **später geladenen** `:root { }`-Block zu überschreiben. Der Browser löst `var()` erst zur Rechenzeit auf, über die normale CSS-Kaskade - kein Build-Step, keine Präprozessor-Variablen, keine Ladereihenfolge-Logik jenseits von "Projekt-Stylesheet nach `Nino.css`".

```css
/* assets/style.css - Beispiel für die wichtigsten Tokens */
:root {
  --color-primary: #4faae8;
  --color-primary-text: #ffffff;
  --color-text: #333333;
  --color-background: #ffffff;
  --fontfamily-text: 'Inter', sans-serif;
  --fontfamily-title: 'Inter', sans-serif;
  --space-1: .75rem;
  --space-2: 1.5rem;
  --radius: .5rem;
}
```

Sobald ein Token auch über `/_editor` editierbar bleiben soll (z. B. eine Akzentfarbe, die die Betreiberin selbst anpassen darf), wird er statt eines Literals als Textfill deklariert - `--color-primary: [[/ui/color-primary]];` - und landet damit im gleichen Fill-Mechanismus wie normaler Seiteninhalt. Alle übrigen `/ui/*`-Werte sind bewusst **nicht** im `/_editor`-Textpanel sichtbar (`text/blacklist.php`) - es sind Entwickler-Design-Tokens, kein Redaktionsinhalt.

> **Wichtig: `_nino/Nino.css` wird nicht geschrieben - sondern überschrieben.**
> `_nino/Nino.css` ist Kernel-Code, genau wie `_nino/Nino.php`. Es wird in einem Projekt **nie direkt bearbeitet** - jede Anpassung (Farben, Abstände, eigene Web-Fonts, zusätzliche Komponenten) gehört in eine eigene, projekteigene Stylesheet-Datei unter `assets/` (laut README-Konvention `assets/style.css`), die in `config.php`s `/nino/html/assets` **nach** `_nino/Nino.css` in dasselbe Bundle eingetragen wird:
> ```php
> '/.cache/style.css' => [ '/_nino/Nino.css', '/assets/style.css' ],
> ```
> Diese Reihenfolge ist die gesamte Mechanik: das eigene `:root { }` im Projekt-Stylesheet gewinnt in der Kaskade gegen die Default-Werte aus `Nino.css`, ohne dass dort auch nur eine Zeile angefasst wird. Dieses Repository selbst bindet zu Demonstrationszwecken drei austauschbare Theme-Dateien (`assets/style.theme01/02/03.css`) - das Prinzip bleibt in jedem Fall dasselbe: **eine eigene Datei, geladen nach dem Kernel-Stylesheet**, nie eine Änderung an `Nino.css` selbst. Ein Update des Kernels (`_nino/Nino.css` ersetzen) bleibt dadurch immer konfliktfrei möglich.

Es gibt bewusst **keine Multi-Theme-/Theme-Switching-Schicht** - ein Projekt hat genau einen aktiven Look, direkt im `:root`-Block seines eigenen Stylesheets gesetzt.

### Namenskonvention

Drei Präfixe, strikt getrennt nach Zuständigkeit:

| Präfix | Bedeutung |
|---|---|
| `ui-<block>` / `ui-<block>--<modifier>` / `ui-<block>-<element>` | BEM-artig, für alles rein CSS-Bezogene (Sections, Buttons, Artikel, Grid) |
| `ui-<property>-<value>` | Utilities (Abstand, Textausrichtung, Opacity) |
| `js-<block>` | JS-Verhalten, strikt getrennt von `ui-*`-Styling - ein Theme kann frei umstylen, ohne je Verhalten zu riskieren, und der Kernel-JS-Code verlässt sich nie auf einen Namen, den ein Theme umbenennen könnte |
| `sc-<block>` | Markup, das ein Kernel-Shortcode selbst erzeugt (`[navigation]`, `[localepicker]`) - dieses HTML ist fest mit `_nino/Nino.php` verdrahtet und nicht projektseitig anpassbar |

Diese Trennung ist auch die Richtschnur für eigene Shortcodes/Komponenten: eigenes Verhalten → `js-*`, eigenes Styling → `ui-*`, `sc-*` bleibt den Kernel-Shortcodes vorbehalten.

### Die Nino CSS-Klassen

#### Rahmen & Struktur

| Klasse | Notiz |
|---|---|
| `ui-grid-row`, `ui-grid-25/33/50/66/75/100` | Flexbox-Grid, mobile-first. Breakpoints `-s-`/`-m-`/`-l-`/`-xl-` bei 640/768/1024/1280px, `ui-grid--fullwidth` entfernt das Row-Padding |
| `ui-grid-top/-middle/-bottom`, `-center` | Vertikale/horizontale Ausrichtung innerhalb der Row |
| `header`, `.ui-logo`, `footer`, `.ui-footer-main/-legal/-title/-logo/-getintouch/-localepicker` | Grundgerüst von Kopf-/Fußbereich - siehe HTML-Aufbau oben für die vollständige Verschachtelung |
| `js-scroll-header`, `body.js-scroll-atf/-btf/-up/-down` | Header blendet je nach Scroll-Position/-Richtung aus/ein; die Scroll-Zustandsklassen landen unbedingt auf `body`, damit auch andere Elemente (z. B. Back-to-top) darauf reagieren können |
| `ui-atf`, `ui-atf--fullscreen` | Seiteneinstieg (Hero). `--fullscreen` (`min-height:100vh`) ist die reine CSS-Variante ohne Bild; `js-cover`/`js-parallex` erreichen denselben Effekt bildbasiert per JS |
| `ui-section`, `--dark/--black/--primary/--alt/--fullwidth`, `--border-1/2/3` | Jede Farbvariante setzt zusätzlich eine passende Link-Farbe (`a:not(.ui-btn)`) |

**Beispiel - 50/50-Grid, Bild randlos bis zum Viewport-Rand:**

```html
<section class="ui-section ui-section--fullwidth">
  <div class="ui-grid-row ui-grid--fullwidth ui-grid-middle">
    <div class="ui-grid-100 ui-grid-m-50 ui-img-cover">
      <img src="..." style="height:480px;">
    </div>
    <div class="ui-grid-100 ui-grid-m-50">
      <article class="ui-article">
        <div class="ui-article-content">...</div>
      </article>
    </div>
  </div>
</section>
```

#### Navigation

| Klasse | Notiz |
|---|---|
| `sc-nav-wrap`, `-burger`, `-regular`, `-content`, `-bg` | Ausgabe von `[navigation]` - die Burger-Variante ist ein Vollbild-Overlay, `regular` rendert inline (z. B. im Footer) |
| `ui-headernav-logo` | Logo-Bild innerhalb des Burger-Overlays |
| `sc-localepicker-wrap`, `-bg` | Ausgabe von `[localepicker]` |

```html
[navigation burger]
  [[/webpage/home/uri]]:[[/webpage/home/name]]
  [[/webpage/contact/uri]]:[[/webpage/contact/name]]
[/navigation]
```

#### ATF/Hero + Cover/Parallax

| Klasse | Notiz |
|---|---|
| `js-cover(--dim)`, `data-cover-height`/`-width` | `data-cover-height="100"` für einen Vollbild-Hero. `--dim` legt einen dunklen Scrim über das Bild - nötig, sobald ein Hero ein echtes (helles) Foto statt eines dunklen Platzhalters nutzt, damit `ui-atf-title`/`-subtitle` in Weiß lesbar bleiben |
| `js-parallex(--dim)` | Gleicher `--dim`-Scrim, Parallax-Scroll-Effekt statt fixem Cover |
| `ui-atf-title`, `-subtitle`, `-arrowdown` | Typografie des Heros; `-arrowdown` ist ein `currentColor`-SVG als `background-image` - passt sich also automatisch der Textfarbe an, kein Icon-Markup nötig |

```html
<section class="ui-atf ui-section--fullwidth js-cover js-cover--dim" data-cover-height="100" style="color:var(--color-primary-text);">
  <img src="...">
  <div class="js-cover-content">
    <h2 class="ui-atf-title">...</h2>
    <p class="ui-atf-subtitle">...</p>
  </div>
</section>
```

Das inline `color` ist der garantierte Weg, Hero-Text über einem `--dim`-Scrim lesbar zu halten - `Nino.css` stylt nur den Scrim selbst (`::before`), nicht den Text.

#### Artikel/Card (immer über `elements`)

| Klasse | Notiz |
|---|---|
| `ui-article`, `--alt`, `--fullwidth`, `-cols`/`-cols-s/m/l/xl`, `-price` | `-price` für ein bepreistes Produkt als normale Artikel-Grid-Karte - anders als `ui-pricing-price` (dedizierte Preistabelle, siehe unten) |
| `ui-article-content`, `-title`, `-subtitle`, `-descr`, `-img`, `-img--maxheight` | |

**Grid-Breiten-Klassen niemals direkt auf `<article>`** - siehe HTML-Aufbau, Regel 3:

```html
[elements /portfolio]
<div class="ui-grid-100 ui-grid-m-33">
  <article class="ui-article">
    <div class="ui-article-content">
      <h4 class="ui-article-title">[[title]]</h4>
      <p class="ui-article-descr">[[description]]</p>
    </div>
  </article>
</div>
[/elements]
```

#### Buttons / Icons

| Klasse | Notiz |
|---|---|
| `ui-btn`, `--primary/--outline/--light/--dark/--big/--small` | `--big`/`--small` skalieren Padding/Schriftgröße/Radius per `calc()` von derselben Basis |
| `ui-icon`, `.small` | |

```html
<a href="#" class="ui-btn ui-btn--primary">Jetzt starten</a>
<svg class="ui-icon small" ...>...</svg>
```

#### Formular

| Klasse | Notiz |
|---|---|
| `ui-form-input/-textarea/-select`, `-message` | Reines Feld-Styling |
| `ui-form`, `.error/.success/.existing/.pending` | Generisches Handling in `Nino.ui.js` - postet automatisch an `POST /` (`Modules\Form`), kein Shortcode nötig |
| `js-newsletter-form` | Eigener Submit-Handler statt der generischen `ui-form`-Behandlung (Neu-/Bereits-angemeldet brauchen unterschiedliche Meldungen), postet an `/.newsletter` |

```html
<form class="ui-form">
  [csrf]
  <label for="name">Name</label>
  <input type="text" id="name" name="name" class="ui-form-input" required>
  <textarea name="message" class="ui-form-textarea" required></textarea>
  <p class="ui-form-message"></p>
  <button type="submit" class="ui-btn ui-btn--primary ui-form-submit">Senden</button>
</form>
```

#### Badge, Alert, Breadcrumbs, Liste

| Klasse | Notiz |
|---|---|
| `ui-badge`, `--pill/--primary/--success/--error` | |
| `ui-alert`, `--success/--error/--info` | Statisches Banner für beliebige Seitenmeldungen - anders als `.ui-form-message` (Formular-Feedback) |
| `ui-breadcrumbs` | `›`-Trenner via `::after`, letztes `li` als aktuelle Seite gestylt |
| `ui-list`, `--check`, `--numbered` | `--check`/`--numbered`-Marker sind `::before` (Unicode-Haken bzw. CSS-`counter()`), kein Bild/SVG |

```html
<span class="ui-badge ui-badge--primary">Neu</span>
<div class="ui-alert ui-alert--info">Hinweistext</div>
<ul class="ui-breadcrumbs">
  <li><a href="#">Start</a></li>
  <li>Aktuelle Seite</li>
</ul>
<ul class="ui-list ui-list--check">
  <li>Persönliche Erstberatung inklusive</li>
</ul>
```

#### Tabelle, Preistabelle, Accordion, Pagination

| Klasse | Notiz |
|---|---|
| `ui-table-wrap` > `ui-table`, `--striped`, `--bordered` | `-wrap` scrollt eine breite Tabelle horizontal statt die Seite zu sprengen. `--striped` tönt Zeilen mit `--color-section-alt-bg` - deshalb auf einer normalen `ui-section`, nicht `--alt`, einsetzen |
| `ui-pricing-row`, `ui-pricing-item(--featured)`, `-title`, `-price` | Kein festes Markup/Shortcode - Karten-Styling, gedacht zum Umschließen einer `[elements]`-Schleife |
| `ui-accordion`, `-trigger`, `-panel` | Reines `<details>`/`<summary>`, kein JS - mehrere `<details>` mit gleichem `name="..."` verhalten sich nativ als exklusive Gruppe |
| `ui-pagination` | Reine Seitenzahlen-Links, kein JS - anders als die Slider-Dot-Pagination (`js-slider-points`) |

```html
<div class="ui-pricing-row">
  [elements /pricelist query="cat=standard"]
  <div class="ui-pricing-item">
    <h4 class="ui-pricing-title">[[title]]</h4>
    <p class="ui-pricing-price">[[price]] &euro;</p>
  </div>
  [/elements]
</div>

<details class="ui-accordion" name="faq" open>
  <summary class="ui-accordion-trigger">Frage?</summary>
  <div class="ui-accordion-panel"><p>Antwort.</p></div>
</details>
```

#### Logo-Leiste, Galerie, Feature-Liste, Timeline, Video-Poster

| Klasse | Notiz |
|---|---|
| `ui-logos`, `-item` | Text-Platzhalter stehen für echte Logo-SVGs/PNGs - Graustufen, Vollfarbe auf Hover |
| `ui-gallery`, `-item`, `--wide`, `--tall` | CSS-Grid, feste `grid-auto-rows`, einzelne Kacheln per Modifier verbreitert/erhöht |
| `ui-feature-list`, `-item` | Icon + Überschrift + Text, gedacht für eine Hälfte des 50/50-Grids (Bild in der anderen Hälfte) |
| `ui-timeline`, `-step`, `-number` | Nummerierte Kreise, per Linie verbunden (nur ab dem `m`-Breakpoint) |
| `ui-video-poster`, `-play` | Statisches Poster-Bild mit Play-Icon, öffnet den echten `.ui-video`-Embed im `js-modal--video`-Lightbox |
| `ui-video`, `--4-3` | Responsiver iframe/video-Wrapper, 16:9 als Default |

```html
<div class="ui-gallery">
  <div class="ui-gallery-item ui-gallery-item--wide">
    <button type="button" class="js-modal-trigger" data-modal-target="galerie-1" aria-label="Bild vergrößern">
      <img src="..." loading="lazy">
    </button>
  </div>
</div>
```

#### JS-gesteuertes Verhalten (`Nino.ui.js`)

| Klasse | Notiz |
|---|---|
| `js-vpa`, `--repeat`, Effekt `--zoom(-out)/-slide-left/-right/-blur/-flip` × `-soft/-medium/-hard`, `--speed-fast/-medium/-slow` | Viewport-Animation. Effekt/Speed sind reines CSS (Custom Properties `--vpa-*`) - keine JS-Änderung nötig, jede Klasse setzt nur diese Variablen und kann daher auch vererbt auf `<body>` gesetzt werden. Demo: `/.demo-vpa` |
| `ui-autoheight`, `data-autoheight-group` | Bewusst `ui-*` (Styling-/Markup-Anliegen, kein Verhaltens-Toggle) |
| `js-slider`, Touch-Swipe, `js-slider-button`, `js-slider-points` | Prev/Next-Buttons und Dot-Pagination werden von JS erzeugt, nicht handgeschrieben |
| `js-preloader` | Vollbild-Spinner-Overlay, entfernt sich bei `window.load` |
| `js-tabs`, `-nav`, `-tab`, `-panel`, `data-tabs-target` | Panel-Wechsel blendet per `@starting-style` ein (reines CSS), Fallback auf Sofort-Anzeige in älteren Browsern |
| `js-modal`, `--lightbox`, `--video`, `js-modal-trigger`, `-close`, `data-modal-target` | Baut auf dem nativen `<dialog>` (`showModal()`/`.close()`) - Fokus-Trap, Escape-Close und Backdrop-Klick kommen dadurch vom Browser |
| `js-toast-trigger`, `data-toast-message`/`-type`, oder `Nino.ui.toast(message, type)` | Inline `onclick` funktioniert nicht (CSP `script-src 'self' 'nonce-...'`) - `js-toast-trigger` ist der deklarative, CSP-sichere Weg aus statischem Markup |
| `js-stat-counter`, `data-stat-counter-to/-suffix/-duration` | Zählt beim Scrollen in den Viewport von 0 hoch |
| `js-back-to-top` | Sichtbarkeit rein CSS (`body.js-scroll-btf`), JS nur für den Klick (`scrollTo` smooth) |
| `js-cookie-banner`, `-actions` | Bottom-Bar statt Vollbild-Overlay. Zustand in `Nino.cookie` unter `nino_consent`; `Nino.ui.cookieConsent.get()`/`.isAccepted()`/`.set()` ist die öffentliche API, gegen die ein eigenes Analytics-Script prüfen sollte, bevor es lädt |

```html
<div class="js-tabs">
  <div class="js-tabs-nav">
    <button type="button" class="js-tabs-tab active" data-tabs-target="tab-1">Leistungen</button>
    <button type="button" class="js-tabs-tab" data-tabs-target="tab-2">Preise</button>
  </div>
  <div class="js-tabs-panel active" id="tab-1"><p>...</p></div>
  <div class="js-tabs-panel" id="tab-2"><p>...</p></div>
</div>

<button type="button" class="js-modal-trigger" data-modal-target="video-modal">Video abspielen</button>
<dialog class="js-modal js-modal--video" id="video-modal">
  <button type="button" class="js-modal-close" aria-label="Schließen">&times;</button>
  <div class="ui-video"><iframe src="..." allowfullscreen></iframe></div>
</dialog>

<button type="button" class="js-toast-trigger" data-toast-message="Gespeichert." data-toast-type="success">Speichern</button>
```

```js
// oder imperativ, z. B. nach einem eigenen XHR-Aufruf - aus assets/script.js,
// nicht aus einem inline <script>-Tag (der CSP-Nonce gehört zum jstext-Shortcode)
Nino.ui.toast( 'Gespeichert.', 'success' );
```

#### Utilities

| Klasse |
|---|
| `ui-m-/-mt-/-mb-/-ml-/-mr-/-p-/-pt-/-pb-0..6` (Abstand, `--space-1..6`) |
| `ui-text-left/-center/-right`, Breakpoint-Präfixe `-s-/-m-/-l-/-xl-` |
| `ui-font-small`/`-big` |
| `ui-opacity-10..90` |
| `ui-hidden`, `ui-invisible`, `ui-sr-only`, `ui-hidden-*`/`ui-visible-*` (Breakpoint-abhängig) |
| `ui-img-cover`, `ui-img-background(--dim)`, `-content` |

```html
<div class="ui-mt-3 ui-p-2 ui-text-center ui-font-big">...</div>
<div class="ui-img-background ui-img-background--dim ui-text-center">
  <img src="...">
  <div class="ui-img-background-content">...</div>
</div>
```

## Referenz: Shortcodes

#### `Elements`
Einbinden einzelner oder mehrerer Elemente in ein Template.
**Shortcode:**
`[element /typ/uri locale="xx" callback="..."]...[[key]]...[/element]`
Rendert den umschlossenen Inhalt einmal für ein einzelnes Element; `[[key]]`-Platzhalter darin werden durch die Feldwerte des Elements ersetzt (`locale`/`callback` optional).
`[elements /typ limit="N" query="key=value" locale="xx" callback="..."]...[[key]]...[/elements]`
Wiederholt den Inhalt für jedes Element eines Typs - optional gefiltert per `query` (`key=value`, `&`-getrennt für mehrere Bedingungen), begrenzt per `limit`. Zusätzlich zu den Feldwerten steht in der Schleife `[[.id]]` (laufender Index) und `[[.uri]]` (die volle Element-Uri) zur Verfügung.

#### `Template`
`[template /pfad/datei]` bindet eine weitere `.tpl`-Datei ein.

#### `Images`
Einbinden eines entwicklerdefinierten Image-Slots.
**Shortcode:**
`[image slotUri alt="..."]`
Rendert ein `<img>`-Tag für einen Bild-Slot, sofern über `/_editor` bereits ein Bild hochgeladen wurde - sonst bleibt die Ausgabe leer. Die verfügbaren Slots (Position, Zielformat) werden in `/_admin` definiert.

#### `Assets`
CSS/JS-Bundle einbinden.
**Shortcode:**
`[assets /.cache/style.css]`
Bindet die für dieses Bundle per `config.php`s `/nino/html/assets` gesammelten Dateien gebündelt (und bei `.min`-Dateinamen minifiziert) als `<link>`- bzw. `<script>`-Tag ein.

#### `Navigation`
Renderer für Navigationsmenüs (Burger- oder Standard-Variante) aus einer zeilenbasierten Mini-Syntax.
**Shortcode:**
`[navigation id="..." class="..." callback="..." burger]uri:Titel:Attribute[/navigation]`
Baut aus zeilenweisen `uri:titel:attribute`-Einträgen im Inhalt eine `<ul>`-Navigation. Eine Inhaltszeile **ohne** `:` wird stattdessen als rohes `<div>` gerendert - so lässt sich z. B. ein Logo zwischen die Link-Zeilen mischen (im Burger-Menü bleibt dieses `<div>` versteckt, solange das Overlay geschlossen ist). Das Flag `burger` (ohne `=`) rendert die Vollbild-Burger-Variante statt der Standard-Variante; die aktuell aktive Uri bekommt automatisch `class="active"`.

#### `Localepicker`
Fertiger Sprachumschalter inklusive Redirect-Handling.
**Shortcode:**
`[localepicker callback="..."]`
Rendert die Sprachauswahl-UI mit allen verfügbaren Locales, aktuelle Locale als aktiver Eintrag markiert (`callback` optional). Der Umschalter selbst läuft über den Query-Parameter `/_nino/locales/current`, den `Localepicker::callbackResponse()` auf jeder Response prüft und dann - über die interne Response-Weiche statt eines direkten `header('Location: ...')` - zur äquivalenten Route in der Zielsprache umleitet (`Http::findRouteUri()`, nicht dieselbe URL mit anderem Locale-Flag - `/legal` und `/rechtliches` können also unterschiedliche URLs derselben Seite in unterschiedlichen Sprachen sein).

#### `Jstext`
Stellt die aktuellen Textfills als JSON auch dem Frontend-JavaScript zur Verfügung.
**Shortcode:**
`[jstext]`
Rendert ein `<script>`-Tag (CSP-Nonce-abgesichert), das alle aktuellen Textfills als `NinoJstext`-Objekt bereitstellt. Gehört einmal pro Seite direkt vor die Asset-Einbindung in `html-footer.tpl`.
**Javascript:**
`Nino.content.getText( key )`
Liest einen darüber bereitgestellten Text aus (`window.NinoJstext`), z. B. `Nino.content.getText('/form/info/success')`. Liefert `''`, falls der Key nicht existiert.

#### `Csrf`
CSRF-Schutz für Formulare per Session-Token.
**Shortcode:**
`[csrf]`
Rendert ein verstecktes `_csrf`-Inputfeld mit dem aktuellen Session-Token - gehört in jedes Formular, das serverseitig per Csrf-Callback geprüft wird (z. B. das Newsletter-Formular, siehe HTML-Aufbau unten).

Diese acht Shortcodes decken alles ab, was eine Template-Autorin direkt in eckigen Klammern schreibt. Kernel-Module ohne eigenen Shortcode (`Form`, `Newsletter`) werden stattdessen über eine feste CSS-Klasse (`ui-form` bzw. `js-newsletter-form`) angesprochen - siehe `docs/development.de.md`s Modul-Referenz sowie den CSS-Abschnitt oben.
