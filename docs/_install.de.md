# Nino — Install-Handbuch
*[English](_install.md)*

**Links:**
[README](../README.de.md) · [Design-Handbuch](design.de.md) · [Entwickler-Handbuch](development.de.md) · [_editor-Handbuch](_editor.de.md) · [_admin-Handbuch](_admin.de.md) · [Security Policy](../SECURITY.md) · [Changelog](../CHANGELOG.md)

Ein grafischer, nur für Entwickler gedachter Assistent für das initiale Setup eines frischen Checkouts unter `/_install`. Optional - ein Projekt lässt sich genauso gut von Hand einrichten (siehe `docs/development.de.md`) - der Assistent führt lediglich durch dieselben Schritte im Browser statt in der Shell.

Das Projekt wird mit `text/`, `templates/` und `elements/` **vorbefüllt mit einer funktionierenden Startseite** ausgeliefert - `config.php` sieht so aus, als wären Setup (nur die Standardsprache, strukturelle Module), Themes ("agency") und Webpages (`/home` unter `/`, `/404`, `/legal` und `/contact`) von `/_install` bereits einmal durchlaufen worden, sodass ein frischer Checkout ohne jede Einrichtung eine echte Vier-Seiten-Website ausliefert. `/_install` bleibt danach weiterhin nutzbar (und gefahrlos erneut ausführbar - siehe "Was beim Klick auf Weiter angehakt/gelistet ist, ist die ganze Wahrheit" unten), um Sprachen/Module/Seiten zu ergänzen oder die Defaults umzugestalten, bevor Sie es löschen.

## Wann er läuft

`/_install` funktioniert nur, solange `_admin/Admin.php` noch den mitgelieferten Platzhalter-`PASSWORD_HASH` trägt (den Wert, auf den kein echtes Passwort passt). Der letzte Schritt des Assistenten selbst - das Setzen eines echten `/_admin`-Passworts - sperrt `/_install` dauerhaft aus: Sobald ein echter Hash gesetzt ist, verweigert `/_install` bei jedem Aufruf den Dienst, ganz ohne Login-Maske, und es führt kein Weg zurück außer dem manuellen Bearbeiten der `PASSWORD_HASH`-Konstante in `_admin/Admin.php`.

Das bedeutet auch: `/_install` braucht kein eigenes Passwort. Bevor ein echtes `/_admin`-Passwort existiert, ist das Projekt ohnehin noch nicht sinnvoll abgesichert - genau dahin zu kommen ist die Aufgabe des Assistenten.

Wie `_admin` und `_editor` ist auch `_install` vollständig optional und gefahrlos entfernbar: Nach Abschluss einfach den ganzen Ordner löschen (`rm -rf _install`). Ein Projekt, das den Assistenten und seine Content-Library von vornherein gar nicht möchte, kann `_install/` direkt entfernen (genauso optional wie `_admin`/`_editor`) und `text/`/`templates/`/`elements/`/`config.php` stattdessen von Hand aufbauen.

## Der Assistent

Ein streng linearer Ablauf: "Zurück"/"Weiter" am unteren Rand wechseln zwischen den sieben Schritten unten und übernehmen dabei, was der aktuelle Schritt vor dem Weiterschalten speichern muss (Setup wendet seinen Picker an, Themes wendet das gewählte Theme an, Webpages wendet seine Seitenliste an, Personal Infos speichert seine Felder, Admins prüft nur, ob ein Account existiert - Umgebung und Abschluss haben nichts zu übernehmen). Die Schrittliste oben ist reine Fortschrittsanzeige, kein Menü - sie ist nicht klickbar, ein Vorspringen oder Überspringen eines Schritts ist nicht möglich.

### 1. Umgebung

Rein lesende Diagnose: die laufende PHP-Version gegen das vom Kernel benötigte `>= 8.4`, die in der README dokumentierten Extensions (`gd` für Bildzuschnitt/-skalierung, `mbstring`, `session`, `json`, sowie `Phar`/`PharData` für die automatischen Backups von `_editor` und die Wiederherstellung in `_admin`), und ob die Verzeichnisse, in die Nino zur Laufzeit schreibt (Projektwurzel, `text/`, `images/`, `data/`, `.cache/`), beschreibbar sind. "Erneut prüfen" wiederholt lediglich dieselben Prüfungen - geschrieben wird hier nirgends etwas.

### 2. Setup

Wählt Available Locales, eine Native Locale darunter und "Module" (Navigation, Localepicker, Forms, Newsletter - jeweils mit eigenen Text- und/oder Mail-Templates) aus und setzt sie zu echtem Projektinhalt zusammen: die Sprachen/Module/Routen in `config.php`, `/templates` und `/text`. Themes und Seiten haben ihre eigenen Schritte (unten) - Setup fasst beide nicht an.

**Native Locale** ist ein `<select>`, das nur die gerade angehakten Available Locales anbietet - An-/Abhaken oben aktualisiert es live. Es bildet auf `/nino/locales/native` ab (der Fallback überall dort, wo noch keine Sprache bekannt ist, z. B. bei einem Besucher ohne Sprach-Cookie). Eine Native Locale, die tatsächlich zu den in diesem Aufruf gewählten Sprachen gehört, gewinnt immer; alles andere (nichts übermittelt, oder ein veralteter, nicht mehr angehakter Wert) fällt zurück auf: die aktuelle Native Locale behalten, falls sie noch gewählt ist, sonst die erstgewählte Sprache - dieselbe Regel, die `/_install` schon immer angewendet hat, jetzt nur auch direkt wählbar statt nur vererbt.

Wird ein Modul mit `requiresModules` gewählt, wird dieses automatisch mit ausgewählt - die Antwort nach dem Anwenden zeigt die vollständige, aufgelöste Auswahl, nicht nur die angekreuzten Punkte.

Der Picker zeigt bei jedem Aufruf dieses Schritts die aktuelle Auswahl bereits angekreuzt - denn Anwenden ersetzt sie vollständig. Was beim Klick auf "Weiter" angekreuzt ist, ist die ganze Wahrheit: Eine Sprache/ein Modul abzuwählen und erneut anzuwenden entfernt sie tatsächlich, genau wie bei jedem anderen Einstellungsformular. Routen werden dabei immer aus dem tatsächlich in `config.php` gespeicherten Stand neu aufgebaut, nie aus dem Laufzeit-Zustand des aktuellen Requests - eine von einem Modul selbst registrierte Laufzeit-Route (z. B. `POST://.form`) und die eigene Route von `/_install` landen dadurch nie in `config.php`, und eine von Hand außerhalb der Library hinzugefügte Route (z. B. über das Config-Modul von `_admin`) bleibt unabhängig von der Auswahl hier unangetastet.

Templates und Text-Fragmente sind die Ausnahme: Die werden nur ergänzt, nie von einem späteren Anwenden entfernt - auch nicht für ein abgewähltes Modul. Eine Datei zu löschen, die zwischenzeitlich von Hand bearbeitet wurde, wäre ein deutlich riskanteres "Rückgängig" als das Umschalten eines Config-Arrays. Bei Bedarf von Hand entfernen.

### 3. Themes

Wählt das Erscheinungsbild der Website: ein Raster aus Kacheln, eine pro `_install/library/themes/<key>`-Einheit, jede mit Vorschaubild, Titel und Beschreibung aus der eigenen `manifest.php` dieser Einheit (acht Themes sind von Haus aus dabei). Ein Klick auf das Vorschaubild einer Kachel vergrößert es in einer Lightbox; ein Klick irgendwo sonst auf der Kachel wählt das Theme aus. Das aktuell angewendete Theme ist bei jedem Aufruf dieses Schritts bereits vorausgewählt.

Ein Theme ist ein vollständiger, in sich geschlossener Look, nicht nur ein Stylesheet: Seine Einheit bringt die `.css`-Datei mit, die Webfonts, die dieses Stylesheet tatsächlich referenziert, sowie alles Weitere, was sie auflistet (Theme-Bilder, ...). Anwenden kopiert all das genau so ins Projekt, wie Setup die `files` eines Moduls kopiert, und richtet das Asset-Bundle `/nino/html/assets['/.cache/style.css']` in `config.php` auf das kopierte Stylesheet aus - eingesetzt an der Position, die der Eintrag des vorherigen Themes innehatte (die Position eines Stylesheets in diesem Bundle entscheidet, welcher `:root`-Block in der Kaskade gewinnt, siehe `docs/design.de.md`), alle anderen Einträge des Arrays bleiben unangetastet. Gibt es keinen zu ersetzenden Theme-Eintrag, wird es hinten angehängt.

Es ist immer genau ein Theme aktiv, es gibt hier also - anders als bei Setup und Webpages - keine Replace-Semantik zu bedenken: Ein anderes Theme zu wählen überschreibt schlicht die Dateien des vorherigen (gleiche Namen, gleiche Orte). Eine Datei, die das vorherige Theme mitgebracht hat und das neue nicht - etwa ein nicht mehr benutzter Webfont - bleibt liegen statt gelöscht zu werden, dieselbe additive Regel, der auch Templates und Texte bei Setup folgen.

Der gewählte Schlüssel wird unter `/nino/install/theme` in `config.php` gespeichert. Das Asset-Bundle allein könnte nicht zuverlässig sagen, welches Theme es erzeugt hat, sobald ein Projekt seine Stylesheets umbenennt oder von Hand bearbeitet; eine `config.php`, die diesen Schlüssel noch nicht kennt (oder bei der er von Hand entfernt wurde), löst das aktive Theme weiterhin über den Abgleich des Bundles mit dem deklarierten Stylesheet jedes Themes auf.

Das Vorschaubild gehört zum Picker, nicht zum Projektinhalt - es wird direkt aus dem Library-Ordner ausgeliefert und nie irgendwohin kopiert.

### 4. Webpages

Baut die tatsächlichen Seiten des Projekts auf: eine frei gestaltbare, geordnete Liste, die Sie direkt verwalten, statt eine fixe Checkbox pro `_install/library/pages/<key>`-Bundle. Wird mit vier Standardeinträgen ausgeliefert (`/home` unter `/`, `/404`, `/legal`, `/contact` - siehe "Wann er läuft" oben); klicken Sie eine Zeile an, um ihren Editor zu öffnen, oder "New Webpage" für einen leeren - dieselbe Liste-plus-Editor-Form, die auch das Pages-Modul von `_admin` verwendet, sobald `_install` nicht mehr da ist, sodass sich beide wie ein einziges Werkzeug anfühlen statt wie zwei unterschiedlich gebaute. Jeder Eintrag hat:

- **Element-URI** - ein stabiler Bezeichner, frei wählbar, der nicht wie ein echter Pfad aussehen muss (z. B. `/home`). Er ist der Namensraum für die `/webpage<uri>/*`-Textschlüssel dieses Eintrags und wird zum eigenen `uri`-Datenfeld der Route (dem, was `[[/nino/http/response/uri]]` auflöst) - er entscheidet **nicht**, wo die Seite tatsächlich erreichbar ist.
- **Http-URI** - der echte Browser-Pfad (z. B. `/`). Nur dieser bestimmt den Array-Key in `/nino/http/routes`: `\Nino\Http::requestRoute()` matcht eine Route über den Array-Key `'<METHOD>:/'.$uri` als wörtlichen Schlüssel, nicht über einen Scan nach einer Route mit passendem `uri`-Feld - erreichbar ist also immer nur die Http-URI. Diese Trennung von der Element-URI ist es, was der Startseite erlaubt, den stabilen Bezeichner `/home` zu behalten, während sie tatsächlich unter `/` liegt.
- **template** - welches `_install/library/pages/<key>`-Bundle Routen-Body, Template-Datei und tiefergehenden Seiteninhalt liefert. Dasselbe Template kann mehrfach an unterschiedlichen URIs verwendet werden (z. B. zwei unterschiedlich genutzte Seiten, die beide vom "home"-Bundle ausgehen).
- **name/title/description**, pro aktiver Sprache - das eigene Navigationslabel, `<title>` und Meta-Beschreibung dieses Eintrags. Leer gelassen, fällt jedes Feld auf einen generischen Platzhalter zurück ("Page"/"Page Title"/"Page description.") statt auf den Wortlaut, den das Template-Manifest mitbringt - dieser Wortlaut ist jetzt eine Entscheidung pro Instanz, nicht fest im Template verankert.
- **Show in main navigation** - erscheint nur, sobald das Navigation-Modul (Schritt 2) aktiv ist. Angehakte Einträge fließen ins generierte Hauptmenü ein, siehe "Navigation" unten.

Beide URIs durchlaufen dieselbe Sicherheitsnormalisierung (führender Slash, kein `..`, nur einfache Pfadzeichen) und müssen innerhalb der Liste eindeutig sein - unabhängig voneinander, sodass sich zwar keine zwei Einträge eine Element-URI oder eine Http-URI teilen dürfen, die Element-URI des einen Eintrags aber durchaus der Http-URI eines anderen entsprechen darf.

Die ↑/↓-Buttons der Liste sortieren sie direkt um; "Delete page" liegt im Editor eines Eintrags selbst. Hinzufügen, Bearbeiten, Löschen, Umsortieren - all das betrifft nur die Liste im Speicher; nichts erreicht den Server, bevor "Weiter" Routen/Templates/Texte/Blacklist batchweise aus der gesamten Liste in einem Rutsch generiert - dieselbe Replace-Semantik wie bei Setup: Die abgeschickte Liste ist das vollständige, maßgebliche Bild, und ein erneuter Besuch dieses Schritts zeigt immer die aktuelle, gespeicherte Liste statt leer zu starten.

Templates und tieferer Seiteninhalt bleiben additiv, genau wie bei Setup - eine URI aus der Liste zu entfernen löscht die bereits geschriebene Template-Datei oder den Inhalt nicht.

Die Liste wird unter `/nino/install/webpages` in `config.php` als `{ uri, httpUri, template, libraryKey, nav, statusCode, body, text }` gespeichert - dasselbe Array, das auch das Pages-Modul von `_admin` liest und schreibt. `libraryKey` ist das eigene Feld dieses Schritts (von welcher `_install/library/pages`-Einheit der Eintrag stammt); `template`, `statusCode` und `body` beschreiben die daraus entstandene Route, damit das Pages-Modul mit dem Eintrag auch dann noch arbeiten kann, wenn `_install` weg ist. Ein vom Pages-Modul angelegter Eintrag hat gar keinen `libraryKey` - dieser Schritt reicht ihn unverändert durch seinen Replace hindurch, statt ihn abzulehnen, und sein Template-Select zeigt ihn als "already set up".

**Navigation.** Sobald das Navigation-Modul aktiv ist, speisen alle Einträge mit angehaktem "Nav" `[[/website/navigation/main]]` - eine generierte `httpUri:name`-Liste pro aktiver Sprache (der echte, klickbare Pfad - nicht die Element-URI), genau in der Form, die der `[navigation]...[/navigation]`-Shortcode von `\Nino\Modules\Navigation` erwartet (siehe `_install/library/modules/navigation/templates/html-header-nav.tpl`/`html-footer-nav.tpl`). Anders als der Rest der von Webpages erzeugten Inhalte wird dieser Schlüssel bei jedem Anwenden neu generiert - nicht gemergt: Er ist vollständig aus der aktuellen Liste abgeleitet, kein Wert, den Sie vor Abschluss des Assistenten von Hand pflegen sollen.

### 5. Personal Infos

Befüllt gesammelt die Handvoll `/company/*`- und `/website/*`-Textschlüssel, die jedes Projekt unabhängig von der Auswahl in Schritt 2-4 hat - jeweils mit einem sprechenden Label statt des rohen Schlüssels, in derselben Form wie das Text-Panel von `_editor`: Jeder **globale** Schlüssel (Company Name, Company Email, Company Phone, Company Adress, Website Author, Website Host) in einem Fieldset oben, jeder **sprachabhängige** Schlüssel (Company Country, Company Description) darunter hinter einer Sprachauswahl, mit einer Zwischenspeicherung im Arbeitsspeicher, die noch nicht gespeicherte Eingaben beim Sprachwechsel erhält. Jedes Feld ist einzeilig, außer Company Adress und Company Description, die mehrzeilig bleiben. Speichern schreibt die globalen Felder plus die aktuell gewählte Sprache - für die übrigen Sprachen die Auswahl wechseln und erneut speichern.

Alles andere - technische Schlüssel/Design-Tokens (`text/blacklist.php`), der eigene Name/Titel/Beschreibung einer Webpage (deckt Webpages bereits ab) sowie tieferer Modul-/Seiteninhalt - wird hier bewusst ausgeblendet: Das ist als generischer Library-Default in Ordnung, und falls nicht, wird es anschließend über das Text-Panel von `_editor` (bzw. `_admin` für technische Schlüssel) angepasst.

### 6. Admins

Legt den/die ersten `/_editor`-Account(s) mit vollen Rechten (`/*`) an. Der mitgelieferte Platzhalter-Account (`changeme@domain.com`, dessen Passwort-Hash zu keinem Passwort passt) wird automatisch entfernt, sobald ein echter Admin angelegt wird. Das Formular lässt sich erneut absenden, um mehrere Admins einzurichten, oder eine Zeile löschen, um einen wieder zu entfernen - die Liste darf dabei leer werden, "Weiter" ist es, was tatsächlich mindestens einen Account voraussetzt, bevor der Assistent fortfährt.

### 7. Abschluss

Setzt das echte `/_admin`-Passwort. Vorher sicherstellen, dass mindestens ein Admin-Account existiert (Schritt 6) - ohne einen ist nach der Selbstsperre kein Login mehr unter `/_editor` möglich. Dieser Schritt schreibt die `PASSWORD_HASH`-Konstante direkt in `_admin/Admin.php` (der einzige Schritt, der PHP-Quellcode statt einer Datendatei verändert) und beendet damit den eigenen Zugriff von `/_install` endgültig.

Routen lassen sich im Assistenten selbst nicht einsehen - dafür das Config-Modul von `_admin` nutzen (`/_admin` → Config → `/nino/http/routes`), vor oder nach dem Abschluss hier.

## Library-Format

`_install/library/` kennt vier Arten von Einheiten, jede ein Verzeichnis mit einer `manifest.php`:

```
_install/library/
  base/               immer angewendet, unabhängig von der Auswahl
    manifest.php        Routen (robots.txt/sitemap.xml/llms.txt), Templates, Blacklist, files
    templates/           html-header.tpl, html-footer.tpl, mail-header.tpl, ...
    assets/              script.js
    text/global.php, text/de_DE.php, text/en_US.php
  modules/<key>/      wählbar in der "Module"-Liste von Schritt 2
    manifest.php        moduleClass, templates (z. B. Mail-Templates), requiresModules
    templates/, text/global.php, text/<locale>.php
  themes/<key>/       wählbar als Kachel in Schritt 3
    manifest.php        label, description, preview, stylesheet, files
    preview.svg          das Bild der Picker-Kachel - wird nie ins Projekt kopiert
    assets/              style.theme.<key>.css
    fonts/text/, fonts/title/    die von diesem Stylesheet referenzierten Webfonts
  pages/<key>/        wählbar als "template" eines Webpages-Eintrags
    manifest.php        eine Route, templates, elementTypes, blacklist, requiresModules
    templates/, text/<locale>.php
```

Eine Theme-Einheit ist bewusst in sich geschlossen: Sonst liefert nichts in der Library Webfonts aus, alles, was das Stylesheet eines Themes per `@font-face` einbindet, muss also im eigenen `fonts/` dieses Themes liegen. Ein neuntes Theme hinzuzufügen ist genau ein neues Verzeichnis - ohne Codeänderung an irgendeiner Stelle, der Picker listet, was er vorfindet.

Eine `manifest.php` liefert ein einfaches Array zurück, nur mit den benötigten Schlüsseln:

| Schlüssel | Bedeutung |
|---|---|
| `label` | Anzeige im Picker |
| `moduleClass` | Nur Module - wird bei Auswahl zu `/nino/modules` hinzugefügt (ein Modul kann auch reines Template-Bundle ohne diesen Schlüssel sein - nichts als eigene Text-/Mail-Templates) |
| `routes` | Nur Seiten, genau ein Eintrag: `[ 'body' => ..., 'statusCode' => ... ]` (kein `uri` - beide URIs liefert der Webpages-Eintrag, der dieses Template nutzt, siehe Schritt 4 oben). `\Nino\Http::requestRoute()` matcht eine Route über den Array-Key `'<METHOD>:/'.$httpUri`, nicht über einen Scan nach einer Route mit passendem `uri`-Feld - ein Template kann daher immer nur genau die eine Http-URI belegen, die ein Webpages-Eintrag ihm zuweist, nie mehrere. Eine Seite mit sprachabhängigem Inhalt wählt diesen innerhalb ihres eigenen Bodys aus, über denselben `[[/nino/http/response/locale]]`-Fill, den html-header.tpl schon für den Seitentitel nutzt (siehe `pages/legal` als Beispiel) |
| `templates` | `'datei.tpl' => Locale-oder-null` - wird aus dem `templates/`-Ordner dieser Einheit in `/templates` des Projekts kopiert; `null` (oder ein einfacher, unbenannter Listeneintrag) heißt "immer", ein Locale-Code filtert genau wie bei `routes` |
| `elementTypes` | Nur Seiten. Dateinamen, die aus dem eigenen Wurzelverzeichnis der Einheit nach `/elements` kopiert werden |
| `blacklist` | Schlüssel, die an `/text/blacklist.php` angehängt werden (siehe Text-Panel von `_editor` / `docs/_editor.de.md`) |
| `requiresModules` | Weitere Modul-Schlüssel, die automatisch mit ausgewählt werden - bei Setup vom gewählten Modul aus, bei Webpages vom gewählten Template aus |
| `files` | Dateien/Verzeichnisse, die unverändert aus dem Wurzelverzeichnis der Einheit ins Projekt kopiert werden, unter gleichem Namen (`assets` -> `/assets`, `fonts` -> `/fonts`). Genutzt von `base`, `modules/democontent` und jedem Theme |
| `stylesheet` | Nur Themes, Pflicht - der projektrelative Pfad, unter dem das kopierte Stylesheet landet (z. B. `/assets/style.theme.agency.css`). Genau dieser wird in `/nino/html/assets` gebündelt; ein Theme ohne diesen Schlüssel taucht im Picker nicht auf |
| `description` | Nur Themes - der Beschreibungstext der Kachel |
| `preview` | Nur Themes - eine Bilddatei innerhalb der Einheit, gezeigt auf der Kachel und in der Lightbox. Wird direkt aus der Library ausgeliefert, nie ins Projekt kopiert |

Text-Fragmente (`text/global.php`, `text/<locale>.php`) sind einfache `'[[/key]]' => 'wert'`-Arrays, die in die echten `/text/global.php`/`/text/<locale>.php`-Dateien gemergt werden - dieselbe Form, die auch der Text-Editor von `_admin` schon verwendet. Das Text-Fragment einer Seite sollte **keine** `/webpage/<name>/*`-Schlüssel deklarieren - die schreibt Webpages selbst, keyed nach der Element-URI, die der Eintrag tatsächlich gewählt hat (`/webpage<uri>/name`, `.../title`, `.../description`), nicht nach dem Ordnernamen des Templates oder seiner Http-URI; ein Fragment, das trotzdem welche mitbringt, wird beim Anwenden defensiv herausgefiltert.

### Bekannte Einschränkungen (v1)

- **`sitemap.xml`/`llms.txt` werden nicht aus der Webpages-Liste zusammengesetzt** - sie enthalten nur die Wurzel-URL; ergänzen Sie einen Eintrag pro Seite von Hand.
- Der Rechtliches-Link/Cookie-Banner im Footer (`[[/website/legal/uri]]`/`[[/website/legal/name]]`) sowie der Aufruf des Localepickers setzen einen mit dem Template "legal" angelegten Eintrag bzw. 2+ Sprachen voraus. `[[/website/legal/uri]]` spiegelt die Http-URI dieses Eintrags (den echten, klickbaren Pfad), nicht seine Element-URI. Diese beiden Schlüssel werden nur gesetzt, nie zurückgesetzt - entfernen Sie später den "legal"-Eintrag aus der Webpages-Liste, zeigt der Footer-Link weiter auf dessen alte URI, bis entweder ein neuer "legal"-Eintrag hinzukommt oder der Block von Hand aus `templates/html-footer.tpl` entfernt wird.
- Es werden keine Bilder mitgeliefert - `<img>`-Tags in den ausgelieferten Templates (Logo, Hero) verweisen auf Pfade, die Sie selbst befüllen (oder über die Bild-Slots von `_editor` verwalten, siehe `docs/_editor.de.md`).

## Hinweise

- Die Daten jedes Schritts (Texte, Routen, Module, Admin-Accounts) lassen sich auch anschließend von Hand oder über `_admin` bearbeiten - der Assistent ist eine Komfortfunktion, nicht der einzige Weg.
- Weder `_admin` noch `_editor` müssen entfernt oder behalten werden - `/_install` funktioniert neben beiden und fasst `_admin/Admin.php` nur in seinem letzten Schritt an.
