# Der Einrichtungsassistent — Referenzhandbuch

**Sprache:** [English](setup.md) · Deutsch

**Stand:** 5. September 2026 · **Nino-Version:** 0.13.0-beta

Dieses Handbuch erklärt die Entscheidungen und Schreibvorgänge der zehn Schritte des Einrichtungsassistenten – des Erststart-Modus der [`/_admin`-Workbench](_admin.de.md). Falls du stattdessen auf dem kürzesten Weg vom Checkout zur eingerichteten Webseite gelangen möchtest, beginne mit [Erste Schritte](getting-started.de.md); den späteren produktiven Betrieb behandelt [Deployment](deployment.de.md).

**Weitere Links:**
[README](../README.de.md) · [Grundkonzepte](concepts.de.md) · [Entwickler-Handbuch](development.de.md) · [Erste Schritte](getting-started.de.md) · [Einrichtungsassistent](setup.de.md) · [`/_admin`-Workbench](_admin.de.md) · [Templates-Panel](templates.de.md) · [Design-Panel](appearance.de.md) · [Deployment](deployment.de.md) · [Security Policy](https://github.com/dapeio/nino/blob/main/SECURITY.md) · [Changelog](https://github.com/dapeio/nino/blob/main/CHANGELOG.md)

**Wichtig:** Der Assistent erzeugt aus einem frischen Nino-Checkout den ersten lauffähigen Projektstand. Er ist notwendig: Vor seiner Ausführung existieren die eigentlichen Projektverzeichnisse wie `templates/`, `text/`, `elements/` und `images/` noch nicht.

## Wann der Assistent läuft

Bis sein letzter Schritt abgeschlossen ist, antwortet `/_admin` mit dem Assistenten statt mit der Anmeldung. Er liegt in `_admin/install/` und ist für die einmalige Ersteinrichtung gedacht. Er:

- prüft PHP und Schreibrechte;
- legt Sprachen und Module fest;
- erzeugt die Projektverzeichnisse aus seiner Library;
- wendet ein Theme an;
- legt Design, Header und Footer unabhängig fest;
- richtet die ersten Webseiten ein;
- erfasst zentrale Angaben zur Webseite;
- legt die ersten Entwicklerkonten der Workbench an;
- setzt das Recovery-Passwort.

Der Assistent ist kein Updatewerkzeug für ein laufendes Projekt. Nach dem erfolgreichen Abschluss sperrt er sich endgültig selbst aus, und `_admin/install/` kann aus der produktiven Auslieferung entfernt werden.

**Sicherheit:** Bis zum Abschluss besitzt der Assistent keinerlei Zugangsschutz. Führe die Einrichtung lokal oder in einer anderweitig geschützten Umgebung aus, nicht auf einer offen erreichbaren Domain.


## Navigation und Speichern

Jeder Schritt lädt den bereits gespeicherten Stand und zeigt ihn erneut an. Solange der Assistent nicht abgeschlossen ist, kannst du zu einem früheren Schritt zurückkehren, Einstellungen ändern und erneut anwenden.

Dabei gelten drei unterschiedliche Regeln:

| Datenart | Verhalten beim erneuten Anwenden |
|---|---|
| Sprachen, Module, erzeugte Routen und Seitenliste | die sichtbare Auswahl ersetzt den zuvor vom Assistenten verwalteten Stand |
| Templates, Texte und Element-Typen | werden ergänzt oder aktualisiert, aber nicht automatisch gelöscht |
| Theme- und Frame-Dateien | gleichnamige Dateien werden überschrieben; zusätzliche Dateien eines früheren Themes bleiben bestehen |

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

Die Liste bietet jedes Modul an, das eine Installer-Einheit mitliefert: in einem frischen Checkout Navigation, Sprachauswahl, Kontaktformular und Newsletter, dazu jedes Modul, das ein Projekt hinzugefügt hat. Benötigt ein gewähltes Modul ein weiteres Modul, nimmt der Assistent diese Abhängigkeit automatisch in die Auswahl auf. Auch eine verwendete Seitenvorlage kann benötigte Module nachziehen; eine Kontaktseite aktiviert beispielsweise ihre Formular- und Mail-Funktionen.

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

Ein Theme ist ein vollständiger visueller Ausgangspunkt unter `library/themes/<key>`. Es kann Stylesheet, Schriften, Bilder und weitere Assets enthalten. Die Vorschau im Auswahlraster gehört zum gemeinsamen Darstellungskatalog und wird nicht in das Projekt kopiert.

Es ist genau ein Theme aktiv. Beim Anwenden:

1. kopiert der Assistent die im Manifest genannten Dateien in das Projekt;
2. ersetzt das bisherige Theme-Stylesheet im Asset-Bundle `/.cache/style.css`;
3. speichert den gewählten Theme-Schlüssel unter `/nino/install/theme`;
4. installiert die Design-, Header- und Footer-Vorgaben des Manifests als vollständigen Ausgangspunkt für die folgenden Schritte.

Die Position des Stylesheets im Bundle bleibt nach Möglichkeit erhalten, damit sich die CSS-Kaskade nicht unbeabsichtigt ändert. Eigene zusätzliche Bundle-Einträge werden nicht entfernt.

**Wichtig:** Gleichnamige Theme-Dateien werden überschrieben. Dateien, die nur das vorherige Theme mitgebracht hat, bleiben dagegen liegen. Sichere eigene Änderungen deshalb über Git, bevor du das Theme wechselst oder erneut anwendest. Nach dem Abschluss sperrt sich der Assistent; das [Design-Panel](appearance.de.md) der Workbench stellt denselben Theme-Katalog für spätere Änderungen bereit.

## 4. Header

Der `<header>` der Seite ist eine austauschbare Einheit unter `library/header/<key>`, bestehend aus einer `template.tpl` und der `style.css` für ihr Markup. Das Theme wählt den Header vor, gegen den es gezeichnet wurde; dieser eigene Schritt übersteuert ausschließlich diese Auswahl — die Design-Werte sind zu diesem Zeitpunkt noch die vom Theme erklärten.

Beim Anwenden wird die Einheit nach `templates/theme.header.tpl` und `assets/style.header.css` kopiert, ihr Schlüssel unter `/nino/install/header` gespeichert und das Frame-Stylesheet direkt hinter dem Theme im CSS-Bundle gehalten. Dabei wird weder das Theme erneut kopiert noch das Design zurückgesetzt.

Das höhere Vorschau-Iframe rendert die echte Vorlage gegen Framework, das gewählte Theme, dessen erklärte Design-Werte und das eigene Stylesheet des Frames. Wer nach dem Design-Schritt hierher zurückgeht, sieht die Vorschau stattdessen gegen die festgelegten Werte — der Frame wird also immer auf dem gezeigt, was das nächste Weiter auch schreibt. Eine Versionsnummer sagt nichts über ein Layout, und anders als ein Theme hat ein Frame kein Vorschaubild zum Öffnen. Die Vorschau setzt ein, was das Projekt noch nicht hat: eine Platzhaltermarke an der Logo-Stelle, Beispiel-Navigationspunkte und für alles Übrige die Texte der Library. Sie ist ein eigenes, abgeschottetes Dokument, weil ein Frame-Stylesheet breite Selektoren verwendet, die nicht im Installer landen dürfen.

Die Basis-Seitenvorlagen binden die installierte Kopie über `[template /templates/theme.header]` ein, statt das Markup selbst zu tragen. Der Header lässt sich später also über dieselben zwei Projektdateien austauschen.

## 5. Footer

Der Footer-Schritt verwendet unabhängig davon denselben Einheitenvertrag unter `library/footer/<key>`. Beim Anwenden schreibt er `templates/theme.footer.tpl` und `assets/style.footer.css`, speichert `/nino/install/footer` und lässt Theme, Design sowie den gewählten Header unangetastet.

Sein höheres Vorschau-Iframe verwendet denselben Design- und Theme-Kontext wie die Header-Vorschau. Wird Footer nach Header angewendet, bleibt die kanonische Bundle-Reihenfolge erhalten: Theme, Header-Stylesheet, Footer-Stylesheet.

Die Basis-Seitenvorlagen binden ihn über `[template /templates/theme.footer]` ein.

## 6. Design

Ein eigener Schritt — und der letzte der drei, die den Look festlegen —, weil das Theme-Raster bereits eine Pane füllt und hier alles beim Ändern betrachtet werden muss. Beide Frames stehen zu diesem Zeitpunkt, das Specimen wird also auf der Seite gezeichnet, die das Projekt tatsächlich bekommt.

Die Werte, aus denen das Theme liest. Das Design-Modul erzeugt die `--nino-*`-Tokens, ein Theme-Stylesheet weist sie Rollen zu, statt Literale zu schreiben.

**Farbe** — eine Primärfarbe, eine optionale Sekundärfarbe, eine Kontrast- und eine Farbstufe. Jeder Hintergrund entsteht gemeinsam mit der Textfarbe, die darauf gehört, gemessen gegen die WCAG-Kontrastformel — eine Markenfarbe kann also keinen unlesbaren Text erzeugen. Die Chips unter den Reglern zeigen die echten Paare, nicht nur die Hintergründe.

**Größe** — Volume (wie weit die Typo-Skala auffächert), Spacing (Abstände und Zeilenhöhe) und Shaping (Eckenradien). Das Specimen darunter wird in den erzeugten Größen gezeichnet; eine Liste von rem-Werten wäre schneller zu lesen und würde nichts sagen. Der Standardwert jeder Einstellung reproduziert die Skala von `Nino.css`, ein Projekt, das hier nichts ändert, wird also nicht bewegt.

Die Token-Namen beider Hälften stehen in der Referenz [Design-Panel](appearance.de.md).

Das Manifest eines Themes erklärt das Design, mit dem es gezeichnet wurde - ein Theme wählen und „Weiter" drücken erzeugt also den Look, den die Vorschau versprochen hat. Der Farbstreifen unter den Reglern zeigt die echten Paare, nicht nur die Hintergründe.

Dieser Schritt ist optional: Eine Auslieferung ohne das Design-Modul (`app/Nino/Modules/Design/`) installiert genau wie zuvor, nur ohne den Design-Block.

Die Reihenfolge im CSS-Bundle ist der ganze Vertrag, und jede Ebene besitzt darin genau einen Platz:

```
_nino/Nino.css              Framework-Standardwerte
assets/style.design.css     Design - die erzeugten Werte
assets/style.theme.*.css    das Theme - welcher Wert in welche Rolle
assets/style.header.css     die Rahmen - Styling für ihr eigenes Markup
assets/style.footer.css
assets/style.css            die eigenen Übersteuerungen des Projekts
```

Die Bundle-Reihenfolge steht fest und ist unabhängig von der Reihenfolge der Schritte: Das Design wird zuletzt festgelegt, sein Stylesheet bleibt aber die Ebene, aus der das Theme liest.

Das Design-Panel bleibt nach der Installation verfügbar, sodass ein Projekt ohne Neuinstallation umgefärbt werden kann. Siehe die Referenz [Design-Panel](appearance.de.md).

Theme und beide Frame-Kataloge liegen neben den Basis-, Modul- und Seiten-Einheiten unter `_admin/install/library/`. Das Design-Panel liest sie mit, solange der Assistent ausgeliefert ist; sie sind Einrichtungsmaterial und werden beim Anwenden ins Projekt kopiert, nie zur Laufzeit gelesen.

## 7. Routes

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

Eine Seiteneinheit darf außerdem einheitenrelative `files` deklarieren. Sie
werden auf dieselben virtuellen Projektpfade kopiert; aus `images/demo.jpg`
wird damit das öffentliche `images/demo.jpg` des Projekts.

Der Assistent speichert keine eigene Liste: Der Schritt schreibt ausschließlich `/nino/http/routes` und die `/webpage<uri>/*`-Textschlüssel – `name`, `title` und `description` je Sprache, dazu einmalig `uri` (den erreichbaren Pfad der Seite) in `text/global.php`, als technischer Wert auf der Blacklist – und liest die angezeigte Liste beim nächsten Aufruf wieder daraus. Aus der angewendeten Liste entstehen außerdem Templates und bei Bedarf Modulabhängigkeiten. Die mitgelieferten Ausgangspunkte umfassen Startseite, Fehlerseite, rechtliche Angaben und Kontakt.

Eine Route mit dem Template **Blank** erhält eine eigene Kopie davon, benannt nach ihrer Element-URI: Aus einer Route `/team` wird `templates/page-team.tpl`, gerendert über `[template /templates/page-team]`. Blank ist der leere Startpunkt, jede Route damit braucht also eine eigene Seite — bei einer gemeinsamen Datei würde das Bearbeiten einer Blank-Seite alle anderen mit überschreiben. Eine verschachtelte Element-URI wird zu einem Namen zusammengezogen (`/jobs/open` → `page-jobs-open.tpl`), denn nur diese Form listen die Template-Auswahlen. Eine vorhandene Datei wird nie überschrieben; ein erneuter Durchlauf dieses Schritts lässt bereits Gebautes unangetastet. Ab da gehört das Template der Route, und sie wird als eigene Seite zurückgelesen statt als Blank-Einheit — genau wie eine im Panel Routen angelegte Seite. Alle anderen Vorlagen sind fertige Seiten und bleiben geteilt.

Beim erneuten Anwenden ersetzt die Liste nur die Routen, die aus ihrem vorherigen Stand entstanden sind. Manuell angelegte Routen bleiben erhalten. Entfernte Seiten löschen ihre bereits erzeugten Templates und tieferen Inhalte nicht automatisch.

## 8. Persönliche Angaben

„Personal Infos“ bündelt zentrale Textwerte, die unabhängig von Theme und Modulauswahl benötigt werden. Der Schritt bearbeitet ausschließlich die vorgesehenen Schlüssel unter `/company/*` und `/website/*`.

Sprachunabhängig sind beispielsweise:

- Unternehmensname;
- E-Mail-Adresse und Telefonnummer;
- Anschrift;
- Autor und Hosting-Anbieter der Webseite.

Land und Beschreibung werden je Sprache gespeichert. Wechsle deshalb jede aktive Sprache durch und speichere deren Werte.

Der Schritt zeigt bewusst nicht alle Textfills des Projekts. Technische Schlüssel, Design-Tokens und tiefere Seiten- oder Modulinhalte werden später in der Workbench gepflegt – unter Texte und Textschlüssel.

## 9. Accounts

Dieser Schritt legt das Root-Konto der Workbench an: die Rolle **Developer**, Vollzugriff über `/*`, das Konto, mit dem du dich unter `/_admin` anmeldest. Ein zweites entsteht durch erneutes Absenden, dann geht es weiter. Redaktionskonten mit weniger Rechten entstehen später im Panel **Nutzer** der Workbench aus der Rolle **Editor** – beide Rollen schreibt der Schritt Setup, und der Tab Nutzerrollen des Panels Nutzer bearbeitet sie.

Anzugeben sind:

- eine gültige **E-Mail-Adresse**;
- ein **Passwort** mit mindestens 8 Zeichen.

Beides lässt sich später unter **Nutzer** ändern. Die Konten liegen in der `config.php` unter `/nino/auth/user`.

## 10. Finish

Der letzte Schritt setzt das **Recovery-Passwort** und sperrt den Assistenten. Es ist kein Login: `/_admin/recovery.php` fragt danach, wenn die Konten selbst das Problem sind – um eine Sicherung wiederherzustellen oder ein Passwort zurückzusetzen –, und nichts in der Workbench fragt je danach (siehe [Recovery](_admin.de.md#recovery)).

Anzugeben ist:

- ein **Passwort** mit mindestens 8 Zeichen.

Sein Hash wird nach `private/.auth/pw.php` geschrieben, und das Projekt wird über `/nino/install/completed` in der `config.php` als installiert markiert. Jedes von beiden allein hält den Assistenten gesperrt; der Verlust der Passwortdatei gibt ihn also nicht wieder frei. Keines von beiden liegt in einem Werkzeugordner, weshalb ein Update `_nino/`, `_admin/` und die Module vollständig ersetzen kann.

Schlägt der Abschluss fehl, prüfe die Schreibrechte des Verzeichnisses `private/`. Nach diesem Schritt liefert `/_admin` die Anmeldung; der Assistent lässt sich nur wieder öffnen, indem `/nino/install/completed` entfernt und das gespeicherte Geheimnis gelöscht wird.

## Ergebnis prüfen und Assistenten entfernen

Prüfe nach dem Abschluss:

- die öffentliche Startseite und alle eingerichteten Sprachen;
- jede angelegte Route einschließlich `/404`;
- die Anmeldung unter `/_admin` mit dem Root-Konto;
- die vier Tabs des Design-Panels;
- ein im Templates-Panel geöffnetes `page-*.tpl`;
- das Speichern eines Testtexts und eines Testbildes.

Entferne anschließend `_admin/install/` aus der produktiven Auslieferung – die Library darunter wird damit ebenfalls entfernt, im Design-Panel bleibt der Tab Design nutzbar, Theme, Header und Footer haben danach nichts mehr aufzulisten – oder liefere es gesperrt weiter aus, wenn die drei umschaltbar bleiben sollen. Siehe [Deployment](deployment.de.md#der-assistent-nach-der-einrichtung). Struktur, Inhalte, Darstellung und Templates werden danach in der Workbench gepflegt; für tiefergehende Strukturarbeit bleiben der HTML+-Escape-Hatch und Code verfügbar.

## Library-Format

Nino trennt die einmaligen Installer-Quellen vom Darstellungskatalog, der danach bearbeitbar bleibt:

| Pfad | Aufgabe |
|---|---|
| `_admin/install/library/base/` | immer angewendete Routen, Templates, Texte und Assets |
| `app/Nino/Modules/<Modul>/install/`, `app/…/<Modul>/install/` | die eigene Einheit eines Moduls: die wählbare funktionale Ergänzung, neben der Klasse, die sie aktiviert |
| `_admin/install/library/modules/<key>/` | eine wählbare Einheit ohne eigene Laufzeitklasse |
| `_admin/install/library/pages/<key>/` | Ausgangspunkt für eine konkrete Seite |
| `_admin/install/library/themes/<key>/` | visueller Ausgangspunkt, gemeinsam vom Assistenten und dem Design-Panel verwendet |
| `_admin/install/library/header/<key>/`, `_admin/install/library/footer/<key>/` | austauschbarer Frame für beide |

Alles unterhalb von `_admin/install/` wird nach dem Abschluss zusammen mit dem Assistenten entfernt; das `install/`-Verzeichnis eines Moduls bleibt bei seinem Modul und wird zur Laufzeit nie gelesen. Der Katalog ist Einrichtungsmaterial und kein Laufzeit-Plugin-System.

Modul-Einheiten werden gefunden, nicht aufgelistet: Der Assistent durchsucht `_nino/Nino/Modules/*/install/`, dann `<app>/Nino/Modules/*/install/` – wo Ninos optionale Module ausgeliefert werden – und dann das gesamte Anwendungsverzeichnis (`app/` oder `NINO_APP_DIR`) bis zu vier Ebenen tief, dazu `_admin/install/library/modules/`. Der Schlüssel einer Einheit – das, was die Auswahl zurückschickt und `requiresModules` nennt – ist das `key` des Manifests oder, ohne eines, der kleingeschriebene Name des Modulverzeichnisses; er muss ein Slug und eindeutig sein, und die erste Einheit, die einen Schlüssel beansprucht, behält ihn – Ninos eigene Module behalten also ihre.

Das Design-Modul und der Template Builder haben keine Einheit zum Auswählen: Der Setup-Schritt trägt sie in `/nino/modules` ein, sobald ihr Verzeichnis Teil der Auslieferung ist, sodass beide Panels von der ersten `config.php` an in der Workbench sind.

Theme sowie die installerspezifischen Basis-, Modul- und Seiteneinheiten besitzen eine `manifest.php`. Das Manifest beschreibt, was angezeigt, kopiert und konfiguriert wird. Je nach Einheit enthält es beispielsweise:

- Titel, Beschreibung und Vorschaubild;
- benötigte Module;
- Routen und Statuscodes;
- Template- und Element-Dateien;
- Textfragmente pro Sprache;
- Assets und weitere zu kopierende Dateien.

Ein Frame besitzt dagegen kein Manifest: `template.tpl` und eine optionale `style.css` sind alles, was er erklärt. Eigene Library-Einheiten gehören wie der übrige Code zum Projekt und sollten gemeinsam mit ihm versioniert und geprüft werden.

## Was der Assistent bewusst nicht übernimmt

Der Assistent erzeugt einen belastbaren Ausgangspunkt, aber keine fertige individuelle Webseite. Nach der Einrichtung bleiben insbesondere:

- projektspezifische Gestaltung und Frontend-Entwicklung;
- vollständige redaktionelle Inhalte;
- Mail- und Hosting-Konfiguration;
- Tests mit realen Formularen und Empfängern;
- Sicherheitsprüfung und Deployment.

Diese Grenze ist beabsichtigt: Der Assistent automatisiert wiederkehrende technische Grundlagen, ohne die Entscheidungen des konkreten Projekts zu ersetzen.

## Wie es weitergeht

- [Erste Schritte](getting-started.de.md) beschreibt den vollständigen Erfolgsweg.
- [Grundkonzepte](concepts.de.md) erklärt Datenfluss, Routing und Rendering.
- [`/_admin`-Workbench](_admin.de.md) erklärt die Panels, die Konten und die Recovery-Seite.
- [Templates-Panel](templates.de.md) beschreibt den Template Builder im Alpha-Status.
- [Design-Panel](appearance.de.md) erklärt die vier Erscheinungsbild-Editoren.
- [Deployment](deployment.de.md) führt durch Sicherheit, Tests und Go-live.
