# Nino-Dokumentation — Redaktionsplan

Dieses Repository ist der gemeinsame Arbeitsraum für die Neufassung der Nino-Dokumentation. Die Texte werden zuerst auf Deutsch entwickelt und redaktionell abgestimmt. Anschließend entsteht daraus die englische Hauptfassung.

## Festgelegte Zielgruppe

Die Dokumentation richtet sich primär an selbstständige Webentwickler und kleine Agenturen, die individuelle, mehrsprachige und redaktionell pflegbare Webseiten auf klassischem PHP-Hosting entwickeln.

| Rolle | Erwartung an die Dokumentation |
|---|---|
| Interessierte Entwickler | schnell erkennen, ob Nino zum geplanten Projekt passt |
| Webseitenentwickler | eine Webseite einrichten, gestalten und sicher veröffentlichen |
| PHP-Entwickler | Kernel, Callbacks und Module verstehen und erweitern |
| Betreiber | Installation, Deployment, Updates, Backups und Sicherheit nachvollziehen |
| Redakteure | Inhalte in der Workbench `/_admin` ohne Programmierkenntnisse pflegen |

## Dokumentstruktur

| Deutsche Arbeitsfassung | Englische Hauptfassung | Inhalt | Status |
|---|---|---|---|
| `../README.de.md` | `../README.md` | Positionierung, Produktüberblick und Einstieg | veröffentlicht |
| `getting-started.de.md` | `getting-started.md` | vom Checkout zur fertig eingerichteten ersten Webseite | veröffentlicht |
| `concepts.de.md` | `concepts.md` | AppData, Textfills, Elemente, Templates und Shortcodes | veröffentlicht |
| `design.de.md` | `design.md` | Frontend, Designsystem, CSS und praktische Template-Arbeit | **WIP** |
| `development.de.md` | `development.md` | Architektur, Kernel, Callbacks, Module und Referenz | veröffentlicht |
| `deployment.de.md` | `deployment.md` | Webserver, Go-live, Sicherheit, Backups und Updates | veröffentlicht |
| `setup.de.md` | `setup.md` | der Einrichtungsassistent: notwendige Ersteinrichtung und Library-Format | veröffentlicht |
| `_admin.de.md` | `_admin.md` | die Workbench: jedes Panel, Konten, Rollen und Recovery | veröffentlicht |
| – | – | das Design-Panel und der Template-Baukasten sind seit 1.2 nicht mehr Teil von Nino: `appearance.de.md`/`appearance.md` liegt archiviert in [`design-library/docs/`](https://github.com/dapeio/nino-features/tree/main/design-library/docs), `templates.de.md`/`templates.md` beim Feature in [`features/Templates/docs/`](https://github.com/dapeio/nino-features/tree/main/features/Templates/docs) | ausgelagert |
| `features.de.md` | `features.md` | Features: das Panel, das Manifest `feature.php`, das Settings-Schema, Aktivierung, Update und Deaktivierung, die Tests eines Features | veröffentlicht |
| – | `recipes/*.md` | die sieben Erweiterungsrezepte: Panel, Laufzeitmodul, Installer-Paket, Section-Preset, Templates und Seiten-Units, Elementtypen, Feature | veröffentlicht, nur Englisch |

## Laufende Arbeitsreihenfolge

1. Deutsche und englische README einschließlich Screenshots gemeinsam pflegen.
2. Das Design-Handbuch aus dem WIP-Status bis zu einem vollständigen Arbeitsweg ausbauen.
3. Das Workbench-Handbuch und die beiden Referenzen (Assistent, Features) bei Verhaltensänderungen paarweise aktualisieren.
4. Technische Beispiele, Versionsangaben und Sicherheitsanforderungen regelmäßig mit dem Repository abgleichen.
5. Inhalte und Navigation für die spätere Dokumentationswebseite beziehungsweise das Wiki ableiten.

## Veröffentlichungsregeln

- Deutsch ist die Autorensprache; Englisch ist die primäre Veröffentlichungssprache.
- Beide Sprachfassungen werden gemeinsam veröffentlicht.
- Die englische Fassung überträgt Bedeutung, Ton und Rhythmus — nicht die deutsche Satzstellung.
- Technische Beispiele, Pfade und Funktionsnamen müssen in beiden Fassungen identisch sein.
- Bestehende Dateinamen bleiben möglichst erhalten, damit externe Links nicht unnötig brechen.
- Ein Dokument soll entweder einen konkreten Arbeitsweg erklären oder als klar erkennbare Referenz dienen; beides wird nicht mehr unstrukturiert vermischt.
- Die Rezepte unter `recipes/` bleiben wie `AGENTS.md` einsprachig Englisch: eine kanonische Fassung für Menschen und Agenten, damit niemand zwei auseinanderlaufende Anleitungen erhält.

## Spätere Webseite und Wiki

Die Webseite übernimmt nicht einfach die GitHub-Dateiliste. Sie erhält eine eigene Navigation entlang der Nutzerreise: Nino kennenlernen, installieren, Webseite entwickeln, Inhalte pflegen, erweitern und veröffentlichen. Referenzseiten bleiben davon getrennt, werden aber aus denselben Markdown-Quellen erzeugt.
