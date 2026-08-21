# `/_install` — Referenzhandbuch

**Sprache:** [English](_install.md) · Deutsch

**Stand:** 8. August 2026 · **Nino-Version:** 0.11.0-beta.1

Dieses Handbuch erklärt die Entscheidungen und Schreibvorgänge der sieben Schritte von `/_install`. Falls du stattdessen auf dem kürzesten Weg vom Checkout zur eingerichteten Webseite gelangen möchtest, beginne mit [Erste Schritte](getting-started.de.md); den späteren produktiven Betrieb behandelt [Deployment](deployment.de.md).

**Weitere Links:**
[README](../README.de.md) · [Grundkonzepte](concepts.de.md) · [Entwickler-Handbuch](development.de.md) · [Erste Schritte](getting-started.de.md) · [`/_install`-Referenz](_install.de.md) · [`/_admin`-Bedienung](_admin.de.md) · [`/_templates`-Bedienung](_templates.de.md) · [`/_editor`-Bedienung](_editor.de.md) · [`/_theme`-Bedienung](_theme.de.md) · [Deployment](deployment.de.md) · [Security Policy](https://github.com/dapeio/nino/blob/main/SECURITY.md) · [Changelog](https://github.com/dapeio/nino/blob/main/CHANGELOG.md)

**Wichtig:** `/_install` erzeugt aus einem frischen Nino-Checkout den ersten lauffähigen Projektstand. Der Assistent ist notwendig: Vor seiner Ausführung existieren die eigentlichen Projektverzeichnisse wie `templates/`, `text/`, `elements/` und `images/` noch nicht.

## Wann `/_install` verwendet wird

Der Assistent ist für die einmalige Ersteinrichtung gedacht. Er:

- prüft PHP und Schreibrechte;
- legt Sprachen und Module fest;
- erzeugt die Projektverzeichnisse aus seiner Library;
- wendet ein Theme an;
- richtet die ersten Webseiten ein;
- erfasst zentrale Angaben zur Webseite;
- erstellt Zugänge für `/_editor` und `/_admin`; der Admin-Zugang gilt zugleich für `/_templates`.

`/_install` ist kein Updatewerkzeug für ein laufendes Projekt. Nach dem erfolgreichen Abschluss sperrt sich der Assistent selbst und kann aus der produktiven Auslieferung entfernt werden.

**Sicherheit:** Bis zum Abschluss besitzt `/_install` keinen individuellen Zugangsschutz. Führe die Einrichtung lokal oder in einer anderweitig geschützten Umgebung aus, nicht auf einer offen erreichbaren Domain.


## Navigation und Speichern

Jeder Schritt lädt den bereits gespeicherten Stand und zeigt ihn erneut an. Solange der Assistent nicht abgeschlossen ist, kannst du zu einem früheren Schritt zurückkehren, Einstellungen ändern und erneut anwenden.

Dabei gelten drei unterschiedliche Regeln:

| Datenart | Verhalten beim erneuten Anwenden |
|---|---|
| Sprachen, Module, erzeugte Routen und Seitenliste | die sichtbare Auswahl ersetzt den zuvor vom Assistenten verwalteten Stand |
| Templates, Texte und Element-Typen | werden ergänzt oder aktualisiert, aber nicht automatisch gelöscht |
| Theme-Dateien | gleichnamige Dateien werden überschrieben; zusätzliche Dateien eines früheren Themes bleiben bestehen |

Diese Unterscheidung schützt eigene Änderungen. Das Abwählen eines Moduls darf seine Konfiguration entfernen; eine zwischenzeitlich bearbeitete Template-Datei ungefragt zu löschen wäre dagegen nicht sicher.

## 1. Umgebung

Der erste Schritt prüft ausschließlich die Umgebung und schreibt keine Dateien. Kontrolliert werden:

- die laufende PHP-Version;
- die benötigten PHP-Erweiterungen;
- die Schreibbarkeit der Projektwurzel und bereits vorhandener Laufzeitpfade.

Noch nicht vorhandene Projektverzeichnisse sind erwartbar. Entscheidend ist, dass PHP sie später selbst erzeugen darf. „Erneut prüfen“ wiederholt nur dieselben Diagnosen.

Behebe fehlgeschlagene Prüfungen, bevor du fortfährst. Ohne ausreichende Schreibrechte kann der Assistent weder Konfiguration noch Inhalte zuverlässig erzeugen.

## 2. Setup

Setup legt Sprachen und funktionale Module fest und erzeugt die Basis des Projekts.

### Sprachen

**Available Locales** bestimmt die verfügbaren Sprachen. **Native Locale** ist die Standardsprache und muss Teil dieser Auswahl sein. Sie dient technisch als Rückfall, solange für einen Besucher noch keine Sprache feststeht, und bildet inhaltlich die „Muttersprache“ der Webseite.

Beim erneuten Anwenden ersetzt die sichtbare Sprachauswahl den bisherigen Stand. Die Standardsprache bleibt erhalten, sofern sie weiterhin ausgewählt ist; andernfalls verwendet Nino die erste gewählte Sprache.

### Module

Die Library kann unter anderem Demo-Inhalte, Navigation, Sprachauswahl, Formulare und Newsletter bereitstellen. Benötigt ein gewähltes Modul ein weiteres Modul, nimmt der Assistent diese Abhängigkeit automatisch in die Auswahl auf. Auch eine verwendete Seitenvorlage kann benötigte Module nachziehen; eine Kontaktseite aktiviert beispielsweise ihre Formular- und Mail-Funktionen.

Setup schreibt:

- verfügbare und native Sprache nach `config.php`;
- die aktivierten Modulklassen nach `/nino/modules`;
- die von Basis und Modulen gelieferten Routen nach `/nino/http/routes`;
- Templates nach `templates/`;
- globale und sprachabhängige Texte nach `text/`;
- mitgelieferte Element-Typen nach `elements/`;
- weitere deklarierte Dateien an ihre Projektpfade.

Sprachen, Module und die von Setup verwalteten Routen werden ersetzt. Manuell oder durch andere Bereiche angelegte Routen bleiben erhalten. Bereits kopierte Templates, Texte und Element-Typen löscht ein späteres Abwählen nicht.

## 3. Themes

Ein Theme ist ein vollständiger visueller Ausgangspunkt. Es kann Stylesheet, Schriften, Bilder und weitere Assets enthalten. Die Vorschau im Auswahlraster gehört nur zum Assistenten und wird nicht in das Projekt kopiert.

Es ist genau ein Theme aktiv. Beim Anwenden:

1. kopiert `/_install` die im Manifest genannten Dateien in das Projekt;
2. ersetzt das bisherige Theme-Stylesheet im Asset-Bundle `/.cache/style.css`;
3. speichert den gewählten Theme-Schlüssel unter `/nino/install/theme`.

Die Position des Stylesheets im Bundle bleibt nach Möglichkeit erhalten, damit sich die CSS-Kaskade nicht unbeabsichtigt ändert. Eigene zusätzliche Bundle-Einträge werden nicht entfernt.

**Wichtig:** Gleichnamige Theme-Dateien werden überschrieben. Dateien, die nur das vorherige Theme mitgebracht hat, bleiben dagegen liegen. Sichere eigene Änderungen deshalb über Git, bevor du das Theme wechselst oder erneut anwendest. Nach dem Abschluss sperrt sich `/_install`; ein späterer Theme-Wechsel über die Oberfläche ist daher nicht vorgesehen und wird als normale Projektänderung über Dateien und Git durchgeführt.

### Header und Footer

`<header>` und `<footer>` der Seite sind austauschbare Einheiten unter `_install/library/header/<key>` und `_install/library/footer/<key>` - je eine `template.tpl` und die `style.css` für das Markup, das diese Vorlage mitbringt. Beim Anwenden werden sie nach `templates/theme.header.tpl` bzw. `templates/theme.footer.tpl` kopiert und die beiden Stylesheets hinter dem des Themes gebündelt.

Die Basis-Seitenvorlagen binden die installierte Kopie über `[template /templates/theme.header]` ein, statt das Markup selbst zu tragen - ein Rahmen lässt sich später also durch Austausch dieser zwei Dateien wechseln. Ein Theme benennt die Rahmen, gegen die es gezeichnet wurde; die Theme-Auswahl wählt sie vor, die beiden Auswahlfelder übersteuern das.

Unter jedem Auswahlfeld steht der Rahmen gerendert: die echte Vorlage, gegen Framework, Design-Tokens und das Stylesheet des gewählten Themes, in derselben Reihenfolge, in der das CSS-Bundle sie lädt. Eine Versionsnummer sagt nichts über ein Layout, und anders als ein Theme hat ein Rahmen kein Vorschaubild zum Öffnen. Die Vorschau setzt ein, was das Projekt noch nicht hat: eine Platzhaltermarke an der Logo-Stelle, Beispiel-Navigationspunkte und für alles Übrige die Texte der Library. Sie ist ein eigenes, abgeschottetes Dokument statt Markup in der Seite, weil das Stylesheet eines Rahmens nackte Elementselektoren stylt, die sonst im Installer landen würden.

## 4. Design

Ein eigener Schritt, weil das Theme-Raster bereits eine Pane füllt und hier alles beim Ändern betrachtet werden muss.

Die Werte, aus denen das Theme liest. `/_theme` erzeugt die `--nino-*`-Tokens, ein Theme-Stylesheet weist sie Rollen zu, statt Literale zu schreiben.

**Farbe** — eine Primärfarbe, eine optionale Sekundärfarbe, eine Kontrast- und eine Farbstufe. Jeder Hintergrund entsteht gemeinsam mit der Textfarbe, die darauf gehört, gemessen gegen die WCAG-Kontrastformel — eine Markenfarbe kann also keinen unlesbaren Text erzeugen. Die Chips unter den Reglern zeigen die echten Paare, nicht nur die Hintergründe.

**Größe** — Volume (wie weit die Typo-Skala auffächert), Spacing (Abstände und Zeilenhöhe) und Shaping (Eckenradien). Das Specimen darunter wird in den erzeugten Größen gezeichnet; eine Liste von rem-Werten wäre schneller zu lesen und würde nichts sagen. Der Standardwert jeder Einstellung reproduziert die Skala von `Nino.css`, ein Projekt, das hier nichts ändert, wird also nicht bewegt.

Die Token-Namen beider Hälften stehen in [`_theme.de.md`](_theme.de.md).

Das Manifest eines Themes erklärt das Design, mit dem es gezeichnet wurde - ein Theme wählen und „Weiter" drücken erzeugt also den Look, den die Vorschau versprochen hat. Der Farbstreifen unter den Reglern zeigt die echten Paare, nicht nur die Hintergründe.

Dieser Teil des Schritts ist optional: Eine Auslieferung ohne `/_theme` installiert genau wie zuvor, nur ohne den Design-Block.

Die Reihenfolge im CSS-Bundle ist der ganze Vertrag, und jede der drei Auswahlen besitzt darin genau einen Platz:

```
_nino/Nino.css              Framework-Standardwerte
assets/style.design.css     Design - die erzeugten Werte
assets/style.theme.*.css    das Theme - welcher Wert in welche Rolle
assets/style.header.css     die Rahmen - Styling für ihr eigenes Markup
assets/style.footer.css
assets/style.css            die eigenen Übersteuerungen des Projekts
```

`/_theme` bleibt nach der Installation verfügbar, sodass ein Projekt ohne Neuinstallation umgefärbt werden kann. Siehe [`_theme.de.md`](_theme.de.md).

## 5. Routes

Dieser Schritt erzeugt die öffentliche Seitenstruktur. Die Liste lässt sich ergänzen, bearbeiten, löschen und sortieren. Mit „Weiter“ wird die gesamte sichtbare Liste als neuer Stand angewendet.

Jede Seite besitzt:

| Feld | Bedeutung |
|---|---|
| **Element-URI** | stabiler interner Bezeichner, beispielsweise `/home` |
| **HTTP-URI** | öffentlicher Browserpfad, beispielsweise `/` |
| **Template** | Library-Vorlage für Route, Template-Datei und Ausgangsinhalte |
| **Name** | sprachabhängige Bezeichnung, beispielsweise in der Navigation |
| **Title** | sprachabhängiger Seitentitel |
| **Description** | sprachabhängige Meta-Beschreibung |
| **Show in "…" navigation** | je eine Checkbox pro Navigation aus `/nino/html/navs`; die Zugehörigkeit landet als `'navs' => [ 'main' => 1, … ]` auf der Route der Seite, mit der Listenposition der Seite als Priorität |

Element-URI und HTTP-URI müssen innerhalb ihrer jeweiligen Spalte eindeutig sein. Sie dürfen voneinander abweichen: Die Startseite kann intern `/home` heißen und trotzdem unter `/` erreichbar sein.

Eine neue Seite startet mit den Vorschlägen der gewählten Library-Vorlage: HTTP-URI sowie Name, Title und Description in **jeder** aktiven Sprache, gelesen aus den `text/<locale>.php`-Dateien der Vorlage. Ein Wechsel der Vorlage aktualisiert nur Felder, die noch unverändert sind – selbst eingetragener Text bleibt erhalten. Ein leer gelassenes Feld fällt weiterhin auf den allgemeinen Platzhalter („Page“, „Page Title“) zurück.

`/_install` speichert keine eigene Liste: Der Schritt schreibt ausschließlich `/nino/http/routes` und die `/webpage<uri>/*`-Textschlüssel – `name`, `title` und `description` je Sprache, dazu einmalig `uri` (den erreichbaren Pfad der Seite) in `text/global.php`, als technischer Wert auf der Blacklist – und liest die angezeigte Liste beim nächsten Aufruf wieder daraus. Aus der angewendeten Liste entstehen außerdem Templates und bei Bedarf Modulabhängigkeiten. Die mitgelieferten Ausgangspunkte umfassen Startseite, Fehlerseite, rechtliche Angaben und Kontakt.

Eine Route mit dem Template **Blank** erhält eine eigene Kopie davon, benannt nach ihrer Element-URI: Aus einer Route `/team` wird `templates/page-team.tpl`, gerendert über `[template /templates/page-team]`. Blank ist der leere Startpunkt, jede Route damit braucht also eine eigene Seite — bei einer gemeinsamen Datei würde das Bearbeiten einer Blank-Seite alle anderen mit überschreiben. Eine verschachtelte Element-URI wird zu einem Namen zusammengezogen (`/jobs/open` → `page-jobs-open.tpl`), denn nur diese Form listen die Template-Auswahlen. Eine vorhandene Datei wird nie überschrieben; ein erneuter Durchlauf dieses Schritts lässt bereits Gebautes unangetastet. Ab da gehört das Template der Route, und sie wird als eigene Seite zurückgelesen statt als Blank-Einheit — genau wie eine in `/_admin` angelegte Seite. Alle anderen Vorlagen sind fertige Seiten und bleiben geteilt.

Beim erneuten Anwenden ersetzt die Liste nur die Routen, die aus ihrem vorherigen Stand entstanden sind. Manuell angelegte Routen bleiben erhalten. Entfernte Seiten löschen ihre bereits erzeugten Templates und tieferen Inhalte nicht automatisch.

## 6. Persönliche Angaben

„Personal Infos“ bündelt zentrale Textwerte, die unabhängig von Theme und Modulauswahl benötigt werden. Der Schritt bearbeitet ausschließlich die vorgesehenen Schlüssel unter `/company/*` und `/website/*`.

Sprachunabhängig sind beispielsweise:

- Unternehmensname;
- E-Mail-Adresse und Telefonnummer;
- Anschrift;
- Autor und Hosting-Anbieter der Webseite.

Land und Beschreibung werden je Sprache gespeichert. Wechsle deshalb jede aktive Sprache durch und speichere deren Werte.

Der Schritt zeigt bewusst nicht alle Textfills des Projekts. Technische Schlüssel, Design-Tokens und tiefere Seiten- oder Modulinhalte werden später vollständig über `/_admin` oder innerhalb der vergebenen Rechte in `/_editor` gepflegt.

## 7. Zugänge für `/_editor`

Lege mindestens ein erstes Konto für `/_editor` an. E-Mail-Adresse und Passwort müssen gültig sein; das Passwort benötigt mindestens acht Zeichen.

Die hier angelegten Nutzer erhalten vollständige Rechte über `/*`. Ein eventuell noch vorhandener, nicht verwendbarer Platzhalterzugang wird entfernt, sobald ein echtes Konto existiert. Derselbe Schritt kann mehrfach ausgeführt werden; eine bereits vorhandene E-Mail-Adresse wird dabei ersetzt. Für den üblichen Einstieg genügt ein Hauptkonto, weitere eingeschränkte Konten können nach der Installation folgen.

Die Zuständigkeiten sind bewusst geteilt:

- `/_admin` legt weitere Editor-Konten an, löscht sie und bietet einen technischen Wiederherstellungsweg für Berechtigungen;
- `/_editor` ändert die Daten bestehender Nutzer und erlaubt berechtigten Managern, die freigegebenen Rechte zuzuweisen.

So bleibt die Menge der Konten eine technische Strukturentscheidung, während die laufende Pflege bestehender Nutzer im Editor möglich bleibt.

## 8. Abschluss

Der letzte Schritt setzt das echte Passwort für `/_admin` und damit zugleich für `/_templates`. Es muss mindestens acht Zeichen lang sein und ist unabhängig von den Nutzerkonten in `/_editor`.

Vor dem Abschluss muss mindestens ein funktionierender Editor-Nutzer existieren. Der Assistent schreibt den neuen Passwort-Hash nach `private/.auth/pw.php` und setzt `/nino/install/completed` in der `config.php`. Jedes von beiden allein hält `/_install` gesperrt — geht die Passwortdatei verloren, sperrt sich `/_admin` zu, statt den Installer wieder freizugeben.

Schlägt der Abschluss fehl, prüfe die Schreibrechte des Verzeichnisses `private/`. Entferne den Installer nicht, bevor dieser Schritt erfolgreich beendet wurde.

## Ergebnis prüfen und Installer entfernen

Prüfe nach dem Abschluss:

- die öffentliche Startseite und alle eingerichteten Sprachen;
- jede angelegte Route einschließlich `/404`;
- den Login unter `/_editor`;
- den Login unter `/_admin`;
- den Zugriff auf `/_templates` mit demselben Passwort, sofern der Alpha-Builder verwendet werden soll;
- das Speichern eines Testtexts und eines Testbildes.

Entferne anschließend `_install/` aus der produktiven Auslieferung. Der abgeschlossene Projektstand benötigt die Library und den Assistenten nicht mehr. Seitenstruktur, vollständige Inhalte und technische Konfiguration werden danach über `/_admin`, Seitentemplates über `/_templates` und freigegebene redaktionelle Inhalte über `/_editor` gepflegt. Für tiefergehende Strukturarbeit bleiben der HTML+-Escape-Hatch und Code verfügbar.

## Library-Format

Die Library bildet die Ausgangsdaten, aus denen `/_install` das Projekt erzeugt:

| Einheit | Aufgabe |
|---|---|
| `base/` | immer angewendete Grundstruktur, beispielsweise Basis-Routen, Templates, Texte und Assets |
| `modules/<key>/` | wählbare funktionale Ergänzung mit Abhängigkeiten, Routen und Projektdateien |
| `themes/<key>/` | visueller Ausgangspunkt mit Vorschau, Stylesheet und weiteren Dateien; benennt über `header`, `footer` und `design` zusätzlich, wogegen er gezeichnet wurde |
| `header/<key>/`, `footer/<key>/` | austauschbarer Seitenrahmen: eine `template.tpl` und eine optionale `style.css`, sonst nichts - eine `manifest.php` besitzen diese Einheiten als einzige nicht |
| `pages/<key>/` | Vorlage für eine konkrete Seite mit Route, Template und Ausgangsinhalten |

Jede Einheit besitzt eine `manifest.php`. Das Manifest beschreibt, was der Assistent anzeigen, kopieren und konfigurieren soll. Je nach Einheit enthält es beispielsweise:

- Titel, Beschreibung und Vorschaubild;
- benötigte Module;
- Routen und Statuscodes;
- Template- und Element-Dateien;
- Textfragmente pro Sprache;
- Assets und weitere zu kopierende Dateien.

Die Library ist kein zur Laufzeit geladenes Plugin-System. Sie dient ausschließlich dazu, während der Ersteinrichtung einen kontrollierten, nachvollziehbaren Projektstand zu erzeugen. Eigene Library-Einheiten gehören deshalb wie der übrige Code zum Projekt und sollten gemeinsam mit ihm versioniert und geprüft werden.

## Was `/_install` bewusst nicht übernimmt

Der Assistent erzeugt einen belastbaren Ausgangspunkt, aber keine fertige individuelle Webseite. Nach der Einrichtung bleiben insbesondere:

- projektspezifische Gestaltung und Frontend-Entwicklung;
- vollständige redaktionelle Inhalte;
- Mail- und Hosting-Konfiguration;
- Tests mit realen Formularen und Empfängern;
- Sicherheitsprüfung und Deployment.

Diese Grenze ist beabsichtigt: `/_install` automatisiert wiederkehrende technische Grundlagen, ohne die Entscheidungen des konkreten Projekts zu ersetzen.

## Wie es weitergeht

- [Erste Schritte](getting-started.de.md) beschreibt den vollständigen Erfolgsweg.
- [Grundkonzepte](concepts.de.md) erklärt Datenfluss, Routing und Rendering.
- [`/_admin`-Bedienung](_admin.de.md) führt durch die vollständige Projektverwaltung nach der Einrichtung.
- [`/_templates`-Bedienung](_templates.de.md) erklärt die optionale strukturelle Template-Bearbeitung im Alpha-Status.
- [`/_editor`-Bedienung](_editor.de.md) erklärt die tägliche, berechtigungsgesteuerte Pflege der angelegten Inhalte.
- [Deployment](deployment.de.md) führt durch Sicherheit, Tests und Go-live.
