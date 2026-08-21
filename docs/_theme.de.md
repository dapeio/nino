# `/_theme` — Bedienungsanleitung

**Sprache:** [English](_theme.md) · Deutsch

**Letzte Aktualisierung:** 21. August 2026 · **Nino-Version:** 0.11.0-beta.1

Diese Anleitung erklärt die Design-Ebene unter `/_theme`: die Farben, aus denen jedes Stylesheet eines Projekts liest, woher sie kommen und was eine Änderung bewirkt. Der strukturelle Seitenaufbau ist in der [`/_templates`-Bedienung](_templates.de.md) beschrieben, die tägliche Inhaltspflege in der [`/_editor`-Bedienung](_editor.de.md).

**Weiterführende Links:**
[README](../README.de.md) · [Grundkonzepte](concepts.de.md) · [Entwickler-Handbuch](development.de.md) · [Erste Schritte](getting-started.de.md) · [`/_install`-Referenz](_install.de.md) · [`/_admin`-Bedienung](_admin.de.md) · [`/_templates`-Bedienung](_templates.de.md) · [`/_editor`-Bedienung](_editor.de.md) · [`/_theme`-Bedienung](_theme.de.md) · [Deployment](deployment.de.md) · [Security Policy](https://github.com/dapeio/nino/blob/main/SECURITY.md) · [Changelog](https://github.com/dapeio/nino/blob/main/CHANGELOG.md)

**Sicherheit:** `/_theme` nutzt dieselbe Anmeldung, dasselbe Passwort, denselben Sperrstatus und dieselbe Sitzung wie `/_admin`. Wird `_admin/` aus einer Auslieferung entfernt, steht auch `/_theme` nicht zur Verfügung.

## Wofür `/_theme` da ist

Ein Stylesheet fragt eine Farbe über ihren Namen an — `var(--nino-alt)` für einen Abschnittshintergrund, `var(--nino-on-alt)` für den Text darauf. `/_theme` legt fest, was diese Namen wert sind.

Sie geben eine Markenfarbe und einige Vorlieben an. `/_theme` berechnet daraus die vollständige Palette und das Größenraster und schreibt beides in eine Datei. Am Layout ändert sich nichts, nur an den Werten hinter den Namen.

Der eigentliche Punkt ist die Paarung. Zu jedem Hintergrund gehört die Textfarbe, die darauf gehört, und dieses Paar wird vor dem Schreiben gegen die WCAG-Kontrastformel gemessen. Sie können keine Markenfarbe wählen, die unleserlichen Text erzeugt — denn die Textfarbe wählen Sie nicht, sie wird für den gewählten Hintergrund berechnet.

## Anmeldung und Oberfläche

Öffnen Sie `https://ihre-domain.example/_theme` und melden Sie sich mit dem `/_admin`-Passwort an. Sie finden dort:

- **Primär** — die Markenfarbe. Alles Weitere wird daraus abgeleitet.
- **Sekundär** — ein optionaler zweiter Akzent. Bleibt das Feld leer, folgt er der Primärfarbe.
- **Kontrast** — `Soft`, `Default` oder `High`.
- **Farben** — `Clean`, `Default` oder `Vibrant`.

**Vorschau** rechnet neu, ohne zu speichern. **Speichern** schreibt das Stylesheet.

## Die Einstellungen

### Primär und Sekundär

Zwei Hex-Farben. Die Primärfarbe wird zur `origin`-Fläche und färbt die neutralen Grautöne ein — ein zur Markenfarbe hin verschobenes Grau wirkt gewählt und nicht zufällig. Die Sekundärfarbe wird zur `vibrant`-Fläche. Ohne Sekundärfarbe sind `vibrant` und `origin` identisch, was für ein Ein-Akzent-Design richtig ist.

Ihre Markenfarbe wird nicht unverändert als Hintergrund verwendet. Sie wird entlang der wahrnehmungsbezogenen Helligkeitsachse verschoben, bis der Text darauf das Kontrastziel erreicht — erkennbar bleiben Farbton und Sättigung. Ein Markenblau kann als Fläche etwas dunkler herauskommen als im Logo; es ist trotzdem dasselbe Blau.

### Kontrast

Legt ausschließlich das zu erreichende Verhältnis fest.

| Einstellung | Fließtext | Sekundärtext |
|---|---|---|
| Soft | 4,5:1 | 3,0:1 |
| Default | 4,5:1 | 4,5:1 |
| High | 7,0:1 | 4,5:1 |

4,5:1 ist die WCAG-AA-Anforderung für Fließtext (SC 1.4.3), 7,0:1 entspricht AAA. `Soft` unterschreitet AA für Fließtext nicht — es lockert nur die untergeordnete Stufe. Rahmen und Fokusringe zielen unabhängig von dieser Einstellung immer auf 3:1 (SC 1.4.11).

### Farben

Skaliert allein die Sättigung: `Clean` dämpft auf 45 %, `Vibrant` verstärkt auf 150 %. Die Helligkeit bleibt beim Kontrast. Dass die beiden Regler auf getrennten Achsen arbeiten, macht sie erst vorhersagbar — sonst würden beide um denselben Wert streiten und keiner täte, was sein Name verspricht.

Die Statusfarben ignorieren diese Einstellung. Rot muss rot bleiben, damit die Markenregler eine Gefahrenfläche nicht in etwas Beruhigendes verwandeln können.

## Das Größenraster

Dieselbe Trennung, auf Größen angewandt: `/_theme` veröffentlicht einen festen Satz Stufen, das Theme entscheidet, welche Stufe eine Komponente nutzt. Nummeriert statt benannt, denn eine Größe hat keine eigene Bedeutung — `--nino-space-3` ist eine Stufe, `--nino-alt` eine Fläche.

| Token | Stufen |
|---|---|
| `--nino-text-1` … `--nino-text-6` | die Typo-Skala, von Fließtext bis Display |
| `--nino-space-1` … `--nino-space-6` | die Abstands-Skala |
| `--nino-radius-1` … `--nino-radius-3` | Eckenradien |
| `--nino-radius-full` | eine Pille; bei jeder Einstellung dieselbe Antwort |
| `--nino-line-height` | der vertikale Rhythmus |

Drei Einstellungen formen es.

**Volume** legt fest, wie weit die Typo-Skala auffächert. Sie ist am Fließtext verankert: Stufe 1 bleibt bei jeder Einstellung, bewegt wird das Display-Ende. Eine Skala wird größer, indem sie oben auffächert — nicht, indem sie die Größe mit hochschiebt, die man tatsächlich liest.

**Spacing** skaliert die Abstände und bewegt die Zeilenhöhe mit. Es greift bei den kleinen Stufen am stärksten — die großen sind bereits abschnittsgroß, und sie zu verdoppeln erzeugt nur Scrollweg.

**Shaping** setzt die drei Radien. Absolute Werte statt eines Faktors, denn ein Radius hat einen Boden bei 0 und eine Decke bei der halben Box, an der ein Faktor vorbeischießen würde.

Der Standardwert jeder Einstellung reproduziert exakt die Skala von `Nino.css`. Das Einschalten der Design-Ebene darf ein Projekt nicht bewegen, das nichts verlangt hat.

`--nino-text-4` bis `--nino-text-6` wachsen bei 768px erneut, in einer eigenen Media Query des Rasters — am selben Breakpoint, den `Nino.css` bereits nutzt. Das Raster hat keine Hell/Dunkel-Variante und wird deshalb, anders als die Palette, nur einmal geschrieben.

## Die Flächen

Eine Fläche ist ein Hintergrund samt allem, was darauf lesbar ist. Es gibt neun:

| Fläche | Verwendung |
|---|---|
| `default` | der Seitengrund |
| `alt` | ein kaum wahrnehmbarer Schritt davon weg, für abwechselnde Abschnitte |
| `dark` | ein bewusst dunkler Block; in hellem und dunklem Modus gleich |
| `black` | die tiefste Stufe, für Footer und schwere Abschnitte |
| `origin` | die Markenfläche |
| `vibrant` | der zweite Akzent |
| `success`, `warning`, `danger` | Status, mit festen Farbtönen |

Jede veröffentlicht zehn Werte:

| Token | Bedeutung |
|---|---|
| `--nino-<fläche>` | der Hintergrund |
| `--nino-on-<fläche>` | Fließtext darauf |
| `--nino-on-<fläche>-muted` | Sekundärtext darauf |
| `--nino-<fläche>-link` | Linkfarbe darauf |
| `--nino-<fläche>-border` | ein Rahmen dagegen, mit 3:1 |
| `--nino-<fläche>-focus` | der Fokusring, mit 3:1 |
| `--nino-<fläche>-hover` | der Hintergrund eine Interaktionsstufe höher |
| `--nino-<fläche>-active` | der Hintergrund im gedrückten Zustand |
| `--nino-<fläche>-disabled` | Text, der bewusst nicht lesbar ist |
| `--nino-<fläche>-shadow` | ein Schatten, der auf diesem Grund funktioniert |

Sprechen Sie eine Fläche an, nie eine Rampenstufe. `var(--nino-alt)` sagt, was gemeint ist; eine Nummer sagt nur, an welcher Stelle einer Liste sie steht, die neu nummeriert werden kann.

## Hell und Dunkel

Die Datei wird dreimal geschrieben: einmal für Hell, einmal innerhalb von `@media (prefers-color-scheme: dark)` und einmal für `:root[data-nino-mode="dark"]`. Der Media-Block ist so abgesichert, dass eine ausdrückliche Hell-Wahl die Systemeinstellung schlägt, und der Attribut-Block steht zuletzt, damit eine ausdrückliche Dunkel-Wahl alles andere schlägt.

Praktisch bedeutet das: derselbe Token-Name liefert in allen drei Zuständen den richtigen Wert — System hell, System dunkel und manuelle Übersteuerung in beide Richtungen.

## Wo die Datei landet

`/_theme` schreibt `/assets/style.design.css` und setzt sie im CSS-Bundle unmittelbar hinter `_nino/Nino.css`:

```
_nino/Nino.css            Framework
assets/style.design.css   ← hier erzeugt
assets/style.theme.*.css  das Theme
assets/style.css          eigene Übersteuerungen
```

Die Reihenfolge ist die Regel, die das Ganze trägt: Design liefert die Werte, das Theme entscheidet, was damit geschieht, Ihr eigenes Stylesheet hat das letzte Wort. Was später in der Liste steht, übersteuert alles Frühere.

**Bearbeiten Sie `style.design.css` nicht.** Jedes Speichern schreibt die Datei vollständig neu. Eigene Änderungen gehören in `assets/style.css`, die nie angefasst wird.

## Woher die Einstellungen kommen

Der Themes-Schritt in `/_install` schreibt zuerst den Ausgangspunkt aus dem `design`-Block, mit dem das gewählte Theme gezeichnet wurde. Der folgende Design-Schritt speichert die Auswahl der Bedienung, und `/_theme` bearbeitet danach dieselben Einstellungen. Alle drei Wege verwenden dasselbe `Theme::write()` - also einen Generator und ein Stylesheet, nicht getrennte Installations- und Laufzeitkopien.

## Das Design später ändern

`/_theme` bleibt nach der Installation verfügbar. Ein laufendes Projekt umzufärben heißt: eine neue Primärfarbe wählen und speichern. Keine Neuinstallation, keine Inhaltsmigration. Vorlagen, Abschnitte und Elemente bleiben unberührt — sie haben immer nur die Token-Namen verwendet.

## Fehlerbehebung

| Symptom | Ursache und Abhilfe |
|---|---|
| Farben ändern sich nach dem Speichern nicht | Browser-Cache. Mit Cache-Umgehung neu laden; das Bundle wird beim Schreiben neu erzeugt. |
| Die Markenfarbe wirkt als Fläche anders | So gewollt. Die Helligkeit wurde bis zum Kontrastziel verschoben, Farbton und Sättigung bleiben erhalten. |
| Sekundäre Elemente sehen aus wie primäre | Keine Sekundärfarbe gesetzt — `vibrant` fällt auf `origin` zurück. Eine setzen. |
| `style.design.css` verliert manuelle Änderungen | Die Datei wird erzeugt. Nutzen Sie `assets/style.css`. |
| `/_theme` zeigt wiederholt die Anmeldeseite | Gemeinsame `/_admin`-Sitzung. `/_admin`-Anmeldung und eine mögliche Sperre prüfen. |

## Aktuelle Grenzen

- **Bisher ein umgestelltes Theme.** `agency` ist als Abbildungsschicht neu geschrieben - jede Farbrolle einem `--nino-*`-Token zugewiesen - und erklärt den `design`-Block, aus dem `/_install` es startet. Die anderen sieben tragen weiterhin handgesetzte literale Farben und ignorieren diese Einstellungen, bis sie ebenso umgestellt sind.

## Nächste Schritte

- [`/_templates`-Bedienung](_templates.de.md) baut Seiten aus Abschnitten, die diese Flächen nutzen.
- [`/_admin`-Bedienung](_admin.de.md) beschreibt die technische Projektverwaltung, mit der dieses Werkzeug seine Anmeldung teilt.
- [Entwickler-Handbuch](development.de.md) dokumentiert den Token-Vertrag für Stylesheet-Autoren.
