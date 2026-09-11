# Features — Handbuch und Vertrag

**Sprache:** [English](features.md) · Deutsch

**Stand:** 11. September 2026 · **Nino-Version:** 1.2.0-beta

Dieses Handbuch erklärt, was ein Feature ist, wie ein Betreiber es im Panel **Features** der Workbench einschaltet, und was ein Entwickler liefern muss, damit ein Verzeichnis eines wird: das Manifest, das Settings-Schema, den Lebenszyklus und die Tests. Der Kernel-Vertrag dahinter ist `\Nino\Features` in `_nino/Nino/Features/Features.php`; `tests/features-smoke.php` prüft ihn gegen die Fixtures unter `tests/fixtures/features/`. Wer ein eigenes Feature Schritt für Schritt bauen will, folgt dem [Feature-Rezept](recipes/feature.md).

**Weitere Links:**
[README](../README.de.md) · [Grundkonzepte](concepts.de.md) · [Entwickler-Handbuch](development.de.md) · [Rezepte](recipes/README.md) · [Erste Schritte](getting-started.de.md) · [Einrichtungsassistent](setup.de.md) · [`/_admin`-Workbench](_admin.de.md) · [Features](features.de.md) · [Deployment](deployment.de.md) · [Security Policy](https://github.com/dapeio/nino/blob/main/SECURITY.md) · [Changelog](https://github.com/dapeio/nino/blob/main/CHANGELOG.md)

## Was ein Feature ist

Ein Feature ist ein installierbares Paket: ein Verzeichnis unterhalb von `features/`, das ein Modul mitbringt – seine Laufzeitklasse, bei Bedarf ein Panel der Workbench, eine Install-Einheit mit Templates und Texten, und ein Manifest `feature.php`, das sagt, was es ist, für welche Nino-Version es geschrieben wurde und welche Einstellungen es anbietet. Ein Feature wird nicht installiert: Es wird in das Verzeichnis gelegt – als Kopie, später als Download – und in der Workbench eingeschaltet. Ein Checkout bringt kein Feature mit; `features/` enthält nichts als seine `.htaccess`. Die Features, die Nino veröffentlicht – `Newsletter` und `Search` darunter –, liegen im Katalog-Repository [dapeio/nino-features](https://github.com/dapeio/nino-features), dort je ein Verzeichnis unterhalb von `features/` mit Manifest – und Test, README und Changelog, wo das Feature sie mitbringt –, und kommen in ein Projekt, indem dieses Verzeichnis in dessen eigenes `features/` kopiert wird.

Drei Arten von Modulen gibt es in einem Nino-Projekt, und der Unterschied ist, wem das Verzeichnis gehört:

| Art | Ort | Wer schaltet es ein |
|---|---|---|
| **Kernel-Modul** | `_nino/Nino/Modules/<Name>/` – die immer aktiven (`Assets`, `Cache`, `Csrf`, `Elements`, `Images`, `Jstext`, `Template`) und die optionalen (`Form`, `Navigation`, `Localepicker`, `Maintenance`) | der Einrichtungsassistent, oder von Hand in `/nino/modules` |
| **Feature** | `features/<Name>/`, ein Verzeichnis je Feature, mit `feature.php` | das Panel Features |
| **Projektmodul** | `app/<Vendor>/…` unter einem eigenen Namespace | von Hand in `/nino/modules` |

„Modul“ bleibt der technische Begriff für die Klasse, die in `/nino/modules` steht und deren `init()` der Kernel aufruft; „Feature“ ist das Paket, das eine solche Klasse liefert. Ein Kernel-Modul gehört zu Nino und wird mit `_nino/` als Ganzes ersetzt. Ein Projektmodul gehört dem Projekt und trägt seinen eigenen Namespace. Ein Feature liegt dazwischen: Es kommt von außen, wird aber vom Projekt gehalten – mit einer Versionsnummer, mit Einstellungen in der `config.php` und mit Dateien unter `data/`, die dem Projekt gehören, nicht dem Feature.

**Warum?** Vor dieser Aufteilung lagen Ninos optionale Module unter `app/Nino/Modules/`, neben den Klassen des Projekts, und ein Update musste sie Verzeichnis für Verzeichnis vergleichen. Jetzt gehört `app/` allein dem Projekt, `_nino/` ist wieder vollständig ersetzbar, und ein Paket, das ein Projekt hinzunimmt, hat einen eigenen Ort mit einem eigenen Vertrag.

## Das Verzeichnis `features/`

Jedes Feature ist genau ein Verzeichnis, benannt nach der Modulklasse, die es mitbringt – am Newsletter-Feature des Katalogs, einmal hineinkopiert: `features/Newsletter/Newsletter.php` ist `\Nino\Modules\Newsletter`, `features/Newsletter/Admin/Admin.php` ist `\Nino\Modules\Newsletter\Admin`. Der Verzeichnisname muss ein Klassenname-Segment sein (`Newsletter`, nicht `newsletter`), denn er *ist* der Klassenname – ein Manifest kann keine andere Klasse erklären, siehe [Das Manifest](#das-manifest-featurephp).

Der Autoloader löst `Nino\Modules\*` als zusammengeführte Sicht über vier Roots auf, in dieser Reihenfolge: `_nino/`, `_admin/`, `features/`, `app/`. Unter dem Features-Root ist das Präfix `Nino/Modules/` das Verzeichnis selbst – `features/<Name>/<Name>.php`, nicht `features/Nino/Modules/<Name>/`. Die Reihenfolge sagt, was ein Root dem anderen antun darf: `_nino/` zuerst, damit ein ausgeliefertes Modul nie verdeckt werden kann; `_admin/` vor `features/`, damit ein Feature keine Workbench-Ansicht ersetzt; `features/` vor `app/`, damit ein Projekt kein installiertes Feature ersetzt, indem es eine Datei neben seine eigenen Module legt; `app/` zuletzt und damit nur ergänzend. Die Details stehen im [Entwickler-Handbuch](development.de.md#verzeichnis-und-autoloading).

`NINO_FEATURES_DIR` verlegt das Verzeichnis, so wie `NINO_APP_DIR` es für `app/` tut. Die Konstante wird vor dem Laden von `_nino/Nino.php` definiert – in jedem Einstiegspunkt, also in `index.php`, `_admin/index.php` und `_admin/recovery.php`, die alle drei die Zeile auskommentiert tragen. Der Autoloader und `\Nino\Features::dir()` lesen dieselbe Konstante, ein verlegtes Verzeichnis liefert also auch die Klassen. Es wird als Ganzes ersetzt: Ein Projekt, das es woandershin zeigen lässt, nimmt seine Features mit, oder verliert sie ohne ein Wort.

**Sicherheit:** Alles in `features/` ist serverseitiger Quelltext – Klassen, Panels, Mail- und Seitentemplates, Textdateien. `features/.htaccess` sperrt den Baum wie `app/.htaccess` das seine, und `router.php` tut dasselbe für den eingebauten Server. Ein Webserver, der `.htaccess` nicht liest, braucht die entsprechende Regel; siehe [Deployment](deployment.de.md#nginx-und-andere-webserver). Die browserseitigen Assets eines Features werden wie alle anderen von der Platte gelesen und nach `public/.cache/` beziehungsweise `_admin/.cache/` gebündelt – nichts darin wird je direkt angefragt. Für die Website fügt die Klasse sie in `init()` den Bundles des Projekts hinzu: `\Nino\Html::addAsset( $appData, '/.cache/style.css', '/features/<Name>/assets/site.css' )`, ebenso für `/.cache/script.js`; `\Nino\Filesystem::path()` löst `/features/...` gegen das Features-Verzeichnis auf, verlegt oder nicht. Ein Panel nennt seine Workbench-Assets stattdessen über `assets()`, siehe [Das Panel](#das-panel).

## Das Panel Features

Das Panel liegt in der Gruppe System der Workbench und verlangt bei jeder Aktion `/_admin/features/manage` – eine Entwicklerberechtigung. Es sortiert jedes Verzeichnis unter `features/`, das ein gültiges Manifest trägt, in drei Tabs – **Aktiv**, **Inaktiv**, **Verfügbar** –, jeder mit einer Anzahl beschriftet, Aktiv zuerst und offen. Daneben stehen zwei Filter: ein Suchfeld über Name, Schlüssel, Beschreibung und Kategorie, und eine Auswahl, die auf eine einzelne Kategorie einschränkt – gebaut aus den Kategorien, die die Features und Angebote auf dem Bildschirm tatsächlich tragen, sie bietet also nie eine Überschrift an, unter der nichts liegt. Beide schränken alle Tabs zugleich ein, und die Anzahlen schränken sich mit ihnen ein, sodass eine Suche sagt, wo der Treffer liegt. Tabs und Filter bleiben oben in der Pane stehen, während die Zeilen scrollen, und innerhalb eines Tabs sind die Features nach dem lokalisierten Namen sortiert; die Kategorie führt die Zeile unter jedem Namen an. Wo auch immer ein Feature steht, zeigt es Name und Beschreibung in der Oberflächensprache, die Version aus dem Manifest und das, was einer Aktivierung entgegensteht: eine Nino-Version, für die das Feature nicht geschrieben wurde, eine PHP-Erweiterung, die fehlt, ein benötigtes Feature, das nicht im Verzeichnis liegt. Die Namen kommen aus dem Manifest selbst, nicht aus Textfills, denn die Fills eines Features, das nicht aktiv ist, sind nicht geladen.

Die sieben Aktionen des Panels sind `features/list`, `features/activate`, `features/deactivate`, `features/settings`, `features/remove`, `features/catalogue` und `features/install`; hinter den ersten fünfen stehen `\Nino\Features::all()`, `activate()`, `deactivate()`, `saveSettings()` und `remove()`, hinter den letzten beiden `\Nino\Catalogue::fetch()` und `install()`:

- **Aktivieren** schaltet ein Feature ein. Benötigte Features werden zuerst aktiviert, die Install-Einheit des Features wird angewendet, ohne etwas zu überschreiben, das dein Projekt bereits hat, die Klasse wird in `/nino/modules` eingetragen und die Version unter `/nino/features` aufgezeichnet.
- **Deaktivieren** trägt die Klasse wieder aus – und sonst nichts. Einstellungen, Daten und kopierte Templates bleiben, ein erneutes Einschalten findet alles vor, wie es war. Ein Feature, das ein anderes aktives Feature unter `requires` nennt, hält dieses fest: Das benötigte Feature lässt sich nicht deaktivieren, solange das andere aktiv ist.
- **Entfernen** löscht das Verzeichnis eines inaktiven Features – der eine Schritt, den das Deaktivieren bewusst auslässt. Was das Feature behalten hat, bleibt: seine Einstellungen unter `/nino/features`, seine Dateien unter `data/`, alles, was seine Einheit ins Projekt kopiert hat – wer dasselbe Feature zurückholt, findet seine Einstellungen also wieder. Für ein aktives Feature wird es abgewiesen: Seine Klasse steht in `/nino/modules`, und ein Verzeichnis, das unter dem Autoloader weggelöscht wird, ist beim nächsten Request ein Fatal und keine Meldung.
- **Update** bietet das Panel für ein aktives Feature an, dessen Manifest eine andere Version nennt als die aufgezeichnete – nach dem Ersetzen des Verzeichnisses durch eine neue Fassung. Die Aktualisierung ist dieselbe Aktion wie das Aktivieren: Die Einheit ergänzt, was neu ist, und das Modul darf seine eigenen Daten migrieren, bevor die neue Version aufgezeichnet wird.
- **Einstellungen** stehen auf dem Bildschirm, in den die Zeile eines aktiven Features einsteigt – zusammen mit seinem Update, wo eines wartet, und mit **Deaktivieren**. Das Formular ist das, was das Manifest beschreibt, und Speichern legt es unter `/nino/features` in der `config.php` ab. Jede Einstellung wird geprüft, bevor eine geschrieben wird; ein Fehler nennt die Einstellung, und nichts wird gespeichert.

Der Tab **Verfügbar** ist der Katalog: was er anbietet und diese Installation noch nicht aktuell hat. Über den Tabs schickt der Knopf **Katalog aktualisieren** der Aktionsleiste `features/catalogue`, hinter dem `\Nino\Catalogue::fetch()` und `offers()` stehen (`features/install` steht hinter `install()`), und eine Statuszeile nennt, wann der Katalog zuletzt gelesen wurde, oder dass er noch nicht gelesen wurde. Nichts wird von selbst geladen: `features/list` antwortet mit dem, was `\Nino\Catalogue::cached()` zuletzt unter `data/catalogue.php` hinterlassen hat, sodass Verfügbar sich beim Öffnen des Panels sofort füllt, ohne eine eigene Anfrage; nur Aktualisieren ruft `fetch()` erneut auf. Geladen, bietet Verfügbar **Installieren** für ein Feature, das nicht im Verzeichnis liegt, **Update** für eines, das dort in einer älteren Version liegt, und graut, mit dem, was es verlangt, eines aus, von dem keine Version passt – eines, das schon aktuell ist, erscheint hier gar nicht erst. Das Installieren lädt das Archiv, prüft es gegen den signierten Katalog und legt das Verzeichnis an; eine Aktualisierung ersetzt das Verzeichnis und wendet bei einem aktiven Feature das Update im selben Schritt an. Die Angebote selbst werden immer gegen die Features abgeglichen, die gerade auf der Platte liegen, also zeigt sich eine Installation auf Verfügbar auch ohne ein neues Laden. Wo `features/` nicht beschreibbar ist, verlinkt der Tab stattdessen das Archiv, zum Entpacken von Hand. Siehe [Der Katalog](#der-katalog).

**Wichtig:** Ein Panel, das ein Feature mitbringt, erscheint nach dem Aktivieren erst mit dem nächsten Laden der Workbench, und verschwindet nach dem Deaktivieren ebenso erst dann – die Leiste wird einmal je Seitenaufruf aus der Panel-Registry gebaut. Lade die Seite neu. Es landet in der eigenen **Features**-Gruppe der Leiste, gleich was sein eigenes `nav()` nennt – siehe [Panels der Workbench](development.de.md#panels-der-workbench) – und bietet seine Berechtigung auf dem Tab Nutzerrollen des Panels Nutzer unter genau dieser Gruppenbezeichnung an; die Rolle **Editor**, die der Assistent vor der Aktivierung geschrieben hat, erhält sie nicht von selbst – gib sie ihr dort.

## Das Manifest `feature.php`

Das Manifest liegt neben der Klassendatei und gibt ein Array zurück. Das Beispiel ist das Feature aus dem [Feature-Rezept](recipes/feature.md), ein Katalog mit öffentlichem JSON-Endpunkt und einem Panel:

```php
<?php
// features/Catalog/feature.php - what the Features panel reads. The class is
// not declared here: features/Catalog/ can only ever serve \Nino\Modules\Catalog.
return [
	'key'					=> 'catalog',
	'name'				=> [ 'en_US' => 'Catalog', 'de_DE' => 'Katalog' ],
	'description'	=> [
		'en_US' => 'A product catalogue with a public JSON endpoint and a workbench panel.',
		'de_DE' => 'Ein Produktkatalog mit öffentlichem JSON-Endpunkt und einem Panel der Workbench.',
	],
	'manual'			=> [
		'en_US' => 'Put `[catalog]` where the list belongs. `limit` and `sort` narrow it.',
		'de_DE' => 'Setze `[catalog]` dorthin, wo die Liste hin soll. `limit` und `sort` schränken sie ein.',
	],
	'category'		=> 'content',
	'version'			=> '1.1.0',
	'nino'				=> '^1.0',
	'php'					=> [ 'ext' => [ 'json' ] ],
	'requires'		=> [],
	// The files under data/ this feature owns - what a backup carries
	'data'				=> [ '/data/catalog.php' ],
	'settings'		=> [
		'title'		=> [ 'type' => 'string', 'label' => [ 'en_US' => 'Title', 'de_DE' => 'Titel' ], 'required' => true, 'maxlength' => 60, 'default' => 'Catalog' ],
		'pageSize'	=> [ 'type' => 'int', 'label' => 'Items per page', 'min' => 1, 'max' => 100, 'unit' => 'items', 'default' => 12 ],
		'public'	=> [ 'type' => 'bool', 'label' => 'Public API', 'hint' => 'Whether GET /api/catalog answers at all', 'default' => true ],
		'layout'	=> [ 'type' => 'select', 'label' => 'Layout', 'options' => [ 'list' => 'List', 'grid' => [ 'en_US' => 'Grid', 'de_DE' => 'Raster' ] ], 'default' => 'list' ],
	],
];
```

| Schlüssel | Bedeutung |
|---|---|
| `key` | der Slug des Features (`/^[a-z][a-z0-9-]*$/`): was `requires` nennt, was `/nino/features` als Schlüssel trägt und was `\Nino\Features::setting()` fragt. Ohne Angabe der kleingeschriebene Verzeichnisname |
| `name` | ein String oder eine Map `locale => string`; Pflicht. Das, was eine Zeile im Features-Panel sagt – der Key steht dort nie –, der Name muss das Feature also von den anderen unterscheiden, die ein Projekt installieren könnte. Der Kernel nimmt, was er bekommt, denn zwei davon können aus zwei Katalogen kommen, über die er nicht bestimmt; zusammengehalten wird das im Katalog, so wie [dapeio/nino-features](https://github.com/dapeio/nino-features) zwei Features mit einem Namen abweist |
| `description` | ein String oder eine Map `locale => string`; optional |
| `manual` | ein String oder eine Map `locale => string`; optional. Wie das Feature benutzt wird – das Panel setzt es in eine Box oben auf seinen Bildschirm: Absätze an Leerzeilen, `` `Backticks` `` für Code, sonst nichts, höchstens 10000 Zeichen je Sprache. Kein README: was jemand braucht, um einen Shortcode zu setzen oder ein Attribut zu vergeben, nicht was ein Entwickler braucht, um den Quelltext zu lesen |
| `category` | wofür das Feature da ist, ein Slug – siehe [Kategorien](#kategorien); optional, und das, wonach das Features-Panel gruppiert und filtert |
| `version` | `major.minor.patch`, optional mit Pre-Release-Suffix (`1.0.0-beta.2`); Pflicht. Was das Panel zeigt und `activate()` aufzeichnet |
| `nino` | die Nino-Version, für die das Feature geschrieben wurde, als Constraint; ohne Angabe `*` |
| `php` | `[ 'ext' => [ … ] ]`: PHP-Erweiterungen, die geladen sein müssen |
| `requires` | Schlüssel anderer Features, die vorher aktiv sein müssen; der eigene und doppelte werden verworfen |
| `settings` | `name => schema`, siehe [Das Settings-Schema](#das-settings-schema); ein Name ist ein lowerCamel-Bezeichner (`/^[a-z][a-zA-Z0-9]*$/`) |
| `data` | Pfade unterhalb von `/data/`, die das Feature besitzt – was ein Backup trägt und ein Restore-Callback zusammenführt; `..` ist verboten |

Ein lokalisierter Wert – `name`, `description`, ein `label`, ein `hint`, eine Option eines `select` – wird über `\Nino\Features::localized( $value, $locale )` gelesen: die gefragte Sprache, sonst `en_US`, sonst der erste Eintrag, sonst ein leerer String.

### Kategorien

Eine Kategorie pro Feature: das grobe „wofür ist das da", nach dem das Features-Panel gruppiert und nach dem ein Katalog von vierzig Features durchsucht wird. `\Nino\Features::CATEGORIES` veröffentlicht das Vokabular:

| `category` | Was dort hingehört |
|---|---|
| `content` | Inhaltstypen, und was vorhandene Inhalte zugänglich macht – Beiträge, eine Galerie, Termine, eine Suche |
| `ui` | wie das Vorhandene aussieht und sich verhält – Effekte, ein Slider, eine Lightbox, Seitenkomposition |
| `communication` | Nachrichten von und an Besucher – ein Kontaktformular, ein Newsletter, Kommentare |
| `marketing` | gefunden werden, und es messen – Sitemaps, Social-Cards, Seitenaufrufe |
| `security` | Zugriff schützen, personenbezogene Daten – ein Passwortbereich, ein Consent-Banner, Spamabwehr |
| `system` | Infrastruktur, die ein Besucher nie sieht – ein Mailtransport, entfernte Backups, Webhooks, Importe |

Passt ein Feature in zwei davon, wird von oben nach unten entschieden und die erste genommen, die zutrifft: Schützt es Zugriff oder verarbeitet es personenbezogene Daten (`security`)? Geht es um Auffindbarkeit oder Messung (`marketing`)? Tauscht es Nachrichten mit Menschen aus (`communication`)? Bringt es Inhalte – oder macht es vorhandene zugänglich (`content`)? Verändert es nur, wie Vorhandenes aussieht (`ui`)? Was übrig bleibt, ist `system`. Maßgeblich ist, was der Betreiber will, nie wie das Feature gebaut ist: eine Bildergalerie ist `content`, eine Lightbox über bereits vorhandenen Bildern ist `ui`.

Der Kernel akzeptiert jeden Slug (`/^[a-z][a-z0-9-]{0,23}$/`), nicht nur diese sechs. Ein Feature, das für einen neueren Katalog als den laufenden Kernel geschrieben wurde, steht unter einer Kategorie, die dieser Kernel nicht kennen kann, und muss sich trotzdem installieren lassen – eine unbekannte wird also übernommen und so angezeigt, wie sie dasteht, und das Vokabular wird dort durchgesetzt, wo Features veröffentlicht werden, nicht dort, wo sie gelesen werden. Ein Manifest ohne `category` ist gültig; das Panel sagt dann einfach nichts dazu.

Die Klasse wird nicht erklärt, sondern abgeleitet: `\Nino\Modules\<Verzeichnis>`. Ein `module`-Eintrag, der dieselbe Klasse nennt, wird angenommen; einer, der etwas anderes sagt, wird abgelehnt. Denn der Autoloader liefert aus `features/<Name>/` nur diese eine Klasse, und ein Manifest, das etwas anderes verspricht, wäre falsch.

Die Versionsangabe unter `nino` versteht genug des Composer-Vokabulars, um ein Manifest zu schreiben:

| Constraint | Erfüllt von |
|---|---|
| `*` | jeder Version |
| `1.2.3` | genau dieser Version; `1.2` von jeder `1.2.x`, `1` von jeder `1.x.y` |
| `>=1.2`, `<=1.2`, `>1.2`, `<1.2`, `!=1.2.3` | dem Vergleich |
| `^1.0` | derselben Major-Version ab `1.0.0` (`^0.13`: derselben Minor-Version ab `0.13.0`) |
| `~1.2` | derselben Major-Version ab `1.2.0`; `~1.2.3` derselben Minor-Version ab `1.2.3` |
| `>=1.0 <2.0`, `>=1.0, <2.0` | allen Teilen zugleich |
| `2.0 \|\| ^1.0` | einer der Alternativen |

Ein Pre-Release-Kernel zählt als die Version, der er vorausgeht: Ein Feature mit `^1.0` läuft auf `1.0.0-beta`. `\Nino\Features::satisfies( $constraint, $version )` ist die Funktion dahinter, mit der laufenden Kernel-Version als Standard.

Ein Manifest, das nicht vollständig gültig ist, wird nicht halb angewendet: Das Verzeichnis wird mit einer Warnung übersprungen, die die Datei und den Grund nennt – ein Verzeichnisname, der kein Klassenname ist, eine fehlende Klassendatei, ein Schlüssel, der kein Slug ist, eine Version, die nicht `major.minor.patch` ist, ein Setting mit unbekanntem Typ, ein Default, der sein eigenes Schema nicht besteht. Ein zweites Verzeichnis, das einen bereits vergebenen Schlüssel beansprucht, wird ebenso übersprungen. `\Nino\Features::all()` liest das Verzeichnis einmal je Request und antwortet je Feature mit dem normalisierten Manifest sowie `dir`, `module`, `active`, `installed` (die zuletzt aufgezeichnete Version, `null` vor der ersten), `update` (aktiv, und die aufgezeichnete Version ist eine andere als die des Manifests) und `problems`.

## Das Settings-Schema

Jede Einstellung ist ein Formularfeld, das das Panel rendern und der Kernel prüfen kann. Diese Schlüssel gelten für jeden Typ:

| Schlüssel | Bedeutung |
|---|---|
| `type` | einer von `bool`, `int`, `string`, `text`, `email`, `url`, `select`, `secret`, `lines`; Pflicht |
| `label` | Beschriftung, String oder `locale => string`; ohne Angabe der Name der Einstellung |
| `hint` | Erklärung unter dem Feld, String oder `locale => string` |
| `required` | `true`, wenn ein leerer Wert abgelehnt wird; Standard `false` |
| `default` | der Wert, den `settings()` liefert, solange nichts gespeichert ist; wird gegen das eigene Schema geprüft. Ein `secret` darf keinen haben |

Und diese je Typ:

| Typ | Wert | Eigene Schlüssel | Prüfung |
|---|---|---|---|
| `bool` | `true`/`false` | – | ein Formular darf `'true'`/`'false'`, `1`/`0` und `'1'`/`'0'` senden; alles andere wird abgelehnt, nie umgedeutet |
| `int` | ganze Zahl | `min`, `max` (Ints, `min` nicht über `max`), `unit` (die Einheit des Werts, zur Anzeige) | eine gepostete Ziffernfolge wird zur Zahl; `5.5` ist keine; die Grenzen gelten |
| `string` | eine Zeile | `maxlength` (1 bis 1000, Standard 1000), `pattern` (regulärer Ausdruck, den der Wert treffen muss – verankere ihn selbst) | getrimmt; ein leerer optionaler Wert übergeht das Pattern |
| `text` | mehrere Zeilen | `maxlength` (1 bis 10000, Standard 10000) | getrimmt, Zeilenumbrüche bleiben |
| `email` | eine Adresse | `maxlength` wie `string` | `FILTER_VALIDATE_EMAIL` |
| `url` | eine Adresse | `maxlength` wie `string` | `FILTER_VALIDATE_URL` und ein `http`- oder `https`-Schema; `javascript:` wird abgelehnt |
| `select` | ein Optionswert | `options`: `wert => label`, nicht leer, jedes Label String oder `locale => string` | genau einer der Werte; ein leerer Wert, wenn nicht `required` |
| `secret` | ein String, den das Formular nie zurückzeigt | `maxlength` wie `string` | `''` behält den gespeicherten Wert, `null` löscht ihn, alles andere ersetzt ihn |
| `lines` | eine Liste von Strings | – | ein Textarea-Wert wird an Zeilenumbrüchen geteilt, eine Liste wird genommen; jede Zeile getrimmt, leere Zeilen und Doppelte entfernt, höchstens 1000 Zeichen je Zeile und 200 Zeilen |

Die Werte liegen unter `/nino/features` in der `config.php`, je Feature als `key => { version, settings }`:

```php
'/nino/features' => [
	'catalog' => [
		'version'  => '1.1.0',
		'settings' => [ 'pageSize' => 24, 'public' => false ],
	],
],
```

Ein Feature liest seine Einstellungen über den Kernel, nie aus dem Array selbst:

```php
$pageSize = \Nino\Features::setting( $appData, 'catalog', 'pageSize', 12 );
$all      = \Nino\Features::settings( $appData, 'catalog' );
```

`settings()` beantwortet jede Einstellung, die das Schema erklärt – mit dem gespeicherten Wert, sonst mit dem `default`, sonst mit dem Nullwert des Typs (`false`, `min` oder `0`, `[]`, `''`) – und nur diese: Ein fremder Schlüssel in der `config.php` ist keine Einstellung. Ein gespeicherter Wert, der das Schema nicht mehr besteht – etwa nach einem strenger gewordenen Schema oder einer Handänderung –, wird nicht an das Feature gereicht; stattdessen kommt der Default. `setting()` antwortet für eine Einstellung, die das Schema nicht kennt, mit dem übergebenen `$default`.

Ein Formular postet Strings und bekommt echte Typen zurück: `'25'` wird für ein `int` zu `25`, `'false'` für ein `bool` zu `false`. `\Nino\Features::validateSettings( $schema, $posted, $current )` prüft jede Einstellung, bevor eine angenommen wird, und antwortet mit `values` und `errors` (`name => Meldung`); eine Einstellung, die das Formular nicht gesendet hat, behält ihren aktuellen Wert. `saveSettings( $appData, $key, $posted )` prüft so und schreibt dann den Schlüssel `/nino/features` einmal – oder gar nicht, solange irgendetwas falsch ist.

## Der Lebenszyklus

Es gibt keinen Installationsschritt. Ein Feature liegt im Verzeichnis, und alles Weitere ist eine Aktion des Panels beziehungsweise ein Aufruf von `\Nino\Features`.

### Aktivieren

`\Nino\Features::activate( $appData, $key )` antwortet mit `true` oder mit dem Grund, warum nicht. Der Reihe nach:

1. Ein Feature mit `problems` wird abgewiesen, und die Antwort nennt sie alle.
2. Die Features unter `requires` werden zuerst aktiviert – ein Zyklus wird erkannt und bricht ab, statt endlos zu laufen. Ein benötigtes Feature, das im Verzeichnis liegt, aber nicht aktiv ist, ist kein Hindernis; eines, das fehlt, schon.
3. Liegt ein Verzeichnis `install/` neben der Klasse, wird seine Einheit angewendet – **ohne etwas zu überschreiben**. Eine Route, deren Schlüssel die `config.php` bereits kennt, bleibt; ein Template, das `templates/` bereits hat, bleibt; eine Datei, die da ist, bleibt; ein Textschlüssel, der in `text/<locale>.php` oder `text/global.php` steht, bleibt. Nur was fehlt, wird ergänzt. Ein `config`-Standardwert der Einheit wird nur gesetzt, wo das Projekt keinen hat. Die Blacklist-Einträge der Einheit werden in `text/blacklist.php` zusammengeführt.
4. Die Klasse wird in `/nino/modules` eingetragen und die Version des Manifests unter `/nino/features/<key>/version` aufgezeichnet; gespeicherte Einstellungen bleiben, wie sie sind. `/nino/modules`, `/nino/features` und – wenn die Einheit welche ergänzt hat – `/nino/http/routes` werden gezielt geschrieben.

Die Einheit wird gegen die *gespeicherten* Routen der `config.php` angewendet, nie gegen die lebenden: Das lebende Array trägt die Laufzeitrouten dieses Requests – die der Workbench, die ein aktives Modul in `init()` registriert –, und die dürfen nicht in die `config.php` geschrieben werden. Dieselbe Regel gilt im Setup-Schritt des Assistenten.

Ein aktives Feature erneut zu aktivieren, ohne dass sich seine Version geändert hat, verändert nichts.

### Aktualisieren

Ein Feature wird aktualisiert, indem sein Verzeichnis durch die neue Fassung ersetzt wird und es im Panel erneut aktiviert wird – **Update** ist dieselbe Aktion. Die Einheit ergänzt, was neu ist, und lässt alles stehen, was das Projekt seit der ersten Aktivierung bearbeitet hat: Ein Template, das du geändert hast, wird nicht durch die neue Fassung ersetzt. Was ein Feature unter `data/` hält, kann sich mit der Version aber in seiner Form ändern, und dafür gibt es einen Haken in der Modulklasse:

```php
public static function upgrade( array &$appData, string $fromVersion ): bool
```

`activate()` ruft ihn, wenn das Feature aktiv ist, die aufgezeichnete Version eine andere ist als die des Manifests und die Klasse die Methode hat – mit der aufgezeichneten Version als `$fromVersion`, nachdem die Einheit angewendet wurde und bevor die neue Version aufgezeichnet wird. Das Modul migriert darin seine eigenen Daten. Gibt es `false` zurück, wird die Aktualisierung abgewiesen, und die aufgezeichnete Version bleibt die alte. Eine Klasse ohne `upgrade()` wird ohne Migration aktualisiert.

### Deaktivieren

`\Nino\Features::deactivate( $appData, $key )` trägt die Klasse aus `/nino/modules` aus und schreibt diesen Schlüssel – und sonst nichts. Die Einstellungen und die aufgezeichnete Version unter `/nino/features` bleiben, die Dateien unter `data/` bleiben, kopierte Templates und Texte bleiben, die Routen der Einheit bleiben. Ein erneutes Aktivieren findet das Feature also vor, wie es war. Die Aktion wird abgewiesen, solange ein anderes aktives Feature dieses unter `requires` nennt; ein inaktives Feature zu deaktivieren ist harmlos.

Es gibt keine Deinstallation, mit Absicht: Eine Datei zu löschen, die das Projekt inzwischen bearbeitet haben könnte, wäre nicht sicher. Was ein Feature hinterlassen hat, entfernt ein Entwickler bewusst und von Hand.

### Daten, Backups und Wiederherstellung

Was ein Feature unter `data/` schreibt, gehört dem Projekt: Das tägliche Backup der Workbench trägt es, und `data` im Manifest dokumentiert, welche Dateien das sind. Ein Feature, das eine Wiederherstellung nicht als Überschreiben, sondern als Zusammenführen braucht, registriert in `init()` den Callback `'/nino/admin/restore'` – so, wie ein Modul es immer getan hat. Das Panel Backups und die Recovery-Seite rufen ihn mit `{ dataDir, staging }` auf, dem lebenden `data/`-Verzeichnis und dem entpackten Backup, und das Feature schreibt die Dateien, die ihm gehören, in der entpackten Kopie um, bevor sie über die lebenden kopiert werden. `Newsletter::callbackRestore()` im Newsletter-Feature des Katalogs ist die Referenz: Eine Adresse, die jemand entfernt hat, bleibt entfernt, egal wie alt das eingespielte Backup ist. Der Callback läuft nur, solange das Feature aktiv ist.

### Der Assistent und die Einheiten

Der zweite Schritt des Einrichtungsassistenten bietet keine Features an. Er kennt die Kernel-Module mit einer Einheit – Navigation, Sprachauswahl, Kontaktformular –, die eigenen Module des Projekts unter `app/` und die Einheiten unter `_admin/install/library/modules/`; ein Feature wird nach der Einrichtung im Panel Features eingeschaltet. Beide wenden ihre Einheiten über dieselbe Methode an, `\Nino\Features::applyUnit()`: der Assistent mit Überschreiben, weil eine erneut angewendete Einheit dort ersetzen soll, was sie zuvor kopiert hat; eine Aktivierung ohne. Deshalb liegt die Anwendung im Kernel und nicht im Assistenten – `_admin/install/` darf nach der Einrichtung gelöscht werden, und ein Feature muss sich danach noch aktivieren lassen.

## Der Katalog

Ein Feature, das nicht von Hand hineinkopiert wird, kommt aus einem Katalog: einer `catalogue.json`, über https veröffentlicht neben den Archiven, die sie auflistet, und daneben eine abgetrennte Signatur `catalogue.json.sig`. Ninos eigener ist `https://catalogue.getnino.dev/catalogue.json`, gebaut und signiert vom Katalog-Repository [dapeio/nino-features](https://github.com/dapeio/nino-features) aus denselben Verzeichnissen, aus denen auch eine Handkopie stammt. Die Kernel-Seite ist `\Nino\Catalogue` in `_nino/Nino/Catalogue/Catalogue.php` und der eine HTTP-Client des Kernels, `\Nino\Fetch`; `tests/catalogue-smoke.php` prüft beide ohne Netz.

### Was der Katalog sagt

Format 1 ist ein JSON-Dokument: `format` (`1`), `generated` (wann) und `features`, eine Liste von Einträgen – einer je veröffentlichter Version:

| Feld | Bedeutung |
| --- | --- |
| `key`, `name`, `description`, `version`, `nino`, `php.ext`, `requires` | was das Manifest des Features sagt, siehe [Das Manifest](#das-manifest-featurephp) |
| `directory` | das Verzeichnis, das das Archiv enthält – `Newsletter`, das Klassenname-Segment des Features |
| `archive` | die https-URL des `.tar.gz` |
| `sha256`, `size` | Prüfsumme und Bytelänge genau dieser Datei |
| `released` | das Datum |

Ein Archiv ist ein `.tar.gz` mit genau diesem einen Verzeichnis – das, was unter `features/` landet, und nichts daneben; die `tests/` eines Features werden nicht veröffentlicht. Ein Katalog, der irgendwo falsch ist, wird als Ganzes abgewiesen: `\Nino\Catalogue::parse()` nennt Eintrag und Feld.

### Vertrauen

Die Signatur ist das Vertrauen. Sie ist eine ECDSA-Signatur (Kurve P-256) über SHA-256 der exakten Bytes des Dokuments, DER-kodiert und base64 – das, was `openssl dgst -sha256 -sign key.pem catalogue.json | base64` schreibt. Die öffentliche Hälfte von Ninos Schlüssel wird mit dem Kernel ausgeliefert, als `\Nino\Catalogue::PUBLIC_KEY`; `/nino/catalogue/key` in der `config.php` ersetzt sie durch einen anderen Schlüssel, PEM, für einen eigenen Katalog, und `/nino/catalogue/url` nennt diesen Katalog. Ein leerer Schlüssel prüft nichts, also wird gar kein Katalog angenommen, bis ein Schlüssel konfiguriert ist – die Konstante des Kernels ist leer, bis Ninos erster Schlüssel existiert. `/nino/catalogue/url` auf `''` schaltet den Katalog ab: Der Tab Verfügbar sagt das und bietet keinen Aktualisieren-Knopf, und Nino stellt keine Anfrage.

Nichts wird geglaubt, bevor die Signatur hält: Das Dokument wird geladen, seine Signatur wird geladen, und nur ein Dokument, das der Schlüssel signiert hat, wird gelesen. Eine Installation lädt den Katalog erneut, statt dem zu trauen, was das Panel gezeigt hat, lädt das Archiv mit der Bytegrenze, die der Eintrag nennt, und weist ein Archiv ab, dessen Größe oder SHA-256 vom Eintrag abweicht. Das Archiv wird unterhalb von `data/.features/` entpackt – nie in `features/` selbst –, nachdem jeder Eintrag angesehen wurde: ein Verzeichnis, benannt wie der Eintrag sagt, nur gewöhnliche Dateien und Verzeichnisse, kein Pfad außerhalb, begrenzt in Anzahl und Größe; was herauskam, wird als Feature gelesen und muss Schlüssel und Version sein, die der Katalog versprochen hat, und zu diesem Kernel passen, wie es der Eintrag tat. Erst dann wird das Verzeichnis nach `features/` verschoben und ersetzt, was dort lag; ein Verschieben, das auf halbem Weg scheitert, legt das alte Verzeichnis zurück. Das Staging-Verzeichnis wird so oder so entfernt.

### Der Zwischenspeicher

Ein erfolgreiches Laden wird unter `data/catalogue.php` aufbewahrt – `\Nino\Catalogue::cached()` liest ihn zurück, ohne je eine Anfrage zu stellen, und der Tab Verfügbar des Panels füllt sich beim Öffnen daraus. Die Datei hält fest, wann geladen wurde, unter welcher URL, und das geparste Dokument; eine URL, die nicht mehr zur jetzt konfigurierten passt – auch wenn der Katalog seither abgeschaltet wurde –, lässt `cached()` wieder null antworten, genau wie eine Datei, die nicht das hält, was ein Laden schreibt. Nur die eigene Aktualisieren-Aktion des Panels ruft `fetch()` ein zweites Mal auf, und ein erfolgreiches überschreibt den Zwischenspeicher ganz; die aus einem zwischengespeicherten Dokument berechneten Angebote werden immer gegen die tatsächlich vorhandenen Features abgeglichen, sodass eine Installation oder Aktivierung seit dem letzten Laden auf Verfügbar erscheint, ohne dass neu geladen wird.

### Was Installieren nicht tut

Installieren legt Dateien ab, mehr nicht. Ein frisch installiertes Feature wird im Panel eingeschaltet wie ein von Hand kopiertes, mit allem, was [Aktivieren](#aktivieren) sagt. Auf die Aktualisierung eines aktiven Features folgt vom Panel aus dieses Aktivieren im selben Schritt, damit die Einheit ergänzt, was neu ist, und das Modul seine Daten migrieren darf – siehe [Aktualisieren](#aktualisieren) –, aber `\Nino\Catalogue::install()` selbst aktiviert nichts.

Was es sehr wohl auflöst, sind Abhängigkeiten. `install()` ermittelt zuerst die ganze Menge – was das Feature unter `requires` nennt, was jene wiederum brauchen, und davon nur das, was das Projekt noch nicht hat –, prüft jeden Eintrag gegen diesen Kernel und legt sie dann von unten nach oben ab, sodass das eigentlich gewünschte Feature zuletzt ankommt und sich sofort einschalten lässt. Eine Abhängigkeit, die schon auf der Platte liegt, bleibt genau so, wie sie ist, in welcher Version auch immer: Eine Installation ist nicht der Moment, um etwas zu aktualisieren, das ein Projekt bewusst behalten hat. Eine Abhängigkeit, die der Katalog nicht liefern kann, weist die ganze Installation ab und nennt sie – abgelegt wird dann nichts, denn eine halbe Installation ist schlimmer als keine. Das Panel nennt in seiner Antwort, was mitgekommen ist: Wer auf einem Feature Installieren drückt und drei bekommt, soll das nicht aus dem Tab Inaktiv rekonstruieren müssen.

### Voraussetzungen und Datenschutz

Eine Installation braucht die Erweiterung `curl` oder `allow_url_fopen`, die Erweiterung `openssl`, `phar` für das Archiv und ein beschreibbares Verzeichnis `features/` – wo es nicht beschreibbar ist, verlinkt das Panel das Archiv, zum Entpacken von Hand wie bisher. Jede Anfrage geht über https an den Host des Katalogs und nirgendwo sonst hin: Keine Weiterleitung wird verfolgt, kein anderes Schema geladen, das Zertifikat wird geprüft, der User-Agent sagt `Nino` und sonst nichts – nicht die Version, nicht die Site. Nino stellt diese Anfragen, wenn jemand **Katalog aktualisieren** oder **Installieren** drückt, und zu keiner anderen Zeit; es gibt keine Suche nach Updates im Hintergrund, keine Telemetrie, nichts wird gesendet. Der Weg von Hand bleibt: Ein nach `features/` kopiertes Verzeichnis ist ein Feature wie jedes andere.

## Ein Feature schreiben

### Verzeichnisaufbau

```text
features/Catalog/
├── feature.php              das Manifest
├── Catalog.php              die Laufzeitklasse \Nino\Modules\Catalog
├── Admin/Admin.php          das Panel \Nino\Modules\Catalog\Admin, von adminPanels() beantwortet
├── assets/admin.js          das Skript des Panels
├── text/<locale>.php        die Textfills des Panels, solange das Feature aktiv ist
├── install/                 die Einheit, die activate() anwendet
│   ├── manifest.php
│   ├── templates/
│   └── text/
└── tests/catalog-smoke.php  der eigene Test des Features
```

Nur `feature.php` und `<Name>.php` sind Pflicht. Alles andere ist da, wenn das Feature es braucht, und ein Feature, das nur aus einer Klasse besteht, ist ein vollständiges Feature.

### Die Klasse

Die Klasse ist ein gewöhnliches Laufzeitmodul im Namespace `Nino\Modules`: `init()` registriert Shortcodes, Routen und Callbacks und gibt nichts aus; die Regeln des [Entwickler-Handbuchs](development.de.md#ein-eigenes-modul-entwickeln) gelten unverändert. Drei Methoden sind Feature-spezifisch, alle optional:

| Methode | Zweck |
|---|---|
| `adminPanels( array &$appData ): array` | die Panel-Klassen, die das Feature mitbringt; der Kernel fragt jedes aktive Modul (`\Nino\Modules::collect()`) |
| `upgrade( array &$appData, string $fromVersion ): bool` | die eigenen Daten migrieren, wenn eine neue Version aktiviert wird; `false` weist die Aktualisierung ab |
| `callbackRestore( array &$appData, array &$args ): void` | unter `'/nino/admin/restore'` registriert: die eigenen `data/`-Dateien in der entpackten Kopie zusammenführen |

Einstellungen liest die Klasse über `\Nino\Features::setting()` – mit einem Default, damit sie auch dann funktioniert, wenn das Schema eine Einstellung noch nicht kennt.

### Das Panel

Ein Panel ist eine Klasse mit `actions()`, `nav()` und `perm()`, so wie jedes Panel der Workbench; das [Entwickler-Handbuch](development.de.md#panels-der-workbench) listet den ganzen Vertrag, das [Panel-Rezept](recipes/admin-panel.md) führt durch ein vollständiges Panel samt Frontend. Ein Feature-Panel benennt seine Dateien von dort aus, wo seine Klasse liegt – `\Nino\Admin\Panels::relative( dirname( __DIR__ ). '/text' )` –, damit sie mit dem Verzeichnis umziehen, und schützt jede Aktion mit `\Nino\Admin\Admin::guardPerm()`. Eine URI oder ein Aktionsname, den ein Panel der Workbench selbst bereits besitzt, geht nie an ein Feature. Gleich welche Gruppe sein eigenes `nav()` nennt, setzt die Registry es in die **Features**-Gruppe der Leiste – geprüft wird, ob die Klassendatei des Panels unterhalb von `\Nino\Features::dir()` liegt, nicht der Wert, den das Panel selbst geschrieben hat – sodass eine Person mit genau dieser Gruppe jedes aktive Feature-Panel sieht und keines, das ein Kernel- oder `app/`-Modul dort platziert hat.

### Die Install-Einheit

`install/manifest.php` hat dieselbe Form wie die Einheit eines Kernel-Moduls im Assistenten – siehe das [Library-Format](setup.de.md#library-format) und das [Installer-Rezept](recipes/installer-package.md). `activate()` liest daraus `routes`, `templates`, `files`, `elementTypes`, `blacklist` und `config` sowie `text/global.php` und `text/<locale>.php` für jede verfügbare Sprache; `key`, `label`, `moduleClass`, `requiresModules` und `preset` sind Angaben für den Assistenten und werden bei einer Aktivierung nicht gelesen – das Feature-Manifest trägt sie in eigener Form. Alles, was die Einheit kopiert, gehört danach dem Projekt und wird von einer Aktualisierung nicht mehr angefasst.

### Texte

Zwei Arten von Text gibt es, und sie leben an verschiedenen Orten. Die Worte des Panels – Navigationsbezeichnung, Beschriftungen, Meldungen – liegen unter `text/<locale>.php` neben der Klasse und werden über `text()` des Panels mit den Fills der Workbench zusammengeführt, solange das Feature aktiv ist. Die Worte, die die Webseite braucht – Beschriftungen in einem Template, das die Einheit kopiert –, liegen in `install/text/` und werden bei der Aktivierung einmal in die `text/`-Dateien des Projekts geschrieben, wo die Redaktion sie danach pflegt. Der Name und die Beschreibung des Features selbst stehen im Manifest, weil das Panel Features sie auch dann zeigt, wenn nichts davon geladen ist.

### Tests

`tests/harness.php` ist der gemeinsame Bootstrap aller Smoke-Tests: Er lädt den Kernel und die Shell der Workbench, stellt `check( $label, $condition )`, `ninoSandbox( $name )` (ein frisches, isoliertes Projektverzeichnis in `$appData`, zwei Sprachen, keine Module), `ninoSandboxDir()`, `ninoWarnings()` (die seit dem letzten Aufruf aufgezeichneten Warnungen – ein Test, der eine erwartet, liest sie hier, statt sie auf der Konsole zu sehen) und `ninoDone( $appData )` (Zusammenfassung, Sandbox entfernen, Exit-Status) bereit.

Der Test eines Features liegt bei ihm, unter `features/<Name>/tests/<key>-smoke.php`, und lädt den Harness aus dem Checkout drei Ebenen über sich – oder aus dem Verzeichnis, auf das `NINO_ROOT` zeigt, womit derselbe Test gegen eine andere Nino-Version läuft:

```php
$root = getenv( 'NINO_ROOT' ) ?: dirname( __DIR__, 3 );
require $root. '/tests/harness.php';
```

CI führt `php tests/features-smoke.php` – den Vertragstest gegen `tests/fixtures/features/` – und danach jeden Feature-Test aus: `for test in features/*/tests/*-smoke.php; do [ -e "$test" ] || continue; php "$test" || exit 1; done`, eine Schleife, die ein leeres Glob überstehen muss, weil ein Checkout kein Feature mitbringt. Ein zweiter Job klont den Katalog [dapeio/nino-features](https://github.com/dapeio/nino-features), kopiert jedes seiner Features in den Checkout und führt die eigenen Tests der Features dagegen aus, sodass eine Kernel-Änderung, die ein veröffentlichtes Feature bricht, hier fehlschlägt; die CI des Katalogs macht das Umgekehrte gegen Ninos `main` und den jüngsten Tag. PHPStan analysiert `features/` mit, lässt `features/*/tests/*` aber aus – ein Test ist ein eigenständiges Skript über dem Harness, kein Produktcode.

`tests/fixtures/features/Sample/` ist das Referenz-Feature: ein Manifest mit jedem Settings-Typ, eine Klasse mit `upgrade()`, ein Panel, eine Install-Einheit. Wer den Vertrag an einer Stelle nicht sicher versteht, liest dort nach, was `tests/features-smoke.php` daran prüft.

## Ausblick

Die Features, die Nino veröffentlicht, kommen aus dem Katalog-Repository [dapeio/nino-features](https://github.com/dapeio/nino-features): dort je ein Verzeichnis unterhalb von `features/`, mit Manifest und – wo das Feature sie mitbringt – Test, README und Changelog, von Hand in das `features/` eines Projekts kopiert, oder aus dem signierten Katalog installiert, den das Repository auf getnino.dev veröffentlicht, siehe [Der Katalog](#der-katalog). Und ein eigener Katalog ist eine Frage von URL und Schlüssel – das Format ist klein genug, um es mit dem `bin/build.php` des Repositorys aus einem Verzeichnis voller Features zu veröffentlichen.

## Weiterführende Handbücher

- [Entwickler-Handbuch](development.de.md) erklärt Module, Autoloading, Panels und die Tests.
- [Feature-Rezept](recipes/feature.md) baut ein Feature Schritt für Schritt bis zum bestandenen Test.
- [`/_admin`-Workbench](_admin.de.md) beschreibt das Panel Features neben den anderen Panels.
- [Einrichtungsassistent](setup.de.md) dokumentiert das Library-Format, das eine Install-Einheit teilt.
- [Deployment](deployment.de.md) nennt die Serverregel für `features/` und das Vorgehen bei einer Aktualisierung.
