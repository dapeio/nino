# `/_admin` — Bedienungsanleitung

**Sprache:** [English](_admin.md) · Deutsch

**Stand:** 11. August 2026 · **Nino-Version:** 0.11.0-beta.1

Dieses Handbuch erklärt die vollständige technische und inhaltliche Projektverwaltung unter `/_admin`. Falls du stattdessen nur freigegebene Inhalte im Alltag pflegen möchtest, lies die [`/_editor`-Bedienungsanleitung](_editor.de.md); die schnelle sectionbasierte Seitenkomposition beschreibt die [`/_templates`-Bedienung](_templates.de.md).

**Weitere Links:**
[README](../README.de.md) · [Grundkonzepte](concepts.de.md) · [Entwickler-Handbuch](development.de.md) · [Erste Schritte](getting-started.de.md) · [`/_install`-Referenz](_install.de.md) · [`/_admin`-Bedienung](_admin.de.md) · [`/_templates`-Bedienung](_templates.de.md) · [`/_editor`-Bedienung](_editor.de.md) · [Deployment](deployment.de.md) · [Security Policy](https://github.com/dapeio/nino/blob/main/SECURITY.md) · [Changelog](https://github.com/dapeio/nino/blob/main/CHANGELOG.md)

**Sicherheitshinweis:** `/_admin` richtet sich an Entwickler. Änderungen werden unmittelbar in Konfiguration und Projektdateien geschrieben und können Routing, Datenmodelle, Inhalte und die sichtbare Webseite verändern. Arbeite deshalb mit einem aktuellen Git-Stand oder einer anderen verlässlichen Sicherung.

## Aufgabe und Abgrenzung

`/_admin` bietet vollständigen Zugriff für Entwicklung, Diagnose und Korrekturen. `/_editor` bildet dagegen den freigegebenen Arbeitsbereich für Redakteure und Betreiber. Die Trennung verläuft damit nicht mehr grundsätzlich zwischen Struktur und Inhalt, sondern zwischen technischem Vollzugriff und rollenbasierter Alltagspflege.

| Bereich | `/_admin` | `/_editor` |
|---|---|---|
| Elementtypen | Felder und Datentypen definieren | keine Strukturänderung |
| Elemente | alle Einträge, Sprachen und Speicher-Buckets bearbeiten | freigegebene Einträge innerhalb der Rechte pflegen |
| Texte | Schlüssel, Werte, Sprachen und Editor-Sichtbarkeit vollständig verwalten | freigegebene Werte pflegen |
| Übersetzungen | native Text-/Elements-Inhalte exportieren und in eine Zielsprache importieren | kein Batch-Workflow |
| Bilder | Bildplätze und Zielmaße definieren | Bilder hochladen und ersetzen |
| Routen | Seitenrouten, Templates und Navigation verwalten | Seitentexte pflegen |
| Seitentemplates | Link zum sectionbasierten Template Builder unter `/_templates` | kein Zugriff |
| Nutzer | Konten anlegen, löschen und Rechte technisch verwalten | Profildaten und freigegebene Rechte pflegen |
| Konfiguration | ausgewählte technische Werte bearbeiten | kein Zugriff |
| Backups | vorhandene Sicherungen wiederherstellen | tägliche Sicherung automatisch auslösen |

Die Menübezeichnungen in `/_admin` sind im aktuellen Beta-Stand überwiegend englisch. Dieses Handbuch verwendet die deutschen Begriffe und nennt die sichtbaren Menünamen dazu.

## Anmeldung und sicherer Betrieb

Öffne `https://deine-domain.example/_admin` und melde dich mit dem technischen Passwort an, das im letzten Schritt von `/_install` gesetzt wurde. Dieser Zugang ist unabhängig von den E-Mail-Konten unter `/_editor`.

Nach fünf falschen Anmeldeversuchen sperrt `/_admin` den gemeinsamen Zugang für eine Stunde. Die Sperre gilt projektweit, nicht nur für den verwendeten Browser.

Beachte für den Betrieb:

- verwende `/_admin` ausschließlich über HTTPS;
- teile das technische Passwort nicht mit Redakteuren;
- melde dich nach der Arbeit über **Logout** ab;
- schütze den Bereich bei Bedarf zusätzlich über Webserver, VPN oder IP-Freigaben;
- entferne `_admin/` und `_templates/` aus der Auslieferung, wenn die Oberflächen im laufenden Betrieb nicht benötigt werden.

`/_templates` verwendet dasselbe Passwort, denselben Sperrstatus und dieselbe Sitzung wie `/_admin`. Der Link **Template Builder** im Kopfbereich öffnet das sectionbasierte Alpha-Werkzeug. Ohne `_admin/` kann der eigenständige Builder nicht verwendet werden.

Das Passwort lässt sich außerhalb des Installers mit `php _admin/Admin.php <passwort>` neu hashen. Der ausgegebene Hash ersetzt `PASSWORD_HASH` in `_admin/Admin.php`. Führe diesen Vorgang nur in einer geschützten lokalen Umgebung aus; ein als Kommandozeilenargument eingegebenes Passwort kann in Shell-Verlauf oder Prozessliste sichtbar werden.

## Dashboard

Das **Dashboard** fasst den technischen Projektstand zusammen:

- Anzahl der Elementtypen, Routen und Editor-Konten;
- Datum des letzten automatischen Backups;
- Felder pro Elementtyp;
- Textschlüssel und Bildplätze, die in Templates verwendet, aber noch nicht definiert sind.

Die Kacheln und Hinweise führen direkt in den zugehörigen Bereich. Das Dashboard selbst verändert keine Daten.

## Element Types: Elementtypen definieren

Elementtypen beschreiben wiederkehrende Inhalte wie Leistungen, Teammitglieder oder Referenzen. Jeder Typ entspricht einer Datei unter `elements/`; seine Einträge werden anschließend vollständig unter **Elements** oder innerhalb der vergebenen Rechte in `/_editor` gepflegt.

### Einen Elementtyp anlegen

1. Öffne **Element Types** und wähle **New type**.
2. Vergib eine technische URI. Sie beginnt mit einem Kleinbuchstaben und darf danach Kleinbuchstaben, Ziffern, Bindestriche und Unterstriche enthalten, zum Beispiel `team` oder `service_items`.
3. Vergib einen verständlichen Titel für die Anzeige in `/_editor`.
4. Füge mit **Add field** die benötigten Felder hinzu.
5. Speichere den Typ.

Die URI wird zum Dateinamen `elements/<uri>.php` und lässt sich nach dem Anlegen nicht mehr über die Oberfläche ändern.

### Feldtypen und Optionen

| Typ | Geeignet für |
|---|---|
| `string` | ein- oder mehrzeilige Texte; optional als Rich Text oder feste Auswahlliste |
| `integer` | ganze Zahlen |
| `double` | Dezimalzahlen |
| `boolean` | Ja-/Nein-Werte |
| `array` | einfache Listen oder strukturierte Werte |
| `date` | ein Datum |
| `datetime` | Datum und Uhrzeit |
| `image` | ein Bild mit festgelegten Zielmaßen |

Je nach Typ stehen zusätzliche Eigenschaften bereit:

- **per translation** speichert pro Sprache einen eigenen Wert;
- **Required field** macht das Feld verpflichtend;
- **Rich text** aktiviert beim Typ `string` Fett, Kursiv, Hervorhebung, Inline-Code und Links;
- feste Werte begrenzen ein Textfeld auf eine vorgegebene Auswahl;
- Breite und Höhe bestimmen bei Bildern die Zielmaße;
- eine Einheit oder ein Suffix ergänzt beispielsweise `€`, `km` oder `%` in der Eingabe.

Beim Wechsel zwischen globalen und sprachabhängigen Feldern migriert Nino bestehende Werte. Prüfe das Ergebnis trotzdem in jeder Sprache. Das Speichern eines Typmodells löscht keine vorhandenen Einträge; entfernte Felder erscheinen jedoch nicht mehr in `/_editor`.

Elementtypen können bewusst nicht über `/_admin` gelöscht werden. Damit verhindert Nino, dass ein unbedachter Klick sämtliche zugehörigen Inhalte vernichtet.

## Elements: Inhalte vollständig bearbeiten

Der Bereich **Elements** ergänzt die Typdefinition um die tatsächlichen Einträge. Er bietet denselben grundlegenden Bearbeitungsumfang wie `/_editor`, ist aber nicht durch Editor-Rechte oder ausgeblendete Bereiche eingeschränkt.

![Vollständige Elementbearbeitung in `/_admin`](assets/screenshots/admin-elements.webp)

1. Wähle einen Elementtyp.
2. Öffne einen vorhandenen Eintrag oder lege einen neuen an.
3. Bearbeite globale Felder einmal und sprachabhängige Felder in der gewünschten Sprache.
4. Speichere einen neuen Eintrag zunächst, bevor du Bilder in seine Bildfelder hochlädst.

Zusätzlich zeigt `/_admin` die zugrunde liegenden Speicher-Buckets: `*` für globale Werte und jeweils einen Bucket pro Sprache. Diese technische Sicht hilft bei Migrationen und Diagnose, sollte aber nicht zur normalen Inhaltspflege verleiten. Rich-Text-Werte werden beim Speichern bereinigt; Pflichtfelder und Feldtypen werden vom Kernel geprüft.

Beim Löschen entfernt Nino den Eintrag in allen Sprachen sowie die zugehörigen Elementbilder. Sichere den Projektstand, wenn die Daten nicht anderweitig wiederherstellbar sind.

## Text: Textschlüssel und Werte verwalten

Der Bereich **Text** bietet vollständigen Zugriff auf Schlüssel und Werte und zeigt alle Textfills gruppiert nach dem ersten Segment ihres Schlüssels. Ein Schlüssel wie `/home/intro/title` erscheint damit in der Gruppe `home`.

![Globale und sprachabhängige Textwerte in `/_admin`](assets/screenshots/admin-text.webp)

Hier kannst du:

- Werte global oder pro Sprache pflegen;
- neue Textschlüssel anlegen;
- Schlüssel umbenennen;
- zwischen globaler und sprachabhängiger Speicherung wechseln;
- Schlüssel für `/_editor` ausblenden;
- Schlüssel vollständig löschen.

Beim Umbenennen und beim Wechsel der Speicherform migriert Nino vorhandene Werte. Beim Löschen verschwindet der Wert dagegen aus allen Sprachen. Prüfe deshalb vor einer Löschung, ob der Schlüssel noch in Templates, E-Mails oder projektspezifischen Modulen verwendet wird.

### Fehlende Schlüssel suchen

**Scan templates for missing keys** durchsucht die öffentlichen `.tpl`-Dateien nach statischen Textfills wie `[[/home/intro/title]]`. Gefundene Schlüssel lassen sich einzeln ignorieren oder gemeinsam anlegen.

Der Scan kann dynamisch zusammengesetzte Schlüssel nicht vollständig erkennen. Ein fehlerfreier Scan ersetzt daher nicht den Test aller Seiten und Sprachen.

## Translations: Native Inhalte übersetzen

**Translations** ist die projektweite Übergabe, um eine Website nach Abschluss der nativen Befüllung zu übersetzen. Der Export verwendet immer die konfigurierte native Sprache und bündelt:

- nicht globale, nicht technische Text-Werte;
- sprachabhängige Elementfelder, die im nativen Bucket tatsächlich einen Wert besitzen.

Globale Werte, ausgeblendete technische Texte, Bilder, Element-URIs, Sortierung und Strukturdaten werden nicht exportiert. Das JSON enthält Hinweise für Übersetzungswerkzeuge: Nur Werte übersetzen und Schlüssel, JSON-Datentypen, HTML, URLs, Platzhalter, Shortcodes und Bezeichner unverändert lassen.

1. Lade das native JSON-Paket herunter.
2. Übersetze seine Werte, ohne die Objektstruktur zu verändern.
3. Wähle eine konfigurierte Zielsprache.
4. Lade die `.json`-Datei hoch oder füge ihren Inhalt ein und wähle **Import into selected language**.
5. Kontrolliere die Zahl importierter und übersprungener Werte.

Der Import führt nur zusammen: Passende Werte der Zielsprache werden überschrieben, im Dokument fehlende Werte aber nicht gelöscht. Der Server prüft jeden Pfad gegen einen frisch erzeugten nativen Export, bereinigt Text- und Rich-Text-Werte und überspringt unbekannte, globale, technische und Bildfelder. Die native Sprache kann als Ziel gewählt werden, überschreibt dann jedoch Quellinhalte und sollte daher bewusst gewählt werden.

## Routes: Seitenrouten verwalten

Der Bereich **Routes** verwaltet die über `/_install` angelegten Seitenrouten und die Navigation. Es gibt keine getrennte Seitenliste: Die angezeigte Liste wird bei jedem Aufruf aus `/nino/http/routes` und den `/webpage<uri>/*`-Textschlüsseln abgeleitet. Eine Route, die hier, in `/_install` oder von Hand in der `config.php` entsteht, ist damit überall dieselbe Seite.

Eine Seite besitzt zwei verschiedene URIs:

- **Element URI** ist die stabile interne Identität, an der Seitentexte wie `/webpage<uri>/title` hängen;
- **Http URI** ist der tatsächlich im Browser erreichbare Pfad.

So kann eine Seite intern `/about` heißen und öffentlich beispielsweise unter `/ueber-uns` erreichbar sein.

Beim Anlegen oder Bearbeiten bestimmst du außerdem:

- ein vorhandenes Template aus `templates/page-*.tpl`;
- einen HTTP-Statuscode;
- Navigationsname, Seitentitel und Beschreibung für jede aktive Sprache;
- je eine Checkbox pro Navigation aus `/nino/html/navs`.

Die Zugehörigkeit steht als `'navs' => [ 'main' => 1, … ]` auf der Route der Seite; der Wert ist eine Priorität (kleiner zuerst). Eine hier neu gesetzte Zugehörigkeit landet hinter allem, was bereits in diesem Menü steht; eine von Hand vergebene Priorität setzt ein späteres Speichern nicht zurück. Eine von Hand in die `config.php` geschriebene Route tritt einem Menü genauso bei – ganz ohne Werkzeug – und wird von `[navigation nav="main"]` gerendert.

Die Pfeile in der Seitenliste vertauschen zwei Seitenrouten in der `config.php`; jede andere Route behält ihren Platz. Menüeinträge gleicher Priorität folgen der Routenreihenfolge. Reservierte Pfade wie `/_admin`, `/_editor`, `/_install`, `/_templates` können nicht als öffentliche Seiten verwendet werden.

Einige Routen wählen ihr Template zur Laufzeit. In diesem Fall zeigt `/_admin` den bestehenden Route-Body an und lässt ihn beim Speichern unverändert.

Das Löschen einer Seite entfernt ihre Route. Ihre Seitentexte und ihre Template-Datei bleiben bestehen – auf der Festplatte wird nichts für dich gelöscht.

## Images: Bildplätze definieren

Ein Bildplatz verbindet eine technische URI mit einer verständlichen Bezeichnung und festen Zielmaßen. Redakteure sehen diese Plätze anschließend unter **Bilder** in `/_editor` und können dort die eigentliche Datei hochladen.

Für einen neuen Platz werden benötigt:

- URI, zum Beispiel `/home/hero`;
- Bezeichnung, zum Beispiel `Startseite – Titelbild`;
- Breite und Höhe in Pixeln.

**Scan templates for missing image slots** sucht in öffentlichen Templates nach lokalen `<img src="…">`-Verweisen unter `images/`, für die noch kein Bildplatz existiert. Dynamische Bilder und externe URLs werden nicht erfasst.

Beim Löschen eines Bildplatzes wird auch das dort hinterlegte Bild entfernt. Nutze diese Aktion nur, wenn weder Template noch Inhalt den Platz weiter benötigen.

## Users: Editor-Konten und Rechte

Unter **Users** verwaltest du die Konten für `/_editor`. Das technische `/_admin`-Passwort wird hier nicht geführt.

### Konto anlegen

Gib eine gültige E-Mail-Adresse und ein Passwort mit mindestens acht Zeichen an. Ein reguläres neues Konto erhält die üblichen Inhaltsrechte. Die Option **Verwaltung** vergibt Vollzugriff über `/*` und sollte nur für vertrauenswürdige Verantwortliche verwendet werden.

E-Mail-Adresse und Passwort eines bestehenden Kontos werden anschließend in `/_editor` geändert. `/_admin` ist für Anlegen, Löschen und den technischen Zugriff auf Berechtigungen zuständig.

### Berechtigungen bearbeiten

Der Link **Permissions** öffnet die Rechte als JSON-Array. Dieser Editor ist absichtlich nicht auf bekannte Rechte beschränkt und damit ein technisches Notfall- und Entwicklungswerkzeug. Für normale Rollenänderungen ist die Checkbox-Ansicht unter `/_editor` sicherer.

Beispiele:

```json
["/_editor/text/manage", "/_editor/images/manage"]
```

```json
["/*"]
```

Ungültige oder zu weit gefasste Rechte können Nutzer aussperren oder ungewollt freischalten. Sichere `config.php`, bevor du Rechte hier manuell veränderst.

Das Löschen eines Kontos beendet dessen Zugriff. Prüfe vorher, ob mindestens ein verwaltendes Konto erhalten bleibt.

## Restore: Backup wiederherstellen

`/_editor` erzeugt standardmäßig beim ersten authentifizierten Zugriff eines Tages ein verschlüsseltes Backup. Die täglichen Sicherungen werden 14 Tage aufbewahrt. Unter **Restore** zeigt `/_admin` die verfügbaren Daten an.

Wähle das gewünschte Datum und bestätige **Restore**. Vor der Wiederherstellung sichert Nino automatisch noch einmal den aktuellen Zustand. Teste danach mindestens:

- Frontend und alle Sprachen;
- Anmeldung und Rechte in `/_editor`;
- Seiten, Texte, Elemente und Bilder;
- Formulare und Newsletter-Daten.

Die automatische Sicherung ist ein Sicherheitsnetz für redaktionelle Änderungen, ersetzt aber kein externes Backup des vollständigen Projekts.

## Config: technische Konfiguration

Der Bereich **Config** bearbeitet eine bewusst begrenzte Auswahl aus `config.php` als JSON:

| Schlüssel | Erwarteter Wert |
|---|---|
| `/nino/error/log` | `true` oder `false` |
| `/nino/error/display` | `true` oder `false` |
| `/nino/locales/native` | Sprachcode als String |
| `/nino/locales/available` | Liste von Sprachcodes |
| `/nino/html/assets` | Asset-Bundles als Objekt |
| `/nino/http/routes` | vollständige Routing-Tabelle |

Nino prüft JSON-Syntax und Grundtyp, aber nicht jede fachliche Abhängigkeit. Fehler in Routen, Sprachen oder Asset-Bundles können die Webseite oder die Verwaltungsbereiche unzugänglich machen. Nutze für normale Seitenänderungen deshalb **Routes** und für Bildplätze **Images**.

In Produktion muss `/nino/error/display` auf `false` stehen.

## Empfohlener Arbeitsablauf

1. Lege unter **Element Types**, **Text**, **Routes** und **Images** die benötigte Struktur an.
2. Pflege oder korrigiere vollständige Texte und Elemente direkt in `/_admin`.
3. Setze Seitentemplates über [`/_templates`](_templates.de.md) aus vollständigen HTML- und Template-Sections zusammen; nutze für tiefergehende Strukturarbeit den HTML+-Escape-Hatch oder Code und prüfe das Ergebnis im Browser.
4. Prüfe Dashboard und Template-Scans auf fehlende Definitionen.
5. Erstelle unter **Users** passende Konten mit möglichst kleinen Rechten.
6. Teste die freigegebenen Inhalte und Rechte in `/_editor`.
7. Prüfe Frontend, Sprachen, Formulare und responsive Darstellung.
8. Committe die entstandenen Projektdateien in Git.

## Wenn etwas nicht funktioniert

| Problem | Prüfung |
|---|---|
| Anmeldung nach mehreren Versuchen gesperrt | Eine Stunde warten; die Sperre ist projektweit. |
| Speichern schlägt fehl | Schreibrechte für die betroffene Datei beziehungsweise das Verzeichnis prüfen. |
| Template fehlt in **Routes** | Nur vorhandene Dateien `templates/page-*.tpl` werden angeboten. |
| Seite lässt sich im Template Builder nicht speichern | Nach externer Änderung neu laden, eindeutige Section-IDs und nicht geschlossene `<section>`-Tags prüfen; siehe [`/_templates`-Bedienung](_templates.de.md). |
| Texte oder Bilder fehlen im Scan | Dynamische Schlüssel und Bilder werden nicht zuverlässig statisch erkannt. |
| Backup-Liste ist leer | Zuerst mit einem Editor-Konto anmelden und prüfen, ob Backups aktiviert sowie Schreibrechte vorhanden sind. |
| Webseite funktioniert nach **Config** nicht | Letzten Git-Stand oder Backup wiederherstellen und JSON sowie Schlüsselstruktur prüfen. |

## Wie es weitergeht

- [`/_templates`-Bedienung](_templates.de.md) erklärt die sectionbasierte Seitenkomposition im Alpha-Status.
- [`/_editor`-Bedienung](_editor.de.md) erklärt die tägliche, berechtigungsgesteuerte Inhaltspflege.
- [Entwickler-Handbuch](development.de.md) beschreibt APIs, Module und direkte Arbeit an Projektdateien.
- [Deployment](deployment.de.md) behandelt Zugriffsschutz, Backups und sicheren Produktivbetrieb.
