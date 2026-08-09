# `/_templates` — Template-Builder (Alpha)

**Sprache:** [English](_templates.md) · Deutsch

**Stand:** 8. August 2026 · **Nino-Version:** 0.11.0-beta.1

Dieses Handbuch erklärt die strukturelle Bearbeitung von Seiten- und Abschnittstemplates unter `/_templates`. Falls du stattdessen Texte, Elemente, Seiten oder Konfiguration vollständig verwalten möchtest, lies die [`/_admin`-Bedienungsanleitung](_admin.de.md); die direkte Arbeit mit HTML+, Rendering und Shortcodes behandelt das [Entwickler-Handbuch](development.de.md).

**Weitere Links:**
[README](../README.de.md) · [Grundkonzepte](concepts.de.md) · [Entwickler-Handbuch](development.de.md) · [Erste Schritte](getting-started.de.md) · [`/_install`-Referenz](_install.de.md) · [`/_admin`-Bedienung](_admin.de.md) · [`/_templates`-Bedienung](_templates.de.md) · [`/_editor`-Bedienung](_editor.de.md) · [Deployment](deployment.de.md) · [Security Policy](https://github.com/dapeio/nino/blob/main/SECURITY.md) · [Changelog](https://github.com/dapeio/nino/blob/main/CHANGELOG.md)

> **Status: Alpha.** Der Template-Builder ist nutzbar und durch Smoke-Tests abgesichert, wird sich aber voraussichtlich noch deutlich verändern. Oberfläche, Blockbibliothek und Bedienabläufe sind noch keine stabilen Verträge. Die gespeicherten Dateien bleiben dagegen normales, lesbares `.tpl`-Markup und können jederzeit direkt bearbeitet werden.

## Aufgabe und Abgrenzung

`/_templates` ist ein grafischer Struktur-Editor für ausgewählte Templates unter `templates/`. Er hilft dabei, Bereiche einzufügen, zu verschachteln, zu sortieren und über Klassen oder Attribute zu konfigurieren.

Der Builder zeigt bewusst nicht die fertige Webseite. Statt Farben, Schriften und realer Inhalte stellt die Arbeitsfläche vor allem dar:

- Rasterbreiten;
- vertikale Abstände;
- verschachtelte Container;
- bekannte Blocktypen;
- bestehendes, noch unbekanntes Markup.

Die visuelle Endkontrolle erfolgt deshalb immer im Frontend. Theme-Gestaltung und CSS bleiben im aktuellen Stand direkte Projektarbeit. Das geplante `/_themes` soll später ein eigenes grafisches Design-System für Theme-Vorlagen bereitstellen und zunächst ebenfalls als Alpha erscheinen.

## Zugang und Sicherheit

Öffne `https://deine-domain.example/_templates`. Der Bereich verwendet dasselbe technische Passwort, denselben Sperrstatus und dieselbe Sitzung wie `/_admin`.

Du kannst den Builder auch über **Template Builder** im Kopfbereich von `/_admin` öffnen. Ohne aktive Admin-Anmeldung erscheint zunächst dessen Login; nach erfolgreicher Anmeldung führt Nino zurück zum Builder.

Beachte für den Betrieb:

- verwende `/_templates` ausschließlich über HTTPS;
- gib den Zugang nur an Entwickler und vertrauenswürdige Designer mit technischem Verständnis weiter;
- arbeite mit einem aktuellen Git-Stand;
- prüfe jede Änderung im Frontend und in allen relevanten Viewports;
- entferne `_templates/` nach der Entwicklung aus der produktiven Auslieferung, wenn der Builder dort nicht benötigt wird.

`/_templates` hängt von `/_admin` ab. Wird `_admin/` entfernt, ist auch der Template-Builder nicht mehr funktionsfähig.

## Oberfläche

Der Arbeitsbereich besteht aus drei Spalten:

| Bereich | Aufgabe |
|---|---|
| **Templates** | vorhandene Templates auswählen und ihren Schreibstatus erkennen |
| **Canvas** | Struktur auswählen, verschachteln und umordnen |
| **Settings / Blocks** | Eigenschaften des gewählten Blocks bearbeiten oder neue Blöcke einfügen |

Beim Öffnen eines Templates liest der Builder dessen HTML-Struktur ein. Änderungen bleiben zunächst im Browser und werden erst mit **Save** in die Datei geschrieben.

> **Wichtig:** Ein Wechsel des Templates oder das Verlassen der Seite kann ungespeicherte Änderungen verwerfen. Der Builder warnt davor, trotzdem ersetzt diese Warnung keinen bewussten Speichervorgang.

## Welche Templates bearbeitet werden können

Beschreibbar sind ausschließlich:

- `templates/page-*.tpl`;
- `templates/section-*.tpl`.

Andere Templates werden zwar in der Liste angezeigt, bleiben aber schreibgeschützt. Das betrifft insbesondere Dateien, die Header oder Footer trennen, keinen HTML-Baum enthalten oder sich nicht zuverlässig und bytegenau einlesen und wieder ausgeben lassen.

Diese Grenze verhindert, dass der Builder technische Includes oder unbekannte Sonderfälle versehentlich umschreibt. Solche Dateien werden weiterhin direkt im Editor beziehungsweise in der Entwicklungsumgebung bearbeitet.

## Ein Template bearbeiten

1. Wähle links ein beschreibbares Template.
2. Klicke im Canvas auf den Block, den du bearbeiten möchtest.
3. Ändere rechts die angebotenen Einstellungen.
4. Füge bei Bedarf einen Block aus **Blocks** hinzu.
5. Ordne, dupliziere oder entferne ausgewählte Blöcke.
6. Speichere mit **Save**.
7. Öffne die betroffene Seite im Frontend und prüfe das Ergebnis.

Die verfügbaren Einstellungen werden aus der Blockdefinition abgeleitet. Je nach Block können sie unter anderem CSS-Klassen, responsive Rasterbreiten, Abstände, Attribute, Tags oder direkt bearbeitbaren Text steuern.

Beim Einfügen entscheidet die aktuelle Auswahl über die Position:

- bei einem geeigneten Container wird der neue Block darin eingefügt;
- bei einem Blatt-Element wird er daneben eingefügt;
- ohne passende Auswahl landet er am Ende des Dokuments.

Die Aktionen zum Verschieben nach oben oder unten, Duplizieren und Entfernen beziehen sich immer auf den ausgewählten Block.

## Struktur statt Metadaten

Der Builder speichert keine zusätzliche Projektdatei und versieht das Template nicht mit proprietären Builder-Attributen. Die Identität und Einstellungen eines Blocks ergeben sich aus seinem HTML-Tag und seinen CSS-Klassen.

Dadurch gelten zwei wichtige Eigenschaften:

1. Vorhandene, von Hand geschriebene Templates bleiben grundsätzlich bearbeitbar.
2. Ein gespeichertes Template bleibt normales HTML+ und kann anschließend wieder von Hand geändert werden.

Wird ein Template geöffnet und unverändert gespeichert, muss der Inhalt bytegenau erhalten bleiben. Kann der Builder diesen Round-Trip nicht sicher gewährleisten, bietet er die Datei nur schreibgeschützt an.

## Unbekanntes Markup

Nicht jedes Element muss in der Blockbibliothek bekannt sein. Unbekanntes Markup erscheint als gestrichelter Strukturblock und bleibt beim Speichern erhalten. Bekannte Kindblöcke darin können weiterhin ausgewählt und bearbeitet werden.

Prüfe solche Bereiche besonders sorgfältig im Quelltext und Frontend. Der Builder erhält die Struktur, kann für unbekannte Elemente aber keine passenden Einstellungen oder semantischen Hinweise anbieten.

Verwendet das Projekt CSS-Selektoren, die gezielt auf eine Element-ID wie `#hero` zugreifen, zeigt der Builder einen Hinweis. Da die Oberfläche ihre Darstellung vor allem aus Klassen ableitet, kann sie die vollständige CSS-Kaskade solcher Selektoren nicht zuverlässig nachbilden.

## Blockbibliothek

Die Palette bündelt wiederverwendbare Strukturbausteine und Hilfselemente. Eine Blockdefinition besteht im Kern aus einem Manifest unter `_templates/library/` und kann zusätzlich eigenes Ausgangs-Markup mitbringen.

Die Bibliothek enthält derzeit unter anderem Bausteine für:

- Seiten- und Abschnittsstruktur;
- Raster und Spalten;
- Abstände und Ausrichtung;
- Typografie und Medien;
- Navigation und interaktive Komponenten;
- Hilfselemente für vorhandene Klassenmodelle.

Da der Builder Alpha ist, sind Umfang, Benennung und Gruppierung dieser Palette noch veränderlich. Maßgeblich bleibt das gespeicherte Template, nicht die Anzahl aktuell mitgelieferter Blöcke.

## Speichern und Schutzregeln

Vor dem Schreiben prüft Nino den resultierenden Template-Baum. Ein Speichervorgang wird unter anderem abgelehnt, wenn:

- die Datei nicht dem Muster `page-*.tpl` oder `section-*.tpl` entspricht;
- der resultierende Baum leer ist;
- ein HTML-Tag außerhalb der erlaubten Liste vorkommt;
- ein Event-Handler-Attribut wie `onclick` oder ein anderes `on*`-Attribut enthalten ist.

Insbesondere `<script>` gehört nicht in einen über den Builder gespeicherten Strukturblock. JavaScript wird als Projekt-Asset entwickelt und eingebunden.

Das Schreiben erfolgt atomar: Erst wenn die neue Datei vollständig bereitsteht, ersetzt sie den vorherigen Stand. Der Builder legt dabei keine eigene Sicherungskopie an. Templates gehören deshalb in Git; zusätzlich enthalten die von Nino erzeugten Projektbackups die Template-Dateien.

## Grenzen des aktuellen Alpha-Stands

Der Template-Builder konzentriert sich auf das sichere Bearbeiten vorhandener Seiten- und Abschnittsstrukturen. Noch nicht Teil des stabilen Ablaufs sind insbesondere:

- das Anlegen eines vollständig neuen Templates über die Oberfläche;
- das Extrahieren einer Auswahl in ein neues `section-*.tpl`;
- eine pixelgenaue Vorschau des finalen Themes;
- ein stabil zugesagter Umfang der Blockbibliothek.

Für neue oder technisch besondere Templates bleibt die direkte Arbeit an `.tpl`-Dateien daher ein normaler Bestandteil der Entwicklung.

## Wenn etwas nicht funktioniert

| Problem | Prüfung |
|---|---|
| Login wird erneut angezeigt | Mit dem Passwort von `/_admin` anmelden; Sperrstatus und Session-Cookies prüfen. |
| Template ist schreibgeschützt | Dateiname, HTML-Round-Trip und unterstützten Dateityp prüfen. |
| Einstellung fehlt | Block ist möglicherweise unbekannt oder die Bibliotheksdefinition bietet diese Option noch nicht an. |
| Darstellung im Canvas weicht vom Frontend ab | Der Canvas zeigt Struktur, keine vollständige Theme-Vorschau; CSS, Inhalte und `#id`-Selektoren im Browser prüfen. |
| Speichern wird abgelehnt | Leeren Baum, unerlaubte Tags, `on*`-Attribute und Schreibrechte von `templates/` prüfen. |
| Änderung ist nach dem Wechsel verschwunden | Ungespeicherte Änderungen wurden nur im Browser gehalten; erneut durchführen und vor dem Wechsel speichern. |

## Wie es weitergeht

- [`/_admin`-Bedienung](_admin.de.md) erklärt Elemente, Texte, Seiten und technische Konfiguration.
- [Entwickler-Handbuch](development.de.md) beschreibt HTML+, Rendering, Assets und Tests.
- [Grundkonzepte](concepts.de.md) ordnet Templates in Datenfluss und Architektur ein.
- [Deployment](deployment.de.md) behandelt Zugriffsschutz, Schreibrechte und die Entfernung optionaler Werkzeuge.
