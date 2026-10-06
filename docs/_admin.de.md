# `/_admin` — Die Workbench

**Sprache:** [English](_admin.md) · Deutsch

**Stand:** 22. September 2026 · **Nino-Version:** 1.3.1

Dieses Handbuch erklärt die eine Verwaltungsoberfläche eines Nino-Projekts: `/_admin`, die Workbench. Entwickler richten das Projekt hier ein und bauen Struktur und Erscheinungsbild; Redakteure pflegen hier die Inhalte. Was ein Konto sieht, bestimmen seine Rechte. Der Assistent, der aus einem frischen Checkout ein Projekt macht, ist der Erststart-Modus der Workbench und hat eine eigene Referenz, den [Einrichtungsassistenten](setup.de.md); der [Template-Baukasten](https://github.com/dapeio/nino-features/blob/main/features/Templates/docs/templates.de.md) ebenfalls – der allerdings ist ein Feature aus dem Katalog und nicht Teil von Nino.

**Weitere Links:**
[README](../README.de.md) · [Grundkonzepte](concepts.de.md) · [Entwickler-Handbuch](development.de.md) · [Rezepte](recipes/README.md) · [Erste Schritte](getting-started.de.md) · [Einrichtungsassistent](setup.de.md) · [`/_admin`-Workbench](_admin.de.md) · [Features](features.de.md) · [Deployment](deployment.de.md) · [Security Policy](https://github.com/dapeio/nino/blob/main/SECURITY.md) · [Changelog](https://github.com/dapeio/nino/blob/main/CHANGELOG.md)

**Sicherheitshinweis:** Jedes Panel schreibt unmittelbar in Konfiguration und Projektdateien. Ein Entwicklerkonto kann Routing, Datenmodelle, Templates und die sichtbare Webseite verändern, ein Redaktionskonto die Inhalte. Arbeite mit einem aktuellen Git-Stand oder einer anderen verlässlichen Sicherung, ausschließlich über HTTPS, und gib jedem Konto genau die Rolle, die es braucht.

## Aufgabe und Abgrenzung

Ein Login, eine Navigation, jeder Bildschirm ein Panel. Die Panels sind danach gruppiert, was sie verändern:

| Gruppe | Panels | Wer |
|---|---|---|
| **Inhalt** | Dashboard, Elemente (Elementtypen), Texte (Textschlüssel), Bilder (Bildplätze), Anfragen, Log | Redakteure und Entwickler |
| **Struktur** | Routen, Navigationen | Entwickler |
| **Features** | was die aktiven Features mitbringen | wer die eigene Berechtigung des Feature-Panels hält |
| **System** | Nutzer (Nutzerrollen, Anmeldeschutz, Recovery-Passwort), Sprache (Übersetzungen), Backups, Konfiguration, Features, Wartung | Entwickler – und jedes Konto für sein eigenes Profil unter Nutzer |

Ein Bildschirm in Klammern ist ein **Tab** des Panels davor: Das Panel Elemente öffnet auf den Einträgen und trägt Elementtypen als zweiten Tab, sodass die Form der Inhalte direkt neben den Inhalten liegt. Ein Tab ist ein eigener Bildschirm – mit eigener Berechtigung, sodass ein Redakteur Elemente ohne Elementtypen sieht, und eigenem tiefen Link, `#types`.

Jedes Panel außer dem Dashboard öffnet mit demselben Kopf: sein Name links oben, daneben die Tabs, wo es welche hat, und am rechten Ende der Zeile die Schaltflächen, die ein Panel über seinem Bildschirm behält. Das Dashboard sind die Kacheln allein.

Anfragen, Navigationen und Wartung gehören zu optionalen Kernel-Modulen und sind vorhanden, solange ihr Modul aktiv ist, in `/nino/modules` ein- oder ausgeschaltet. Ein **Feature** – ein installierbares Paket unter `features/`, aus dem Katalog [dapeio/nino-features](https://github.com/dapeio/nino-features) hineinkopiert und im Panel Features eingeschaltet – bringt sein Panel auf dieselbe Weise mit – das Newsletter-Feature des Katalogs ein Panel Newsletter, sein Forms-Feature ein Panel Formulare, sein Template-Baukasten ein Panel Templates, und ein Checkout bringt keines davon mit; jedes landet in der eigenen Gruppe Features der Leiste. Die Panels oben, die in keiner der beiden Listen stehen, sind die eigenen der Workbench: `_admin` hält die Hülle, und jede Ansicht darin ist ein Modul unter `_admin/Nino/Modules/<Name>/` – Verzeichnis für Verzeichnis hinzugefügt und wieder entfernt. Ein Modul, das ein Projekt hinzufügt, oder ein Feature, das es installiert, kann auf dieselbe Weise ein eigenes Panel mitbringen; siehe das [Entwickler-Handbuch](development.de.md#panels-der-workbench) und [Features](features.de.md).

Ein zweites Werkzeug gibt es nicht. `/_editor`, `/_install`, `/_design` und `/_templates` früherer Versionen sind fort: Was von ihnen geblieben ist, ist ein Panel hier oder – beim Template-Baukasten – ein Feature aus dem Katalog, und ein früher reservierter Pfad ist jetzt ein gewöhnlicher Seitenpfad.

Alle Panel-Namen folgen der Oberflächensprache des Kontos. Dieses Handbuch nennt die deutschen Bezeichnungen.

## Erststart: der Einrichtungsassistent

Ein frischer Checkout hat noch kein Projekt. Bis der letzte Schritt des Assistenten abgeschlossen ist, zeigt `/_admin` statt der Anmeldung den Assistenten: sechs Schritte von der Umgebungsprüfung bis zu den Konten und dem Recovery-Passwort. Die Referenz [Einrichtungsassistent](setup.de.md) erklärt jeden Schritt und was er schreibt.

Der Assistent liegt in `_admin/install/`. Sobald er sich selbst ausgesperrt hat, kann dieses Verzeichnis aus einer Produktivauslieferung entfernt werden: Nichts außerhalb liest seine Library, und alles, was er kopiert hat, bleibt dort liegen, wo er es geschrieben hat.

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
| Log | `/_admin/logs/view` |
| Templates (das Feature Template-Baukasten) | `/_admin/templates/manage` |
| Routen | `/_admin/routes/manage` |
| Navigationen | `/_admin/navs/manage` |
| Nutzer (eigenes Profil) | keine – jedes Konto |
| Nutzer (andere Konten), Nutzerrollen (Tab von Nutzer) | `/_admin/users/manage` |
| Anmeldeschutz (Tab von Nutzer) | `/_admin/lockout/manage` |
| Recovery-Passwort (Tab von Nutzer) | `/_admin/recoverypw/manage` |
| Sprache | `/_admin/language/manage` |
| Übersetzungen (Tab von Sprache) | `/_admin/translations/manage` |
| Backups | `/_admin/backups/manage` |
| Konfiguration | `/_admin/config/manage` |
| Features | `/_admin/features/manage` |
| Wartung | `/_admin/maintenance/manage` |

Das Panel eines Features bringt seine Berechtigung mit – das Newsletter-Feature des Katalogs `/_admin/newsletter/manage`, sein Forms-Feature `/_admin/forms/manage`, und so weiter –, und der Tab Nutzerrollen des Panels Nutzer bietet sie an, solange das Feature aktiv ist.

### Feinere Rechte innerhalb eines Panels

Die Berechtigungen oben sind Türen: Sie sagen, welche Panels ein Konto öffnen darf. Innerhalb von Elemente und Texte lässt sich eine Rolle zusätzlich Aktion für Aktion und Feld für Feld beschreiben – für Redakteure, die Texte ändern, aber keine Einträge anlegen dürfen, oder die einen Typ betreuen und die übrigen nur sehen.

| Was | Berechtigung |
|---|---|
| Ein Element eines Typs anlegen | `/_admin/elements/services/insert` |
| Ein Feld davon ändern | `/_admin/elements/services/update/title` |
| Jedes Feld davon | `/_admin/elements/services/update/*` |
| Eines löschen | `/_admin/elements/services/delete` |
| Alles an genau diesem Typ | `/_admin/elements/services/*` |
| Einen Textschlüssel ändern | `/_admin/text/update/template/page-home/hero/title` |
| Jeden Schlüssel eines Templates | `/_admin/text/update/template/page-home/*` |
| Die Seitenangaben einer Route | `/_admin/text/update/_nino/webpage/home/*` |

Eine ganze Seite braucht zwei davon – ihr Template und ihre Seitenangaben –, denn die Angaben einer Seite, ihr Name im Menü, ihr Titel und ihre Beschreibung, sind Schlüssel des Systems unter `/_nino` und keine Wörter des Templates. Das sind gewöhnliche Berechtigungsstrings, für die dieselbe `/*`-Regel gilt wie für alle anderen – eine Rolle lässt sich so grob oder so fein beschreiben, wie sie es braucht. Die Panel-Berechtigung bleibt nötig: `/_admin/elements/manage` lässt das Panel überhaupt erscheinen, die feineren sagen, was darin getan werden darf.

**Sie greifen nur, wenn du sie vergibst.** Eine Rolle, die keine davon hält, behält genau das, was ihre Panel-Berechtigung immer bedeutet hat – jede Aktion an jedem Typ und jedem Schlüssel. Die erste feinere Berechtigung für ein Panel ist es, die sagt: „Diese Rolle wird im Detail beschrieben“; von da an erlaubt dieses Panel, was die Rolle nennt, und sonst nichts. Bestehende Rollen ändern sich also nicht, bis du sie änderst.

Die Liste dieser Rechte ist unbegrenzt – sie wächst mit jedem Typ, jedem Feld und jedem Textschlüssel eines Projekts –, deshalb bietet der Editor **Nutzerrollen** sie nicht als eine Liste an: Unter der Rechteauswahl wählen drei voneinander abhängige Listen eines aus – **Bereich** (ein Typ, eine Textgruppe), **Aktion** (anlegen, ändern, löschen oder alles im Bereich) und, wo die Aktion Felder hat, **Feld** (alle Felder oder eines). **Recht hinzufügen** übernimmt es in die Auswahl, wo es nach seinem Platz in diesem Baum heißt und mit demselben ✕ wieder entfernt wird wie jedes andere Recht; getippt wird nichts, hinzufügen lässt sich also nur, was die Panels auflisten. Ein Feld, dessen Name ein Leerzeichen oder einen Umlaut enthält, kann nicht Teil eines Rechts sein und wird nicht aufgelistet – es bleibt unter dem Pauschalrecht seines Typs. Ein feineres Recht, das eine Rolle schon hält und das kein Panel mehr auflistet, behält sie, sichtbar unter *Nicht angeboten*. Das erste feinere Recht eines Panels fragt vorher nach, weil das Panel danach nur noch erlaubt, was die Rolle nennt, und ebenso das Entfernen des letzten mit dem ✕, weil das Panel sich danach wieder nach dem ganzen Bereich richtet (mit Nein bleibt es); eine Zeile unter der Auswahl nennt die Panels, für die eine Rolle das hat, und eine Zusammenfassung – *Diese Rolle darf …* – sagt in Worten, was die Rolle darf: Vollzugriff, die Bereiche, die sie öffnet, was sie in einem einzeln geregelten Panel darf, und ein Panel, für das sie Einzelrechte hat, ohne es zu öffnen.

Was eine Rolle nicht darf, wird ihr nicht angeboten: Ein Feld, das sie nicht ändern darf, steht schreibgeschützt da, „Neues Element“ und „Löschen“ verschwinden, und eine Textgruppe, in der sie nichts schreiben darf, verliert ihre Schaltfläche Speichern. Die Ablehnung selbst passiert serverseitig, sodass auch eine Anfrage am Bildschirm vorbei abgelehnt wird.

Ein Panel, für das dem Konto die Berechtigung fehlt, wird gar nicht erst gerendert, und seine Aktionen antworten in jedem Fall mit `403`; eine Fläche zeigt nur die Tabs, die das Konto hält. Ein fehlender Menüpunkt oder Tab ist deshalb meist beabsichtigt und kein Darstellungsfehler.

Nach fünf Fehlversuchen ist ein Konto eine Stunde gesperrt – beide Zahlen sind der Tab **Anmeldeschutz** des Panels Nutzer, der auch die Konten auflistet, die gerade gesperrt sind, und eine Sperre aufhebt; derselbe Zähler läuft je Adresse, sodass auch das Raten über Konten hinweg gedrosselt ist – hinter einem Reverse Proxy ist diese Adresse für alle der Proxy, solange **Reverse Proxies vor dieser Website** ihn nicht nennt, und dann sperrt das Raten eines Fremden die ganze Seite aus. Konten und Rollen liegen in der `config.php`, die Zähler der Anmeldedrossel in `private/data/auth-tries.php` – nicht in der `config.php`, damit eine Folge von Fehlversuchen diese Datei nicht bei jedem Versuch neu schreibt.

Für den Betrieb:

- verwende `/_admin` ausschließlich über HTTPS;
- lege Redaktionskonten mit der Rolle **Editor** an und lege eine Rolle nur für einen konkreten Bedarf an;
- halte die Zahl der Entwicklerkonten klein;
- melde dich nach der Arbeit über **Abmelden** ab;
- schütze den Pfad zusätzlich über Webserver, VPN oder IP-Freigaben, wenn das Hosting es erlaubt.

Wenn die Konten selbst das Problem sind – das letzte Entwicklerpasswort vergessen, eine misslungene Wiederherstellung – ist die [Recovery-Seite](#recovery) der Weg zurück.

## Die Oberfläche

Die Leiste links trägt Marke, Konto, Zahnrad und Navigation; die Fläche rechts zeigt das gewählte Panel. Auf dem Telefon ist die Leiste eine Zeile am oberen Rand, und die Panels sind ein Menü darin: eine Auswahlliste mit den Gruppen als Abschnitten.

- **Gruppen.** Die Navigation ist in Inhalt, Struktur, Features und System mit je einer Überschrift unterteilt; eine Gruppe ohne Inhalt - Features, in einem Projekt ohne aktiviertes Feature - trägt gar keine Überschrift. Ein Konto, das nur eine Gruppe sieht, bekommt eine schlichte Liste. Eine Überschrift ist ein Knopf: Er schließt seine Gruppe und öffnet sie wieder, und der Browser merkt sich, welche Gruppen geschlossen sind. Die Gruppe des offenen Panels ist immer offen, und in der zusammengeklappten Leiste (siehe *Einklappen*) sind die Überschriften nur Trennlinien und nichts ist verborgen. Auf dem Telefon zeigt die Auswahlliste die Überschriften als ihre Abschnitte.
- **Tabs.** Ein Panel mit mehreren Bildschirmen trägt eine Tab-Leiste am Kopf seiner Fläche – Elemente und Elementtypen, Nutzer, Nutzerrollen, Anmeldeschutz und Recovery-Passwort – und kommt auf dem Tab zurück, auf dem du es verlassen hast. Jeder Tab ist ein eigener Bildschirm: seine Berechtigung, sein tiefer Link (`#roles`), sein Zustand.
- **Einklappen.** Der kleine Doppelpfeil neben der Marke klappt die Leiste zu einer Spalte aus Symbolen zusammen. Ein Panel, das die ganze Breite braucht – der Template Builder – klappt sie von sich aus ein und nimmt der Fläche die Lesebreiten-Grenze; klappst du sie von Hand wieder auf, bleibt sie auf jedem Panel offen, bis du sie wieder einklappst. Die Wahl liegt im Browser, nicht auf dem Server.
- **Tiefe Links.** Die Adresszeile folgt dir: `#elements/team/ada` ist das Element, das du gerade bearbeitest, `#types` der Tab Elementtypen. Ein Neuladen oder ein Lesezeichen öffnet genau diesen Stand, und die Zurück- und Vor-Knöpfe des Browsers gehen ihn ab: Jeder Schritt, den Du machst – ein Panel aus der Leiste, ein Tab, eine Zeile einer Liste, ein Zurück-Link, die Knöpfe für voriges und nächstes Element –, ist ein eigener Schritt, und was die Adresse nur mit dem Bildschirm in Einklang hält (die Adresse eines neuen Elements nach dem Speichern, die Pfeiltasten einer Tab-Leiste), ist keiner. Die Einträge der Leiste sind echte Links: Strg-Klick oder Mittelklick öffnet ein Panel in einem neuen Tab, und ein kopierter Link führt dorthin. Ein tiefer Link übersteht die Anmeldung – wer `#elements/team/ada` abgemeldet öffnet und sich anmeldet, landet im Element – und den Wechsel der Oberflächensprache.
- **Zahnrad.** Oberflächensprache und helles oder dunkles Farbschema. Die Sprache bestimmt auch die Inhaltssprache, mit der die Formulare unter Texte und Elemente öffnen.
- **Panelwechsel** setzt kein Panel zurück: Der Template Builder behält sein ungespeichertes Dokument, ein Elementformular seine ungespeicherten Werte, bis du speicherst oder die Seite verlässt. Das Verlassen eines der eigenen Formulare der Werkbank mit ungespeicherten Änderungen fragt vorher nach – **Speichern**, **Verwerfen** oder **Abbrechen** –, wohin der Ausgang auch führt: ein Zurück-Link, die Knöpfe für voriges und nächstes Element, eine Kopie eines Elements, das Abmelden, die Oberflächensprache, das Ein- oder Ausschalten oder Installieren eines Features, das Zurückspielen einer Sicherung, das Beenden der eigenen Sitzungen, eine Änderung, die das Formular neu zeichnet, oder die Zurück- und Vor-Knöpfe des Browsers. Auch der Browser fragt, wenn der Tab geschlossen oder neu geladen wird, und ein Formular mit ungespeicherten Eingaben zeigt an seinem Fuß *Ungespeicherte Änderungen*. Abbrechen bleibt, wo man ist; ein Speichern, das scheitert, bringt das Formular mit seinen Fehlern zurück auf den Bildschirm. Das Templates-Panel fragt beim Verlassen mit einer eigenen Abfrage (OK/Abbrechen) nach, und sein ungespeichertes Dokument gehört nicht zu den Fragen beim Abmelden, beim Wechsel der Oberflächensprache und beim Ein- oder Ausschalten eines Features.

- **Die Statuszeile.** Das Elementformular und die drei Formulare unter Nutzer (ein neues Konto, ein Konto, eine Rolle) sagen am Fuß, was aus dem letzten Speichern wurde – *Wird gespeichert …*, *Gespeichert um 09:41.*, *Ungespeicherte Änderungen*, sobald du wieder tippst, oder warum es gescheitert ist. Tippst du, während ein Speichern noch läuft, steht danach *Ungespeicherte Änderungen*, nicht *Gespeichert*. Eine Ablehnung nennt das Feld, um das es geht, und markiert es; mit der nächsten Eingabe in das Feld verschwindet die Markierung. Der Fehler steht in der Oberflächensprache und mit den Grenzen und Werten darin (*Das Passwort braucht mindestens 8 Zeichen.*) – nur ein Fehler, für den Nino keinen Satz hat, zeigt die eigene Meldung des Servers, mit der Statusnummer davor. Eine Anfrage, die den Server nie erreicht hat, sagt das, statt eine Nummer zu zeigen. Die übrigen Formulare zeigen weiter ein schlichtes *Gespeichert.*, und unter einer Breite von etwa 38 rem blendet eine untere Leiste ihre Statuszeile aus – ein Fehler dort ist erst zu sehen, wenn die Leiste wieder breit genug ist; unter dieser Breite zeigt die Leiste stattdessen *Ungespeicherte Änderungen*.
- **Eine Sitzung, die unter einem offenen Formular endet.** Eine Sitzung endet nach langer Ruhe oder nach einem Abmelden in einem anderen Tab. Die Seite bittet dann in einem Dialog über allem, was du getippt hast, um eine neue Anmeldung – nichts geht verloren. Bist du wieder angemeldet, wird gesendet, was du gerade tun wolltest. *Schließen* gibt dir die Seite ohne Anmeldung zurück: Die Anfragen, die gewartet haben, melden ihren Fehler im Panel, und die Formulare sind wieder bedienbar, sodass du Eingaben herauskopieren kannst, bevor du neu lädst. Hat sich in der Zwischenzeit in einem anderen Tab desselben Browsers ein anderes Konto angemeldet, bietet der Dialog nur das Neuladen an: Ein Formular, das für ein Konto ausgefüllt wurde, darf kein anderes speichern. Hat sich dasselbe Konto in der Zwischenzeit in einem anderen Tab dieses Browsers neu angemeldet und damit den Token der Seite ersetzt, heilt sich die Seite auf dieselbe Weise, ohne etwas zu verlangen.

Welches Panel auch offen ist – Speichern schreibt die Projektdateien sofort. Es gibt keinen Entwurfszustand und keinen eigenen Veröffentlichen-Schritt; prüfe danach das Frontend und jede betroffene Sprache.

## Inhalt

### Dashboard

Das **Dashboard** ist das erste Panel und fasst zusammen, was das Konto sehen darf: eine Kachel je Panel, das etwas zu zählen hat – Elemente je Typ, Anfragen, Nutzer, Elementtypen, Routen, noch fehlende Textschlüssel und Bildplätze, aktive Features, und was das Panel eines Features zählt, beim Newsletter des Katalogs die Abonnenten – dazu das Datum der letzten Sicherung, die jüngsten Log-Einträge und, solange die Website abgeschaltet ist, dass die Wartung an ist. Jede Kachel führt beim Klick zu dem Panel, für das sie zählt; das Dashboard selbst ändert nichts.

Über den Kacheln stehen **Hinweise** – was Aufmerksamkeit braucht, bis es erledigt ist. *Der Mailversand scheitert seit …* erscheint für jedes Konto, sobald ein Aufruf von `mail()` oder des registrierten Transports gescheitert ist – die Mail des Kontaktformulars an den Inhaber, eine Newsletter-Bestätigung, eine Test-Mail des Mailers – und verschwindet mit dem nächsten Aufruf, in dem jede Mail zugestellt wurde, eine Test-Mail des Mailers eingeschlossen; er behält das erste Datum des Fehlers und die Zahl der gescheiterten Aufrufe, keine Adresse. *Noch nicht übersetzte Texte (N) in einer Sprache* erscheint für ein Konto, das das Panel Texte öffnen darf, je Sprache einmal, und führt dorthin: Ein Text zählt, wenn die Muttersprache der Website zu einem Schlüssel einen hat und die andere Sprache keinen oder einen leeren; eine Sprache ohne Textdatei zählt jeden solchen Schlüssel. Versteckte Schlüssel und solche, die nicht von der Sprache abhängen, werden nicht gezählt.

### Elemente

Elemente sind wiederkehrende strukturierte Inhalte – Teammitglieder, Leistungen, Referenzen –, deren Felder ein Entwickler auf dem Tab **Elementtypen** dieses Panels festlegt. Redakteure und Entwickler pflegen die Einträge im selben Panel; der Tab gehört dem Entwickler.

1. Wähle einen Typ. Seine Einträge sind eine Tabelle: eine Spalte je Feld, das in eine Zelle passt (keine Bilder, Listen oder Rich Text), die Uri zuerst, die Zellen in der Übersetzung, auf die die Werkbank steht – leer, wo ein Eintrag noch keine hat. Suchen, nach einer Spalte sortieren, blättern.
2. Öffne einen Eintrag (seine Zeile) oder wähle **Neues Element**. Ein Typ, der seine Elemente nummeriert, nennt die Uri, die er gleich anlegt; jeder andere fragt nach einem Slug aus Kleinbuchstaben, Ziffern, Binde- und Unterstrichen.
3. Fülle die globalen Felder einmal und die übersetzten je Sprache – der Sprachumschalter sitzt im Formular, ungespeicherte Werte überstehen den Wechsel. Ein Feld heißt nach dem eigenen Fill seines Typs, wenn es einen gibt (`/_admin/elements/field/<typ>/<key>`, wie das Feature Social ihn mitbringt), sonst nach dem Wort, das die Workbench für den Schlüssel hat (*title* ist „Titel“), sonst nach dem Schlüssel selbst mit großem Anfangsbuchstaben (`price_default` heißt „Price_default“).
4. **Speichern**. Ein Pflichtfeld trägt ein Sternchen. Ein Speichern, das ein leeres findet, hält an, markiert jedes solche Feld mit einem Satz darunter, setzt den Fokus auf das erste und nennt im Sprachumschalter die Sprachen mit offenen Feldern (*de_DE – 2 offen*); gesendet wird erst, wenn sie ausgefüllt sind. Eine Liste von Texten wird als Zeilen bearbeitet – in eine Zeile tippen, sie verschieben, entfernen, eine hinzufügen –, jedes andere Listen- oder Objektfeld wird als JSON eingegeben, wo Text, der kein gültiges JSON ist, das Speichern auf dieselbe Weise anhält und im Feld stehen bleibt. Geschrieben und geprüft werden nur die Sprachen, die seit dem Öffnen des Formulars bearbeitet wurden, und wenn keine bearbeitet wurde, die Sprache auf dem Bildschirm. Ein Bildfeld wird erst verfügbar, nachdem ein neues Element einmal gespeichert wurde; Nino verarbeitet den Upload dann auf die Maße, die der Typ vorgibt.

Ein Feld, das auf andere Elemente verweist, ist eine Auswahlliste oder, wo der Typ mehrere erlaubt, eine geordnete Liste mit Suchfeld, Verschiebe-Knöpfen und einem Maximum, das der Typ setzen kann. Ein gelöschtes Ziel wird als *fehlend* angezeigt statt stillschweigend entfernt.

**Rohdaten**, am Fuß des Formulars, zeigt die Buckets, in denen der Eintrag liegt: `*` für die globalen Felder und einer je Sprache. Die Ansicht ist nur lesend und für Diagnose und Migrationen gedacht.

**‹ Vorheriges Element** und **Nächstes Element ›**, rechts in der Kontextleiste des Formulars, gehen die Einträge des Typs in der Reihenfolge der Liste durch, ohne zu ihr zurückzukehren. Wie der Zurück-Link fragen sie vorher nach, wenn das Formular ungespeicherte Änderungen enthält.

**Duplizieren** übernimmt alle Werte des offenen Eintrags in ein neues Element – alle Sprachen, alle Felder, außer der Uri und den Bildern, die zu dem Eintrag gehören, für den sie hochgeladen wurden. Geschrieben ist noch nichts: Gib der Kopie eine Uri und speichere sie.

Ein Bildfeld lädt für sich hoch, sobald die Datei gewählt ist, und sagt, wenn das Bild kleiner als das Soll-Maß des Feldes ist und hochskaliert wurde. **Bild entfernen** nimmt es sofort aus dem gespeicherten Element: Das Feld wird geleert und die Datei gelöscht, die der Upload dieses Eintrags geschrieben hat. Ein Dateiname, der von Hand geschrieben wurde, bleibt auf der Platte.

**Löschen** entfernt den Eintrag in jeder Sprache und die Bilder, die nur seine Bildfelder nutzten. Nur eine Sicherung bringt ihn zurück.

### Texte

**Texte** enthält die einzelnen Textfills der Seite – Überschriften, Beschreibungen, Kontaktdaten, Beschriftungen. Die Liste hat zwei Blöcke. **Seiten** hat eine Zeile je Seite, benannt nach ihrer Route in der Sprache auf dem Bildschirm (eine Seite mit Unterseiten zeigt sie eingerückt darunter). **Allgemein** hat die eigenen Texte des Projekts (Unternehmen, Website, E-Mail-Versand), die allgemeinen Wörter, Bausteine wie die Kopf- und Fußrahmen und die Mails, die Module, die Features, das System – die Namen der Sprachen und die Seitenangaben von Seiten ohne eigene Route – und jeden Schlüssel, den ein Projekt sich ausgedacht hat. Öffne eine Zeile, bearbeite die Texte in der gewählten Sprache und **Speichern**.

Eine Zeile ist ein Formular aus **Abschnitten**, einem je Teil ihrer Schlüssel – *Einleitung*, *Eintrag 1*, *Banner* – in der Reihenfolge, in der das Template sie zeigt, und die Felder darin tragen Namen in der Sprache der Oberfläche: Die Workbench kennt die Wörter, aus denen Schlüssel gebaut sind, und nennt *title* „Titel“, *item-3* „Eintrag 3“ und *unit-day* „Einheit · Tag“; ein Wort, das sie nicht kennt, steht so da, wie es geschrieben ist, mit großem Anfangsbuchstaben (*Tagline*). Der Schlüssel selbst steht klein unter jedem Feld. Ein Text, der in jeder Sprache gleich ist – der Name eines Unternehmens –, trägt das Kennzeichen **Alle Sprachen** und steht in seinem Abschnitt zuerst. Das Formular einer Seite beginnt mit ihren **Seitenangaben**, einmal je Route, die das Template hat: der Name im Menü, der Seitentitel und die Beschreibung für Suchmaschinen – die Texte der Seite und ihre Angaben werden also zusammen in einer Anfrage gespeichert. Der Sprachumschalter in der Leiste oben ändert die Texte der Sprache und sonst nichts.

Die **Suche** über der Liste findet einen Text nach dem, was er sagt – in jeder Sprache –, nach den Namen, unter denen er gezeigt wird, oder nach seinem Schlüssel. Jedes Wort muss vorkommen, Akzente und Groß- und Kleinschreibung spielen keine Rolle, und ein Wort, das mit einem Schrägstrich beginnt, wird nur im Schlüssel gesucht: `/template/page-home` sind alle Texte der Startseite, `/common/` die allgemeinen Wörter. Die Treffer ersetzen die Liste, fünfzig auf einmal und **Weitere anzeigen** für den Rest: ein Pfad (*Leistungen › Eintrag 1 › Titel*), der Ausschnitt des Textes mit den markierten Wörtern und der Schlüssel. Ein Klick öffnet die Zeile an diesem Feld und setzt den Cursor hinein; stehen die Wörter in einer anderen Sprache als der auf dem Bildschirm, schaltet das Kürzel der Sprache neben dem Treffer um – von selbst wechselt die Sprache nie. In der Trefferliste wird nichts bearbeitet. **Leer in** einer Sprache zeigt die Texte, zu denen diese Sprache noch keinen hat. Die Adresse nennt, was geöffnet ist – `#text/template/page-home/intro/title` ist dieses Feld dieser Seite, `#text/template/page-home` die Seite –, ein Lesezeichen oder ein Link an eine Kollegin führt also dorthin.

Formatierte Felder bieten Fett, Kursiv, Hervorhebung, Inline-Code und Links; Zeichenzähler zeigen die vom Entwickler vorgesehene Länge. **Strg** oder **Cmd** mit **B** und **I** machen fett und kursiv, wie die beiden Knöpfe; **U** tut nichts, denn Unterstreichen gehört nicht zu den Formaten.

Was ein Feld darüber hinaus enthalten darf, ist sein **Format**, das der Entwickler im Tab **Textschlüssel** festlegt (bei einem Elementfeld im Typ): *Formatiert* ist eine Zeile mit den genannten Tags, *Zeilenumbrüche* macht die Eingabetaste zum Zeilenumbruch, und *Absätze und Listen* bringt Absätze sowie Aufzählungen und nummerierte Listen dazu. Dort beginnt die **Eingabetaste** einen neuen Absatz oder Listenpunkt, **Umschalt+Eingabe** ist ein Zeilenumbruch darin; die **Eingabetaste** in einem leeren Listenpunkt beendet die Liste; die **Rücktaste** am Anfang eines Absatzes oder Punktes verbindet ihn mit dem davor. Eingefügter und hineingezogener Text ist immer reiner Text: In den beiden weiteren Formaten wird ein Zeilenwechsel zum Umbruch, und in *Absätze und Listen* beginnt eine Leerzeile einen neuen Absatz. Der Editor teilt, verbindet und wandelt Listen selbst, deshalb macht **Strg+Z** das Tippen rückgängig, diese Schritte aber nicht – prüfe eine größere Änderung vor dem Speichern.

Ein leerer Text gilt als nicht geschrieben: Ein Schlüssel, zu dem die Muttersprache der Website einen Text hat und eine andere Sprache keinen oder einen leeren Wert, ist die Arbeit, die das Dashboard als *nicht übersetzt* in dieser Sprache zählt – nichts wird von selbst eingesetzt, und keine Sprache greift auf eine andere zurück. Ein Schlüssel, der hier nicht erscheint, ist entweder für die Bearbeitung ausgeblendet oder technisch, und die eigenen Wörter der Workbench (`/_admin/...`) sind kein Text der Website und erscheinen nie. Eine Rolle, die nur einen Teil der Texte schreiben darf, sieht den Rest schreibgeschützt: Das Recht auf ein Template deckt dessen Seitenangaben nicht ab, denn sie sind Schlüssel des Systems (siehe [Feinere Rechte innerhalb eines Panels](#feinere-rechte-innerhalb-eines-panels)). Schlüssel anlegen, umbenennen und löschen ist Sache des Tabs **Textschlüssel**; die projektweite Übersetzungsübergabe ist der Tab **Übersetzungen** des Panels Sprache.

### Bilder

**Bilder** listet die Bildplätze, die der Entwickler auf dem Tab **Bildplätze** definiert hat, gruppiert nach Uri-Bereich – die Plätze eines Templates nach `template/<kategorie>`, die anderen nach ihrem ersten Segment –, mit Beschriftung, Shortcode und Zielmaßen. Wähle eine Datei für einen Platz und starte den Upload; Nino prüft und verarbeitet sie, weist eine ungültige oder zu große Datei ab und ersetzt das aktuelle Bild sofort.

Ein Foto behält die Ausrichtung, die seine Kamera festgehalten hat: Die EXIF-Ausrichtung des JPEG wird aus seinem Kopf gelesen und angewendet, bevor das Bild auf das Ziel zugeschnitten wird, ein aufrechtes Hochformat wird also nicht auf der Seite liegend gespeichert. Bilder, die vorher hochgeladen wurden, bleiben, wie sie gespeichert sind, und müssen neu hochgeladen werden.

Ein Bild, das kleiner ist als das Soll-Maß des Platzes, wird trotzdem gespeichert – und die Zeile unter dem Feld sagt es, mit seiner Größe (*Gespeichert – aber Dein Bild ist nur 300 × 150 px groß, kleiner als die Soll-Maße, und wurde hochskaliert. Es kann unscharf wirken.*). Gemeint ist die Größe, in der das Bild angezeigt wird; ein Foto, das die Kamera auf der Seite liegend gespeichert hat, wird also aufrecht gemessen. **Bild entfernen** nimmt das Bild nach einer Rückfrage aus dem Platz: Die Datei wird gelöscht, und die Website zeigt dort kein Bild, bis ein neues hochgeladen wird; der Platz selbst bleibt. Erst wird der Eintrag geschrieben, dann die Datei gelöscht, und nur eine Datei, die dem Platz gehört, wird gelöscht – ein Dateiname, den jemand von Hand in die `config.php` geschrieben hat (ein Bild, das ein Template wörtlich einbindet), wird aus dem Platz entfernt und bleibt auf der Platte.

Unter jedem Platz steht, wo er verwendet wird – *Verwendet auf: Start (/)* –, gelesen aus den Templates, die die Seiten der Website rendern, `[template]`-Einbindungen eingeschlossen. Ein Platz, den keine Seite zeigt, sagt es (*Nirgends eingebunden*): Ein Bild, das dort hochgeladen wird, erscheint nicht auf der Website. Darunter steht je Sprache ein Feld **Alt-Text**: Er beschreibt, was das Bild zeigt, für Menschen, die es nicht sehen, und `[image]` schreibt ihn als `alt`-Attribut in der Sprache der Seite. Leer heißt dekorativ (`alt=""`), es sei denn, das Template gibt einen eigenen Alt-Text vor. Die Reihenfolge ist: der gespeicherte Text der Sprache, dann das `alt="…"` des Templates, dann keiner; die Beschriftung des Platzes wird nie als Alt-Text benutzt. Ein Alt-Text wird mit dem Platz in der `config.php` gespeichert, nicht in einer Datei `text/<Sprache>.php`, und ist deshalb nicht Teil eines Übersetzungs-Exports oder -Imports.

Auch das Logo der Website ist so ein Platz, `/logo`: Header, Navigation, die Open-Graph- und Twitter-Tags und die Mails des Form-Moduls zeigen, was dort hochgeladen ist, und nichts – kein kaputtes Bild, kein leeres Tag –, solange nichts hochgeladen ist. Ein Upload wird auf die Zielform des Platzes zugeschnitten (zunächst 500 × 100); ein Logo anderer Form braucht zuerst ein geändertes Maß des Platzes im Tab **Bildplätze**.

Jedes Upload-Feld sagt, was der Server annimmt, noch bevor du wählst: *Bis zu 2 MB und 20 Megapixel.* Die Größe ist die kleinste von drei Grenzen – Ninos eigene 8 MB, PHPs `upload_max_filesize` und PHPs `post_max_size` (0 heißt dort: keine) – und die Pixel sind Ninos 20 Megapixel: Ein Bild zu dekodieren braucht etwa vier Byte je Pixel, und das ist, was der Speicher eines günstigen Hosters hergibt. Eine Datei über der Grenze lehnt schon der Browser ab, mit Begründung, bevor sie gesendet wird; eine Datei, die der Browser nicht beurteilen kann (ein Format, das er nicht dekodiert), überlässt er dem Server, der den Grund ebenfalls nennt – größer als 8 MB, mehr als 20 Megapixel, kein JPEG, PNG, WebP oder GIF, oder größer, als PHP durchlässt (*PHP-Upload-Limit: 2 MB*). Das Letzte ist eine Einstellung des Servers, nicht von Nino: Erhöhe `upload_max_filesize` und `post_max_size` in der `php.ini` oder frage den Hoster.

### Anfragen

**Anfragen** listet die gespeicherten Einträge aller Formulare, solange das Form-Modul aktiv ist, die jüngste zuerst. Das Panel kennt keine eigenen Feldnamen: Eine Karte zeigt das Datum, aus welchem Formular die Anfrage kam, die Adresse zum Antworten als Mailto-Link und jeden Wert unter dem Label, unter dem er erhoben wurde – ein Projekt mit eigenen Formularen (siehe [Formulare](development.de.md#formulare)) sieht deren Felder hier also ohne jede Konfiguration. Ein Wert, dessen Feld das Formular inzwischen verloren hat, erscheint weiterhin, unter seinem eigenen Namen: Ein Formular, das ein Feld abgelegt hat, darf die Antworten nicht mitnehmen.

Eine Auswahl schränkt auf ein Formular ein, ein Suchfeld durchsucht Werte und Labels, beides über der Liste; der Export schreibt, was die beiden übrig gelassen haben – ein Export bei ausgewähltem Formular ist also der dieses Formulars. Eine Karte klappt zu ihrem vollen Text auf und bietet dann **Löschen** für diese eine Anfrage – die Bitte, die ein Mensch zu seiner eigenen Anfrage äußert, und der einzige Schreibvorgang des Panels. Ein Eintrag aus der Zeit, bevor Anfragen eine ID trugen, bietet keines, weil es nichts gibt, womit er sich über eine Löschung hinweg eindeutig ansprechen ließe. Das Anwählen einer Adresse öffnet dein Mailprogramm; Nino antwortet nicht von sich aus.

Wie lange Einträge bleiben und ob sie überhaupt geschrieben werden, sind `/nino/form/retention` und `/nino/form/store` in der `config.php` – das Forms-Feature aus dem Katalog bietet beides in seinem eigenen Panel an.

### Newsletter

**Newsletter** gehört zum Newsletter-Feature aus dem Katalog [dapeio/nino-features](https://github.com/dapeio/nino-features) und ist da, solange das Feature nach `features/` kopiert und im Panel Features eingeschaltet ist. Die eigene README des Features dokumentiert das Panel: die Liste der Abonnements, ihre Exporte und die Löschung, die eine ältere Sicherung nicht rückgängig macht.

### Log

**Log** zeigt das Aktivitätsprotokoll: Anmeldungen und jede erfolgreiche Änderung, mit dem Konto, das sie vorgenommen hat. Einträge bleiben 14 Tage erhalten und sind nur lesend. Das ist nicht das PHP-Fehlerprotokoll; das schaltet **Konfiguration**.

## Struktur

### Templates

**Templates** gehört zum Template-Baukasten-Feature aus dem Katalog [dapeio/nino-features](https://github.com/dapeio/nino-features) und ist da, solange das Feature nach `features/` kopiert und im Panel Features eingeschaltet ist. Es ist ein Workspace-Panel: Die Leiste klappt ein, und seine drei Spalten stehen nebeneinander. Das [Handbuch](https://github.com/dapeio/nino-features/blob/main/features/Templates/docs/templates.de.md) des Features dokumentiert das Panel – was es zusammensetzt, seine Regeln zur Quelltextsicherheit und den Manifest-Vertrag der Preset-Bibliothek.

### Elementtypen

Elementtypen beschreiben wiederkehrende Inhalte. Jeder Typ ist eine Datei unter `elements/`; seine Einträge werden unter **Elemente** gepflegt – dem Panel, dessen Tab dieser Bildschirm ist, erreichbar über die Leiste am Kopf seiner Fläche oder mit `#types`.

1. Wähle **Neuer Typ**.
2. Gib ihm eine technische Uri – zuerst ein Kleinbuchstabe, dann Kleinbuchstaben, Ziffern, Binde- und Unterstriche, etwa `team` oder `service_items`. Sie wird zu `elements/<uri>.php` und lässt sich danach nicht ändern.
3. Gib ihm einen Titel für das Panel Elemente.
4. Füge die Felder mit **Feld hinzufügen** hinzu und speichere. Der Schlüssel eines Feldes wird aus den Wörtern gewählt, die Felder am häufigsten sind – *Titel*, *Untertitel*, *Text*, *Beschreibung*, *Bild*, *Alternativtext*, *Bildunterschrift*, *Link*, *Beschriftung*, *Name*, *E-Mail*, *Telefon*, *Adresse*, *Datum*, *Autor*, *Preis*, *Symbol* –, und die Redakteure sehen ihn unter diesem Wort; **Eigener Schlüssel …** blendet das Textfeld für jeden anderen Schlüssel ein, mit den Prüfungen, die es immer hatte.

| Feldtyp | Geeignet für |
|---|---|
| `string` | ein- oder mehrzeiliger Text; wahlweise Rich Text oder feste Auswahl |
| `integer` | ganze Zahlen |
| `double` | Dezimalzahlen |
| `boolean` | Ja/Nein |
| `array` | eine Liste von Texten, als Zeilen bearbeitet; jede andere Liste oder jeder strukturierte Wert wird als JSON eingegeben, und ungültiges JSON hält das Speichern am Feld an |
| `date` | ein Datum |
| `datetime` | Datum und Uhrzeit |
| `image` | ein Bild mit festen Zielmaßen |
| `element` | ein Verweis auf ein Element eines anderen Typs |

Je nach Typ ist ein Feld *pro Übersetzung* oder global, Pflicht oder optional, Rich Text – mit **Absätzen und Listen**, wenn sein Text mehr als eine Zeile ist, oder bei einem einfachen Textfeld mit **Zeilenumbrüche behalten**, damit die Zeilenwechsel seines Textes als `<br>` auf die Seite kommen –, auf feste Werte beschränkt, mit Maßen, Einheit oder Suffix versehen oder mit einer Zeilenzahl, mit der sein Eingabefeld öffnet. Ein `image`-Feld kann ein **Alt-Text-Feld** nennen: ein einfaches `string`-Feld desselben Typs, das pro Übersetzung geschrieben wird. Beide Formulare sagen es dann, der Alt-Text des Bildes wird dort je Sprache geschrieben, und `alt="[[<Feld>]]"` im Template ist für ein Element, das noch keinen Text hat, leer – ein dekoratives Bild. Ein Verweis auf ein globales Feld, ein Rich-Text-Feld, das Bild selbst oder ein Feld, das es nicht gibt, wird beim Speichern des Typs verworfen. Ein `element`-Feld nennt den Typ, auf den es verweist, und darf mehrere Elemente halten, geordnet, mit **Max. Elemente** als Obergrenze (`0` für keine); der Kernel setzt diese Grenze beim Speichern durch. Ein gelöschtes Ziel bleibt als *fehlend* stehen, und Verweise sind nicht Teil eines Übersetzungs-Exports.

Der Wechsel eines Felds zwischen global und pro Übersetzung migriert die vorhandenen Werte; prüfe das Ergebnis in jeder Sprache. Das Speichern eines Typs löscht keine Einträge, ein entferntes Feld verschwindet aber aus dem Formular.

**Ein Feld umbenennen** heißt, seinen Namen in der Zeile des Feldes zu ändern: Die Zeile sagt, dass die gespeicherten Werte mit dem Speichern des Typs unter den neuen Namen umziehen, und das Speichern fragt vorher. Jeder Wert jedes Eintrags zieht um – die globalen, jede Sprache, auch eine, die das Projekt nicht mehr anbietet, und die Vorgaben, mit denen ein neues Element beginnt –, ein Umbenennen ist also nicht dasselbe wie Entfernen und neu Anlegen. Mehrere Umbenennungen in einem Speichern werden gegen den Typ gelesen, wie er war; zwei Felder können also die Namen tauschen. Abgelehnt wird mit dem Namen des Feldes, wenn zwei Felder eins würden, wenn der neue Name noch einem Feld gehört, das nicht selbst umbenannt wird, wenn unter ihm noch die Werte eines früher entfernten Feldes liegen (sie tauchten in Einträgen auf, die sie nie hatten) und bei einem `image`-Feld, das den Namen eines anderen übernähme – die Datei eines Uploads trägt den Feldnamen, und die Bilder überschrieben einander. Ein Bild zieht mit seinem Feld um: Sein Wert ist der Dateiname, und die Datei behält ihn – gib einem neuen Bildfeld deshalb nicht den Namen, den ein Bildfeld vor seiner Umbenennung hatte, denn seine Uploads hießen gleich und überschrieben die alte Datei. Das **Alt-Text-Feld** eines Bildes zieht bei der Umbenennung des Feldes, das es nennt, mit. Was eine Umbenennung nicht ändert, meldet sie nach dem Speichern: Templates, die weiter `[[<alter Name>]]` füllen (sie zeigten es, wie es dasteht), Rollen mit dem Recht `/_admin/elements/<Typ>/update/<alter Name>` und ein Bezeichnungstext, der für das Feld hinterlegt ist. Das änderst Du von Hand.

**Typ duplizieren**, oberhalb des Löschens, legt aus dem Formular, so wie es gerade steht, einen neuen Typ unter einer neuen Uri und einem optionalen Titel an – seine Felder und ob er seine Elemente nummeriert, gespeichert oder nicht –, genau so, als hättest Du sie in einem neuen Typ eingegeben, aber ohne Elemente. Was nicht im Formular steht, bleibt beim Original: ein Standardwert oder ein Callback, die von Hand in die Typdatei geschrieben wurden, und die Werte, mit denen ein neues Element beginnt. Ein Typ, der seine Elemente nummeriert, beginnt wieder bei `00001`. Das Formular fragt vorher nach ungespeicherten Eingaben und öffnet die Kopie, wenn sie angelegt ist. Ein Elementfeld, das auf das Original verweist, verweist weiter darauf, und eine Rolle, deren Rechte auf das Original beschränkt sind, hat auf der Kopie keine.

**Elemente dieses Typs nummerieren**, in der Gruppe *Element URIs* des Typs, ersetzt den Slug durch einen Zähler für Einträge, die keinen Namen haben, der in eine URL gehört – ein Galeriebild, eine Preiszeile: `/gallery/00001`, `/gallery/00002`. Nummern werden nie wiederverwendet, ein späteres Einschalten ist sicher, und der Zähler liegt in der Typdatei unter derselben Sperre wie das Element.

**Elementtyp löschen**, am Fuß des Typformulars, entfernt die Typdatei, alle Elemente darin und die Bilder, die daran hängen. Es ist die einzige Schaltfläche des Panels, die Inhalte zerstört, und deshalb nur erreichbar, wenn die Uri des Typs daneben eingetippt wird – ein einzelner Klick kommt nicht dorthin. Ein Typ, auf den das `element`-Feld eines anderen Typs verweist, wird abgelehnt und das Feld benannt: Sein Löschen ließe den Verweis ins Leere zeigen. Rückgängig geht das nicht; zurück führt nur ein Backup.

### Routen

**Routen** verwaltet die Seitenrouten – die vom Assistenten angelegten, die hier ergänzten und die von Hand in die `config.php` geschriebenen; sie sind eine Liste, bei jeder Anfrage aus `/nino/http/routes` und den Textschlüsseln `/_nino/webpage<uri>/*` abgeleitet.

Eine Seite hat zwei Uris: Die **Element URI** ist ihre stabile Identität, der Anker ihrer Seitentexte wie `/_nino/webpage<uri>/title`; ihr Speichern schreibt auch `/_nino/webpage<uri>/uri`, den erreichbaren Pfad, sodass ein Template mit `[[/_nino/webpage/site-contact/uri]]` verlinkt statt einen Pfad zu wiederholen. Die **HTTP URI** ist dieser erreichbare Pfad. Eine Seite kann intern `/about` heißen und im Browser unter `/ueber-uns` liegen.

Eine neue Seite beginnt mit einem leeren Formular: keine URIs, keine Texte. Vorgeschlagen wird nur das Template – `page-blank`, wenn `templates/` es enthält, sonst nichts, sodass ein Template gewählt werden muss; nie ein anderes Seiten-Template, das eine Kopie einer fertigen Seite unter dem neuen Pfad veröffentlichen würde. Eine Seite braucht beide URIs, ein Template sowie **Name** und **Titel** in jeder aktiven Sprache; die Beschreibung ist optional und bleibt leer, wenn sie leer gelassen wird. Das Formular prüft das selbst und markiert, was fehlt, und der Server weist ein Speichern ohne diese Angaben ab und schreibt nichts – ein fehlender Schlüssel erschiene als rohes `[[/_nino/webpage<uri>/name]]` auf der Seite und in den Menüs. Der Webpages-Schritt des Assistenten bleibt, wie er ist.

Dazu gehören ein HTTP-Statuscode und ein Kästchen je in `/nino/html/navs` registrierter Navigation. Die Zugehörigkeit liegt auf der Route als `'navs' => [ 'main' => 1, ... ]`, der Wert eine Priorität; eine hier ergänzte Zugehörigkeit beginnt hinter allem, was schon im Menü ist, und eine von Hand gesetzte Priorität wird nie zurückgesetzt. Die Pfeile tauschen zwei Seitenrouten in der `config.php`.

Die Liste zeigt jede Seite mit ihrem Namen – in der Inhaltssprache, die zuletzt in Elemente oder Text gewählt wurde (davor in der Muttersprache), sonst in der ersten Sprache, die einen hat – über ihrem Pfad; **↗** öffnet die Seite in einem neuen Tab.

`/_admin` ist reserviert und kann keine öffentliche Seite sein. Eine Route, die ihr Template zur Laufzeit wählt, zeigt ihren vorhandenen Body und behält ihn. Das Löschen einer Seite entfernt ihre Route und fragt vorher. Die Frage nennt, was bleibt: die Texte der Seite – `/_nino/webpage<uri>/name|title|description` in den Sprachen, die einen Wert haben, und `/_nino/webpage<uri>/uri` – und ihre Templatedatei, mit der Zahl der weiteren Routen, die sie nutzen. Bei einer Route, die ihr Template zur Laufzeit wählt, wird stattdessen ihr Body genannt.

**Feature-Routen.** Eine Seite, die ein Feature selbst ausliefert – der Blog von Posts, die Newsletter-Seite, Hellos `/hello` –, steht in keiner `config.php`; sie fehlt deshalb in der Liste oben und lässt sich hier weder verschieben noch löschen. Unter den Seiten führt das Panel sie als **Feature-Routen**: eine Zeile je Seite, mit dem dafür geschriebenen Namen über dem Pfad, den das Feature ihr gegeben hat – ein Platzhalter wie `/blog/*` steht für die Seiten darunter. Ihr Formular zeigt diesen Pfad schreibgeschützt und von der Route sonst nichts: nur **Name**, **Titel** und **Beschreibung** in jeder aktiven Sprache, mit denselben Regeln wie bei einer Seite, gespeichert unter `/_nino/webpage<uri>/name`, `…/title` und `…/description`. Mehr wird nicht geschrieben: keine Route, kein `uri`-Schlüssel – das Feature bestimmt den Pfad, und ein gespeicherter wäre falsch, sobald es ihn ändert – und nichts auf der Blacklist. Solange kein Name geschrieben ist, bleibt so eine Seite aus jedem Menü, und der Seitenkopf zeigt den rohen Fill.

### Navigationen

**Navigationen** ist die andere Hälfte dessen, was Routen bearbeitet: ein Menü nach dem anderen, in seiner Reihenfolge. Es gehört zum Navigation-Modul.

Ein geöffnetes Menü zeigt seine Einträge, wie sie gerendert werden, mit ↑ / ↓ zum Verschieben, × zum Herausnehmen (die Route bleibt) und einer Auswahl, die jede `GET`-Route am Ende anfügt. Die Auswahl beginnt bei einer leeren Wahl, und **Hinzufügen** wartet auf eine Route. Das alles ändert eine Arbeitskopie im Browser und schreibt nichts: **Speichern** schreibt die ganze Reihenfolge in einer Anfrage, unter der Sperre auf der `config.php`, und die Statuszeile meldet bis dahin ungespeicherte Änderungen. Eine Route, die weggelassen wird, verliert ihre Zugehörigkeit; Prioritäten bleiben dicht, `1..n` je Menü. Der Zurück-Link – wie die Abmeldung und die Oberflächensprache – fragt vor dem Verwerfen ungespeicherter Änderungen, und beim erneuten Anzeigen des Panels werden die Routen neu gelesen, eine geänderte Arbeitskopie bleibt aber unberührt. Eine Route ohne `/_nino/webpage<uri>/name` ist markiert, weil `[navigation]` sie überspringt statt einen leeren Link zu rendern.

Auch eine Route, die nur zur Laufzeit existiert – das `/blog` eines Features, das keinen Eintrag in der `config.php` hat –, lässt sich wählen. Ihre Zugehörigkeit steht unter `/nino/html/navroutes`, z. B. `'GET://blog' => [ 'main' => 3 ]`, und `[navigation]` liest sie für eine Route, die gerade existiert: Ein ausgeschaltetes Feature nimmt seinen Menüeintrag mit, und das nächste Speichern dieses Menüs entfernt, was dafür gespeichert war. Von den Laufzeitrouten werden Wildcard-Routen (`/blog/*`) und die Workbench nicht angeboten, technische Routen – `robots.txt`, `/.search` – schon.

**Anlegen** registriert die Id eines Menüs; **Umbenennen** prüft, dass die Id in der Registry und auf jeder Route frei ist, und folgt ihr dann in beide; **Löschen** entfernt es überall. Keines davon rührt das Argument `[navigation nav="…"]` in deinen Templates an – das ist Inhalt, den du selbst aktualisierst.

### Textschlüssel

**Textschlüssel**, ein Tab des Panels Texte, ist dessen technische Seite: jeder Schlüssel, auch die ausgeblendeten und die eigenen Wörter der Workbench, in denselben Zeilen wie das Panel Texte (ohne die Seiten, die nur dieses Panel kennt), mit

- derselben **Suche**, die auch ausgeblendete Schlüssel findet, und einem Filter **Nur ausgeblendete**; jeder Schlüssel zeigt unter seinem Schlüssel den Namen, unter dem ihn das Panel Texte zeigt – *Eintrag 1 › Titel* –, und ein Treffer oder die Adresse `#keys/template/page-home/intro/title` öffnet die Zeile an diesem Schlüssel;
- globaler oder sprachabhängiger Speicherung, umschaltbar mit Migration der vorhandenen Werte;
- **neuen Schlüsseln** und **Umbenennen**, wieder mit Migration – nur zu Schlüsseln, die der Schlüssel-Grammatik `/<namensraum>/<kategorie>/<teil>/<name>` folgen (siehe [Entwickler-Handbuch](development.de.md#die-schlüssel-grammatik)). Das Formular hat ein Feld je Segment und prüft es beim Tippen: Ein Segment mit Großbuchstaben oder Punkt wird markiert, und der Knopf bleibt aus; unter den Feldern stehen der Schlüssel, den sie ergeben, und der Name, unter dem er gezeigt wird. Der Namensraum ist eine Auswahl, die zunächst `/project` anbietet; **Entsperren** fügt `/template`, `/feature` und `/module` hinzu, deren Schlüssel normalerweise zu einem Template, einem Feature oder einem Modul gehören und von dessen Install-Einheit geliefert werden – `/_nino` und `/_admin` werden nie angeboten. Die Kategorie ist eine Auswahl dessen, was es gibt (die Templates des Projekts und `common`, die installierten Features, die Module des Kernels) und für `/project` ein Feld, das `company`, `website`, `mail` und die Kategorien vorschlägt, die Schlüssel schon nutzen. **Umbenennen** an einem Schlüssel öffnet dasselbe Formular mit seinen Segmenten, ein Schlüssel eines anderen Namensraums als `/project` schon entsperrt; ein Schlüssel, der keiner Grammatik folgt, beginnt bei `/project` mit leeren Feldern. Der Server entscheidet die Form noch einmal, was auch immer das Formular schickte: Er weist jede andere mit einem Satz zurück, der sie nennt, und benennt keinen Schlüssel unter `/_nino/` oder `/_admin/` um, der nach dem heißt, was der lesende Code verlangt – diese Schlüssel haben kein **Umbenennen**, nur ihre Werte werden bearbeitet. Schlüssel, die ein Projekt schon hat, bleiben, wie sie sind, und bearbeitbar – gespeichert, ausgeblendet, gelöscht, zu einem umbenannt, der der Grammatik folgt;
- dem Ausblenden eines Schlüssels aus dem Panel Texte;
- seinem **Format** und **Limit**;
- dem Löschen eines Schlüssels aus jeder Sprache – prüfe vorher seine Verwendung in Templates, Mails und Modulen.

**Format** sagt, was der Wert enthalten darf, und damit, welchen Editor das Panel Texte anbietet: *Reiner Text* (ein Textfeld), *Formatiert* (Fett, Kursiv, Hervorhebung, Code, Links), *Zeilenumbrüche* (dasselbe, mit der Eingabetaste als Umbruch) und *Absätze und Listen*. *Automatisch* – die Vorgabe – liest es aus den gespeicherten Werten; ein Schlüssel, der ein `<br>` enthält, wie der Gruß am Ende einer Mail, wird also als Zeilenumbrüche bearbeitet und verliert es beim nächsten Speichern nicht mehr. **Limit** ist die Zeichenzahl, bis zu der der Zähler zählt; leer heißt automatisch, ein wenig über dem längsten Text, den der Schlüssel enthält. Beides wird mit **Übernehmen** angewendet, das sie zusammen mit den beiden Kästchen des Schlüssels speichert und dann zur Kategorie zurückkehrt. Sie liegen in `text/meta.php`, ein Eintrag je Schlüssel, und ziehen beim Umbenennen mit. Ein Format, das weniger fasst als das vorige – Absätze zu reinem Text, Zeilenumbrüche zu formatiert –, wandelt jeden gespeicherten Text des Schlüssels in jeder Sprache um und fragt vorher, mit der Angabe, was aus dem Text wird; Zeilenumbrüche und das Ende von Absätzen und Punkten bleiben in reinem Text als Zeilen und werden in *Formatiert* zu einem Leerzeichen. Das Erweitern wandelt andersherum: In reinem Text wird jeder Zeilenwechsel zu einem Umbruch. Ein Limit unter dem längsten Text des Schlüssels wird abgelehnt. Ungespeicherte Änderungen der geöffneten Kategorie werden vor dem Neuladen erfragt. Bedenke, wo ein Schlüssel verwendet wird: Ein Umbruch im Wert ist an jeder Stelle ein `<br>`, an der der Fill steht, ein Schlüssel in *Zeilenumbrüche* gehört also nicht in ein Attribut oder einen `[json …]`-Shortcode, der reinen Text erwartet. Einen Schlüssel in *Absätze und Listen* setze in ein Element mit der Klasse `nino-richtext`, das die Absatzabstände und Listenzeichen zurückholt, die der Reset des Stylesheets wegnimmt.

Der Anfangswert eines neuen Schlüssels – auch eines, den der Scan anlegt – wird auf das Format gebracht, das sein Wert zeigt, wie jeder Wert, der aus der Workbench gespeichert wird: Tags, die dieses Format nicht kennt, und Shortcodes werden entfernt.

**Templates nach fehlenden Schlüsseln durchsuchen** findet statische Textfills wie `[[/template/page-home/intro/title]]` in den `.tpl`-Dateien, die kein Schlüssel beantwortet, und bietet je Schlüssel drei Antworten – so lässt sich eine lange Liste in mehreren Sitzungen abarbeiten:

- **ein Anfangswert** legt den Schlüssel mit diesem Text in jeder Sprache an;
- **ein leeres Feld** wird dieses eine Mal übergangen – der Schlüssel kommt beim nächsten Scan wieder;
- **Dauerhaft ignorieren** legt den Schlüssel still: Er verschwindet aus dem Scan, aus der Dashboard-Kachel und aus dem Panel Texte und steht hier als ausgeblendeter Schlüssel. Das Häkchen *ausgeblendet* zu entfernen – oder ihn zu löschen – holt ihn in den Scan zurück.

Der Scan führt jeden Schlüssel auf, den er findet, auch die, die er nicht anlegen darf, und sagt in der Zeile, warum. Ein Schlüssel, der der Grammatik folgt, bekommt das Eingabefeld von oben; bei einem `/feature`- oder `/module`-Schlüssel steht in der Zeile außerdem, dass er normalerweise zu diesem Feature oder Modul gehört, dessen Install-Einheit ihn liefert – fehlt er, ist das Feature vielleicht nicht aktiv, oder der Schlüssel ist falsch geschrieben. Ein Schlüssel, der keiner Grammatik folgt – eine alte Form, ein Schlüssel der Workbench, einer, den ein Template erfunden hat –, hat kein Eingabefeld: Benenne ihn im Template um; ignorieren lässt er sich weiterhin. Ein Schlüssel des Systems unter `/_nino/` hat weder Eingabefeld noch *ignorieren*, und die Zeile nennt, wer ihn schreibt: das Panel Routen, wenn eine Seite mit dieser Element-URI gespeichert wird – für Name, Titel und Beschreibung der Seite eines Features unter *Feature-Routen* –, oder das Panel Sprache, wenn die Sprache hinzugefügt wird; wo ihn niemand schreibt, ändere, was das Template liest. Jede Zeile zählt in der Dashboard-Kachel. Platzhalter, die ein Shortcode füllt – `[[name]]` in einem Mail-Template, `[[.rel]]` –, sind keine Schlüssel und stehen nicht in der Liste.

Unter den Zeilen vermerkt der Scan die Schlüssel eines Templates, die ein anderes ebenfalls liest, *auch von anderen Templates gelesen*. Er ändert nichts und zählt nichts: Ein Wort, das mehrere Templates lesen, gehört nach `/template/common`, und es dorthin zu verschieben, entscheidet der Entwickler.

Dynamisch zusammengesetzte Schlüssel liegen außerhalb eines statischen Scans.

**Speichern** in einer Kategorie schreibt die globalen Werte einmal und jede Sprache, die seit dem Öffnen der Kategorie bearbeitet wurde, eine Sprache nach der anderen; die Bestätigung erscheint nach der letzten. Ein Fehler hält dort an, nennt Schlüssel und Sprache und lässt die noch nicht geschriebenen Sprachen als ungespeichert markiert.

### Bildplätze

**Bildplätze** ist ein Tab des Panels Bilder. Ein Bildplatz verbindet eine technische Uri (`/template/page-home/hero/image`) mit einer Beschriftung und festen Zielmaßen; Redakteure befüllen ihn unter **Bilder**. **Templates nach fehlenden Bildplätzen durchsuchen** findet lokale `<img src="…">`-Verweise unter `images/` ohne Platz. Das Löschen eines Platzes löscht das darin gespeicherte Bild. Jede Zeile sagt außerdem, wenn keine Seite den Platz zeigt – *nirgends eingebunden* – und, wenn ein Template ihn nennt, das keine Route rendert, welches. Ein Platz, den der Scan anlegt, zeigt das so lange, bis sein Template `[image <uri>]` statt eines wörtlichen `<img>` benutzt.

## System

### Nutzer

Jedes Konto kann unter **Nutzer** die eigene E-Mail-Adresse und das eigene Passwort ändern; eine Änderung am eigenen Konto verlangt das aktuelle Passwort. **Überall abmelden** beendet jede Sitzung des Kontos – nach einem verlorenen Gerät oder einem Verdacht auf Missbrauch. Das Panel steht unter System, dieser erste Tab gehört aber jedem Konto.

Ein Konto mit `/_admin/users/manage` sieht außerdem die anderen Konten und kann:

- eines **anlegen**, mit Adresse, einem Passwort aus mindestens acht Zeichen und einer Rolle;
- Adresse ändern, ein neues Passwort setzen und ihm eine **andere Rolle** geben, oder keine, in **einem Speichern** – das Passwort nur bei einem Konto, das kein Recht hält, das dem eigenen fehlt (wer ein Passwort setzt, kann sich damit anmelden), und eine Rolle nur, wenn sie sich von der gespeicherten unterscheidet: Nur die Adresse eines Kontos zu ändern, das weiter reicht als die eigene Rolle, bleibt möglich, eine andere, weitere Rolle nicht – und nie dem eigenen Konto: abmelden und einen anderen Verwalter bitten;
- seine Sitzungen beenden;
- es **deaktivieren** und wieder aktivieren. Ein deaktiviertes Konto kann sich nicht anmelden, und seine Sitzungen enden sofort; vor dem Deaktivieren wird nachgefragt. Das eigene Konto und das letzte aktive Konto mit Vollzugriff lassen sich nicht deaktivieren;
- es **löschen**. Das eigene Konto und das letzte aktive Konto mit Vollzugriff – eigenem oder dem seiner Rolle – lassen sich nicht löschen, und der letzte Vollzugriff lässt sich auch nicht über einen Rollenwechsel abgeben. Ein deaktiviertes Konto zählt nicht als Vollzugriff.

Die Liste nennt zu jedem Konto seine Rolle, ob es deaktiviert ist, bis wann es gesperrt ist und wann es sich zuletzt angemeldet hat – oder dass es das nie getan hat. Der Zeitpunkt der letzten Anmeldung steht im eigenen Datensatz des Kontos in der `config.php`, neben seinen Sitzungen.

**Nutzerrollen**, der zweite Tab, ist der Ort, an dem die Rollen entstehen. Eine Rolle hat eine Kennung (ein Slug, nach dem Anlegen fest), einen Namen, einen Schalter **Vollzugriff**, der für alle Rechte auf einmal steht, auch die künftiger Module, und darunter die Rechte selbst in derselben Auswahl, die ein Mehrfach-Element-Feld benutzt: die der Rolle als kurze Liste mit je einem ✕, alles andere hinter einem Suchfeld. Jeder Eintrag heißt nach dem Panel oder Tab, das er öffnet, in der Gruppenreihenfolge der Navigation. Ein Recht, das diese Installation hält, das aber gerade kein Panel anbietet – ein abgeschaltetes Modul, ein gelöschtes Modul, ein von Hand geschriebenes Recht –, steht ebenfalls dort, unter **Nicht angeboten** und mit seiner eigenen Zeichenkette: Es ist in Kraft, bleibt also sichtbar, behaltbar und entfernbar, statt aus dem Formular zu verschwinden und beim nächsten Speichern der Rolle wegzufallen. Die beiden Rollen des Assistenten, Editor und Developer, sind hier gewöhnliche Rollen. Eine Rolle, die Konten halten, lässt sich nicht löschen; eine Änderung, die dem eigenen Konto *Nutzer verwalten* nähme, wird abgelehnt, ebenso eine, nach der kein aktives Konto mehr Vollzugriff hätte. Die eigenen Rechte kann hier niemand erweitern. Die feineren Rechte innerhalb von Elemente und Text kommen unter der Auswahl hinzu, siehe [Feinere Rechte innerhalb eines Panels](#feinere-rechte-innerhalb-eines-panels).

**Anmeldeschutz**, der dritte Tab, hält die Drossel vor der Anmeldung: **Fehlversuche bis zur Sperre** (`/nino/auth/maxtries`, 1–100) und **Dauer der Sperre** (`/nino/auth/cooldown`, 60–604800 Sekunden). Beide waren eine Gruppe von Konfiguration und behalten dessen Prüfung. Darunter listet **Gesperrte Konten** jedes Konto auf, das gerade gesperrt ist, mit dem Zeitpunkt, zu dem die Sperre endet, und **Sperre aufheben** lässt es sofort wieder anmelden; der Zähler dieses Kontos beginnt bei null. Eine gesperrte Adresse ist kein Konto und steht nicht in der Liste – sie muss weiter ablaufen. Der Tab braucht `/_admin/lockout/manage`.

**Recovery-Passwort**, der vierte Tab, ändert das Passwort, nach dem [`/_admin/recovery.php`](#recovery) fragt: das bisherige Recovery-Passwort, das neue – mindestens acht Zeichen – und das neue noch einmal. Eine abweichende Wiederholung sendet nichts, und nach einem Erfolg sind die drei Felder leer. Ein falsches bisheriges Passwort zählt auf dieselben fünf Versuche wie `recovery.php`; fünf falsche hier sperren also `recovery.php` eine Stunde. Das neue Passwort wird zuerst geprüft, eine fehlerhafte Anfrage verbraucht also keinen Versuch. Eine bereits offene Recovery-Sitzung bleibt offen. Ein erstes Passwort setzt der Tab nicht: Fehlt `private/.auth/pw.php`, sagt er das und verweist auf den Einzeiler unter [Recovery](#recovery). Der Tab braucht `/_admin/recoverypw/manage`, ein eigenes Recht, das die Rolle Entwickler über den Vollzugriff hält und die Rolle Redakteur nicht – wer es hält, kann `recovery.php` sperren und mit dem Recovery-Passwort eine Sicherung über das ganze Projekt legen.

### Sprache

**Sprache** ist das Formular der beiden Sprach-Einstellungen der `config.php`, gemeinsam gespeichert, mit der Übersetzungsübergabe als zweitem Tab.

| Einstellung | Schlüssel | Steuerelement |
|---|---|---|
| Sprachen | `/nino/locales/available` | Kästchen |
| Native Sprache | `/nino/locales/native` | Auswahl |

Die Sprachliste zeigt jede Sprache, die das Projekt kennt – die in der `config.php` und jede `text/<locale>.php` auf der Platte – und ob diese Datei existiert und wie viele Schlüssel sie hält. **Eine Sprache hinzufügen** schreibt `text/<locale>.php` als Gerüst mit leeren Werten und schaltet die Sprache *nicht* ein; sie gibt der Sprache auch ihren Namen – `/_nino/locale/<code>/name` in `text/global.php`, mit dem Code als Wert, wenn der Schlüssel nicht schon da ist –, den die Sprachwahlen zeigen; ändere diesen Namen im Panel Texte. Eine Sprache, deren Datei schon existiert, bekommt den Namen auf dieselbe Weise, und sonst wird nichts an der Datei angefasst. Übersetze die Sprache unter Texte oder importiere sie auf dem Tab Übersetzungen, hake sie dann an und speichere. Die native Sprache kann nur eine der angehakten sein, deshalb werden beide gemeinsam gespeichert.

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

**Backups** listet die verfügbaren Daten und stellt eines wieder her. **Jetzt sichern** schreibt sofort ein weiteres Archiv, benannt nach Datum und Uhrzeit – `2026-10-02-170512` – neben dem des Tages: Es ersetzt die tägliche Sicherung nicht, die der Stand vor der Arbeit des Tages ist, und es nimmt keinen Lock, die Workbench arbeitet also währenddessen weiter. Diese Archive bleiben wie die täglichen 14 Tage erhalten, höchstens aber die neuesten zehn; solange Sicherungen ausgeschaltet sind, gibt es die Schaltfläche nicht. Vor einer Wiederherstellung wird der aktuelle Stand noch einmal gesichert, sodass sich ein falscher Griff selbst rückgängig machen lässt. Prüfe danach mindestens das Frontend in jeder Sprache, Anmeldung und Rechte, Seiten, Texte, Elemente, Bilder sowie Formular- und Newsletter-Daten.

Ein Modul, das eigene Dateien unter `data/` hält, führt sie bei einer Wiederherstellung über den Callback `/nino/admin/restore` zusammen (das Newsletter-Feature des Katalogs tut das). Die tägliche Sicherung ist ein Sicherheitsnetz für redaktionelle Fehler, kein Ersatz für eine externe Sicherung des gesamten Projekts.

### Konfiguration

**Konfiguration** bearbeitet eine bewusst begrenzte Auswahl der `config.php` als Formular. Jeder Wert ist typisiert und wird geprüft; die Seite wird in einem Zug geschrieben.

| Gruppe | Einstellung | Schlüssel | Steuerelement |
|---|---|---|---|
| Fehler und Diagnose | Fehler in ein Log schreiben | `/nino/error/log` | Schalter |
| Fehler und Diagnose | Fehler im Frontend anzeigen | `/nino/error/display` | Schalter |
| Fehler und Diagnose | Session-Cookie immer als secure setzen | `/nino/session/force-secure-cookie` | Schalter |
| Fehler und Diagnose | Reverse Proxies vor dieser Website | `/nino/http/proxies` | eine Adresse oder ein CIDR-Bereich je Zeile |
| Workbench | Tägliche verschlüsselte Sicherung | `/nino/admin/backups` | Schalter |
| Workbench | Aktivitätsprotokoll führen | `/nino/admin/logs` | Schalter |
| Seiten-Cache | Gerenderte Seiten cachen | `/nino/cache/status` | Schalter |
| Seiten-Cache | Lebensdauer einer gecachten Seite | `/nino/cache/ttl` | Zahl, 10–2592000 Sekunden |
| Seiten-Cache | Nie cachen | `/nino/cache/blacklist` | eine Uri je Zeile |

Die Anmeldedrossel ist der Tab **Anmeldeschutz** des Panels Nutzer, die Sprachen sind das Panel **Sprache**. Routen, Navigationen und die Asset-Bundles werden hier ebenfalls nicht bearbeitet: Die ersten beiden haben ihre Panels, die Bundle-Reihenfolge trägt die CSS-Kaskade und bleibt eine bewusste Dateibearbeitung.

**Der Seiten-Cache.** Mit **Gerenderte Seiten cachen** speichert `Modules\Cache` eine fertige Seite und liefert sie ohne Rendern erneut aus. Nie gecacht: alles außer einem schlichten `GET` mit `200`, alles mit Query-Variablen, jede Uri unter `/_` oder `/.`, jede Anfrage eines angemeldeten Besuchers, jede Seite, deren Route einen eigenen Handler hat (ein Modul-Endpunkt, die Seiten des Posts-Features), und alles, was eine Wildcard-Route beantwortet – dort erfindet der Besucher die Adressen, und eine Seite je erfundener Adresse ist Plattenplatz, dessen Größe ein Fremder bestimmt. **Nie cachen** ergänzt eigene Ausnahmen; ein abschließendes `/*` deckt einen Teilbaum ab. Das `[csrf]`-Token und die `[jstext]`-Nonce werden je Antwort neu gestempelt, und eine gespeicherte Seite behält die `Content-Security-Policy`, mit der sie gesendet wurde, samt allem, was ein Feature für die Seite ergänzt hat. Jedes Speichern in der Workbench leert den gesamten Cache; Antworten tragen `X-Nino-Cache: hit` oder `miss`.

**Als wer ein Besucher zählt.** Jede Regel je IP – die Anmeldedrossel, die Sendegrenze für Mail, das Rate-Limit eines Formulars, die Adresse, unter der eine Session steht – zählt die Adresse, mit der PHP spricht. Hinter einem Reverse Proxy ist das der Proxy, für jeden Besucher gleichermaßen, alle teilen sich also einen Eimer. **Reverse Proxies vor dieser Website** beendet das: Steht die Adresse des Proxys (oder sein CIDR-Bereich) in der Liste, wird der Besucher stattdessen aus `X-Forwarded-For` gelesen. Tragen Sie dort nur Proxies ein, die Sie betreiben oder bezahlen – den Header kann jeder Client schreiben, und erst ein Eintrag, der kein Proxy ist, macht eine gefälschte Adresse glaubwürdig. Leer ist der sichere Wert und die Vorgabe. Eine Zeile, die weder Adresse noch CIDR-Bereich ist, wird zurückgewiesen statt gespeichert, denn sie träfe nichts, während das Formular aussieht, als wäre etwas eingestellt.

In Produktion muss `/nino/error/display` aus sein.

### Wartung

**Wartung** ist ein einziger Schalter: Solange er an ist, bekommt jeder Besuch, der nicht in der Workbench angemeldet ist, statt der Website eine 503-Antwort – die Seite selbst, und ebenso ein Modul-Endpunkt wie `/.form` des Kontaktformulars, denn die Website ist für beide gleichermaßen down. Das Panel zeigt, in welchem der beiden Zustände die Website gerade ist, den Schalter selbst und die Sekundenzahl, die als `Retry-After`-Header gesendet wird, damit ein braver Browser oder Bot wartet, bevor er es erneut versucht. Ein angemeldetes Konto sieht die Website weiterhin wie gewohnt – öffne sie in einem anderen Browser oder melde dich ab, um eine Änderung zu prüfen, bevor die Wartung wieder ausgeschaltet wird. `/_admin` funktioniert währenddessen durchgehend weiter, sodass das Zurückschalten nie vom Schalter selbst abhängt – ebenso die Anmeldung, sodass ein Betreiber, der noch nicht angemeldet ist, sich trotzdem anmelden kann. Solange die Wartung an ist, trägt jede Ansicht der Workbench einen Hinweis darauf, für die Konten, die dieses Panel haben, und jede Seite der Website, die ein angemeldetes Konto öffnet, oben einen Banner, der zum Panel verweist, wo das Konto es benutzen darf.

Die Wartungsseite trägt Kopf- und Fußbereich der Website, wo ein Projekt ein `templates/page-maintenance.tpl` hat – von Hand aus dem `install/`-Verzeichnis des Moduls hineinkopiert, mit `/module/maintenance/page/title` und `/module/maintenance/page/text` als seinen beiden Texten, von da an im Panel Texte wie jeder andere Schlüssel bearbeitbar. Ein Projekt ohne eines bekommt die schlichte, in sich geschlossene Seite des Moduls (`templates/page-maintenance.<Sprache>.tpl` im Modul, Deutsch und Englisch), in der Muttersprache der Website und mit denselben beiden Texten – wo das Projekt keine festgelegt hat, mit den Vorgaben des Moduls in dieser Sprache, für eine Muttersprache ohne eigene Seite auf Englisch –, sodass der Schalter funktioniert, sobald das Modul existiert; ein eigenes `page-maintenance.tpl` des Projekts hat immer Vorrang und behält die Sprache der Route des Besuchs. Der Einrichtungsassistent installiert die gestaltete Fassung nicht von sich aus. Der Seiten-Cache wird für die Dauer außer Betrieb genommen, damit er weder eine alte Seite über die 503 hinweg ausliefert noch die 503 selbst speichert.

### Features

**Features** sortiert jedes Feature im Verzeichnis `features/` – ein installierbares Paket mit einem Manifest `feature.php`, aus dem Katalog [dapeio/nino-features](https://github.com/dapeio/nino-features) hineinkopiert; ein Checkout bringt keines mit – in drei Tabs, **Aktiv**, **Inaktiv** und **Verfügbar**, jeder mit einer Anzahl beschriftet – Aktiv zuerst, und das ist auch der Tab, mit dem das Panel aufgeht. Ein Filter neben den Tabs schränkt alle Tabs zugleich nach Name, Schlüssel, Beschreibung oder Kategorie ein, und eine Kategorieauswahl daneben schränkt auf eine einzelne Kategorie ein; die Anzahlen folgen beiden, sodass eine Suche sagt, auf welchem Tab der Treffer liegt. Die Auswahl bietet nur die Kategorien an, die die Features und Angebote auf dem Bildschirm tatsächlich tragen – siehe [Kategorien](features.de.md#kategorien) –, und Tabs wie Filter bleiben oben stehen, während die Liste scrollt. Innerhalb eines Tabs sind die Features nach dem Namen sortiert, unter dem sie gezeigt werden. Wo auch immer ein Feature steht, zeigt es Name – mit einem Badge dahinter, wo das Manifest eine `maturity` angibt, etwa *Beta* oder *Beispiel* –, Beschreibung und Version und das, was dem Einschalten entgegensteht: eine Nino-Version, für die es nicht geschrieben wurde, eine fehlende PHP-Erweiterung, ein benötigtes Feature, das nicht da ist. Das Aktivieren wendet die Install-Einheit des Features an, ohne etwas zu überschreiben, das das Projekt bereits hat, trägt seine Klasse in `/nino/modules` ein, zeichnet seine Version auf und sagt, was es eingeschaltet hat – *Eingeschaltet: Social-Media-Links, Lightbox.*, die benötigten Features eingeschlossen –, in einem Dialog, bevor die Workbench neu lädt; das Deaktivieren trägt die Klasse aus und sonst nichts, und wird abgewiesen, solange ein anderes aktives Feature es benötigt. Ist es aus, nennt ein Dialog die Templates, Texte und Elemente, die seine Shortcodes noch enthalten – sie erscheinen als schlichter Text, solange das Feature aus ist. Jedes Feature ist eine Zeile. In ein **aktives** steigt man ein: Sein eigener Bildschirm beginnt mit **So wird es verwendet**, der kurzen Anleitung aus dem Manifest des Features – wo sein Shortcode hingehört, welches Attribut was tut –, darunter die Einstellungen, die sein Manifest erklärt – bei einem Feature mit eigenem Panel stattdessen der Tab **Einstellungen** dieses Panels, auf den dieser Bildschirm verlinkt und den ein Konto, das nur `/_admin/features/manage` hält, als einzigen Tab des Panels bekommt –, das Update, wenn sein Verzeichnis durch eine neuere Fassung ersetzt wurde, und **Deaktivieren**; so bleibt die Liste eine Zeile je Feature. Die Anleitung ist offen, wenn der Bildschirm aufgeht, und schließt mit einem Klick auf ihre Überschrift; eine lange scrollt in ihrer Box, statt die Einstellungen nach unten zu schieben, und ein Feature, dessen Manifest keine trägt, bekommt keine Box. Ein **inaktives** behält sein **Aktivieren** in der Zeile, daneben ein **Entfernen**, das sein Verzeichnis löscht – der eine Schritt, den das Deaktivieren auslässt; was das Feature behalten hat, bleibt, wer es zurückholt, findet seine Einstellungen also wieder –, und ein Angebot behält sein **Installieren**, weil es bei beiden nichts gibt, in das man einsteigen könnte. Das Installieren eines Features, das das Projekt nicht hat, schaltet es in derselben Anfrage ein, aus einem Klick werden also nicht zwei; ein Feature, das da ist und abgeschaltet wurde, bleibt abgeschaltet, denn jemand hat es abgeschaltet, und eine neuere Version ist nicht seine Meinungsänderung. Das Installieren löst außerdem auf, was ein Feature benötigt: Die ganze Menge wird zuerst ermittelt, gegen diesen Kernel geprüft und von unten nach oben abgelegt, ein Installieren kann also mehr als ein Feature bringen – und die Antwort sagt, welche, mit Namen, in einem Dialog, denn ein installiertes Angebot ist kein Angebot mehr und die folgende Liste lässt seine Zeile aus. Gespeichert werden die Einstellungen unter `/nino/features` in der `config.php`, und jeder Wert wird geprüft, bevor einer geschrieben wird.

**Verfügbar** listet, was der Katalog anbietet und noch nicht in der aktuellen Version installiert ist – gar nicht im Verzeichnis, oder dort in einer älteren Fassung. Über den Tabs liest ein Knopf **Katalog aktualisieren** den Katalog – `https://catalogue.getnino.dev/catalogue.json` als Standard; `/nino/catalogue/url` in der `config.php` nennt einen anderen, `''` schaltet ihn ab und nimmt den Knopf weg – und eine Statuszeile sagt, wann er zuletzt gelesen wurde, oder dass er noch nicht gelesen wurde; die Workbench nimmt sonst von sich aus nie Kontakt auf. Was ein Lesen findet, wird unter `data/catalogue.php` aufbewahrt und von dort bei jedem späteren Öffnen des Panels gezeigt, sodass Verfügbar sich beim Öffnen sofort füllt, ohne eine eigene Anfrage. Geladen, bietet der Tab **Installieren** für ein Feature, das nicht im Verzeichnis liegt, **Update** für eines, das dort in einer älteren Version liegt, und graut, mit dem, was es verlangt, eines aus, von dem keine Version passt. Eine Installation lädt das Archiv, prüft es gegen den signierten Katalog, legt das Verzeichnis an und schaltet das Feature ein; die Aktualisierung eines aktiven Features legt die Dateien ab und wendet das Update in einer zweiten Anfrage an, die das Panel von sich aus stellt, und ein Feature, das das Projekt abgeschaltet hat, bleibt abgeschaltet. Wo `features/` nicht beschreibbar ist, verlinkt der Tab stattdessen das Archiv zum Entpacken von Hand. Wie der Katalog geprüft wird und wie der Zwischenspeicher gehalten und ungültig wird, steht in [Der Katalog](features.de.md#der-katalog).

Ein Panel, das ein Feature mitbringt, erscheint mit dem nächsten Laden der Workbench nach dem Aktivieren und verschwindet mit dem nächsten Laden nach dem Deaktivieren. Dieses Laden macht die Workbench selbst: Alles, was ein Feature ein- oder ausschaltet – Aktivieren, Deaktivieren und das Installieren, das einschaltet, was das Projekt noch nicht hatte –, baut die Seite am Ende neu auf und behält dabei die Adresse auf diesem Panel, denn die Leiste, die Assets eines Features und seine Wörter werden alle gerendert, bevor der Browser die Seite bekommt. Es landet in der eigenen **Features**-Gruppe der Leiste, gleich was sein eigenes `nav()` nennt, und seine Berechtigung wird auf dem Tab Nutzerrollen des Panels Nutzer unter dieser Gruppe angeboten – sie muss der Rolle Editor dort gegeben werden, weil der Assistent diese Rolle geschrieben hat, bevor es das Feature gab. Manifest, Settings-Schema und Lebenszyklus stehen im Handbuch [Features](features.de.md).

### Suche

**Suche** gehört zum Search-Feature aus dem Katalog [dapeio/nino-features](https://github.com/dapeio/nino-features) und ist da, solange das Feature nach `features/` kopiert und im Panel Features eingeschaltet ist; seine eine Aktion, **Suchindex erstellen**, baut die unter `/nino/elements/index` konfigurierten Indexe neu auf. Die eigene README des Features dokumentiert das Panel und die Indexkonfiguration; siehe auch [Suchindex für Elements](development.de.md#suchindex-für-elements).

## Recovery

`/_admin/recovery.php` ist der Weg zurück, wenn die Konten selbst das Problem sind: jedes Entwicklerpasswort vergessen, oder eine Wiederherstellung misslungen. Die Seite fragt nach dem **Recovery-Passwort** aus dem letzten Schritt des Assistenten – kein Login; die Workbench fragt nur an einer Stelle danach, im Tab **Recovery-Passwort** von Nutzer, der es ändert – und bietet drei Dinge:

- **Eine Sicherung wiederherstellen**, aus der Liste der Daten, nach einer Sicherung des aktuellen Stands;
- **Ein Passwort setzen** für ein Konto, das es gibt, ausgewählt aus einer Liste: Es bekommt das neue Passwort, wird überall abgemeldet und, falls es gesperrt oder deaktiviert war, entsperrt und wieder aktiviert – ein wiederhergestelltes Entwicklerkonto kommt also hinein. Ein Tippfehler kann kein Konto benennen, es gibt keines zu benennen;
- **Ein Konto mit Vollzugriff anlegen** – für den Fall, dass kein Konto mehr übrig ist. Das ist eine eigene Aktion, sie fragt vor dem Schreiben nach einer Bestätigung und weist eine Adresse ab, die schon ein Konto hat.

Fünf Fehlversuche sperren die Seite eine Stunde, die falschen bisherigen Passwörter aus dem Tab Recovery-Passwort eingeschlossen. Der Hash des Geheimnisses liegt in `private/.auth/pw.php` – außerhalb der `config.php`, damit eine Wiederherstellung ihn nicht zurückrollen kann, und außerhalb jedes Werkzeugverzeichnisses, damit ein Update ihn nicht mitnimmt. Diese Datei schreiben nur der letzte Schritt des Assistenten und der Tab Recovery-Passwort. Ein vergessenes Geheimnis, bei dem der Tab kein bisheriges Passwort zum Abfragen hat, wird von Hand geschrieben – die Datei ist ein PHP-Stub, der sich nicht ausliefern lässt, mit dem Hash darin:

```bash
php -r 'echo "<?php http_response_code(403); exit; return \x27", password_hash( $argv[1], PASSWORD_DEFAULT ), "\x27;\n";' -- '<passwort>' > private/.auth/pw.php
```

Tu das nur in einer geschützten lokalen Umgebung – ein Passwort auf der Kommandozeile kann im Shell-Verlauf oder in der Prozessliste sichtbar werden.

## Empfohlener Arbeitsablauf

1. Führe den Assistenten aus, dann lösche `_admin/install/` aus der Produktivauslieferung.
2. Baue die Struktur unter **Elementtypen** (ein Tab von Elemente), **Textschlüssel** (Texte), **Bildplätze** (Bilder), **Routen** und **Navigationen**.
3. Setze die Seiten unter **Templates** zusammen, passe `assets/theme.css` an, wo das ausgelieferte Aussehen nicht das gewünschte ist, und prüfe das Ergebnis im Browser.
4. Fülle die Inhalte unter **Elemente**, **Texte** und **Bilder**; übergib eine Sprache unter **Sprache › Übersetzungen**.
5. Prüfe Dashboard und die beiden Scans auf fehlende Definitionen.
6. Lege die Redaktionskonten unter **Nutzer** mit der Rolle Editor an – lege auf dem Tab **Nutzerrollen** eine Rolle an, wo die beiden nicht reichen – und prüfe, was sie sehen.
7. Prüfe Frontend, jede Sprache, die Formulare und das responsive Layout.
8. Committe die Projektdateien.

## Wenn etwas nicht funktioniert

| Problem | Prüfen |
|---|---|
| Anmeldung nach mehreren Versuchen gesperrt | Die Sperrdauer abwarten (eine Stunde als Standard) oder die Sperre eines Kontos unter **Nutzer › Anmeldeschutz** aufheben; eine Adressensperre steht dort nicht und muss weiter ablaufen; die Sperre gilt je Konto und je Adresse. Hinter einem Reverse Proxy **Konfiguration › Reverse Proxies vor dieser Website** setzen, sonst teilen sich alle Besucher eine Adresse und eine Sperre. |
| Ein Panel oder ein Tab fehlt | Dem Konto fehlt die Berechtigung, oder sein Modul ist nicht aktiv. |
| Speichern schlägt fehl | Die Meldung sagt, warum, wo Nino einen Satz dafür hat (ein Wert außerhalb des Bereichs, eine schon verwendete Adresse, eine Datei über PHPs Upload-Limit). Sonst: Schreibrechte der betroffenen Datei oder des Verzeichnisses. |
| Über einem Formular erscheint ein Anmelde-Dialog | Die Sitzung ist zu Ende; melde dich neu an, dann wird die Anfrage weitergesendet. *In einem anderen Tab ist ein anderes Konto angemeldet* heißt genau das: Lade die Seite neu. |
| Template fehlt unter **Routen** | Angeboten werden nur vorhandene Dateien `templates/page-*.tpl`. |
| Eine Seite lässt sich unter **Templates** nicht speichern | Nach einer externen Änderung neu laden, eindeutige Section-Ids und unpaarige `<section>`-Tags prüfen; siehe das [Handbuch](https://github.com/dapeio/nino-features/blob/main/features/Templates/docs/templates.de.md) des Template-Baukastens. |
| Texte oder Bilder fehlen in einem Scan | Dynamische Schlüssel und Bilder sind statisch nicht erkennbar. |
| Die Backup-Liste ist leer | Sicherungen sind ausgeschaltet, oder heute gab es noch keine angemeldete Anfrage. **Jetzt sichern** schreibt sofort das erste Archiv. |
| Die Suche liefert keine Elemente | Das Search-Feature des Katalogs in `features/` und im Panel Features eingeschaltet, `/nino/elements/index` in der `config.php`, dann **Suchindex erstellen**. |
| Webseite nach **Konfiguration** kaputt | Letzten Git-Stand oder Sicherung wiederherstellen. |
| Kein Entwicklerpasswort funktioniert mehr | `/_admin/recovery.php` mit dem Recovery-Passwort. |

## Wie es weitergeht

- [Einrichtungsassistent](setup.de.md) dokumentiert die sechs Erststart-Schritte und das Library-Format.
- Der **Template-Baukasten** – Seitentemplates aus ganzen Abschnitten – ist ein Feature aus dem Katalog [dapeio/nino-features](https://github.com/dapeio/nino-features); sein [Handbuch](https://github.com/dapeio/nino-features/blob/main/features/Templates/docs/templates.de.md) liegt dort ebenfalls.
- [Entwickler-Handbuch](development.de.md) beschreibt APIs, Module, Panels und die direkte Arbeit an Projektdateien.
- [Deployment](deployment.de.md) behandelt Webserver, Sicherheit, Sicherungen und Go-Live.
