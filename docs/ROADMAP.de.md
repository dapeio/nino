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

## Geplante Dokumentstruktur

| Deutsche Arbeitsfassung | Englische Hauptfassung | Inhalt | Status |
|---|---|---|---|
| `../README.de.md` | `../README.md` | Positionierung, Produktüberblick und Einstieg | in Arbeit |
| `getting-started.de.md` | `getting-started.md` | vom Checkout zur fertig eingerichteten ersten Webseite | deutscher Entwurf |
| `concepts.de.md` | `concepts.md` | AppData, Textfills, Elemente, Templates und Shortcodes | deutscher Entwurf |
| `design.de.md` | `design.md` | Frontend, Designsystem, CSS und praktische Template-Arbeit | **WIP** |
| `development.de.md` | `development.md` | Architektur, Kernel, Callbacks, Module und Referenz | bestehend, neu zu gliedern |
| `deployment.de.md` | `deployment.md` | Webserver, Go-live, Sicherheit, Backups und Updates | deutscher Entwurf |
| `_install.de.md` | `_install.md` | notwendige Ersteinrichtung und Library-Format | bestehend, neu zu gliedern |
| `_admin.de.md` | `_admin.md` | technische Projektverwaltung für Entwickler | unvollständig |
| `_editor.de.md` | `_editor.md` | redaktionelle Pflege für Betreiber | bestehend, neu zu gliedern |

## Arbeitsreihenfolge

1. Deutsche README abschließen.
2. Screenshots und Alt-Texte ergänzen.
3. Getting Started als vollständigen Erfolgsweg schreiben.
4. Grundkonzepte aus den bestehenden Handbüchern herauslösen.
5. Design- und Entwickler-Handbuch auf Aufgaben und Referenzen aufteilen.
6. Deployment- und Sicherheitsweg vervollständigen.
7. Rollenhandbücher für `/_install`, `/_admin` und `/_editor` überarbeiten.
8. Jede freigegebene deutsche Fassung in ein stilistisch entsprechendes Englisch übertragen.
9. Inhalte und Navigation für die spätere Dokumentationswebseite bzw. das Wiki ableiten.

## Veröffentlichungsregeln

- Deutsch ist die Autorensprache; Englisch ist die primäre Veröffentlichungssprache.
- Beide Sprachfassungen werden gemeinsam veröffentlicht.
- Die englische Fassung überträgt Bedeutung, Ton und Rhythmus — nicht die deutsche Satzstellung.
- Technische Beispiele, Pfade und Funktionsnamen müssen in beiden Fassungen identisch sein.
- Bestehende Dateinamen bleiben möglichst erhalten, damit externe Links nicht unnötig brechen.
- Ein Dokument soll entweder einen konkreten Arbeitsweg erklären oder als klar erkennbare Referenz dienen; beides wird nicht mehr unstrukturiert vermischt.

## Spätere Webseite und Wiki

Die Webseite übernimmt nicht einfach die GitHub-Dateiliste. Sie erhält eine eigene Navigation entlang der Nutzerreise: Nino kennenlernen, installieren, Webseite entwickeln, Inhalte pflegen, erweitern und veröffentlichen. Referenzseiten bleiben davon getrennt, werden aber aus denselben Markdown-Quellen erzeugt.