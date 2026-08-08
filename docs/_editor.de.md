# Nino — Editor-Handbuch
*[English](_editor.md)*

**Links:**
[README](../README.de.md) · [Design-Handbuch](design.de.md) · [Entwickler-Handbuch](development.de.md) · [_admin-Handbuch](_admin.de.md) · [_install-Handbuch](_install.de.md) · [_templates-Handbuch](_templates.de.md) · [Security Policy](../SECURITY.md) · [Changelog](../CHANGELOG.md)

Exakte Anleitung für die tägliche Pflege der Website über `/_editor` — ohne Programmierkenntnisse
Technische Hintergründe: `docs/development.de.md`.

### Anmeldung

Unter `/_editor` mit E-Mail-Adresse und Passwort einloggen. Nach zu
vielen Fehlversuchen wird das Konto vorübergehend gesperrt
(Standard: 5 Versuche, danach 1 Stunde Cooldown — konfiguriert über
`/nino/auth/maxtries`/`/nino/auth/cooldown`, siehe
`docs/development.de.md`).
( Die Fehlermeldung unterscheidet bewusst
nicht zwischen "E-Mail unbekannt" und "Passwort falsch" — beide zeigen
dieselbe generische Meldung, damit sich über die Fehlermeldung selbst
keine gültigen E-Mail-Adressen erraten lassen. )

## 1. Der Admin-Bereich

Ein erfolgreicher Login rotiert die PHP-Session-ID — eventuell müssen nach einem Login die Inhalte in einer anderen Registerkarte neu geladen werden müssen.

### a) Dashboard

Die wichtigsten Informationen auf einen Blick. Die letzten Formular-Anfragen, alle Newsletteranmeldungen, das letzte Backup und die letzten Änderungen im Admin-Bereich.

### b) Elemente

Wiederkehrende Inhaltsbausteine (z. B. Angebote, Referenzen,
Preislisten, Team-Mitglieder) mit vom Entwickler vordefinierten
Formularen (Feldmodell, angelegt über `/_admin` → "Element Types", siehe
Abschnitt 11).

**Ablauf:**
1. Elementtyp aus der Liste wählen (die Übersicht zeigt zu jedem Typ die
   Anzahl vorhandener Einträge).
2. Bestehendes Element öffnen oder neu anlegen.
3. Jedes Feld ist pro Sprache separat befüllbar (Sprach-Reiter oben im
   Formular) — ein Feld in einer Sprache leer zu lassen ist gültig,
   rendert im Template dann als leerer Fill.
4. Bildfelder laden über ein eigenes Upload-Fenster hoch; das System
   schneidet automatisch zentriert zu und skaliert auf das vom
   Entwickler vorgegebene Zielformat (dieselbe Verarbeitung wie bei
   Bild-Slots, siehe Abschnitt 4 — der Unterschied ist nur, ob das Bild
   an einem festen Slot oder an einem konkreten Element hängt).
5. Speichern schreibt sofort in die zugehörige `elements/<typ>.php`.

**Löschen** entfernt ein Element inklusive aller zugehörigen Bilder
**unwiderruflich** — kein Papierkorb, keine Bestätigungsstufe jenseits
des Löschen-Dialogs selbst. Absicherung ist ausschließlich das
automatische Backup (Abschnitt 8) — eine versehentliche Löschung lässt
sich nur über `/_admin` → "Wiederherstellung" rückgängig machen, nicht
aus `/_editor` selbst heraus.

**Berechtigung:** `Elements::MANAGE_PERM` (Checkbox "Elemente" in der
Nutzerverwaltung, siehe Abschnitt 5).

### c) Texte

Feste Textstellen der Website, nach Bereich/Kategorie gruppiert (z. B.
alle Texte einer Seite zusammen). Eine Kategorie zeigt alle
Textstellen der aktuell gewählten Sprache gemeinsam; Änderungen an
mehreren Feldern werden gesammelt in einem Schritt gespeichert
(Batch-Speichern), nicht Feld für Feld einzeln übertragen.

Bei Werten, die Formatierung erlauben (fett, kursiv, Links — erkannt
über `Html::containsHtml()`), erscheint automatisch eine kleine
Formatierleiste; die eingegebene Formatierung wird beim Speichern über
`Html::sanitizeHtml()` auf eine kleine, sichere Tag-Whitelist reduziert
— eingefügtes `<script>` oder ein `javascript:`-Link überlebt das
Speichern nicht.

Rein technische Werte (Design-Tokens wie Farben, Abstände unter
`/ui/*`) tauchen hier absichtlich **nicht** auf — sie stehen in
`text/blacklist.php` und sind bewusst nur Entwicklern über die
Rohdateien zugänglich (siehe `docs/design.de.md`s Architektur-Abschnitt).

**Export/Import** existiert für Backup/Migration einzelner
Textbestände außerhalb des regulären automatischen Backups (z. B. um
Text zwischen zwei Umgebungen abzugleichen).

**Berechtigung:** eigene Text-Berechtigung (Checkbox "Texte").

### d) Bilder

Jede Bild-Position ist ein fester **Slot** mit vom Entwickler
vorgegebenem Zielformat (angelegt über `/_admin` → "Bilder", siehe
Abschnitt 11). Ein Upload ersetzt das vorherige Bild an genau dieser
Stelle — Zuschnitt (zentriert) und Zielgröße übernimmt das System
automatisch über `gd`; der Upload selbst wird dabei komplett
neu-encodiert (nie die hochgeladenen Bytes 1:1 übernommen), inklusive
Prüfung der echten Bilddaten (nicht nur der angegebenen Dateiendung),
einer 8-MB-Obergrenze und einer 8000-Pixel-Quellauflösungs-Obergrenze.
Ein als Bild getarnter, aber tatsächlich ausführbarer Upload kann so
nicht durchrutschen.

**Unterscheidung, die häufig verwechselt wird:** ein Bild-**Slot**
(dieser Abschnitt) ist eine feste, einmalige Position im Template
(z. B. das Hero-Bild einer Seite); ein Bild-**Feld** an einem Element
(Abschnitt 2) gehört zu einem einzelnen Element-Datensatz und kann
beliebig oft vorkommen (ein Foto pro Team-Mitglied). Beide landen
technisch im selben `images/`-Verzeichnis und laufen durch dieselbe
Verarbeitung, sind aber administrativ getrennt.

**Berechtigung:** eigene Bilder-Berechtigung (Checkbox "Bilder").

### e) Nutzer

**Eigenes Konto (jeder eingeloggte Nutzer):**
- Eigenes Passwort/E-Mail ändern, jeweils mit Bestätigung durch das
  aktuelle Passwort.
- "Überall abmelden" beendet alle aktiven Sitzungen des eigenen Kontos
  auf allen Geräten — sofort nutzen bei Verdacht auf Kompromittierung
  (siehe Sicherheitshinweise, Abschnitt 12).

**Mit Verwaltungsrechten (Checkbox "Vollzugriff"/Manager) zusätzlich:**
- Berechtigungen anderer Nutzer per Checkbox setzen: Elemente, Texte,
  Bilder, Anfragen, Newsletter, Log — oder "Vollzugriff" für alles
  gleichzeitig. Die verfügbaren Checkboxen sind serverseitig
  festgeschrieben (eine feste Liste bekannter Berechtigungsstrings) —
  es lässt sich über dieses Formular nie eine Berechtigung setzen, die
  die Oberfläche selbst nicht kennt.
- **Am eigenen Konto lässt sich nichts über diesen Weg ändern** —
  bewusste Absicherung dagegen, sich selbst versehentlich die eigenen
  Verwaltungsrechte zu entziehen.
- "Überall abmelden" auch für andere Nutzer auslösbar.

**Neue Nutzer anlegen oder Nutzer löschen** ist bewusst Entwicklern
über `/_admin` vorbehalten, nicht über `/_editor` möglich (siehe Abschnitt
11) — die Trennung stellt sicher, dass das Anlegen eines ersten
Kontos nie von einem bereits bestehenden `/_editor`-Konto abhängt.

**Ist einmal niemand mehr mit Verwaltungsrechten übrig** (letztes
Manager-Konto gelöscht, oder sich versehentlich selbst über die rohen
`/_admin`-Berechtigungen ausgesperrt), hilft nur `/_admin` — dafür den
Entwickler kontaktieren, der Zugriff auf das `_admin`-Passwort hat.

**Berechtigung:** `Users::MANAGE_PERM` für den Verwaltungsteil; das
eigene Passwort/E-Mail ändern und "Überall abmelden" für sich selbst
braucht keine gesonderte Berechtigung, nur einen aktiven Login.

### f) Anfragen

Alle über das öffentliche Kontaktformular eingegangenen Nachrichten,
neueste zuerst — als Sicherheitsnetz zusätzlich zur automatisch
versendeten E-Mail (falls diese im Spam landet, verloren geht, oder der
Mailversand gerade dem Rate-Limit unterliegt — siehe
`docs/development.de.md`s `Mail`-Abschnitt). Rein lesend, kein
Löschen/Bearbeiten über `/_editor`. 90 Tage Historie, danach automatisch
bereinigt (3 Monate, `Form::RETENTION_MONTHS`).

**Berechtigung:** eigene Anfragen-Berechtigung (Checkbox "Anfragen").

### g) Newsletter

Liste aller Newsletter-Anmeldungen. Die Anmeldung selbst läuft als
**Double-Opt-in**: ein Eintrag ist zunächst nur "ausstehend" und wird
erst "aktiv", sobald der Bestätigungslink aus der automatisch
versendeten E-Mail angeklickt wurde — ein bloßes Ausfüllen des
Anmeldeformulars trägt niemanden tatsächlich ein.

Abonnenten können sich jederzeit selbst über einen Abmeldelink
austragen (in jeder ausgehenden Newsletter-Mail enthalten, sobald das
Template ihn einbindet — siehe `docs/development.de.md`s
`Newsletter`-Abschnitt für `getUnsubscribeLink()`); zusätzlich lässt
sich jeder Eintrag hier manuell löschen (z. B. auf Zuruf, oder um einen
offensichtlich falsch eingetragenen Test-Eintrag zu entfernen).

**Berechtigung:** eigene Newsletter-Berechtigung (Checkbox
"Newsletter").

### h) Log

Protokoll der letzten Anmeldungen und admin-seitigen Änderungen (wer
hat wann was bearbeitet) — jede mutierende Aktion über `/_editor`
(Speichern, Löschen, Berechtigungsänderung, ...) erzeugt einen
Eintrag; reine Anzeige-/Lese-Aufrufe (eine Liste öffnen, ohne etwas zu
ändern) tauchen bewusst nicht auf, um das Log lesbar zu halten. Rein
lesend, 14 Tage Historie.

**Berechtigung:** eigene Log-Berechtigung (Checkbox "Log").

## 2. Der Entwickler-Bereich (_admin)

### a) Automatische Backups

Einmal täglich, ausgelöst beim ersten Login des Tages (nicht als
gesonderter Cronjob — läuft im Hintergrund des ersten `/_editor`-Aufrufs,
den ein eingeloggter Nutzer an diesem Tag macht), wird eine
verschlüsselte Sicherung aller über `/_editor` veränderbaren Inhalte
erstellt (Elemente, Texte, Bilder, Nutzerkonten). 14 Tage Historie,
ältere Backups werden automatisch gelöscht.

Die Backup-Dateien selbst sind AES-256-GCM-verschlüsselt und liegen
unter einem einmaligen, nirgendwo verlinkten Zufallsnamen — sie dienen
ausschließlich der Wiederherstellung über `/_admin` (Abschnitt 9) und
sind nicht direkt per Browser-Aufruf einsehbar (ein direkter Request
liefert nur einen 403-Statuscode, keine Daten).

Ein Entwickler kann Backups (und unabhängig davon das Activity-Log,
Abschnitt 8b) über `config.php`s `/nino/editor/backups`/
`/nino/editor/logs` projektweit abschalten.


### b) Backup-Wiederherstellung (nur über `/_admin`)

Der Stand eines beliebigen Tages der letzten 14 Tage lässt sich über
`/_admin` → "Wiederherstellung" zurückholen. Der aktuelle (möglicherweise
fehlerhafte) Stand wird davor automatisch als eigener Sicherheits-
Snapshot gesichert — eine Wiederherstellung ist also selbst wieder
umkehrbar, indem der Snapshot direkt danach erneut eingespielt wird.

`/_admin` hat ein eigenes, von den `/_editor`-Konten komplett unabhängiges
Passwort (nur dem Entwickler bekannt) — deshalb funktioniert die
Wiederherstellung auch dann noch, wenn ausgerechnet die
`/_editor`-Nutzerkonten selbst der beschädigte Teil sind.

### c) Erster Zugang & Passwörter (`/_admin`)

Ohne ein einziges bestehendes `/_editor`-Konto gibt es keinen Weg, sich
dort einzuloggen — der allererste Zugang entsteht daher immer über
`/_admin` → "Nutzer", mit der Checkbox für Verwaltungsrechte angehakt,
damit dieses erste Konto anschließend auch alle anderen Konten und
Berechtigungen von `/_editor` aus verwalten kann. Neue `/_editor`-Konten
anlegen oder bestehende löschen bleibt danach dauerhaft eine
`/_admin`-Aufgabe (siehe Abschnitt 5).

`/_admin` selbst hat kein Formular-Login mit E-Mail — nur ein einziges,
projektweites Passwort, das per Kommandozeile gesetzt wird (`php
_admin/Admin.php <Passwort>`, Ergebnis-Hash in `_admin/Admin.php`s
`PASSWORD_HASH`-Konstante eingetragen). Der mitgelieferte
Platzhalter-Hash matcht absichtlich kein reales Passwort — vor dem
ersten echten Einsatz muss ein eigenes gesetzt werden, sonst ist `/_admin`
für niemanden nutzbar, auch nicht für den Entwickler selbst.

## d) Weitere `/_admin`-Bereiche (Kurzüberblick für Betreiber)

Diese Bereiche sind reines Entwickler-Tooling, werden hier nur der
Vollständigkeit halber kurz erwähnt — Details siehe
`docs/development.de.md`:

- **Element Types** — legt das Feldmodell für Elementtypen fest (welche
  Felder ein "Angebot" oder "Team-Mitglied" hat), bevor `/_editor` →
  "Elemente" (Abschnitt 2) damit Daten pflegen kann.
- **Bilder** — legt Bild-Slots (feste Positionen mit Zielformat) an,
  bevor `/_editor` → "Bilder" (Abschnitt 4) sie befüllen kann; enthält
  außerdem eine Scan-Funktion, die Templates nach referenzierten, aber
  noch nicht angelegten Slots durchsucht.
- **Texte** — legt neue Text-Keys an, benennt sie um, und enthält
  dieselbe Scan-Funktion für in Templates verwendete, aber noch nicht
  definierte `[[key]]`-Platzhalter — bevor `/_editor` → "Texte"
  (Abschnitt 3) sie befüllen kann.
- **Konfiguration** — rohe Ansicht/Bearbeitung ausgewählter,
  freigegebener `config.php`-Schlüssel (Locales, Fehleranzeige/-log,
  Asset-Bundles, Routen) — nicht alles in `config.php` ist hier
  editierbar, nur ein bewusst begrenzter, typgeprüfter Ausschnitt.
- **Nutzer** — siehe Abschnitt 5/10.
- **Wiederherstellung** — siehe Abschnitt 9.

## 3. Sicherheitshinweise

- Passwörter nicht teilen, auch nicht innerhalb des Teams — jedes
  `/_editor`-Konto ist einzeln zurückverfolgbar über das Log (Abschnitt
  8b), ein geteiltes Passwort zerstört diese Nachvollziehbarkeit.
- Bei Verdacht auf Kompromittierung (Passwort versehentlich
  weitergegeben, verdächtige Log-Einträge) sofort "Überall abmelden"
  (Abschnitt 5) nutzen und danach das Passwort ändern — in dieser
  Reihenfolge, damit eine bereits laufende fremde Sitzung nicht durch
  den reinen Passwortwechsel weiterläuft.
- Das Log zeigt jederzeit nachvollziehbar, wer zuletzt was geändert
  hat — bei einem unerklärten Inhaltswechsel als Erstes dort
  nachsehen, bevor eine Wiederherstellung (Abschnitt 9) erwogen wird.
- Ein automatisches Backup ersetzt keine bewusste Kontrolle: Löschungen
  (Abschnitt 2) sind sofort wirksam, das Backup fängt sie erst beim
  nächsten täglichen Lauf ab, danach ist der vorherige Stand nur noch
  über die 14-Tage-Historie erreichbar.
