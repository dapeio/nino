# Der Einrichtungsassistent — Referenzhandbuch

**Sprache:** [English](setup.md) · Deutsch

**Stand:** 7. September 2026 · **Nino-Version:** 1.0.0-beta

Dieses Handbuch erklärt die Entscheidungen und Schreibvorgänge der sechs Schritte des Einrichtungsassistenten – des Erststart-Modus der [`/_admin`-Workbench](_admin.de.md). Falls du stattdessen auf dem kürzesten Weg vom Checkout zur eingerichteten Webseite gelangen möchtest, beginne mit [Erste Schritte](getting-started.de.md); den späteren produktiven Betrieb behandelt [Deployment](deployment.de.md).

**Weitere Links:**
[README](../README.de.md) · [Grundkonzepte](concepts.de.md) · [Entwickler-Handbuch](development.de.md) · [Rezepte](recipes/README.md) · [Erste Schritte](getting-started.de.md) · [Einrichtungsassistent](setup.de.md) · [`/_admin`-Workbench](_admin.de.md) · [Features](features.de.md) · [Deployment](deployment.de.md) · [Security Policy](https://github.com/dapeio/nino/blob/main/SECURITY.md) · [Changelog](https://github.com/dapeio/nino/blob/main/CHANGELOG.md)

**Wichtig:** Der Assistent erzeugt aus einem frischen Nino-Checkout den ersten lauffähigen Projektstand. Er ist notwendig: Vor seiner Ausführung existieren die eigentlichen Projektverzeichnisse wie `templates/`, `text/`, `elements/` und `images/` noch nicht.

## Wann der Assistent läuft

Bis sein letzter Schritt abgeschlossen ist, antwortet `/_admin` mit dem Assistenten statt mit der Anmeldung. Er liegt in `_admin/install/` und ist für die einmalige Ersteinrichtung gedacht. Er:

- prüft PHP und Schreibrechte;
- legt Sprachen und Module fest;
- erzeugt die Projektverzeichnisse aus seiner Library, das Aussehen der Seite darunter;
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
| Das Aussehen – `assets/theme.css` und die beiden Frame-Templates | gleichnamige Dateien werden überschrieben |

Diese Unterscheidung schützt eigene Änderungen. Das Abwählen eines Moduls darf seine Konfiguration entfernen; eine zwischenzeitlich bearbeitete Template-Datei ungefragt zu löschen wäre dagegen nicht sicher.

## 1. Umgebung

Der erste Schritt prüft ausschließlich die Umgebung und schreibt keine Dateien. Kontrolliert werden:

- die laufende PHP-Version;
- die benötigten PHP-Erweiterungen;
- die Schreibbarkeit der Projektwurzel und bereits vorhandener Laufzeitpfade.

Noch nicht vorhandene Projektverzeichnisse sind erwartbar. Entscheidend ist, dass PHP sie später selbst erzeugen darf. „Erneut prüfen“ wiederholt nur dieselben Diagnosen.

Behebe fehlgeschlagene Prüfungen, bevor du fortfährst. Ohne ausreichende Schreibrechte kann der Assistent weder Konfiguration noch Inhalte zuverlässig erzeugen.

## 2. Setup

Setup legt Sprachen fest und erzeugt die Basis des Projekts.

### Sprachen

**Available Locales** bestimmt die verfügbaren Sprachen. **Native Locale** ist die Standardsprache und muss Teil dieser Auswahl sein. Sie dient technisch als Rückfall, solange für einen Besucher noch keine Sprache feststeht, und bildet inhaltlich die „Muttersprache“ der Webseite.

Beim erneuten Anwenden ersetzt die sichtbare Sprachauswahl den bisherigen Stand. Die Standardsprache bleibt erhalten, sofern sie weiterhin ausgewählt ist; andernfalls verwendet Nino die erste gewählte Sprache.

### Module

Navigation, Sprachauswahl (der Locale Picker) und das Kontaktformular sind keine Wahl mehr: `\Nino\Install\Setup::ALWAYS_MODULES` nennt ihre Einheiten-Schlüssel, und jeder Setup-Durchlauf wendet alle drei Einheiten an und trägt alle drei Klassen in `/nino/modules` ein - genau wie bei einem tatsächlich gewählten Modul. Ein Entwicklerwerkzeug, das als Modul ausgeliefert wird, läuft weiter wie bisher - eingetragen, sobald seine Klasse existiert (`TOOL_MODULES`), ohne eigene Einheit. `Maintenance` ist das eine, das Nino noch mitbringt.

Die verbleibende Liste bietet jedes *andere* Modul an, das eine Installer-Einheit mitliefert: in einem frischen Checkout keines, dazu jedes Modul, das ein Projekt unter `app/` hinzugefügt hat, oder eine eigene Fassung unter `_admin/install/library/modules/`. Features – etwa Newsletter und Suche aus dem Katalog – werden auch hier nicht angeboten: Ein Feature wird aus [dapeio/nino-features](https://github.com/dapeio/nino-features) nach `features/` kopiert und nach der Einrichtung im [Panel Features](features.de.md) der Workbench eingeschaltet. Benötigt ein gewähltes Modul ein weiteres Modul, nimmt der Assistent diese Abhängigkeit automatisch in die Auswahl auf - und findet sie bereits vorhanden, wenn diese Abhängigkeit eines der drei immer aktiven Module ist. Auch eine verwendete Seitenvorlage kann benötigte Module nachziehen; eine Kontaktseite funktioniert zum Beispiel, weil das Modul des Kontaktformulars ohnehin immer da ist.

Setup schreibt:

- verfügbare und native Sprache nach `config.php`;
- die aktivierten Modulklassen - die immer aktiven drei, jedes Entwicklerwerkzeug, dessen Klasse existiert, und was sonst gewählt wurde - nach `/nino/modules`;
- die von Basis und jeder angewandten Einheit gelieferten Routen nach `/nino/http/routes`;
- Templates nach `templates/`;
- globale und sprachabhängige Texte nach `text/`;
- mitgelieferte Element-Typen nach `elements/`;
- weitere deklarierte Dateien an ihre Projektpfade.

Sprachen, die gewählten *anderen* Module und die von Setup verwalteten Routen werden bei einem späteren erneuten Anwenden ersetzt; die drei immer aktiven Einheiten und die Routen/Templates/Texte, die sie mitbringen, entfernt es dabei nie. Manuell oder durch andere Bereiche angelegte Routen bleiben erhalten. Bereits kopierte Templates, Texte und Element-Typen löscht ein späteres Abwählen nicht.

### Das Aussehen

Keine Wahl und kein Schritt: Die Base-Einheit liefert ein Theme aus, und jedes Projekt startet davon. Drei Dateien, kopiert wie jede andere Datei einer Einheit:

| Datei | Was sie ist |
|---|---|
| `assets/theme.css` | das ganze Aussehen in einem Stylesheet: die Design-Token, die Rollen, denen sie zugewiesen sind, die drei Webfaces und die CSS für beide Frames darunter |
| `templates/theme.header.tpl` | der `<header>` der Seite, von `html-header.tpl` über `[template /templates/theme.header]` eingebunden |
| `templates/theme.footer.tpl` | der `<footer>` der Seite, auf demselben Weg eingebunden |

Die Seitentemplates binden die beiden Frames ein, statt ihr Markup selbst zu tragen – jeder von beiden lässt sich also neu schreiben, ohne den Seitenrahmen darum anzufassen. Ein fehlender Include löst zu einer leeren Zeichenkette auf; deshalb führt die Base-Einheit beide Dateien auf: Eine Auslieferung, die eine davon vergisst, liefert eine Seite ohne Header aus, lautlos.

Die Reihenfolge im CSS-Bundle ist der ganze Vertrag, und jede Schicht besitzt darin genau einen Platz:

```
_nino/Nino.css              Framework-Vorgaben
assets/theme.css            das Aussehen - Token, Rollen, Schriften, beide Frames
assets/style.css            das eigene des Projekts, leer ausgeliefert
```

Setup ergänzt das Bundle unter `/nino/html/assets` um die beiden Projekteinträge, sofern sie noch nicht darin stehen, und hängt an, statt zu ersetzen – was ein Projekt selbst hinzugefügt hat, behält seinen Platz. `assets/style.css` wird einmal leer geschrieben und danach nie wieder angefasst, eine Regel dort überschreibt also alles darüber.

Bis Nino 1.1 hat der Assistent hier vier Fragen gestellt – ein Theme aus einem Katalog von zehn, einen Header und einen Footer aus dreizehn Frames und die daraus kompilierten Design-Werte. Das tut er nicht mehr. Der Katalog liegt in [`design-library/`](https://github.com/dapeio/nino-features/tree/main/design-library) des Feature-Repositories und wartet auf das Feature **Design**, das seine eigene CSS über die mitgelieferte kompilieren wird.

## 3. Routes

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

## 4. Persönliche Angaben

„Personal Infos“ bündelt zentrale Textwerte, die unabhängig von der Modulauswahl benötigt werden. Der Schritt bearbeitet ausschließlich die vorgesehenen Schlüssel unter `/company/*` und `/website/*`.

Sprachunabhängig sind beispielsweise:

- Unternehmensname;
- E-Mail-Adresse und Telefonnummer;
- Anschrift;
- Autor und Hosting-Anbieter der Webseite.

Land und Beschreibung werden je Sprache gespeichert. Wechsle deshalb jede aktive Sprache durch und speichere deren Werte.

Der Schritt zeigt bewusst nicht alle Textfills des Projekts. Technische Schlüssel, Design-Tokens und tiefere Seiten- oder Modulinhalte werden später in der Workbench gepflegt – unter Texte und Textschlüssel.

## 5. Accounts

Dieser Schritt legt das Root-Konto der Workbench an: die Rolle **Developer**, Vollzugriff über `/*`, das Konto, mit dem du dich unter `/_admin` anmeldest. Ein zweites entsteht durch erneutes Absenden, dann geht es weiter. Redaktionskonten mit weniger Rechten entstehen später im Panel **Nutzer** der Workbench aus der Rolle **Editor** – beide Rollen schreibt der Schritt Setup, und der Tab Nutzerrollen des Panels Nutzer bearbeitet sie.

Anzugeben sind:

- eine gültige **E-Mail-Adresse**;
- ein **Passwort** mit mindestens 8 Zeichen.

Beides lässt sich später unter **Nutzer** ändern. Die Konten liegen in der `config.php` unter `/nino/auth/user`.

## 6. Finish

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
- dass Header, Footer und die Webfonts, die das Theme deklariert, alle da sind;
- das Speichern eines Testtexts und eines Testbildes.

Entferne anschließend `_admin/install/` aus der produktiven Auslieferung: Nichts außerhalb liest seine Library, und was er bereits kopiert hat, bleibt dort liegen, wo es geschrieben wurde. Siehe [Deployment](deployment.de.md#der-assistent-nach-der-einrichtung). Struktur, Inhalte, Darstellung und Templates werden danach in der Workbench gepflegt; für tiefergehende Strukturarbeit bleiben der HTML+-Escape-Hatch und Code verfügbar.

## Library-Format

Alles, woraus der Assistent kopiert, ist einmalige Installer-Quelle, in einer von vier Formen:

| Pfad | Aufgabe |
|---|---|
| `_admin/install/library/base/` | immer angewendete Routen, Templates, Texte und Assets |
| `_nino/Nino/Modules/<Modul>/install/`, `app/…/<Modul>/install/` | die eigene Einheit eines Moduls: die wählbare funktionale Ergänzung, neben der Klasse, die sie aktiviert |
| `_admin/install/library/modules/<key>/` | eine wählbare Einheit ohne eigene Laufzeitklasse |
| `_admin/install/library/pages/<key>/` | Ausgangspunkt für eine konkrete Seite |

Alles unterhalb von `_admin/install/` wird nach dem Abschluss zusammen mit dem Assistenten entfernt; das `install/`-Verzeichnis eines Moduls bleibt bei seinem Modul, und nur der Assistent liest es. Die Library ist Einrichtungsmaterial und kein Laufzeit-Plugin-System.

Modul-Einheiten werden gefunden, nicht aufgelistet: Der Assistent durchsucht `_nino/Nino/Modules/*/install/` – Ninos eigene optionale Module – und dann das gesamte Anwendungsverzeichnis (`app/` oder `NINO_APP_DIR`) bis zu vier Ebenen tief, dazu `_admin/install/library/modules/`. Der Schlüssel einer Einheit – das, was die Auswahl zurückschickt und `requiresModules` nennt – ist das `key` des Manifests oder, ohne eines, der kleingeschriebene Name des Modulverzeichnisses; er muss ein Slug und eindeutig sein, und die erste Einheit, die einen Schlüssel beansprucht, behält ihn – Ninos eigene Module behalten also ihre.

Nicht durchsucht wird `features/`. Ein Feature trägt eine `install/`-Einheit derselben Form, aber `\Nino\Features::activate()` wendet sie an, wenn das Feature in der Workbench eingeschaltet wird – über dasselbe `applyUnit()`, das der Assistent verwendet, hier mit Überschreiben, dort nur ergänzend, damit die Anwendung der Einheit das Entfernen von `_admin/install/` überlebt. Siehe [Features](features.de.md).

Ein Entwicklerwerkzeug, das als Modul ausgeliefert wird, hat keine Einheit zum Auswählen: Der Setup-Schritt trägt es in `/nino/modules` ein, sobald seine Klasse existiert, sodass sein Panel von der ersten `config.php` an in der Workbench ist.

Die Basis-, Modul- und Seiteneinheiten besitzen je eine `manifest.php`. Das Manifest beschreibt, was angezeigt, kopiert und konfiguriert wird. Je nach Einheit enthält es beispielsweise:

- Titel, Beschreibung und Vorschaubild;
- benötigte Module;
- Routen und Statuscodes;
- Template- und Element-Dateien;
- Textfragmente pro Sprache;
- Assets und weitere zu kopierende Dateien.

Eigene Library-Einheiten gehören wie der übrige Code zum Projekt und sollten gemeinsam mit ihm versioniert und geprüft werden.

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
- Der **Template-Baukasten** – Seitentemplates aus ganzen Abschnitten – ist ein Feature aus dem Katalog [dapeio/nino-features](https://github.com/dapeio/nino-features); sein [Handbuch](https://github.com/dapeio/nino-features/blob/main/features/Templates/docs/templates.de.md) liegt dort ebenfalls.
- [Deployment](deployment.de.md) führt durch Sicherheit, Tests und Go-live.
