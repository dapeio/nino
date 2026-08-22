# `/_editor` — Bedienungsanleitung

**Sprache:** [English](_editor.md) · Deutsch

**Stand:** 8. August 2026 · **Nino-Version:** 0.11.0-beta.1

Dieses Handbuch erklärt die tägliche Arbeit mit freigegebenen Texten, Elementen, Bildern, Nutzern und Betriebsdaten unter `/_editor`. Falls du vollständigen technischen und inhaltlichen Zugriff benötigst, lies die [`/_admin`-Bedienungsanleitung](_admin.de.md); die strukturelle Bearbeitung von Templates beschreibt die [`/_templates`-Bedienung](_templates.de.md).

**Weitere Links:**
[README](../README.de.md) · [Grundkonzepte](concepts.de.md) · [Entwickler-Handbuch](development.de.md) · [Erste Schritte](getting-started.de.md) · [`/_install`-Referenz](_install.de.md) · [`/_admin`-Bedienung](_admin.de.md) · [`/_templates`-Bedienung](_templates.de.md) · [`/_editor`-Bedienung](_editor.de.md) · [`/_design`-Bedienung](_design.de.md) · [Deployment](deployment.de.md) · [Security Policy](https://github.com/dapeio/nino/blob/main/SECURITY.md) · [Changelog](https://github.com/dapeio/nino/blob/main/CHANGELOG.md)

**Sicherheit:** Welche Bereiche du siehst und verwenden darfst, hängt von den Rechten deines Kontos ab. Ein fehlender Menüpunkt ist deshalb häufig beabsichtigt und kein Darstellungsfehler.

## Anmeldung und Oberfläche

Öffne `https://deine-domain.example/_editor` und melde dich mit E-Mail-Adresse und Passwort an. Konten werden bei der Einrichtung oder später unter `/_admin` angelegt.

Auf der Anmeldeseite kannst du die Sprache der Oberfläche wählen. Nach der Anmeldung findest du oben beziehungsweise seitlich:

- deine E-Mail-Adresse;
- **Abmelden**;
- ein Zahnrad für Oberflächensprache und helles oder dunkles Farbschema;
- die für dein Konto freigegebenen Bereiche.

Die zuletzt gewählte Sprache bleibt für die Sitzung erhalten und wird auch von den Sprachumschaltern in **Texte** und **Elemente** verwendet.

Nach wiederholt falschen Zugangsdaten kann Nino das Konto vorübergehend sperren. Wende dich an einen Administrator, wenn du dich trotz korrektem Passwort nicht mehr anmelden kannst.

## Rechte und sichtbare Bereiche

`/_editor` blendet Bereiche ohne passende Berechtigung aus. Die API prüft dieselben Rechte nochmals beim Speichern oder Laden.

| Bereich | Berechtigung |
|---|---|
| Elemente | `/_editor/elements/manage` |
| Texte | `/_editor/text/manage` |
| Bilder | `/_editor/images/manage` |
| Anfragen | `/_editor/submissions/view` |
| Newsletter | `/_editor/newsletter/manage` |
| Log | `/_editor/logs/view` |
| andere Nutzer und Rechte | `/_editor/users/manage` |
| alle Bereiche | `/*` |

Jedes Konto kann unabhängig davon das eigene Profil unter **Nutzer** bearbeiten.

## Dashboard

Das **Dashboard** zeigt den für dich relevanten Betriebsstand. Dazu gehören – abhängig von deinen Rechten –:

- Anzahl der Elemente nach Typ;
- neue oder gespeicherte Anfragen;
- Newsletter-Abonnenten;
- Datum des letzten Backups;
- letzte protokollierte Aktivitäten.

Die Kacheln führen in die jeweiligen Bereiche. Werte aus nicht freigegebenen Bereichen werden nicht angezeigt.

## Globale und übersetzte Inhalte

Nino unterscheidet zwei Arten von Feldern:

- **Global** gilt in allen Sprachen identisch, beispielsweise Preis, Datum oder eine interne Kennung.
- **Übersetzung** besitzt pro Sprache einen eigenen Wert, beispielsweise Titel oder Beschreibung.

Der Entwickler legt diese Zuordnung in `/_admin` fest. In `/_editor` bearbeitest du nur die daraus entstehenden Felder.

Du kannst innerhalb eines Formulars zwischen den Sprachen wechseln. Noch nicht gespeicherte Eingaben der zuvor gewählten Sprache bleiben währenddessen im Browser erhalten. Klicke nach allen Änderungen auf **Speichern** und warte auf die Bestätigung, bevor du den Bereich verlässt oder die Seite neu lädst.

## Texte pflegen

Der Bereich **Texte** enthält einzelne Textfills wie Überschriften, Beschreibungen, Kontaktdaten oder Beschriftungen. Die Schlüssel werden nach ihrem ersten Pfadsegment gruppiert; du bearbeitest jeweils eine vollständige Gruppe.

![Globale und übersetzte Textwerte in `/_editor`](assets/screenshots/editor-text.webp)

1. Öffne **Texte**.
2. Wähle die passende Gruppe.
3. Bearbeite globale Werte und wähle für übersetzte Werte die gewünschte Sprache.
4. Nutze bei formatierten Feldern nur die angebotenen Funktionen für Fett, Kursiv, Hervorhebung, Inline-Code und Links.
5. Klicke auf **Speichern** und warte auf die Meldung **Gespeichert**.

Zeichenzähler zeigen die vom Entwickler vorgesehene Maximallänge. Ein Textschlüssel, der hier nicht erscheint, kann in `/_admin` bewusst ausgeblendet oder technisch reserviert sein.

Der projektweite JSON-Übersetzungsworkflow liegt bewusst unter **Translations** in `/_admin`. Er bündelt native Text- und Elements-Inhalte; `/_editor` bleibt auf die Änderung einzelner freigegebener Werte konzentriert.

## Elemente pflegen

Elemente sind wiederkehrende strukturierte Inhalte wie Teammitglieder, Leistungen, Termine oder Referenzen. Welche Typen und Felder vorhanden sind, bestimmt der Entwickler in `/_admin`.

![Freigegebenes Element in `/_editor` bearbeiten](assets/screenshots/editor-elements.webp)

### Neues Element anlegen

1. Öffne **Elemente** und wähle den gewünschten Typ.
2. Wähle **Neues Element**.
3. Vergib eine eindeutige URI aus Kleinbuchstaben, Ziffern, Bindestrichen und Unterstrichen, zum Beispiel `offene-workshops`.
4. Fülle alle Pflichtfelder aus.
5. Pflege globale Werte und die benötigten Übersetzungen.
6. Speichere das Element.

Die URI ist die dauerhafte technische Kennung. Wähle sie kurz und sprechend; sie lässt sich später nicht über das Formular ändern.

### Vorhandenes Element bearbeiten

Öffne den Typ und anschließend den gewünschten Eintrag. Globale Felder werden einmal gespeichert, übersetzte Felder je Sprache. Eingaben können je nach Modell als Text, Zahl, Datum, Auswahl, Ja/Nein-Wert, Liste oder Bild erscheinen.

Ein Bildfeld ist erst verfügbar, nachdem das neue Element einmal gespeichert wurde. Beim anschließenden Upload verarbeitet Nino die Datei auf die vom Entwickler festgelegten Zielmaße.

**Löschen** entfernt das Element in allen Sprachen. Auch Bilder, die ausschließlich zu seinen Bildfeldern gehören, werden gelöscht. Die Aktion lässt sich nur über eine Sicherung rückgängig machen.

## Bilder ersetzen

Der Bereich **Bilder** enthält feste Bildplätze, die der Entwickler in `/_admin` definiert hat. Sie werden nach ihrem URI-Bereich gruppiert und zeigen Bezeichnung, Shortcode und Zielmaße.

1. Öffne die passende Gruppe.
2. Wähle eine Bilddatei für den gewünschten Platz.
3. Prüfe die angegebenen Zielmaße.
4. Starte den Upload und warte auf **Gespeichert**.

Nino prüft und verarbeitet die Datei. Ungültige oder zu große Bilder werden abgelehnt. Ein erfolgreicher Upload ersetzt das bisherige Bild sofort; es gibt keinen zusätzlichen Veröffentlichungs-Schritt.

Bildplätze lassen sich in `/_editor` weder anlegen noch löschen. Das geschieht unter `/_admin`, damit Templates und Inhalt dieselbe technische Struktur verwenden.

## Nutzerprofil und Konten

Unter **Nutzer** kann jedes Konto die eigene E-Mail-Adresse und das eigene Passwort ändern. Für Änderungen am eigenen Konto muss das aktuelle Passwort bestätigt werden. Ein neues Passwort benötigt mindestens acht Zeichen; ein leeres Passwortfeld lässt das bisherige Passwort unverändert.

Mit **Überall abmelden** werden alle aktiven Sitzungen des gewählten Kontos beendet. Verwende diese Funktion nach einem Passwortverlust, auf einem verlorenen Gerät oder bei einem vermuteten Fremdzugriff.

### Andere Nutzer verwalten

Konten mit `/_editor/users/manage` sehen auch andere bestehende Nutzer. Sie können:

- E-Mail-Adresse oder Passwort ändern;
- Sitzungen des Kontos beenden;
- bekannte Rechte über Checkboxen vergeben;
- Vollzugriff `/*` setzen.

Ein Nutzer darf die eigenen Rechte nicht selbst erweitern. Neue Konten anlegen und bestehende Konten löschen bleibt Aufgabe von `/_admin`.

Vergib nur die Rechte, die für die tatsächliche Aufgabe benötigt werden. Vollzugriff sollte auf wenige verantwortliche Personen begrenzt bleiben.

## Anfragen bearbeiten

Unter **Anfragen** erscheinen gespeicherte Formulareingänge, sofern das Formular-Modul verwendet wird und dein Konto die passende Leseberechtigung besitzt.

Die Liste zeigt Datum, Kategorie, Absender und Nachricht. Lange Inhalte lassen sich ein- und ausklappen. Über die E-Mail-Adresse öffnest du das lokale Mailprogramm; Nino versendet dabei nicht automatisch eine Antwort.

**Als CSV exportieren** lädt die aktuell vorhandenen Einträge herunter. Die Ansicht ist bewusst schreibgeschützt: Anfragen werden hier nicht verändert oder gelöscht.

## Newsletter verwalten

Der Bereich **Newsletter** zeigt die gespeicherten Anmeldungen. Du kannst:

- einzelne E-Mail-Adressen öffnen;
- alle Adressen als BCC-Zeile kopieren;
- die Liste als CSV exportieren;
- eine Anmeldung nach Bestätigung löschen.

Prüfe vor dem Versand, ob dein Mailprogramm BCC tatsächlich verwendet, damit Empfängeradressen nicht gegenseitig sichtbar werden.

Eine gelöschte Adresse wird zusätzlich als entfernt vermerkt. Dadurch soll eine spätere Wiederherstellung eines älteren Backups die Abmeldung nicht unbemerkt rückgängig machen.

## Log einsehen

**Log** zeigt das Aktivitätsprotokoll von `/_editor`, beispielsweise Anmeldungen und erfolgreiche Änderungen. Die Einträge werden für 14 Tage aufbewahrt und sind schreibgeschützt.

Dieses Protokoll ist nicht mit dem technischen PHP-Fehlerprotokoll identisch. Fehlerprotokollierung wird separat über `config.php` beziehungsweise `/_admin` gesteuert.

## Speichern, Backups und Veröffentlichung

Änderungen in `/_editor` werden direkt in die dateibasierten Projektdaten geschrieben. Es gibt weder Entwurfsstatus noch einen getrennten Veröffentlichungs-Button. Prüfe Änderungen deshalb unmittelbar im Frontend und in allen betroffenen Sprachen.

Standardmäßig erzeugt Nino beim ersten authentifizierten Editor-Zugriff eines Tages ein verschlüsseltes Backup und bewahrt tägliche Sicherungen 14 Tage auf. Die Wiederherstellung erfolgt unter `/_admin`.

Diese Backups schützen vor vielen Bedienfehlern, ersetzen jedoch keine externe Sicherung des vollständigen Projekts. Bilder, Konfiguration, Texte, Elemente und Betriebsdaten gehören gemeinsam in den Backup-Plan.

## Empfohlene Rollen

| Rolle | Sinnvolle Rechte |
|---|---|
| Redaktion | Texte, Elemente und gegebenenfalls Bilder |
| Kommunikation | Anfragen und Newsletter; bei Bedarf zusätzlich Texte |
| Betreuung | Inhalte, Log und Nutzerverwaltung |
| Technische Gesamtverantwortung | Vollzugriff `/*`; nur für wenige Konten |

Die genaue Aufteilung hängt vom Projekt ab. Beginne mit kleinen Rechten und erweitere sie nur bei einem konkreten Bedarf.

## Wenn etwas nicht funktioniert

| Problem | Prüfung |
|---|---|
| Ein Bereich fehlt | Rechte des Kontos unter **Nutzer** oder in `/_admin` prüfen. |
| Speichern schlägt fehl | Pflichtfelder, Zeichengrenzen und Schreibrechte des Servers prüfen. |
| Bild-Upload ist noch nicht verfügbar | Neues Element zuerst einmal speichern. |
| Bild wird abgelehnt | Dateiformat, Dateigröße und Zielmaße prüfen; gegebenenfalls Entwickler kontaktieren. |
| Änderungen erscheinen nicht im Frontend | richtige Sprache, richtigen Eintrag und Browser-Cache prüfen. |
| Anmeldung funktioniert nicht | Zugangsdaten und mögliche temporäre Sperre prüfen; notfalls Konto über `/_admin` zurücksetzen. |
| Backup-Datum bleibt leer | Backups und Schreibrechte durch einen Entwickler prüfen lassen. |

## Wie es weitergeht

- [`/_admin`-Bedienung](_admin.de.md) erklärt den vollständigen technischen und inhaltlichen Zugriff.
- [`/_templates`-Bedienung](_templates.de.md) erklärt den strukturellen Template-Builder im Alpha-Status.
- [Grundkonzepte](concepts.de.md) beschreibt, wie Texte, Elemente, Bilder und Templates zusammenwirken.
- [Deployment](deployment.de.md) behandelt Backups, Zugriffsschutz und sicheren Betrieb.
