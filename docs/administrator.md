# Administrator-Handbuch

Anleitung für die tägliche Pflege der Website über `/_admin` und `/_dev` -
keine Programmierkenntnisse nötig. Technische Details: `docs/developer.md`.

## Anmeldung

Unter `/_admin` mit E-Mail-Adresse und Passwort. Nach zu vielen
Fehlversuchen wird das Konto vorübergehend gesperrt (Schutz vor
automatisiertem Ausprobieren).

## Elemente

Wiederkehrende Inhaltsbausteine (z. B. Angebote, Referenzen, Preislisten)
mit vom Entwickler vordefinierten Formularen. Typ wählen, Element öffnen
oder neu anlegen; jedes Feld ist pro Sprache befüllbar (Reiter oben).
**Löschen** entfernt ein Element inklusive Bildern unwiderruflich - zur
Absicherung dient das automatische Backup (unten).

## Texte

Feste Textstellen der Website, nach Bereich gruppiert. Eine Kategorie
zeigt alle Textstellen der gewählten Sprache; Änderungen werden gesammelt
gespeichert. Bei formatierbaren Werten erscheint automatisch eine kleine
Formatierleiste. Rein technische Werte (Farben, Maße) tauchen hier
absichtlich nicht auf.

## Bilder

Jede Bild-Position ist ein fester Slot mit vorgegebenem Zielformat. Ein
Upload ersetzt das vorherige Bild an genau dieser Stelle - Zuschnitt und
Größe übernimmt das System.

## Nutzer

Eigenes Passwort/E-Mail ändern (mit Bestätigung durch das aktuelle
Passwort), mit Verwaltungsrechten auch die anderer Nutzer. "Überall
abmelden" beendet alle aktiven Sitzungen eines Kontos. Neue Nutzer
anlegen/löschen ist bewusst Entwicklern über `/_dev` vorbehalten.

**Berechtigungen:** Mit Verwaltungsrechten lässt sich pro Nutzer per
Checkbox festlegen, worauf er Zugriff hat (Elemente, Texte, Bilder,
Anfragen, Newsletter, Log - oder "Vollzugriff"). Beim eigenen Konto lässt
sich nichts ändern. Ist einmal niemand mehr mit Verwaltungsrechten übrig,
hilft nur `/_dev` - dafür den Entwickler kontaktieren.

## Anfragen

Alle über das Kontaktformular eingegangenen Nachrichten, neueste zuerst -
als Sicherheitsnetz zusätzlich zur E-Mail. Rein lesend, 90 Tage Historie.

## Newsletter

Liste aller Newsletter-Anmeldungen. Die Anmeldung selbst ist ein
Double-Opt-in: Ein Eintrag wird erst aktiv, wenn der Bestätigungslink aus
der automatischen E-Mail angeklickt wurde. Abonnenten können sich über
einen Abmeldelink selbst austragen; zusätzlich lassen sich Einträge hier
löschen.

## Log

Protokoll der letzten Anmeldungen und Änderungen (wer hat wann was
bearbeitet). Rein lesend, 14 Tage Historie - reine Anzeigeaufrufe tauchen
nicht auf.

## Automatische Backups

Einmal täglich beim ersten Login wird im Hintergrund eine verschlüsselte
Sicherung aller über `/_admin` änderbaren Inhalte erstellt (14 Tage
Historie). Die Dateien dienen ausschließlich der Wiederherstellung über
`/_dev`. Ein Entwickler kann Backups und Log über `config.php` abschalten.

## Wiederherstellung (`/_dev`)

Der Stand eines beliebigen Tages der letzten 14 Tage lässt sich über
`/_dev` → "Wiederherstellung" zurückholen. Der aktuelle Stand wird davor
automatisch gesichert - eine Wiederherstellung ist also selbst wieder
umkehrbar. `/_dev` hat ein eigenes, von den `/_admin`-Konten unabhängiges
Passwort (kennt nur der Entwickler) - deshalb funktioniert die
Wiederherstellung auch, wenn die Nutzerkonten selbst beschädigt sind.

## Sicherheitshinweise

Passwörter nicht teilen; bei Verdacht auf Kompromittierung sofort
"Überall abmelden" nutzen und das Passwort ändern. Das Log zeigt
jederzeit, wer zuletzt was geändert hat.