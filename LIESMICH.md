# Design-Labor (`design-lab.php`)

Ein Entwicklerwerkzeug für den Feinschliff der Design-Varianten einer Nino-Site (Header, Footer, Section, Tiles, Card, Buttons, Lists, Tables, Forms, Tabs, Accordion). Part und Variante wählen, Template und CSS in zwei Textfeldern bearbeiten, daneben läuft eine Live-Vorschau mit dem echten Compiler und der echten Musterseite des Design-Panels.

**Es ist kein Teil von Nino.** Nach Gebrauch **vom Server löschen**: Die Datei liegt im Web-Root und kann Dateien unter `features/Design/library/` überschreiben. Nicht ins Repository, nicht in ein Release aufnehmen.

## Voraussetzungen

- Nino-Site mit dem Feature **Design** (Kernel 1.11 oder neuer), PHP 8.3.
- Zugang nur mit einer angemeldeten Workbench-Sitzung eines Kontos mit **allen Rechten** (`/*`, z. B. Rolle Developer). Sonst antwortet die Datei mit 403.
- Definiert die Site `NINO_PRIVATE_DIR`, `NINO_CONFIG_DIR`, `NINO_APP_DIR` oder `NINO_FEATURES_DIR` in `index.php`, dieselben `define(...)`-Zeilen oben in `design-lab.php` eintragen (dort auskommentiert vorbereitet).

## Benutzung in drei Schritten

1. `design-lab.php` ins Wurzelverzeichnis der Site legen (neben `index.php`), in der Workbench (`/_admin/`) anmelden, dann `https://deine-site/design-lab.php` öffnen.
2. Oben **Part**, **Variante** und ggf. **Datei** wählen (Hauptdatei, Stil-CSS wie `nav-hover-pill.css`, Reglerdateien unter `knobs/`), dazu **Theme**, hell/dunkel/System, Breite, Sprache, bei Header/Footer das Menü (wie die Site, kurz, lang). Template und CSS bearbeiten: die Vorschau zeichnet nach ~300 ms neu, ohne die Seite neu zu laden, die Scrollposition bleibt.
3. **In die Bibliothek speichern** (Strg/Cmd+S) schreibt die Textfelder in die Dateien unter `features/Design/library/`. **Zurücksetzen** lädt die Datei neu. Das Theme der Site wird nicht angewendet: danach im Design-Panel „Auf Website anwenden“.

## Bedienung im Einzelnen

- Der Chip „ungespeichert/gespeichert“ zeigt den Zustand; beim Wechsel von Part/Variante/Datei und beim Verlassen der Seite wird bei ungespeicherten Änderungen gefragt. Es gibt kein automatisches Speichern.
- Hat die Variante kein Template (alle außer Header/Footer), ist das Template-Feld ausgegraut. Bei einer **Reglerdatei** erscheint „Reglerstellung“: der Compiler schreibt eine solche Datei nur abseits der Mitte, die Vorschau stellt den Regler auf die gewählte Stufe.
- **Ansicht** springt zu einem anderen Bereich der Musterseite (Vorgabe: der Bereich des Parts). **Neu zeichnen** erzwingt eine neue Vorschau.
- Tab rückt ein (Umschalt+Tab aus, auch über mehrere Zeilen), Esc und danach Tab verlässt das Feld. Die Teilung zwischen Editor und Vorschau lässt sich ziehen (oder mit den Pfeiltasten). Auf schmalem Schirm stehen die Teile untereinander.
- Fehler (z. B. `@import` im CSS, ungültige Zeichen) erscheinen rot über der Vorschau, die letzte gute Vorschau bleibt stehen. Warnungen (nicht geschlossene Tags, unausgeglichene Klammern, `[template …]` ohne Datei, Shortcodes, die im Ergebnis stehen bleiben) und Hinweise der Bibliothek stehen darüber, die Vorschau wird trotzdem gezeichnet.

## Grenzen der Vorschau

- Gezeigt wird die Musterseite des Design-Panels in der gewählten Theme-Auswahl, nicht die Seiten der Site. Das eigene `style.css` der Site und Seitenstile des Projekts fehlen (wie im Design-Panel).
- Eingebettet in einen abgeschotteten Rahmen: Skripte im getippten Template laufen nicht (sie werden gesperrt), Skripte der Site/des Frameworks (Menü, Tabs, Accordion) laufen. Links und Formulare im Rahmen tun nichts.
- Das Menü kommt aus den Routen der Site; „lang“ hängt 14 erfundene Einträge an.
- Bearbeitet werden nur Dateien der Varianten (`template.tpl`, `*.css`). `base.css`, die Themes und die Varianten der Features (`features/*/design/`) sind nicht dabei. Neue Dateien und Varianten legt das Labor nicht an; neue oder umbenannte Dateien erscheinen erst nach dem Neuladen der Seite.
- Die Vorschau kopiert je Zeichnung die Bibliothek (1,2 MB) in das temporäre Verzeichnis des Servers und löscht sie danach. `private/` und `public/` bleiben unberührt.
- Die Antwort einer Vorschau ist rund 0,6 MB (die Schriften stehen als Data-URI im Blatt); auf einem entfernten Server ist das spürbar.

## Sicherheit

- Nur Workbench-Sitzung mit Recht `/*`; jede POST-Anfrage braucht das CSRF-Token des Kernels (Header `X-CSRF-Token`).
- Geschrieben werden nur Dateien, die in der frisch gelesenen Bibliotheksliste stehen; Pfade aus der Anfrage werden nie verwendet. Links, `..` und Pfade außerhalb der Bibliothek werden abgelehnt. CSS mit `@import`, `javascript:`, `<script` oder `</style` wird nicht gespeichert, Null-Bytes und ungültiges UTF-8 auch nicht; höchstens 512 KB je Text.
- Die Vorschau läuft in `<iframe sandbox="allow-scripts" srcdoc>` ohne `allow-same-origin` und unter einem CSP, das Skripte nur mit Nonce erlaubt.
- Vor dem Überschreiben prüft das Labor, ob die Datei auf dem Server seit dem Laden geändert wurde, und fragt nach.
