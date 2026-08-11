# `/_templates` — Template Builder (Alpha)

**Sprache:** [English](_templates.md) · Deutsch

**Stand:** 11. August 2026 · **Nino-Version:** Unreleased

Der Template Builder ist der schnelle Weg vom `page-*.tpl` zur befüllten Seite. Er behandelt ein Template als geordnete Abfolge vollständiger HTML-Sections und wiederverwendbarer `[template]`-Sections, statt jeden verschachtelten DOM-Knoten zur Bearbeitung anzubieten.

[README](../README.de.md) · [`/_admin`-Bedienung](_admin.de.md) · [`/_templates`-Bedienung](_templates.de.md) · [`/_editor`-Bedienung](_editor.de.md) · [Entwickler-Handbuch](development.de.md)

> **Alpha:** Seitendateien bleiben gewöhnliches HTML+ und sind zur Laufzeit nicht vom Werkzeug abhängig. Preset-Library und Composer-Ablauf können sich noch verändern.

## Aufgabe und Abgrenzung

Nutze `/_templates`, um:

- vorhandene Dateien `templates/page-*.tpl` zu öffnen;
- ein neues `page-*.tpl` mit echtem Dateinamen, Anzeigenamen, Header, Footer und VPA-Standard anzulegen;
- vollständige Sections anhand ihres Aussehens aus einer durchsuchbaren, getaggten visuellen Library einzufügen;
- eine alleinstehende `[template /templates/<name>]`-Section direkt über **Add section** einzufügen, zu verschieben, zu ersetzen oder zu duplizieren;
- Header und Footer aus passenden Nicht-Seiten-`.tpl`-Dateien zu wählen oder einen Slot auf **None** zu setzen;
- eine stabile Section-ID zu vergeben;
- Oberfläche, Hintergrund, Überschrift, Content-Modul, Aktion, Layout und Viewport-Animation zu konfigurieren;
- alle sichtbaren Content-Bausteine umzusortieren, zu duplizieren oder zu entfernen, während Header und Footer außerhalb des Canvas fix bleiben;
- erzeugte Textfills direkt in der nativen Sprache zu befüllen;
- eine vorhandene Elements-Collection zu wählen oder den empfohlenen Elementtyp des Moduls anzulegen;
- eine einzelne Section bewusst als HTML+ zu bearbeiten, wenn der Composer nicht ausreicht.

Der Template Builder erzeugt keine Routen, bearbeitet nicht den Inhalt eingebundener Header-/Footer-Dateien, übersetzt nicht alle Sprachen und pflegt keine einzelnen Elements-Einträge. Seine isolierten Vorschauen verwenden das aktuelle Projekt-Stylesheet und definierte Beispieldaten, führen aber das JavaScript der fertigen Seite nicht aus. Dafür bleiben `/_admin`, das Frontend und Code zuständig. Der frühere DOM-orientierte Builder wurde durch diesen sectionbasierten Ablauf ersetzt.

## Zugang und Sicherheit

Öffne `https://deine-domain.example/_templates`. Das Werkzeug teilt Passwort, Sperrstatus und Sitzung mit `/_admin`. Es benötigt `_admin/Admin.php`; ohne `/_admin` fehlt auch sein Authentifizierungs-Backend.

Geschrieben werden:

- `templates/page-*.tpl` über **Save template**, **New page template** oder **Delete**; gelöschte Templates lassen sich im Builder nicht wiederherstellen;
- `text/<native-sprache>.php` beim Speichern der nativen Inhalte;
- `elements/<typ>.php`, wenn die automatische Elementtyp-Erstellung bestätigt ist;
- die Projektkonfiguration, wenn eine fehlende Bildplatz-Definition automatisch angelegt wird. Der eigentliche Bild-Upload bleibt in `/_admin`.

Verwende HTTPS, behandle das technische Passwort vertraulich und arbeite mit einem wiederherstellbaren Projektstand. Entferne Entwicklerwerkzeuge aus der Produktivauslieferung, wenn sie dort nicht benötigt werden.

## Hauptablauf

1. Wähle links ein Seitentemplate oder lege es über **New page template** an. Der Dialog fragt den vollständigen Dateinamen, Anzeigenamen, Header, Footer und den VPA-Standard ab.
2. Öffne **Add section**.
3. Durchsuche oder filtere unter **Choose** die große Galerie und wähle ein Preset anhand seiner echten Markup-Vorschau. Wiederverwendbare Nicht-Seiten-`.tpl`-Dateien stehen dort als Kategorie **Templates** bereit und werden ohne unnötigen Konfigurationsschritt eingefügt.
4. Wechsle zu **Configure & fill**, vergib eine sprechende ID wie `main-hero` oder `services-overview` und passe nur relevante Einstellungen an. Seltenere Abstands- und Rahmenoptionen liegen unter **Advanced**.
5. Befülle im selben Schritt **Native content**. Wähle für ein wiederholbares Modul eine Elements-Collection; existiert sie noch nicht, lasse die automatische Schema-Erstellung aktiv.
6. Vergleiche die Live-Vorschau und füge die Section ein. Empfohlene Textschlüssel, Elementtypen und Bildplatz-Definitionen können dabei mit angelegt werden.
7. Öffne bei Bedarf Bildplätze oder einzelne Elements-Einträge direkt in `/_admin`.
8. Ordne HTML- und Template-Section-Karten und speichere das Seitentemplate.
9. Prüfe die echte Seite im Browser. Vervollständige Übersetzungen danach über den vorhandenen JSON-Batch-Ablauf oder `/_admin`.

Die schnelle native Befüllung legt neue Schlüssel in der nativen Projektsprache an und ändert bei sprachabhängigen Schlüsseln nur diese Sprache. Ein bereits globaler Schlüssel bleibt bewusst global. Bestehende Übersetzungs-Buckets werden nie geleert oder überschrieben.

## Seiten- und Section-Einstellungen

**Name**, **Header**, **Footer**, **VPA** und **Delete** liegen in den Template Settings. Die Header-/Footer-Selects zeigen die echten `.tpl`-Dateinamen, listen dem Projekt bekannte Nicht-Seiten-Templates und bieten außerdem **None**. Der ausgewählte Wert wird weiterhin als gewöhnlicher `[template /templates/<name>]`-Shortcode geschrieben; die Controls verhindern nur, dass die Seitenschale mit verschiebbarem Content verwechselt wird. **Delete** entfernt genau die aktuell geladene Revision der Datei nach einer ausdrücklichen Bestätigung; eine Wiederherstellung erfordert Versionsverwaltung oder ein anderes externes Backup.

**VPA** auf Template-Ebene liefert den Standard für Sections mit der Einstellung **Page**. Eine Änderung setzt verwaltete Sections neu zusammen, aktualisiert deren `js-vpa`-Klasse und bleibt auch in einem noch leeren Template erhalten. **On** oder **Off** an einer einzelnen Section überschreibt den Template-Standard.

Der Composer gruppiert Einstellungen nach Zweck:

| Gruppe | Beispiele |
|---|---|
| Identität | Section-ID und entstehender Präfix `/page-<seite>/<section>/…` |
| Hintergrund & Überschrift | Oberfläche, Bild/Parallax, Überschriftenumfang, Ausrichtung |
| Content-Modul | Text, Media-Split, Artikel, Listen, Slider, Tabs, Testimonials, Team, Statistiken, Features, Pricing, Tabellen, Badges, Formulare, Galerie, Video, Hinweise |
| Content-Quelle | natives Textfill oder Elements-Collection |
| Aktion | keiner, Link, Primary-Button oder Primary- plus Outline-Button |
| Erweitert | Padding, Margin und Border |

Kuratierte Presets zeigen nur kompatible Auswahlmöglichkeiten. **Blank Section** stellt den vollständigen Composer bereit, wenn eine Kombination nicht durch ein fokussiertes Preset abgedeckt ist.

## Quelltext-Sicherheit und HTML+-Escape-Hatch

Beim Laden scannt das Backend oberste `<section>`-Elemente, ohne den umgebenden Quelltext zu serialisieren. Eine alleinstehende `[template /templates/<name>]`-Zeile außerhalb einer Section wird zur eigenen Template-Section-Karte; innerhalb einer Section bleibt der Shortcode Bestandteil dieser Section. Markierte Header-/Footer-Shortcodes werden stattdessen zu festen Settings-Slots. Sonstiger Quelltext wird als gesperrtes Raw-Segment ausgeliefert. Beim Speichern gilt:

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

System-Presets liegen unter:

```text
_templates/library/<preset>/manifest.php
```

Ein Manifest enthält Name, Beschreibung, Kategorie, Tags, Version, Defaults und erlaubte Einstellungswerte. Nicht angegebene Achsen werden auf ihren Default festgelegt; dadurch bleibt ein kuratiertes Preset fokussiert. Das Preset `blank` erlaubt ausdrücklich den gesamten Composer.

Eine optionale `section.tpl` neben dem Manifest erzeugt ein codebasiertes Preset. Verfügbare Tokens sind:

```text
{{section:id}}
{{section:classes}}
{{section:meta}}
{{content:prefix}}
{{elements:type}}
{{text:<suffix>}}
{{image:<suffix>}}
```

Der erzeugte Quelltext wird in die Seite kopiert. Wird das Preset später entfernt, bleibt die öffentliche Webseite funktionsfähig; nur die visuelle Composer-Bearbeitung dieser Section steht nicht mehr zur Verfügung.

## Aktuelle Grenzen

- Library und Konfiguration rendern erzeugtes Markup mit `/.cache/style.css` und Beispieldaten. Die Vorschauen sind isoliert und führen bewusst kein Projekt-JavaScript aus, senden keine Formulare ab und folgen keinen Links.
- Visuelle Content-Einheiten sind oberste `<section>`-Elemente und alleinstehende `[template /templates/<name>]`-Zeilen; markierte Header-/Footer-Slots liegen stattdessen in den Template Settings.
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
