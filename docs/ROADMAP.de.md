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
| Redakteure | Inhalte über `/_editor` ohne Programmierkenntnisse pflegen |

## Dokumentstruktur

| Deutsche Arbeitsfassung | Englische Hauptfassung | Inhalt | Status |
|---|---|---|---|
| `../README.de.md` | `../README.md` | Positionierung, Produktüberblick und Einstieg | veröffentlicht |
| `getting-started.de.md` | `getting-started.md` | vom Checkout zur fertig eingerichteten ersten Webseite | veröffentlicht |
| `concepts.de.md` | `concepts.md` | AppData, Textfills, Elemente, Templates und Shortcodes | veröffentlicht |
| `design.de.md` | `design.md` | Frontend, Designsystem, CSS und praktische Template-Arbeit | **WIP** |
| `development.de.md` | `development.md` | Architektur, Kernel, Callbacks, Module und Referenz | veröffentlicht |
| `deployment.de.md` | `deployment.md` | Webserver, Go-live, Sicherheit, Backups und Updates | veröffentlicht |
| `_install.de.md` | `_install.md` | notwendige Ersteinrichtung und Library-Format | veröffentlicht |
| `_admin.de.md` | `_admin.md` | technische Projektverwaltung für Entwickler | veröffentlicht |
| `_design.de.md` | `_design.md` | Theme, Design, Header und Footer nach der Installation | veröffentlicht (Alpha) |
| `_templates.de.md` | `_templates.md` | sectionbasierte Komposition von Seitentemplates | veröffentlicht (Alpha) |
| `_editor.de.md` | `_editor.md` | redaktionelle Pflege für Betreiber | veröffentlicht |

## Laufende Arbeitsreihenfolge

1. Deutsche und englische README einschließlich Screenshots gemeinsam pflegen.
2. Das Design-Handbuch aus dem WIP-Status bis zu einem vollständigen Arbeitsweg ausbauen.
3. Rollenhandbücher für `/_install`, `/_admin`, `/_design`, `/_templates` und `/_editor` bei Verhaltensänderungen paarweise aktualisieren.
4. Technische Beispiele, Versionsangaben und Sicherheitsanforderungen regelmäßig mit dem Repository abgleichen.
5. Inhalte und Navigation für die spätere Dokumentationswebseite beziehungsweise das Wiki ableiten.

## Veröffentlichungsregeln

- Deutsch ist die Autorensprache; Englisch ist die primäre Veröffentlichungssprache.
- Beide Sprachfassungen werden gemeinsam veröffentlicht.
- Die englische Fassung überträgt Bedeutung, Ton und Rhythmus — nicht die deutsche Satzstellung.
- Technische Beispiele, Pfade und Funktionsnamen müssen in beiden Fassungen identisch sein.
- Bestehende Dateinamen bleiben möglichst erhalten, damit externe Links nicht unnötig brechen.
- Ein Dokument soll entweder einen konkreten Arbeitsweg erklären oder als klar erkennbare Referenz dienen; beides wird nicht mehr unstrukturiert vermischt.

## Spätere Webseite und Wiki

Die Webseite übernimmt nicht einfach die GitHub-Dateiliste. Sie erhält eine eigene Navigation entlang der Nutzerreise: Nino kennenlernen, installieren, Webseite entwickeln, Inhalte pflegen, erweitern und veröffentlichen. Referenzseiten bleiben davon getrennt, werden aber aus denselben Markdown-Quellen erzeugt.
