# Design-Handbuch – WIP

**Sprache:** [English](design.md) · Deutsch

**Stand:** 8. August 2026 · **Status:** **Work in Progress**

> **Hinweis:** Dieses Handbuch wird aktuell überarbeitet. Bitte nutze bis zur Fertigstellung die Informationen aus:
> - **[Entwickler-Handbuch](development.de.md)** (für Backend-Integration)
> - **[Builder](https://github.com/dapeio/nino-features/tree/main/features/Builder)** (ein Feature aus dem Katalog, für Template-Struktur)
> - **[Haupt-Repository `dapeio/nino`](https://github.com/dapeio/nino)** (für aktuelle Frontend-Konventionen)

## Haken in `Nino.css`

Eine Design-Schicht – die ausgelieferte `assets/theme.css`, das Design-Feature des Katalogs, die `assets/style.css` eines Projekts – ändert das Aussehen, indem sie Custom Properties in `:root` setzt, im Bundle nach `Nino.css`. Sie halten die Schriften, die Größen und die Zahlen, die diese skalieren. Jede Vorgabe ist das, was die Regeln hatten, bevor es die Property gab: Eine Seite, die keine davon setzt, sieht aus wie bisher. Ein Fall weicht ab: Eine Untertitel-Klasse oder `.nino-code` an einem Element, das der Browser fett setzt – einer `h1` bis `h6` oder etwas in einem `strong` –, nimmt das Gewicht ihrer Rolle an, `normal`, wo sie fett war.

| Property | Vorgabe | Gelesen von |
|---|---|---|
| `--fontfamily-title`, `-subtitle`, `-text`, `-button`, `-code` | der System-Stack; `-button` ist `var(--fontfamily-text)` | die Schriftfamilie der fünf Schriftrollen |
| `--font-<rolle>-weight` | `bold` bei `title` und `button`, sonst `normal` | das Gewicht einer Rolle |
| `--font-<rolle>-transform` | `none` | das `text-transform` einer Rolle |
| `--font-<rolle>-size` | `title` `var(--text-4)`, `subtitle` `var(--text-2)`, `text` `1rem`, `button` `var(--fontsize-btn)`, `code` `.875rem` | die Größe einer Rolle |
| `--hero-title-scale`, `--hero-text-scale` | `1.2` und `1.2`; ab 768px `1.25` und `10 / 9` | die Größe von `.nino-hero-title` und `.nino-hero-subtitle`: die Größe der Titel- und der Untertitel-Rolle mal die Zahl |
| `--article-title-scale` | `5 / 6`; ab 768px `25 / 32` | die Größe von `.nino-article-title`: die Größe der Titel-Rolle mal die Zahl |
| `--scale-quiet`, `--scale-loud` | `1` | die Varianten `--quiet` und `--loud` einer Section, eines Articles und eines Heros: Die Zahl multipliziert die Stufe der Skala, auf der sie stehen |
| `--grid-gutter` | `var(--space-2)`; ab 768px `var(--space-3)` | das seitliche Padding von `.nino-grid-row`, der Abstand zwischen Viewport und Row |
| `--shadow-card` | `none` | der `box-shadow` von `.nino-article` und `.nino-pricing-item` |

- **Die Rollen.** Titel, Untertitel, Text, Button und Code. Die Titel-Rolle lesen `.nino-section-title`, `.nino-article-title` und `.nino-hero-title`, und jede `h1` bis `h6` allein für Gewicht und Transform: Eine Überschrift ohne Klasse behält die Schrift des Textes und die Größe, die der Browser ihr gibt. Die Untertitel-Rolle lesen die drei Untertitel, die Text-Rolle die Seite (Gewicht und Transform), die Button-Rolle `.nino-btn` samt `--small` und `--big` (und der Filter-Button für die Größe) und die Code-Rolle `.nino-code`. Ein `code`, `kbd`, `samp` oder `pre` ohne eigene Klasse gehört zu keiner Rolle: Der Browser setzt es kleiner als den Text um es herum, und die Seite liest die Textgröße nicht an `body`, damit es dabei bleibt.
- **Die Skala.** `--text-1` ist `--font-text-size`; `--text-2` bis `--text-6` sind `--font-text-size` mal `1.125`, `1.25`, `1.5`, `1.8` und `2.5`, und ab 768px `--text-4` bis `--text-6` mal `1.6`, `2` und `3`. **Die ausgelieferte `assets/theme.css` weist `--text-1` bis `--text-6` selbst zu**, aus ihrem eigenen Raster, und was sie zuweist, gewinnt gegen `Nino.css`: In einem Projekt, das aus ihr entstanden ist, folgt die Skala `--font-text-size` erst, wenn diese sechs Zeilen weg sind.
- **Der Hero, der Article-Titel und die Varianten.** Der Zeilenabstand eines Heros geht mit seiner Größe mit. Ein Article-Titel ist fünf Sechstel der Titel-Rolle (ab 768px `25 / 32`): Das ist das `--text-3`, auf dem er stand, während die anderen Titel auf `--text-4` stehen. Die beiden Faktoren, die ab 768px sinken (`--article-title-scale`, `5 / 6` auf `25 / 32`, und `--hero-text-scale`, `1.2` auf `10 / 9`), ergeben nur dann wieder die Größen, die die Regeln vor den Rollen hatten, wenn die Größe der jeweiligen Rolle eine Stufe der Skala ist: Eine Design-Schicht, die `--font-title-size` oder `--font-subtitle-size` in rem festlegt, setzt auch diese beiden Faktoren, sonst werden der Article-Titel und der Hero-Untertitel ab 768px in rem kleiner. Eine Variante `--quiet` oder `--loud` steht auf einer Stufe der Skala – ein Section-Titel auf `--text-3` oder `--text-5`, ein Article-Titel auf `--text-2` oder `--text-4` – und ist diese Stufe mal `--scale-quiet` oder `--scale-loud`.
- **Wo man sie setzt.** In `:root`. Die Größe einer Rolle wird dort aufgelöst (`--font-title-size` ist das `--text-4` von `:root`); ein `--text-n` oder `--fontsize-btn`, das eine Section oder eine Spalte für sich setzt, erreicht deshalb die Basisklassen der Titel, Untertitel und Buttons darin nicht. Um sie in einer einzelnen Section zu ändern, setze dort die `--font-<rolle>-size`. Die Varianten `--quiet` und `--loud` und die Text-Klassen `.nino-section-text` und `.nino-article-descr` stehen dagegen auf der Skala und lesen die Stufe selbst: Sie folgen einem `--text-n`, das eine Section oder eine Spalte für sich setzt. Ein Hero-Titel oder -Untertitel, der neben der Variante auch seine Basisklasse trägt, folgt ihm nur unter 768px: Ab dort legt die Basisklasse ihre Größe erneut fest.
- **Eine Karte.** `.nino-article` (und mit ihm ein `.nino-bento-item`, das es trägt, wie der Katalog beide zeichnet) und `.nino-pricing-item` lesen `--shadow-card`. Ein `.nino-article--borderless` hat keinen, und die mittlere Karte von `.nino-pricing-row--feature-middle` und der Toast behalten ihre eigenen Schatten.

## Geplante Inhalte

| Abschnitt | Status | Beschreibung |
|---|---|---|
| **Frontend-Architektur** | Geplant | Aufbau des CSS-Frameworks, Naming-Conventions, Grid-System |
| **Design-Tokens** | Geplant | Farben, Abstände, Typografie (die `--nino-*`-Eigenschaften in `assets/theme.css`) |
| **Template-Arbeit** | Geplant | Praktische Anleitung für `page-*.tpl` und `section-*.tpl` |
| **Shortcodes & Komponenten** | Geplant | Integration von Nino-Kernkomponenten (z. B. Navigation, Locale-Picker) |
| **Eigene Frontend-Elemente** | Geplant | Entwicklung individueller Blöcke und Shortcodes |
| **Responsive Design** | Geplant | Breakpoints, Media Queries, mobile Optimierung |
