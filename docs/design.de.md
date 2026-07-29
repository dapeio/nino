# Phile — Design-Handbuch

**Links:** [README](../README.de.md) · [English](design.md) · [Entwickler-Handbuch](development.de.md) ([English](development.md)) · [Admin-Handbuch](admin.de.md) ([English](admin.md)) · [Changelog](../CHANGELOG.md)

Katalog der eingebauten Frontend-Bausteine, keine externen Bibliotheken.
Ergänzt `_phile/Phile.css`/`_phile/Phile.js`/`_phile/Phile.ui.js` sowie
`docs/development.de.md` (wie die Rendering-Pipeline unter der Haube
funktioniert — dieses Dokument setzt das voraus und beschreibt die
Design-Seite davon: welche Mechanismen ein Template-Autor tatsächlich
anfasst, und was das eingebaute UI-Kit an Bausteinen mitbringt).

## Teil 1: Designrelevante Mechanismen

Alles Sichtbare auf einer Phile-Seite entsteht aus vier Mechanismen, die
sich kombinieren lassen. `docs/development.de.md` beschreibt sie aus
Framework-Sicht (Klassen, Callbacks, Datenfluss); hier geht es darum,
was sie für eine Template-Autorin praktisch bedeuten.

### Textfills — `[[key]]`

Feste Textstellen, verwaltbar über `/_admin` → "Texte". Ein Fill wird
per einfachem `str_replace()` gegen den Textbestand der aktuellen
Locale ersetzt — kein Escaping, kein Markdown, keine Logik. Ein Fill
kann selbst wieder `[[andererKey]]` enthalten (die Ersetzungsschleife
löst das rekursiv auf), das ist das Muster, das `text/global.php` für
Design-Tokens nutzt, die sich gegenseitig referenzieren (z. B. eine
Akzentfarbe, die an mehreren Stellen wiederverwendet wird).

**Zwei Nuancen, die beim Templatebau öfter überraschen:**

- Ein Fill, der in keiner `text/*.php`-Datei existiert, wird **nicht**
  ersetzt — er bleibt als sichtbarer `[[/webpage/xyz/description]]`-
  String im gerenderten HTML stehen. Es gibt keinen Fallback-Mechanismus.
  Jede neu geroutete Seite braucht ihre `/title`- und
  `/description`-Fills in **allen** aktiven Locale-Dateien, sonst
  bricht die Meta-Description sichtbar.
- Rein technische Werte (Farben, Maße, `/ui/*`-Tokens) landen zwar auch
  über den Fill-Mechanismus, tauchen aber absichtlich **nicht** im
  `/_admin`-Text-Panel auf — sie stehen in `text/blacklist.php` und
  sind damit vor versehentlicher Bearbeitung durch Redakteure
  geschützt, bleiben aber für Entwickler in `text/global.php` weiter
  editierbar.

### Shortcodes — `[name arg]content[/name]`

Alles, was mehr als reinen Text braucht (eine Schleife über Inhalte,
bedingtes Rendering, ein eingebettetes Sub-Template), ist ein
Shortcode. Anders als Fills sind Shortcodes **Callback-Aufrufe** — jeder
Shortcode-Name ist letztlich `Callbacks::doCallbacks( $appData,
'/phile/html/shortcode/<name>', $args )`, und der Rückgabewert des
Handlers wird **rekursiv erneut gerendert** (Fills und weitere
Shortcodes darin werden also auch aufgelöst). Das ist der Grund, warum
`[template x]` funktioniert, ohne dass `x.tpl` selbst noch einmal
manuell durch die Rendering-Pipeline geschickt werden muss — und
gleichzeitig der Grund, warum ein `[template x]` *innerhalb* von
`x.tpl` in eine Endlosschleife läuft (siehe die "Gotcha"-Warnung unten
für eine verwandte Falle).

### Elemente — wiederkehrender Inhalt

Ein Elementtyp (z. B. `/services`, `/team`, `/pricelist`) ist ein vom
Entwickler in `/_dev` definiertes Feldmodell; die Daten pro Sprache
pflegt eine Redakteurin über `/_admin` → "Elemente". Im Template werden
Elemente nie hartkodiert, sondern immer über die Shortcodes `[element
uri="..."]` (ein einzelnes) oder `[elements uri="..." query="..."]`
(eine gefilterte Liste, einmal pro Treffer gerendert) eingebunden — der
umschlossene Inhalt ist dabei ein kleines, lokales Mini-Template, in dem
`[[title]]`, `[[description]]` etc. die Feldwerte **dieses konkreten
Elements** sind, nicht globale Textfills. Diese Ähnlichkeit in der
Syntax (`[[key]]` sieht identisch aus, egal ob es ein globaler Textfill
oder ein Element-Feld ist) ist beabsichtigt, aber die Auflösung
passiert an völlig unterschiedlichen Stellen der Pipeline — siehe
`docs/development.de.md`s Templating-Abschnitt für die genaue
Reihenfolge.

**Faustregel für dieses Repository selbst:** Artikel/Cards werden immer
über den `elements`-Shortcode gebaut, nie mit im Template hartkodiertem
Inhalt — selbst auf den Demo-Seiten. `page-home.tpl`s Leistungs- und
Portfolio-Abschnitte folgen dem ebenfalls (getragen von den
Elementtypen `services`/`portfolio`) statt handgeschriebener
`<article>`-Blöcke.

### Locales — mehrsprachiger Inhalt

Jede Route kann eine eigene Locale tragen (`config.php`s
`/phile/http/routes`, Key `locale`); `[[key]]`-Fills lösen sich immer
gegen die *aktuelle* Locale auf, Elemente tragen ihre Felddaten pro
Locale getrennt in derselben Datei. Der Sprachwechsel selbst läuft über
zwei Widgets, die beide denselben sicheren Redirect-Mechanismus nutzen
(siehe `docs/development.de.md`s Lifecycle-Abschnitt für die Details, warum
das kein simpler `header('Location: ...')`-Aufruf sein darf):

- `[localepicker]` — ein fertiges Sprachwechsel-Widget
  (`sc-localepicker-*`-Klassen, siehe unten)
- Der `/_phile/locales/current`-Query-Parameter, den `Locales::request()`
  direkt auswertet — für einen eigenen, nicht vom Kernel gestalteten
  Sprachumschalter

Beide finden über `Http::findRouteUri()` die *äquivalente* Route in der
Zielsprache (nicht einfach dieselbe URL mit anderem Locale-Flag) —
`/legal` und `/rechtliches` können also unterschiedliche URLs für
dieselbe Seite in unterschiedlichen Sprachen sein.

## Teil 2: Core-Inhalte im Detail

### Architektur

```
_phile/Phile.css       Core: Reset, Grid, Sections, Buttons, ui-*-Verhaltens-
                        Support-CSS. Design-Tokens (Farbe/Abstand/Typo/Radius)
                        leben in einem :root { --token: value; }-Block ganz
                        oben in der Datei — nie als hartkodierte Werte über
                        die restliche Datei verstreut.
_phile/Phile.js         Core: Client-Erkennung, Cookies, DOM-ready/-resize/
                        -scroll-Events, XHR, Auth, Jstext. Wird sowohl von
                        _admin/_dev als auch der öffentlichen Site genutzt.
_phile/Phile.ui.js      Core, nur öffentliche Site: Cover, Parallax, VPA,
                        Autoheight, Slider, Scroll-Header, generisches
                        .ui-form-Handling. Nie von _admin/_dev referenziert —
                        bewusst ausgelagert, damit deren Bundle schlank bleibt.

assets/style.theme01.css  Theme: projektspezifische Overrides. Ein Projekt
assets/script.js           überschreibt die benötigten Tokens im eigenen
                            :root { ... }-Block (siehe _phile/Phile.css'
                            "00 Theme"-Abschnitt) — CSS Custom Properties
                            lösen sich zur Rechenzeit über die Kaskade auf,
                            es braucht also keine gesonderte Ladereihenfolge/
                            Build-Schritt-Logik, nur Laden nach Phile.css.
                            Welche Theme-Datei je Projekt aktiv ist, steuert
                            config.php (/phile/html/assets).

text/global.php         Enthält nur die Handvoll /ui/*-Fills, die entweder
                        weiterhin _admin-editierbar sein müssen, oder die ein
                        Kontext ohne eigenes Stylesheet (z. B.
                        templates/mail-header.tpl, ein in sich geschlossenes
                        E-Mail-Dokument) als Literalwert braucht. Ein Token
                        lässt sich jederzeit als --token: [[/phile/path]]; in
                        einem :root-Block statt eines Literalwerts anlegen,
                        sobald ein Projekt es admin-editierbar haben will —
                        derselbe Fill-Mechanismus, den auch Textinhalt nutzt.
                        Jeder noch vorhandene /ui/*-Key ist standardmäßig
                        (text/blacklist.php) aus dem Admin-Text-Panel
                        ausgeblendet, da es Entwickler-Design-Tokens sind,
                        keine Seiteninhalte.
```

Ladereihenfolge ist wichtig: `_phile/Phile.css` vor
`assets/style.theme01.css`, damit die `:root`-Overrides eines Projekts
die Kaskade gewinnen. Registriert in `config.php`s
`/phile/html/assets`.

Es gibt absichtlich keine Multi-Theme-/Theme-Switching-Schicht — ein
Projekt hat genau einen aktiven Look, direkt im eigenen
`assets/style*.css`s `:root`-Block gesetzt (plus `text/global.php` für
das seltene Token, das weiterhin _admin-editierbar bleiben soll). Eine
frühere Version dieses Frameworks hatte eine (ein
`/theme/<name>/`-Verzeichnis + ein `/phile/theme/fills`-Config-Key,
vor `global.php` gemergt), vor dem Alpha-Release entfernt: es war
Entwickler-Tooling, das als Feature getarnt war — kein admin-seitiger
Umschalter, nie ein echtes zweites Theme ausgeliefert — und ungenutzt
dort herumzuliegen wirkte eher unfertig als flexibel. Eine
Entwicklerin, die zwei Looks vergleichen will, kann das weiterhin von
Hand tun (zwei Kopien von `assets/style*.css`, tauschen und diffen),
ganz ohne Framework-Unterstützung dafür.

### Namenskonvention

- `ui-<block>` / `ui-<block>--<modifier>` / `ui-<block>-<element>` —
  BEM-artig, für alles rein CSS-Bezogene (Sections, Buttons, Artikel,
  Grid).
- `ui-<property>-<value>` — Utilities (Abstand, Textausrichtung,
  Opacity).
- `js-<block>` — JS-Verhaltens-Hooks, getrennt vom `ui-*`-Styling,
  damit ein Theme frei umgestalten kann, ohne je Verhalten zu
  riskieren, und Core-JS sich nie auf einen Namen verlässt, den das
  Theme umbenennen könnte. **Umgesetzt**: `cover`, `parallex`,
  `slider`, `vpa` sind `js-*` (`Phile.ui.js` sucht nach
  `.js-cover`/`.js-parallex`/`.js-slider`/`.js-vpa`, CSS matcht
  dieselben Klassen). `autoheight` und das generische
  Kontaktformular-Handling blieben bewusst `ui-*` (`.ui-autoheight`,
  `.ui-form`) — sie sind eher Markup-/Styling-Belange als
  Verhaltens-Toggles, daher nicht umbenannt.
- `sc-<block>` — von Kernel-Shortcodes generiertes Markup
  (`[navigation]`, `[localepicker]`), da dieses HTML fest mit
  `_phile/Phile.php` selbst ausgeliefert wird und nicht
  projektanpassbar ist. Sowohl die PHP-Templates dieser beiden
  Shortcodes als auch ihr CSS nutzen dieses Präfix (`sc-nav-wrap`,
  `sc-nav-content`, `sc-localepicker-wrap`, ...).

### Bausteine

Jeder Baustein unten ist vollständig fertig verdrahtet.

**Rahmen/Struktur**

| Block | Hinweise |
|---|---|
| Grid (`ui-grid-row`, `ui-grid-25/33/50/66/75/100`, `-s-/-m-/-l-/-xl-`-Breakpoints) | Breakpoints bei 640/768/1024/1280px |
| Reset | Systemschriftart-Stack als Default (`html { font-family: ... }` in `_phile/Phile.css`) — kein mitgeliefertes Font-File pro Projekt nötig. Ein Theme-Stylesheet kann trotzdem eigene `@font-face`/`font-family`-Regeln mitbringen (`assets/style.theme01.css` tut genau das) |
| Navigation (Burger + regulär) | `Navigation::doShortcode()`s Ausgabe und CSS nutzen beide das `sc-*`-Präfix (`sc-nav-wrap`, `sc-nav-burger`, `sc-nav-regular`, `sc-nav-content`, `sc-nav-bg`). Eine `[navigation]`-Inhaltszeile ohne `:` rendert als schlichtes `<div>` statt eines Nav-`<li>` (`Navigation::$html['div']`) — erlaubt, beliebiges Markup (z. B. ein Logo-Bild) zwischen `uri:title`-Link-Zeilen zu mischen. Innerhalb der Burger-Variante sind diese zusätzlichen `<div>`s versteckt, solange das Overlay geschlossen ist |
| ATF/Hero (`ui-atf(--fullscreen)`, `ui-cover`) | `--fullscreen` (`min-height:100vh`, flex-zentriert) ist die reine-CSS-Fullscreen-Hero-Variante für einen bildlosen `ui-atf`; `js-cover`/`js-parallex` erreichen denselben Effekt über `data-cover-height="100"`, da sie ohnehin bereits per JS dimensioniert werden |
| Sections (`ui-section`, `--dark/--black/--primary/--alt/--fullwidth`, `--border-1/2/3`) | Jede Farbvariante setzt zusätzlich einen normalen `a:not(.ui-btn)`-Linkfarbwert — `a { color: inherit }` allein ist nicht spezifisch genug, um zuverlässig gegen andere `a`-Regeln in der Kaskade zu gewinnen |

**JS-getriebenes Verhalten (`Phile.ui.js`)**

| Block | Hinweise |
|---|---|
| Viewport-Animation (`js-vpa`, `--repeat`, Effekt `--zoom`/`--zoom-out`/`--slide-left`/`--slide-right`/`--blur`/`--flip` × `-soft`/`-medium`/`-hard`, Geschwindigkeit `--speed-fast`/`-medium`/`-slow`) | Effekt-/Geschwindigkeitsvarianten sind reines CSS (Custom Properties), keine JS-Änderung nötig. Demo: `/demo-vpa` |
| Autoheight (`ui-autoheight`, `data-autoheight-group`) | Behielt bewusst das `ui-*`-Präfix (siehe Namenskonvention) — eher ein Markup-/Styling-Belang als ein Verhaltens-Toggle |
| Cover (`js-cover(--dim)`, `data-cover-height/-width`) | `data-cover-height="100"` für eine Fullscreen-Hero. `--dim` legt einen dunklen Schleier darüber (siehe Utilities/Img-Tools' `ui-img-background--dim` für dieselbe Idee auf einem statischen Bild) — nötig, sobald eine Hero ein echtes (helles) Foto statt eines dunklen Platzhalters nutzt, damit der weiße `ui-atf-title`/`-subtitle`-Text lesbar bleibt |
| Parallax (`js-parallex(--dim)`) | Derselbe `--dim`-Schleier wie `js-cover`, gleicher Grund |
| Preloader (`js-preloader`) | Fullscreen-Spinner-Overlay, entfernt bei `window.load`. Markup liegt in `templates/html-footer.tpl` direkt vor `[jstext]` |
| Slider (`js-slider`, Touch-Swipe, Vor/Zurück `js-slider-button`s) | Vor-/Zurück-Buttons und Dot-Pagination (`js-slider-points`) werden von JS erzeugt, nicht handgeschrieben |
| Scroll-Header (`js-scroll-header`, `body.js-scroll-atf/-btf/-up/-down`) | Blendet den Header abhängig von Scrollposition/-richtung ein/aus. Die Scroll-Status-Klassen auf `body` werden bedingungslos gesetzt (nicht nur wenn `.js-scroll-header` existiert), damit andere Elemente — z. B. Back-to-Top — sich ebenfalls daran einhängen können |
| Back-to-Top-Button (`js-back-to-top`) | Sichtbarkeit ist reines CSS, getrieben von `body.js-scroll-btf` (erscheint nach dem Scrollen über die Fold); JS behandelt nur den Klick (`window.scrollTo` smooth) |
| Hero-Scroll-Pfeil (`ui-atf-arrowdown`, `data-arrow-target`) | Der Chevron ist ein `currentColor`-SVG als `background-image` (siehe `_phile/Phile.css`), braucht also nichts außer der Klasse und einem `data-arrow-target`-CSS-Selektor-Attribut — kein Icon-Markup, kein separates Asset |
| Stat-Counter (`js-stat-counter`, `data-stat-counter-to/-suffix/-duration`) | Zählt von 0 hoch, sobald in den Viewport gescrollt (dieselbe `getBoundingClientRect`-Prüfung wie `js-vpa`), animiert via `requestAnimationFrame` |
| Generisches Formular-Handling (`ui-form`, postet an `/`) | Behielt `ui-*` (siehe Namenskonvention) — zielt automatisch auf `Shortcodes\Form`s `POST /`-Endpunkt, kein Shortcode nötig |
| Tabs (`js-tabs`, `js-tabs-nav`/`-tab`, `js-tabs-panel`, `data-tabs-target`) | Panel blendet beim Wechsel über `@starting-style`/`transition-behavior:allow-discrete` ein (reines CSS) — fällt in Browsern ohne Unterstützung auf ein sofortiges Ein-/Ausblenden zurück |
| Modal / Lightbox / Video (`js-modal`, `--lightbox`, `--video`, `js-modal-trigger`, `js-modal-close`, `data-modal-target`) | Baut auf dem nativen `<dialog>`-Element auf (`showModal()`/`.close()`) statt handgerolltem JS — Focus-Trapping, Escape-zum-Schließen und der Backdrop-Klick-Bereich kommen dadurch gratis vom Browser |
| Toast (`js-toast-trigger`, `data-toast-message`/`-type`, oder `Phile.ui.toast(message, type)` aus eigenem JS) | Inline-`onclick` funktioniert hier nicht — die Site sendet einen `Content-Security-Policy: script-src 'self' 'nonce-...'`-Header, der Inline-Event-Handler ohne passende Nonce blockiert, und es gibt keinen allgemein verfügbaren Nonce-Fill für Templates. `js-toast-trigger` ist der CSP-sichere deklarative Weg, einen Toast aus statischem Markup zu feuern |

**Content-Komponenten**

| Block | Hinweise |
|---|---|
| Artikel/Card (`ui-article`, `--alt`/`--fullwidth`, `-cols`/`-cols-s/m/l/xl`, `-price`) | `-price` ist für ein bepreistes Item als reguläre Artikel-Grid-Card gedacht — unterscheidet sich von `ui-pricing-price` (Pricing-Tabelle unten), das für eine dedizierte Preistabelle ist |
| Buttons (`ui-btn`, `--primary/--outline/--light/--dark/--big/--small`) | `--big`/`--small` skalieren Padding/Schriftgröße/Radius über `calc()` von derselben Basis, statt separater Literalwerte |
| Icons (`ui-icon`, `.small`) | |
| Formularfeld-Skins (`ui-form-input/-textarea/-select/-message`) | Nur Styling — siehe Kontaktformular unten für den Versandmechanismus |
| Localepicker | Kernel-Shortcode `[localepicker]` und CSS nutzen beide das `sc-*`-Präfix (`sc-localepicker-wrap`, `sc-localepicker-bg`) |
| Kontaktformular | Kein dedizierter Shortcode — `<form class="ui-form">` mit den richtigen Feldnamen direkt im Template schreiben (`page-home.tpl` macht genau das). `Phile.ui.js`s generisches `.ui-form`-Handling zielt bereits auf `Shortcodes\Form`s `POST /`-Endpunkt. Ein gemeinsames Postfach/Config pro Projekt |
| Badge/Pill (`ui-badge`, `--pill/--primary/--success/--error`) | |
| Breadcrumbs (`ui-breadcrumbs`) | `›`-Trenner via `::after`-Content, letztes `li` als aktuelle Seite gestylt |
| Alert/Inline-Feedback (`ui-alert`, `--success/--error/--info`) | Statisches Banner für beliebige Seitenmeldungen — unterscheidet sich von `.ui-form-message`, dem eigenen Submit-Feedback-Absatz des Kontaktformulars |
| Video-Embed-Wrapper (`ui-video`, `--4-3`) | Responsiver iframe/Video-Wrapper, Standard 16:9. Nutzt `aspect-ratio` statt des klassischen `padding-top`-Prozent-Hacks, da dessen Prozentwert sich gegen die Breite des *umgebenden Blocks* auflöst, nicht gegen die eigene, `max-width`-begrenzte Breite des Elements |
| Tabelle (`ui-table-wrap` > `ui-table`, `--striped`, `--bordered`) | `ui-table-wrap { overflow-x:auto }` um die `<table>`, damit eine breite Tabelle auf schmalem Viewport horizontal scrollt statt die ganze Seite breit zu zwingen — dieselbe Logik wie beim Grid. `--striped` färbt auch `tbody`-Zeilen mit `--color-section-alt-bg` — eine gestreifte Tabelle auf eine schlichte `ui-section` setzen, nicht `ui-section--alt`, sonst verschwindet die Tönung im Section-Hintergrund |
| Liste (`ui-list`, `--check`, `--numbered`) | Unterscheidet sich von Breadcrumbs/Pagination (strukturelle Nav-Listen mit fester Form). `--check`/`--numbered`-Marker sind `::before` (ein Unicode-`✓` bzw. ein CSS-`counter()`), kein `<img>`/SVG |
| Preistabelle (`ui-pricing-row`, `ui-pricing-item(--featured)`, `-title`, `-price`) | Kein festes Markup/Shortcode — Card-Styling gedacht, um einen `[elements type="pricelist" content="..."]`-Aufruf zu umschließen |
| Accordion (`ui-accordion`, `-trigger`, `-panel`) | Reines `<details>`/`<summary>`, kein JS — mehrere `<details>` mit demselben `name="..."`-Attribut verhalten sich nativ als exklusive Gruppe in aktuellen Browsern |
| Pagination (`ui-pagination`) | Nur Seitenzahl-Links, kein JS — welche Seite aktiv ist und wie viele es gibt, ist ein Template-/Backend-Belang. Unterscheidet sich von der Slider-Dot-Pagination (`js-slider-points`), die JS-getrieben ist |
| Logo-/Partner-Strip (`ui-logos`, `-item`) | Text-Platzhalter stehen für echte Logo-SVGs/-PNGs — Graustufen, Vollfarbe bei Hover |
| Galerie/Mosaik-Grid (`ui-gallery`, `-item`, `--wide`, `--tall`) | CSS-Grid, feste `grid-auto-rows`, einzelne Kacheln über die Modifikatoren verbreitert/erhöht |
| Feature-Liste (`ui-feature-list`, `-item`) | Icon + Überschrift + Text-Zeilen, gedacht für eine Hälfte des bestehenden 50/50-Grid-Musters (Bild in der anderen Hälfte) |
| Prozess-Timeline (`ui-timeline`, `-step`, `-number`) | Nummerierte Kreise, verbunden durch eine Linie (`::before` auf jedem Schritt außer dem ersten) — die Linie rendert erst ab dem `m`-Breakpoint |
| Video-Poster (`ui-video-poster`, `-play`) | Statisches Poster-Bild mit Play-Icon, öffnet das echte `.ui-video`-Embed in einer `js-modal--video`-Lightbox beim Klick |
| Cookie-Consent-Banner (`js-cookie-banner`, `-actions`) | Unterer Balken, kein Vollbild-Overlay. Zustand liegt in `Phile.cookie` (`Phile.js`) unter einem `phile_consent`-Key; `Phile.ui.cookieConsent.get()`/`.isAccepted()`/`.set()` ist die öffentliche API, gegen die ein eigenes Analytics-/Tracking-Skript gaten sollte, bevor es etwas Nicht-Essenzielles lädt |

**Utilities**

| Block |
|---|
| Abstand (`ui-m-/-mt-/-mb-/-ml-/-mr-/-p-/-pt-/-pb-0..6`) |
| Textausrichtung (`ui-text-left/center/right`, `-s-/-m-/-l-/-xl-`) |
| Schriftgröße (`ui-font-small/-big`) |
| Opacity (`ui-opacity-10..90`) |
| Sichtbarkeit (`ui-hidden`, `ui-invisible`, `ui-sr-only`, `ui-hidden-*`/`ui-visible-*`) |
| Bild-Tools (`ui-img-cover`, `ui-img-background(--dim)`, `-content`) |

### SEO / Social / AI

Alles über denselben Textfill-Mechanismus, den auch Inhalt nutzt (siehe
oben) — keine separate Config-Oberfläche, jeder Key unten ist automatisch
über `_admin`s bestehendes Text-Panel editierbar, sobald er in
`global.php`/`{locale}.php` steht.

- `<title>`/`og:title`/`twitter:title` und `<meta
  name="description">`/`og:description`/`twitter:description` in
  `templates/html-header.tpl` lösen sich alle über
  `[[/webpage[[/phile/http/response/uri]]/title|description]]` auf —
  dasselbe Pro-Seite-Lookup-Muster, das `/title` bereits nutzte, um ein
  Geschwister-`/description`-Key erweitert. **Jede geroutete Seite
  braucht beide Keys** — anders als bei einer fehlenden Template-Variable
  gibt es keinen Fallback: `Html::_renderFills()` ist ein reines
  `str_replace()` gegen bekannte Keys, ein undefinierter bleibt als
  wörtlicher `[[/webpage/.../description]]`-String in der Ausgabe stehen,
  statt einen Fehler zu werfen.
- `og:image`/`twitter:image` fallen standardmäßig auf `/images/logo.png`
  zurück (mit `https://` + `/website/url` + `/phile/dir` als Präfix, da
  Social-Plattformen eine absolute URL erwarten) — gegen ein echtes
  1200×630-Share-Bild pro Projekt tauschen.
- `<link rel="canonical">`/`og:url` nutzen `[[/phile/http/request/uri]]`
  (den tatsächlich eingehenden Pfad, z. B. `/kontakt`), nicht
  `/phile/http/response/uri` (das interne Routing-Ziel, z. B.
  `/contact` intern für denselben Request) — die beiden unterscheiden
  sich bei locale-gerouteten Seiten, und Canonical braucht die
  öffentlich sichtbare Variante.
- JSON-LD-`LocalBusiness`-strukturierte Daten
  (`templates/html-header.tpl`) — von Suchmaschinen und zunehmend von
  KI-Assistenten/-Agenten für strukturierte Fakten gelesen, gebaut aus
  denselben `/company/*`-Fills, die auch der Footer bereits rendert.
- `robots.txt`/`sitemap.xml`/`llms.txt`
  (`templates/robots.tpl`/`sitemap-xml.tpl`/`llms-txt.tpl`, in
  `config.php` wie jede andere Seite geroutet, nur mit einem
  `header` → `Content-Type`-Override statt des üblichen `text/html`).
  `robots.txt` erlaubt standardmäßig die großen KI-Crawler (GPTBot,
  ClaudeBot, PerplexityBot, Google-Extended, CCBot), damit die Site über
  KI-Suche gefunden/zitiert werden kann — einzelne auf `Disallow: /`
  umstellen, um diesen Bot auszuschließen. `sitemap.xml` listet nur die
  native Locale der Site (`/phile/locales/native`), keine
  Locale-Alternativen.

### Demo-Seiten

- `/demo-elements` (`templates/.demo-elements.tpl`) — jeder einzelne
  Baustein aus den Tabellen oben, einzeln. Lebende Version des
  "Markup-Referenz"-Abschnitts unten.
- `/demo-sections` (`templates/.demo-sections.tpl`) — realistische,
  copy-paste-fertige, vollständige Abschnitte, die diese Bausteine
  kombinieren (Fullscreen-Hero mit und ohne Bild, Parallax-Zitat,
  50/50-Bild+Text-Grids in beide Richtungen plus randlose Variante,
  Artikel-Grids, Statistik-Zeile, Preistabelle, Vergleichstabelle,
  Feature-Checkliste, Kontaktformular, CTA-Banner, FAQ-Accordion,
  Logo-/Partner-Strip, Bildgalerie/Mosaik, Feature-Split,
  Prozess-Timeline, Video-Poster + Lightbox, Full-Bleed-Textbanner).
  Fotos stammen aus `/images` — Dummy-Stockfotos
  (`<id>-<breite>x<höhe>.jpg`, fünf Seitenverhältnisse: 16:9, 4:3, 1:1,
  3:4, 21:9), gegen echte Projektfotos tauschen, sobald ein Abschnitt
  von der Demo auf eine echte Seite wandert.

Beide Seiten verlinken sich gegenseitig. Keine ist für den Live-Betrieb
eines echten Projekts gedacht — beide sind reines Entwickler-Referenz-
Tooling und in `sitemap.xml` bewusst ausgelassen.

`.ui-logo` (`templates/html-header.tpl`/`html-footer.tpl`) referenziert
`images/logo.png`, was standardmäßig noch nicht im Repository existiert
— beide Stellen zeigen bereits darauf (inkl. `alt`-Attribut), ein
`logo.png` nach `/images` zu legen ist der einzig verbleibende Schritt,
sobald eines verfügbar ist.

### Markup-Referenz

Lebende, funktionierende Version von allem unten unter `/demo-elements`
(`templates/.demo-elements.tpl`) — mit `.demo-elements.tpl` synchron
halten, wenn sich eines von beiden ändert.

**Grid**

```html
<div class="ui-grid-row">
  <div class="ui-grid-100 ui-grid-m-50 ui-grid-l-25">...</div>
  <div class="ui-grid-100 ui-grid-m-50 ui-grid-l-25">...</div>
</div>
```

50/50 Bild+Text, Bild bis zum Viewport-Rand statt innerhalb des
Row-Paddings:

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

**ATF/Hero + Cover/Parallax**

```html
<!-- h2, wenn diese Hero die eigene Headline der Seite ist, h3 bei mehreren
     Abschnitten auf der Seite -->
<section class="ui-atf ui-atf--fullscreen ui-section--fullwidth ui-section--dark ui-text-center">
  <div class="ui-grid-row">
    <div class="ui-grid-100">
      <h2 class="ui-atf-title">...</h2>
      <p class="ui-atf-subtitle">...</p>
    </div>
  </div>
</section>
```

Cover/Parallax, Fullscreen via `data-cover-height="100"`, `--dim` für
einen dunklen Schleier über einem echten (hellen) Foto:

```html
<section class="ui-atf ui-section--fullwidth js-cover js-cover-center js-cover--dim" data-cover-height="100" style="color:var(--color-primary-text);">
  <img src="...">
  <div class="js-cover-content">
    <h2 class="ui-atf-title">...</h2>
    <p class="ui-atf-subtitle">...</p>
  </div>
</section>

<section class="js-parallex js-parallex--dim" style="color:var(--color-primary-text);">
  <img src="...">
  <div class="js-cover-content">...</div>
</section>
```

Das Inline-`color` ist der framework-garantierte Weg, Hero-Text über
einem `--dim`-Schleier lesbar zu halten — `_phile/Phile.css` selbst
stylt nur den Schleier (`::before`), nicht den Text.

**Sections**

```html
<section class="ui-section ui-section--alt"> <!-- oder --dark / --black / --fullwidth -->
  <h3 class="ui-section-title">...</h3>
  <p class="ui-section-subtitle">...</p>
</section>
```

**Buttons / Icons**

```html
<a href="#" class="ui-btn ui-btn--primary">...</a> <!-- --outline / --light / --dark / --big -->
<svg class="ui-icon small" ...>...</svg>
```

**Artikel/Card (immer über `elements`)**

**Grid-Breiten-Klassen niemals direkt auf `<article>`** — in ein
schlichtes `<div>` mit der Grid-Klasse wrappen, `<article>` trägt nur
`ui-article`/dessen Modifikatoren:

```html
[elements /portfolio]
<div class="ui-grid-100 ui-grid-m-33">
  <article class="ui-article"> <!-- ui-article--alt für die Alt-Variante -->
    <div class="ui-article-content">
      <h4 class="ui-article-title">[[title]]</h4>
      <p class="ui-article-descr">[[description]]</p>
    </div>
  </article>
</div>
[/elements]
```

**Preistabelle (über `elements`)**

```html
<div class="ui-pricing-row">
  [elements /pricelist query="cat=standard"]
  <div class="ui-pricing-item">
    <h4 class="ui-pricing-title">[[title]]</h4>
    <p class="ui-pricing-price">[[price]] &euro;</p>
    <span class="ui-badge">[[cat]]</span>
  </div>
  [/elements]
</div>
```

**Formular**

```html
<form class="ui-form">
  <label for="name">Name</label>
  <input type="text" id="name" name="name" class="ui-form-input" required>
  <textarea name="message" class="ui-form-textarea" required></textarea>
  <p class="ui-form-message"></p>
  <button type="submit" class="ui-btn ui-btn--primary ui-form-submit">Absenden</button>
</form>
```

**Toast**

```html
<!-- deklarativ, aus statischem Markup -->
<button type="button" class="js-toast-trigger" data-toast-message="Gespeichert." data-toast-type="success">Speichern</button>
```

```js
// oder imperativ, z. B. nach einem eigenen XHR-Aufruf — aus
// assets/script.js, nicht aus einem Inline-<script>-Tag (die
// CSP-Nonce ist intern für den jstext-Shortcode, Inline-Script-Tags
// in Templates laufen daher nicht)
Phile.ui.toast( 'Gespeichert.', 'success' );
```

Für weitere Bausteine (Badge, Breadcrumbs, Alert, Liste, Tabelle,
Video-Embed, Accordion, Tabs, Modal/Lightbox, Slider, Viewport-
Animation, Autoheight, Stat-Counter, Back-to-Top, Preloader,
Scroll-Header, Cookie-Banner, Pagination) siehe die lebende Referenz
unter `/demo-elements` — Markup und Klassennamen folgen exakt dem
Muster der Bausteine-Tabellen oben.

### Site-Struktur: Starter-Seiten

`page-home.tpl`, `page-services.tpl`, `page-about-me.tpl`,
`page-contact.tpl`, `page-404.tpl` und
`page-legal.{de_DE,en_US}.tpl` sind als echte Routen
(`config.php`s `/phile/http/routes`) ausgeliefert — ein
Startpunkt mit Platzhalterinhalt für ein neues Projekt statt leerer
Stubs, gedacht zum Bearbeiten (Text via `text/*.php`, Abschnitte aus
`/demo-sections` getauscht/erweitert) statt von Grund auf neu gebaut zu
werden.

Ein Abschnitt, der identisch auf mehr als einer Seite vorkommt, wird in
ein eigenes `templates/section-*.tpl`-Partial ausgelagert und über
`[template /templates/section-*]` eingebunden, derselbe Include-
Mechanismus, den auch `[template /templates/html-header]`/`html-footer`
nutzen — `section-contact.tpl` (Adresse + Kontaktformular) ist das
erste davon, geteilt zwischen `page-contact.tpl` und der
Abschluss-CTA-Section von `page-home.tpl`. Das bevorzugen, statt das
Markup eines Abschnitts auf eine zweite Seite zu kopieren.

## Teil 3: Entwicklung eigener Design-Shortcodes

Ein Design-Shortcode ist genau wie jedes andere Modul aus
`docs/development.de.md`s "Eigene Module entwickeln"-Abschnitt aufgebaut
— es gibt keine gesonderte "Frontend-Shortcode-API". Ein minimaler
eigener Shortcode für z. B. ein Team-Mitglieder-Grid mit
kontextabhängigem Markup:

```php
<?php
declare(strict_types=1);

namespace MyProject\Modules {

    class TeamGrid {

        public static function init( array &$appData ): void {
            \Phile\Html::addShortcode( $appData, 'teamgrid', [ self::class, 'doShortcode' ] );
        }

        public static function doShortcode( array &$appData, array $args ): string {

            $columns = $args['columns'] ?? '3';
            $html    = '<div class="ui-grid-row ui-team-grid" data-columns="'. htmlspecialchars( $columns ). '">';
            $html   .= '[elements uri="/team"]'. $args['content']. '[/elements]';
            $html   .= '</div>';

            return $html;
        }
    }
}
```

Registriert in `config.php`s `/phile/modules`, ist `[teamgrid
columns="4"]<article class="ui-article">...</article>[/teamgrid]` ab
dem nächsten Request nutzbar. Wichtige Punkte dabei, direkt aus der
Templating-Pipeline abgeleitet (siehe `docs/development.de.md`):

- **Der Rückgabewert wird automatisch erneut gerendert.** Der Handler
  oben gibt ein rohes `[elements uri="/team"]...[/elements]`-Fragment
  zurück, kein fertiges HTML — der Shortcode-Dispatcher ruft nach dem
  Aufruf selbstständig `renderHtml()` erneut auf dem Rückgabewert auf,
  löst also das eingebettete `[elements]` mit auf. Ein eigener
  Design-Shortcode kann sich so problemlos aus anderen Shortcodes
  zusammensetzen, ohne selbst `Html::renderHtml()` aufzurufen.
- **`$args['content']` ist der umschlossene Inhalt roh, unverarbeitet.**
  Wer ihn direkt einbaut (wie oben), bekommt die automatische
  Nach-Rendering-Pass gratis dazu; wer ihn manipuliert (z. B. eine
  Teilmenge extrahiert), bleibt selbst dafür verantwortlich, dass das
  Ergebnis noch gültiges Shortcode-/Fill-Markup ist.
- **Nutzerdaten in generiertem Markup immer escapen**
  (`htmlspecialchars()`, wie oben beim `columns`-Argument) — ein
  Shortcode-Argument kann im Prinzip von einer admin-editierbaren
  Textquelle stammen, falls das Template selbst Werte aus `[[key]]`
  in ein Argument einsetzt.
- **CSS-Klassen nach der Namenskonvention wählen** (siehe Teil 2 oben):
  `js-*` nur für tatsächliches Verhalten, `ui-*` für Styling, kein
  eigenes `sc-*` — dieses Präfix ist den Kernel-eigenen Shortcodes
  vorbehalten.
- **Ein eigener Shortcode kann eigenes CSS/JS mitbringen** — einfach in
  `assets/style.theme01.css`/`assets/script.js` ergänzen (oder eigene
  Dateien in `config.php`s `/phile/html/assets`-Bundle aufnehmen), kein
  separater Build-Schritt nötig, siehe "Architektur" oben.

## Falle: shortcode-förmiger Text in Kommentaren, überall

Jeder Kommentar oder String, der wie `[shortcodename ...]` aussieht,
wird als echter Shortcode-Aufruf geparst — `Html::renderHtml()` weiß
nicht und kümmert sich nicht darum, ob das in einem `/* CSS-Kommentar
*/` oder einem `<!-- HTML-Kommentar -->` steht, es durchsucht einfach
den gesamten Inhalt per Regex. `Assets::_createCachefile()` schickt den
**gesamten** zusammengefügten CSS/JS-Inhalt dadurch (Fills **und**
Shortcodes), bevor er gecacht wird, und jedes `.tpl`-Template
durchläuft es bei jedem Rendern, ausnahmslos.

Das ist in diesem Projekt bereits zweimal passiert, beide Male in einem
Kommentar, der den `elements`-Shortcode beiläufig mit eckigen Klammern
erwähnte. Beide Male lief `elements` mit einem leeren/kaputten
Typ-Argument und löste einen `trigger_error()` aus, was das **gesamte**
Seiten-Rendering brach, nicht nur den kommentierten Abschnitt.
`[navigation]`/`[localepicker]` tauchen harmlos in bestehenden
Section-Header-Kommentaren in `Phile.css` auf, weil diese beiden
Shortcodes bei fehlerhaften/fehlenden Args einfach still nichts tun —
`elements`/`element` tun das nicht, "hat bei diesem anderen Kommentar
funktioniert" ist also kein verlässliches Signal.

**`[wort ...]`-Klammersyntax in Kommentaren in jeder `.tpl`-/`.css`-/
`.js`-Datei vermeiden, die durch die Asset-Pipeline oder den
Template-Renderer läuft** — stattdessen "den elements-Shortcode" in
Prosa schreiben statt `[elements ...]`. Wenn eine Seite plötzlich mit
einem `trigger_error`-Dump statt zu rendern 500t, zuerst die zuletzt
bearbeitete Datei nach `\[` durchsuchen.
