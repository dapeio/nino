# `/_admin` — Die Workbench

**Sprache:** [English](_admin.md) · Deutsch

**Stand:** 5. September 2026 · **Nino-Version:** 0.13.0-beta

Dieses Handbuch erklärt die eine Verwaltungsoberfläche eines Nino-Projekts: `/_admin`, die Workbench. Entwickler richten das Projekt hier ein und bauen Struktur und Erscheinungsbild; Redakteure pflegen hier die Inhalte. Was ein Konto sieht, bestimmen seine Rechte. Der Assistent, der aus einem frischen Checkout ein Projekt macht, ist der Erststart-Modus der Workbench und hat eine eigene Referenz, den [Einrichtungsassistenten](setup.de.md); die beiden großen Entwickler-Panels ebenfalls: [Templates](templates.de.md) und [Design](appearance.de.md).

**Weitere Links:**
[README](../README.de.md) · [Grundkonzepte](concepts.de.md) · [Entwickler-Handbuch](development.de.md) · [Erste Schritte](getting-started.de.md) · [Einrichtungsassistent](setup.de.md) · [`/_admin`-Workbench](_admin.de.md) · [Templates-Panel](templates.de.md) · [Design-Panel](appearance.de.md) · [Deployment](deployment.de.md) · [Security Policy](https://github.com/dapeio/nino/blob/main/SECURITY.md) · [Changelog](https://github.com/dapeio/nino/blob/main/CHANGELOG.md)

**Sicherheitshinweis:** Jedes Panel schreibt unmittelbar in Konfiguration und Projektdateien. Ein Entwicklerkonto kann Routing, Datenmodelle, Templates und die sichtbare Webseite verändern, ein Redaktionskonto die Inhalte. Arbeite mit einem aktuellen Git-Stand oder einer anderen verlässlichen Sicherung, ausschließlich über HTTPS, und gib jedem Konto genau die Rolle, die es braucht.

## Aufgabe und Abgrenzung

Ein Login, eine Navigation, jeder Bildschirm ein Panel. Die Panels sind danach gruppiert, was sie verändern:

| Gruppe | Panels | Wer |
|---|---|---|
| **Inhalt** | Dashboard, Elemente (Elementtypen), Texte (Textschlüssel), Bilder (Bildplätze), Anfragen, Newsletter, Log | Redakteure und Entwickler |
| **Struktur** | Templates, Design, Routen, Navigationen | Entwickler |
| **System** | Nutzer (Nutzerrollen, Anmeldeschutz), Sprache (Übersetzungen), Backups, Konfiguration, Suche | Entwickler – und jedes Konto für sein eigenes Profil unter Nutzer |

Ein Bildschirm in Klammern ist ein **Tab** des Panels davor: Das Panel Elemente öffnet auf den Einträgen und trägt Elementtypen als zweiten Tab, sodass die Form der Inhalte direkt neben den Inhalten liegt. Ein Tab ist ein eigener Bildschirm – mit eigener Berechtigung, sodass ein Redakteur Elemente ohne Elementtypen sieht, und eigenem tiefen Link, `#types`.

Anfragen, Newsletter, Navigationen und Suche gehören zu optionalen Modulen und sind vorhanden, solange ihr Modul aktiv ist. Templates und Design sind ebenfalls Module: Sie werden mit jedem Checkout ausgeliefert, und ein Projekt, das sie nicht will, löscht ihr Verzeichnis. Und die Panels oben, die nicht in dieser Liste stehen, ebenso: `_admin` hält die Hülle, und jede Ansicht darin ist ein Modul unter `_admin/Nino/Modules/<Name>/` – Verzeichnis für Verzeichnis hinzugefügt und wieder entfernt. Ein Modul, das ein Projekt hinzufügt, kann auf dieselbe Weise ein eigenes Panel mitbringen; siehe das [Entwickler-Handbuch](development.de.md#panels-der-workbench).

Ein zweites Werkzeug gibt es nicht. `/_editor`, `/_install`, `/_design` und `/_templates` früherer Versionen sind alle hier, und ein früher reservierter Pfad ist jetzt ein gewöhnlicher Seitenpfad.

Alle Panel-Namen folgen der Oberflächensprache des Kontos. Dieses Handbuch nennt die deutschen Bezeichnungen.

## Erststart: der Einrichtungsassistent

Ein frischer Checkout hat noch kein Projekt. Bis der letzte Schritt des Assistenten abgeschlossen ist, zeigt `/_admin` statt der Anmeldung den Assistenten: zehn Schritte von der Umgebungsprüfung bis zu den Konten und dem Recovery-Passwort. Die Referenz [Einrichtungsassistent](setup.de.md) erklärt jeden Schritt und was er schreibt.

Der Assistent liegt in `_admin/install/`. Sobald er sich selbst ausgesperrt hat, kann dieses Verzeichnis aus einer Produktivauslieferung entfernt werden; das Design-Panel verliert dann seinen Katalog für Theme, Header und Footer und sagt das auch, sonst ändert sich nichts. Behalte es ausgeliefert, wenn diese drei umschaltbar bleiben sollen.

## Anmeldung, Konten und Rollen

Öffne `https://deine-domain.example/_admin` und melde dich mit E-Mail-Adresse und Passwort deines Kontos an. Das erste Konto legt der Schritt **Accounts** des Assistenten an, jedes weitere das Panel **Nutzer**.

Getrennte Passwörter für Entwickler- und Redaktionsarbeit gibt es nicht mehr. Ein Konto hält eine **Rolle**, und eine Rolle ist eine benannte Menge von Rechten, gespeichert in der `config.php` unter `/nino/auth/roles`. Der Assistent schreibt zwei; der Tab **Nutzerrollen** des Panels Nutzer ändert sie oder legt weitere an (auch ihre Namen):

| Rolle | Rechte | Sieht |
|---|---|---|
| **Editor** | die Berechtigung jedes Inhalt-Panels, das es beim Lauf des Assistenten gibt | die Gruppe Inhalt, und unter System das eigene Profil |
| **Developer** | `/*` | alles |

Eine Berechtigung ist eine Zeichenkette pro Panel oder Tab; `/*` deckt jeden Pfad darunter ab, `/_admin/*` würde also ebenfalls jedes Panel öffnen, und `/*` auch jedes Panel eines künftigen Moduls.

| Panel | Berechtigung |
|---|---|
| Dashboard | keine – jedes Konto |
| Elemente | `/_admin/elements/manage` |
| Elementtypen (Tab von Elemente) | `/_admin/types/manage` |
| Texte | `/_admin/text/manage` |
| Textschlüssel (Tab von Texte) | `/_admin/keys/manage` |
| Bilder | `/_admin/images/manage` |
| Bildplätze (Tab von Bilder) | `/_admin/slots/manage` |
| Anfragen | `/_admin/submissions/view` |
| Newsletter | `/_admin/newsletter/manage` |
| Log | `/_admin/logs/view` |
| Templates | `/_admin/templates/manage` |
| Design | `/_admin/design/manage` |
| Routen | `/_admin/routes/manage` |
| Navigationen | `/_admin/navs/manage` |
| Nutzer (eigenes Profil) | keine – jedes Konto |
| Nutzer (andere Konten), Nutzerrollen (Tab von Nutzer) | `/_admin/users/manage` |
| Anmeldeschutz (Tab von Nutzer) | `/_admin/lockout/manage` |
| Sprache | `/_admin/language/manage` |
| Übersetzungen (Tab von Sprache) | `/_admin/translations/manage` |
| Backups | `/_admin/backups/manage` |
| Konfiguration | `/_admin/config/manage` |
| Suche | `/_admin/search/manage` |

### Feinere Rechte innerhalb eines Panels

Die Berechtigungen oben sind Türen: Sie sagen, welche Panels ein Konto öffnen darf. Innerhalb von Elemente und Texte lässt sich eine Rolle zusätzlich Aktion für Aktion und Feld für Feld beschreiben – für Redakteure, die Texte ändern, aber keine Einträge anlegen dürfen, oder die einen Typ betreuen und die übrigen nur sehen.

| Was | Berechtigung |
|---|---|
| Ein Element eines Typs anlegen | `/_admin/elements/services/insert` |
| Ein Feld davon ändern | `/_admin/elements/services/update/title` |
| Jedes Feld davon | `/_admin/elements/services/update/*` |
| Eines löschen | `/_admin/elements/services/delete` |
| Alles an genau diesem Typ | `/_admin/elements/services/*` |
| Einen Textschlüssel ändern | `/_admin/text/update/page-home/atf/title` |
| Jeden Schlüssel einer Gruppe | `/_admin/text/update/page-home/*` |

Das sind gewöhnliche Berechtigungsstrings, für die dieselbe `/*`-Regel gilt wie für alle anderen – eine Rolle lässt sich so grob oder so fein beschreiben, wie sie es braucht. Die Panel-Berechtigung bleibt nötig: `/_admin/elements/manage` lässt das Panel überhaupt erscheinen, die feineren sagen, was darin getan werden darf.

**Sie greifen nur, wenn du sie vergibst.** Eine Rolle, die keine davon hält, behält genau das, was ihre Panel-Berechtigung immer bedeutet hat – jede Aktion an jedem Typ und jedem Schlüssel. Die erste feinere Berechtigung für ein Panel ist es, die sagt: „Diese Rolle wird im Detail beschrieben“; von da an erlaubt dieses Panel, was die Rolle nennt, und sonst nichts. Bestehende Rollen ändern sich also nicht, bis du sie änderst.

Die Liste dieser Rechte ist unbegrenzt – sie wächst mit jedem Typ, jedem Feld und jedem Textschlüssel eines Projekts –, deshalb bietet der Editor **Nutzerrollen** sie nicht als Liste an: Unter der Rechteauswahl steht ein Feld, in das sich eine eintippen lässt. Sie wird auf ihre Form geprüft, in die Auswahl übernommen und mit demselben ✕ wieder entfernt wie jede andere.

Was eine Rolle nicht darf, wird ihr nicht angeboten: Ein Feld, das sie nicht ändern darf, steht schreibgeschützt da, „Neues Element“ und „Löschen“ verschwinden, und eine Textgruppe, in der sie nichts schreiben darf, verliert ihre Schaltfläche Speichern. Die Ablehnung selbst passiert serverseitig, sodass auch eine Anfrage am Bildschirm vorbei abgelehnt wird.

Ein Panel, für das dem Konto die Berechtigung fehlt, wird gar nicht erst gerendert, und seine Aktionen antworten in jedem Fall mit `403`; eine Fläche zeigt nur die Tabs, die das Konto hält. Ein fehlender Menüpunkt oder Tab ist deshalb meist beabsichtigt und kein Darstellungsfehler.

Nach fünf Fehlversuchen ist ein Konto eine Stunde gesperrt – beide Zahlen sind der Tab **Anmeldeschutz** des Panels Nutzer; derselbe Zähler läuft je Adresse, sodass auch das Raten über Konten hinweg gedrosselt ist. Konten und Rollen liegen in der `config.php`, die Zähler der Anmeldedrossel unter `private/.auth/`.

Für den Betrieb:

- verwende `/_admin` ausschließlich über HTTPS;
- lege Redaktionskonten mit der Rolle **Editor** an und lege eine Rolle nur für einen konkreten Bedarf an;
- halte die Zahl der Entwicklerkonten klein;
- melde dich nach der Arbeit über **Abmelden** ab;
- schütze den Pfad zusätzlich über Webserver, VPN oder IP-Freigaben, wenn das Hosting es erlaubt.

Wenn die Konten selbst das Problem sind – das letzte Entwicklerpasswort vergessen, eine misslungene Wiederherstellung – ist die [Recovery-Seite](#recovery) der Weg zurück.

## Die Oberfläche

Die Leiste links trägt Marke, Konto, Zahnrad und Navigation; die Fläche rechts zeigt das gewählte Panel. Auf dem Telefon ist die Leiste eine Zeile am oberen Rand und die Panels sind Tabs.

- **Gruppen.** Die Navigation ist in Inhalt, Struktur und System mit je einer Überschrift unterteilt. Ein Konto, das nur eine Gruppe sieht, bekommt eine schlichte Liste.
- **Tabs.** Ein Panel mit mehreren Bildschirmen trägt eine Tab-Leiste am Kopf seiner Fläche – Elemente und Elementtypen, Nutzer, Nutzerrollen und Anmeldeschutz – und kommt auf dem Tab zurück, auf dem du es verlassen hast. Jeder Tab ist ein eigener Bildschirm: seine Berechtigung, sein tiefer Link (`#roles`), sein Zustand.
- **Einklappen.** Der kleine Doppelpfeil neben der Marke klappt die Leiste zu einer Spalte aus Symbolen zusammen. Ein Panel, das die ganze Breite braucht – der Template Builder – klappt sie von sich aus ein und nimmt der Fläche die Lesebreiten-Grenze; klappst du sie von Hand wieder auf, bleibt sie auf jedem Panel offen, bis du sie wieder einklappst. Die Wahl liegt im Browser, nicht auf dem Server.
- **Tiefe Links.** Die Adresszeile folgt dir: `#elements/team/ada` ist das Element, das du gerade bearbeitest, `#design/header` der Header-Editor. Ein Neuladen oder ein Lesezeichen öffnet genau diesen Stand, und der Zurück-Knopf des Browsers geht ihn zurück.
- **Zahnrad.** Oberflächensprache und helles oder dunkles Farbschema. Die Sprache bestimmt auch die Inhaltssprache, mit der die Formulare unter Texte und Elemente öffnen.
- **Panelwechsel** setzt kein Panel zurück: Der Template Builder behält sein ungespeichertes Dokument, ein Elementformular seine ungespeicherten Werte, bis du speicherst oder die Seite verlässt. Das Verlassen mit ungespeicherten Änderungen im Templates- oder Design-Panel fragt vorher nach.

Welches Panel auch offen ist – Speichern schreibt die Projektdateien sofort. Es gibt keinen Entwurfszustand und keinen eigenen Veröffentlichen-Schritt; prüfe danach das Frontend und jede betroffene Sprache.

## Inhalt

### Dashboard

Das **Dashboard** ist das erste Panel und fasst zusammen, was das Konto sehen darf: eine Kachel je Panel, das etwas zu zählen hat – Elemente je Typ, Anfragen, Newsletter-Abonnenten, Nutzer, Elementtypen, Routen, noch fehlende Textschlüssel und Bildplätze – dazu das Datum der letzten Sicherung und die jüngsten Log-Einträge. Jede Kachel führt beim Klick zu dem Panel, für das sie zählt; das Dashboard selbst ändert nichts.

### Elemente

Elemente sind wiederkehrende strukturierte Inhalte – Teammitglieder, Leistungen, Referenzen –, deren Felder ein Entwickler auf dem Tab **Elementtypen** dieses Panels festlegt. Redakteure und Entwickler pflegen die Einträge im selben Panel; der Tab gehört dem Entwickler.

1. Wähle einen Typ.
2. Öffne einen Eintrag oder wähle **Neues Element**. Ein Typ, der seine Elemente nummeriert, nennt die Uri, die er gleich anlegt; jeder andere fragt nach einem Slug aus Kleinbuchstaben, Ziffern, Binde- und Unterstrichen.
3. Fülle die globalen Felder einmal und die übersetzten je Sprache – der Sprachumschalter sitzt im Formular, ungespeicherte Werte überstehen den Wechsel.
4. **Speichern**. Ein Bildfeld wird erst verfügbar, nachdem ein neues Element einmal gespeichert wurde; Nino verarbeitet den Upload dann auf die Maße, die der Typ vorgibt.

Ein Feld, das auf andere Elemente verweist, ist eine Auswahlliste oder, wo der Typ mehrere erlaubt, eine geordnete Liste mit Suchfeld, Verschiebe-Knöpfen und einem Maximum, das der Typ setzen kann. Ein gelöschtes Ziel wird als *fehlend* angezeigt statt stillschweigend entfernt.

**Rohdaten**, am Fuß des Formulars, zeigt die Buckets, in denen der Eintrag liegt: `*` für die globalen Felder und einer je Sprache. Die Ansicht ist nur lesend und für Diagnose und Migrationen gedacht.

**Duplizieren** übernimmt alle Werte des offenen Eintrags in ein neues Element – alle Sprachen, alle Felder, außer der Uri und den Bildern, die zu dem Eintrag gehören, für den sie hochgeladen wurden. Geschrieben ist noch nichts: Gib der Kopie eine Uri und speichere sie.

**Löschen** entfernt den Eintrag in jeder Sprache und die Bilder, die nur seine Bildfelder nutzten. Nur eine Sicherung bringt ihn zurück.

### Texte

**Texte** enthält die einzelnen Textfills der Seite – Überschriften, Beschreibungen, Kontaktdaten, Beschriftungen –, gruppiert nach dem ersten Segment ihres Schlüssels: `/home/intro/title` liegt in der Gruppe `home`. Öffne eine Gruppe, bearbeite die globalen Werte und die übersetzten in der gewählten Sprache, und **Speichern**. Formatierte Felder bieten Fett, Kursiv, Hervorhebung, Inline-Code und Links; Zeichenzähler zeigen die vom Entwickler vorgesehene Länge.

Ein Schlüssel, der hier nicht erscheint, ist entweder für die Bearbeitung ausgeblendet oder technisch. Schlüssel anlegen, umbenennen und löschen ist Sache des Tabs **Textschlüssel**; die projektweite Übersetzungsübergabe ist der Tab **Übersetzungen** des Panels Sprache.

### Bilder

**Bilder** listet die Bildplätze, die der Entwickler auf dem Tab **Bildplätze** definiert hat, gruppiert nach Uri-Bereich, mit Beschriftung, Shortcode und Zielmaßen. Wähle eine Datei für einen Platz und starte den Upload; Nino prüft und verarbeitet sie, weist eine ungültige oder zu große Datei ab und ersetzt das aktuelle Bild sofort.

### Anfragen

**Anfragen** listet die gespeicherten Einträge des Kontaktformulars, solange das Form-Modul aktiv ist: Datum, Kategorie, Absender und Nachricht, aufklappbar, als CSV exportierbar. Die Ansicht ist bewusst nur lesend. Das Anwählen einer Adresse öffnet dein Mailprogramm; Nino antwortet nicht von sich aus.

### Newsletter

**Newsletter** listet die Abonnements, solange das Newsletter-Modul aktiv ist. Kopiere alle Adressen als BCC-Zeile, exportiere die Liste als CSV oder lösche ein Abonnement nach Bestätigung. Eine gelöschte Adresse wird zusätzlich als entfernt vermerkt, damit das Einspielen einer älteren Sicherung die Abmeldung nicht stillschweigend rückgängig macht.

### Log

**Log** zeigt das Aktivitätsprotokoll: Anmeldungen und jede erfolgreiche Änderung, mit dem Konto, das sie vorgenommen hat. Einträge bleiben 14 Tage erhalten und sind nur lesend. Das ist nicht das PHP-Fehlerprotokoll; das schaltet **Konfiguration**.

## Struktur

### Templates

Das Panel **Templates** ist der Template Builder: Er setzt die `page-*.tpl`-Dateien des Projekts aus vollständigen Sections zusammen – eine durchsuchbare Bibliothek von Section-Presets, wiederverwendbare `[template]`-Sections, Header und Footer der Seite und eine native Schnellbefüllung der Texte, die eine Section mitbringt. Es ist ein Workspace-Panel: Die Leiste klappt ein, und Templateliste, Section-Canvas und Inspektor stehen nebeneinander.

Alles, was es kann, seine Regeln zur Quelltextsicherheit und der Manifest-Vertrag der Preset-Bibliothek stehen in der Referenz [Templates-Panel](templates.de.md). Das Panel ist ein Modul (`app/Nino/Modules/Templates/`) und verschwindet mit seinem Verzeichnis.

### Design

Das Panel **Design** hält die vier Erscheinungsbild-Entscheidungen der Seite nach dem Assistenten bearbeitbar: **Theme** installiert eine vollständige visuelle Grundlage, **Header** und **Footer** ersetzen je einen Rahmen, **Design** erzeugt Farbpalette und Größenraster aus einer Handvoll Einstellungen und schreibt `assets/style.design.css`. Die vier sind Tabs im Panel; der Aktionsknopf am Fuß wechselt mit dem aktiven Tab.

Theme, Header und Footer lesen den Katalog des Assistenten unter `_admin/install/library/` und sagen es, wenn er entfernt wurde; Design erzeugt statt zu kopieren und funktioniert in jedem Fall. Die Einstellungen, der Token-Vertrag und die Bundle-Reihenfolge stehen in der Referenz [Design-Panel](appearance.de.md). Das Panel ist ein Modul (`app/Nino/Modules/Design/`) und verschwindet mit seinem Verzeichnis.

### Elementtypen

Elementtypen beschreiben wiederkehrende Inhalte. Jeder Typ ist eine Datei unter `elements/`; seine Einträge werden unter **Elemente** gepflegt – dem Panel, dessen Tab dieser Bildschirm ist, erreichbar über die Leiste am Kopf seiner Fläche oder mit `#types`.

1. Wähle **Neuer Typ**.
2. Gib ihm eine technische Uri – zuerst ein Kleinbuchstabe, dann Kleinbuchstaben, Ziffern, Binde- und Unterstriche, etwa `team` oder `service_items`. Sie wird zu `elements/<uri>.php` und lässt sich danach nicht ändern.
3. Gib ihm einen Titel für das Panel Elemente.
4. Füge die Felder mit **Feld hinzufügen** hinzu und speichere.

| Feldtyp | Geeignet für |
|---|---|
| `string` | ein- oder mehrzeiliger Text; wahlweise Rich Text oder feste Auswahl |
| `integer` | ganze Zahlen |
| `double` | Dezimalzahlen |
| `boolean` | Ja/Nein |
| `array` | einfache Listen oder strukturierte Werte |
| `date` | ein Datum |
| `datetime` | Datum und Uhrzeit |
| `image` | ein Bild mit festen Zielmaßen |
| `element` | ein Verweis auf ein Element eines anderen Typs |

Je nach Typ ist ein Feld *pro Übersetzung* oder global, Pflicht oder optional, Rich Text, auf feste Werte beschränkt, mit Maßen, Einheit oder Suffix versehen. Ein `element`-Feld nennt den Typ, auf den es verweist, und darf mehrere Elemente halten, geordnet, mit **Max. Elemente** als Obergrenze (`0` für keine); der Kernel setzt diese Grenze beim Speichern durch. Ein gelöschtes Ziel bleibt als *fehlend* stehen, und Verweise sind nicht Teil eines Übersetzungs-Exports.

Der Wechsel eines Felds zwischen global und pro Übersetzung migriert die vorhandenen Werte; prüfe das Ergebnis in jeder Sprache. Das Speichern eines Typs löscht keine Einträge, ein entferntes Feld verschwindet aber aus dem Formular.

**Elemente dieses Typs nummerieren**, in der Gruppe *Element URIs* des Typs, ersetzt den Slug durch einen Zähler für Einträge, die keinen Namen haben, der in eine URL gehört – ein Galeriebild, eine Preiszeile: `/gallery/00001`, `/gallery/00002`. Nummern werden nie wiederverwendet, ein späteres Einschalten ist sicher, und der Zähler liegt in der Typdatei unter derselben Sperre wie das Element.

**Elementtyp löschen**, am Fuß des Typformulars, entfernt die Typdatei, alle Elemente darin und die Bilder, die daran hängen. Es ist die einzige Schaltfläche des Panels, die Inhalte zerstört, und deshalb nur erreichbar, wenn die Uri des Typs daneben eingetippt wird – ein einzelner Klick kommt nicht dorthin. Ein Typ, auf den das `element`-Feld eines anderen Typs verweist, wird abgelehnt und das Feld benannt: Sein Löschen ließe den Verweis ins Leere zeigen. Rückgängig geht das nicht; zurück führt nur ein Backup.

### Routen

**Routen** verwaltet die Seitenrouten – die vom Assistenten angelegten, die hier ergänzten und die von Hand in die `config.php` geschriebenen; sie sind eine Liste, bei jeder Anfrage aus `/nino/http/routes` und den Textschlüsseln `/webpage<uri>/*` abgeleitet.

Eine Seite hat zwei Uris: Die **Element URI** ist ihre stabile Identität, der Anker ihrer Seitentexte wie `/webpage<uri>/title`; ihr Speichern schreibt auch `/webpage<uri>/uri`, den erreichbaren Pfad, sodass ein Template mit `[[/webpage/site-contact/uri]]` verlinkt statt einen Pfad zu wiederholen. Die **HTTP URI** ist dieser erreichbare Pfad. Eine Seite kann intern `/about` heißen und im Browser unter `/ueber-uns` liegen.

Beim Anlegen oder Bearbeiten legst du außerdem das Template aus `templates/page-*.tpl`, einen HTTP-Statuscode, Navigationsname, Seitentitel und Beschreibung je aktiver Sprache und ein Kästchen je in `/nino/html/navs` registrierter Navigation fest. Die Zugehörigkeit liegt auf der Route als `'navs' => [ 'main' => 1, ... ]`, der Wert eine Priorität; eine hier ergänzte Zugehörigkeit beginnt hinter allem, was schon im Menü ist, und eine von Hand gesetzte Priorität wird nie zurückgesetzt. Die Pfeile tauschen zwei Seitenrouten in der `config.php`.

`/_admin` ist reserviert und kann keine öffentliche Seite sein. Eine Route, die ihr Template zur Laufzeit wählt, zeigt ihren vorhandenen Body und behält ihn. Das Löschen einer Seite entfernt ihre Route; ihre Texte und ihre Templatedatei bleiben.

### Navigationen

**Navigationen** ist die andere Hälfte dessen, was Routen bearbeitet: ein Menü nach dem anderen, in seiner Reihenfolge. Es gehört zum Navigation-Modul.

Ein geöffnetes Menü zeigt seine Einträge, wie sie gerendert werden, mit ↑ / ↓ zum Verschieben, × zum Herausnehmen (die Route bleibt) und einer Auswahl, die jede `GET`-Route am Ende anfügt. Eine Route ohne `/webpage<uri>/name` ist markiert, weil `[navigation]` sie überspringt statt einen leeren Link zu rendern. Prioritäten bleiben dicht, `1..n` je Menü.

**Anlegen** registriert die Id eines Menüs; **Umbenennen** prüft, dass die Id in der Registry und auf jeder Route frei ist, und folgt ihr dann in beide; **Löschen** entfernt es überall. Keines davon rührt das Argument `[navigation nav="…"]` in deinen Templates an – das ist Inhalt, den du selbst aktualisierst.

### Textschlüssel

**Textschlüssel**, ein Tab des Panels Texte, ist dessen technische Seite: jeder Schlüssel jeder Gruppe, mit

- globaler oder sprachabhängiger Speicherung, umschaltbar mit Migration der vorhandenen Werte;
- **neuen Schlüsseln** und **Umbenennen**, wieder mit Migration;
- dem Ausblenden eines Schlüssels aus dem Panel Texte;
- dem Löschen eines Schlüssels aus jeder Sprache – prüfe vorher seine Verwendung in Templates, Mails und Modulen.

**Templates nach fehlenden Schlüsseln durchsuchen** findet statische Textfills wie `[[/home/intro/title]]` in den `.tpl`-Dateien, die kein Schlüssel beantwortet, und bietet je Schlüssel drei Antworten – so lässt sich eine lange Liste in mehreren Sitzungen abarbeiten:

- **ein Anfangswert** legt den Schlüssel mit diesem Text in jeder Sprache an;
- **ein leeres Feld** wird dieses eine Mal übergangen – der Schlüssel kommt beim nächsten Scan wieder;
- **Dauerhaft ignorieren** legt den Schlüssel still: Er verschwindet aus dem Scan, aus der Dashboard-Kachel und aus dem Panel Texte und steht hier als ausgeblendeter Schlüssel. Das Häkchen *ausgeblendet* zu entfernen – oder ihn zu löschen – holt ihn in den Scan zurück.

Dynamisch zusammengesetzte Schlüssel liegen außerhalb eines statischen Scans.

### Bildplätze

**Bildplätze** ist ein Tab des Panels Bilder. Ein Bildplatz verbindet eine technische Uri (`/home/hero`) mit einer Beschriftung und festen Zielmaßen; Redakteure befüllen ihn unter **Bilder**. **Templates nach fehlenden Bildplätzen durchsuchen** findet lokale `<img src="…">`-Verweise unter `images/` ohne Platz. Das Löschen eines Platzes löscht das darin gespeicherte Bild.

## System

### Nutzer

Jedes Konto kann unter **Nutzer** die eigene E-Mail-Adresse und das eigene Passwort ändern; eine Änderung am eigenen Konto verlangt das aktuelle Passwort. **Überall abmelden** beendet jede Sitzung des Kontos – nach einem verlorenen Gerät oder einem Verdacht auf Missbrauch. Das Panel steht unter System, dieser erste Tab gehört aber jedem Konto.

Ein Konto mit `/_admin/users/manage` sieht außerdem die anderen Konten und kann:

- eines **anlegen**, mit Adresse, einem Passwort aus mindestens acht Zeichen und einer Rolle;
- Adresse oder Passwort ändern und seine Sitzungen beenden;
- ihm eine **andere Rolle** geben, oder keine – nie dem eigenen Konto: abmelden und einen anderen Verwalter bitten;
- es **löschen**. Das eigene Konto und das letzte Konto mit Vollzugriff – eigenem oder dem seiner Rolle – lassen sich nicht löschen, und der letzte Vollzugriff lässt sich auch nicht über einen Rollenwechsel abgeben.

**Nutzerrollen**, der zweite Tab, ist der Ort, an dem die Rollen entstehen. Eine Rolle hat eine Kennung (ein Slug, nach dem Anlegen fest), einen Namen, einen Schalter **Vollzugriff**, der für alle Rechte auf einmal steht, auch die künftiger Module, und darunter die Rechte selbst in derselben Auswahl, die ein Mehrfach-Element-Feld benutzt: die der Rolle als kurze Liste mit je einem ✕, alles andere hinter einem Suchfeld. Jeder Eintrag heißt nach dem Panel oder Tab, das er öffnet, in der Gruppenreihenfolge der Navigation. Ein Recht, das diese Installation hält, das aber gerade kein Panel anbietet – ein abgeschaltetes Modul, ein gelöschtes Modul, ein von Hand geschriebenes Recht –, steht ebenfalls dort, unter **Nicht angeboten** und mit seiner eigenen Zeichenkette: Es ist in Kraft, bleibt also sichtbar, behaltbar und entfernbar, statt aus dem Formular zu verschwinden und beim nächsten Speichern der Rolle wegzufallen. Die beiden Rollen des Assistenten, Editor und Developer, sind hier gewöhnliche Rollen. Eine Rolle, die Konten halten, lässt sich nicht löschen; eine Änderung, die dem eigenen Konto *Nutzer verwalten* nähme, wird abgelehnt, ebenso eine, nach der kein Konto mehr Vollzugriff hätte. Die eigenen Rechte kann hier niemand erweitern.

**Anmeldeschutz**, der dritte Tab, hält die Drossel vor der Anmeldung: **Fehlversuche bis zur Sperre** (`/nino/auth/maxtries`, 1–100) und **Dauer der Sperre** (`/nino/auth/cooldown`, 60–604800 Sekunden). Beide waren eine Gruppe von Konfiguration und behalten dessen Prüfung; der Tab braucht `/_admin/lockout/manage`.

### Sprache

**Sprache** ist das Formular der beiden Sprach-Einstellungen der `config.php`, gemeinsam gespeichert, mit der Übersetzungsübergabe als zweitem Tab.

| Einstellung | Schlüssel | Steuerelement |
|---|---|---|
| Sprachen | `/nino/locales/available` | Kästchen |
| Native Sprache | `/nino/locales/native` | Auswahl |

Die Sprachliste zeigt jede Sprache, die das Projekt kennt – die in der `config.php` und jede `text/<locale>.php` auf der Platte – und ob diese Datei existiert und wie viele Schlüssel sie hält. **Eine Sprache hinzufügen** schreibt `text/<locale>.php` als Gerüst mit leeren Werten und schaltet die Sprache *nicht* ein; übersetze sie unter Texte oder importiere sie auf dem Tab Übersetzungen, hake sie dann an und speichere. Die native Sprache kann nur eine der angehakten sein, deshalb werden beide gemeinsam gespeichert.

### Übersetzungen

**Übersetzungen**, der zweite Tab des Panels Sprache, ist die projektweite Übergabe, um eine Seite zu übersetzen, nachdem ihre nativen Inhalte fertig sind. Der Export nutzt die native Sprache und vereint die nicht globalen, nicht technischen Textwerte mit den sprachabhängigen Elementfeldern, die einen nativen Wert halten; globale Werte, technische Texte, Bilder, Element-Uris und Reihenfolgen sind nicht Teil davon. Das JSON trägt Anweisungen für Übersetzungswerkzeuge: nur Werte übersetzen, Schlüssel, Typen, HTML, URLs, Platzhalter, Shortcodes und Bezeichner erhalten.

1. Lade das native Paket herunter.
2. Übersetze seine Werte, ohne die Struktur zu ändern.
3. Wähle die Zielsprache.
4. Lade das JSON hoch oder füge es ein und wähle **In die gewählte Sprache importieren**.
5. Prüfe die Zähler für importierte und übersprungene Werte.

Der Import ergänzt nur: Passende Werte werden überschrieben, im Dokument fehlende bleiben unangetastet. Jeder Pfad wird gegen einen frischen nativen Export geprüft; Text und Rich Text werden bereinigt; unbekannte, globale, technische und Bildfelder werden übersprungen. Der Import in die native Sprache ist möglich, überschreibt aber Quellinhalte.

### Backups

Mit eingeschalteten Sicherungen schreibt die erste angemeldete Anfrage eines Tages eine verschlüsselte Sicherung von allem, was die Workbench schreiben kann – Konfiguration, Texte, Elemente, Bilder, Daten – unter `private/.backups/`; tägliche Sicherungen bleiben 14 Tage erhalten. Die Archive sind mit AES-256-GCM verschlüsselt; der Schlüssel liegt unter `private/.auth/`, die Archive allein sind also unlesbar.

**Backups** listet die verfügbaren Daten und stellt eines wieder her. Vor einer Wiederherstellung wird der aktuelle Stand noch einmal gesichert, sodass sich ein falscher Griff selbst rückgängig machen lässt. Prüfe danach mindestens das Frontend in jeder Sprache, Anmeldung und Rechte, Seiten, Texte, Elemente, Bilder sowie Formular- und Newsletter-Daten.

Ein Modul, das eigene Dateien unter `data/` hält, führt sie bei einer Wiederherstellung über den Callback `/nino/admin/restore` zusammen (das Newsletter-Modul tut das). Die tägliche Sicherung ist ein Sicherheitsnetz für redaktionelle Fehler, kein Ersatz für eine externe Sicherung des gesamten Projekts.

### Konfiguration

**Konfiguration** bearbeitet eine bewusst begrenzte Auswahl der `config.php` als Formular. Jeder Wert ist typisiert und wird geprüft; die Seite wird in einem Zug geschrieben.

| Gruppe | Einstellung | Schlüssel | Steuerelement |
|---|---|---|---|
| Fehler und Diagnose | Fehler in ein Log schreiben | `/nino/error/log` | Schalter |
| Fehler und Diagnose | Fehler im Frontend anzeigen | `/nino/error/display` | Schalter |
| Fehler und Diagnose | Session-Cookie immer als secure setzen | `/nino/session/force-secure-cookie` | Schalter |
| Workbench | Tägliche verschlüsselte Sicherung | `/nino/admin/backups` | Schalter |
| Workbench | Aktivitätsprotokoll führen | `/nino/admin/logs` | Schalter |
| Seiten-Cache | Gerenderte Seiten cachen | `/nino/cache/status` | Schalter |
| Seiten-Cache | Lebensdauer einer gecachten Seite | `/nino/cache/ttl` | Zahl, 10–2592000 Sekunden |
| Seiten-Cache | Nie cachen | `/nino/cache/blacklist` | eine Uri je Zeile |

Die Anmeldedrossel ist der Tab **Anmeldeschutz** des Panels Nutzer, die Sprachen sind das Panel **Sprache**. Routen, Navigationen und die Asset-Bundles werden hier ebenfalls nicht bearbeitet: Die ersten beiden haben ihre Panels, die Bundle-Reihenfolge trägt die CSS-Kaskade und bleibt eine bewusste Dateibearbeitung.

**Der Seiten-Cache.** Mit **Gerenderte Seiten cachen** speichert `Modules\Cache` eine fertige Seite und liefert sie ohne Rendern erneut aus. Nie gecacht: alles außer einem schlichten `GET` mit `200`, alles mit Query-Variablen, jede Uri unter `/_` oder `/.` und jede Anfrage eines angemeldeten Besuchers. **Nie cachen** ergänzt eigene Ausnahmen; ein abschließendes `/*` deckt einen Teilbaum ab. Das `[csrf]`-Token und die `[jstext]`-Nonce werden je Antwort neu gestempelt. Jedes Speichern in der Workbench leert den gesamten Cache; Antworten tragen `X-Nino-Cache: hit` oder `miss`.

In Produktion muss `/nino/error/display` aus sein.

### Suche

**Suche** gehört zum Search-Modul. **Suchindex erstellen** baut jeden gültigen Index unter `/nino/elements/index` neu auf und meldet, wie viele Indexdateien und Elemente geschrieben wurden. Die Indexdefinition ist bewusste Arbeit in der `config.php`, siehe [Elements-Suchindex](development.de.md#suchindex-für-elements); nutze den Knopf nach dem Anlegen, nach dem Ändern indizierter Felder oder nach dem Bearbeiten von Elementdateien von Hand.

## Recovery

`/_admin/recovery.php` ist der Weg zurück, wenn die Konten selbst das Problem sind: jedes Entwicklerpasswort vergessen, oder eine Wiederherstellung misslungen. Die Seite fragt nach dem **Recovery-Passwort** aus dem letzten Schritt des Assistenten – kein Login, und nichts in der Workbench fragt je danach – und bietet genau zwei Dinge:

- **Eine Sicherung wiederherstellen**, aus der Liste der Daten, nach einer Sicherung des aktuellen Stands;
- **Ein Konto zurücksetzen**: Eine vorhandene Adresse bekommt das neue Passwort und wird überall abgemeldet; eine Adresse ohne Konto wird eines mit Vollzugriff.

Fünf Fehlversuche sperren die Seite eine Stunde. Der Hash des Geheimnisses liegt in `private/.auth/pw.php` – außerhalb der `config.php`, damit eine Wiederherstellung ihn nicht zurückrollen kann, und außerhalb jedes Werkzeugverzeichnisses, damit ein Update ihn nicht mitnimmt. Ein neues außerhalb des Assistenten setzen:

```bash
php _admin/Admin.php <passwort>
```

Die Ausgabe ist die vollständige Datei; schreibe sie nach `private/.auth/pw.php`. Tu das nur in einer geschützten lokalen Umgebung – ein Passwort auf der Kommandozeile kann im Shell-Verlauf oder in der Prozessliste sichtbar werden.

## Empfohlener Arbeitsablauf

1. Führe den Assistenten aus, dann lösche `_admin/install/` aus der Produktivauslieferung oder behalte es gesperrt für den Erscheinungsbild-Katalog.
2. Baue die Struktur unter **Elementtypen** (ein Tab von Elemente), **Textschlüssel** (Texte), **Bildplätze** (Bilder), **Routen** und **Navigationen**.
3. Setze die Seiten unter **Templates** zusammen, lege das Erscheinungsbild unter **Design** fest und prüfe das Ergebnis im Browser.
4. Fülle die Inhalte unter **Elemente**, **Texte** und **Bilder**; übergib eine Sprache unter **Sprache › Übersetzungen**.
5. Prüfe Dashboard und die beiden Scans auf fehlende Definitionen.
6. Lege die Redaktionskonten unter **Nutzer** mit der Rolle Editor an – lege auf dem Tab **Nutzerrollen** eine Rolle an, wo die beiden nicht reichen – und prüfe, was sie sehen.
7. Prüfe Frontend, jede Sprache, die Formulare und das responsive Layout.
8. Committe die Projektdateien.

## Wenn etwas nicht funktioniert

| Problem | Prüfen |
|---|---|
| Anmeldung nach mehreren Versuchen gesperrt | Die Sperrdauer abwarten (eine Stunde als Standard, siehe **Nutzer › Anmeldeschutz**); die Sperre gilt je Konto und je Adresse. |
| Ein Panel oder ein Tab fehlt | Dem Konto fehlt die Berechtigung, oder sein Modul ist nicht aktiv. |
| Speichern schlägt fehl | Schreibrechte der betroffenen Datei oder des Verzeichnisses. |
| Template fehlt unter **Routen** | Angeboten werden nur vorhandene Dateien `templates/page-*.tpl`. |
| Eine Seite lässt sich unter **Templates** nicht speichern | Nach einer externen Änderung neu laden, eindeutige Section-Ids und unpaarige `<section>`-Tags prüfen; siehe [Templates-Panel](templates.de.md). |
| **Design** meldet, keine Varianten seien verfügbar | `_admin/install/library/` wurde entfernt; der Tab Design funktioniert weiter. |
| Texte oder Bilder fehlen in einem Scan | Dynamische Schlüssel und Bilder sind statisch nicht erkennbar. |
| Die Backup-Liste ist leer | Sicherungen sind ausgeschaltet, oder heute gab es noch keine angemeldete Anfrage. |
| Die Suche liefert keine Elemente | `/nino/elements/index` und das Search-Modul in der `config.php`, dann **Suchindex erstellen**. |
| Webseite nach **Konfiguration** kaputt | Letzten Git-Stand oder Sicherung wiederherstellen. |
| Kein Entwicklerpasswort funktioniert mehr | `/_admin/recovery.php` mit dem Recovery-Passwort. |

## Wie es weitergeht

- [Einrichtungsassistent](setup.de.md) dokumentiert die zehn Erststart-Schritte und das Library-Format.
- [Templates-Panel](templates.de.md) erklärt den Template Builder und den Vertrag der Section-Presets.
- [Design-Panel](appearance.de.md) erklärt die vier Erscheinungsbild-Editoren und den Token-Vertrag.
- [Entwickler-Handbuch](development.de.md) beschreibt APIs, Module, Panels und die direkte Arbeit an Projektdateien.
- [Deployment](deployment.de.md) behandelt Webserver, Sicherheit, Sicherungen und Go-Live.
