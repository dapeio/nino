# Screenshot-Briefing

Die README zeigt Nino in vier Ansichten. Die Dateien werden unter diesem Verzeichnis abgelegt und aus der Root-README relativ als `docs/assets/screenshots/<datei>.webp` eingebunden.

| Datei | Inhalt | Aussage |
|---|---|---|
| `frontend.webp` | eine vollständig eingerichtete Nino-Webseite | individuelles Ergebnis statt fest vorgegebenem CMS-Layout |
| `install.webp` | Theme-Auswahl oder ein anderer aussagekräftiger Schritt in `/_install` | geführte, notwendige Ersteinrichtung |
| `admin.webp` | Übersicht oder struktureller Editor in `/_admin` | technische Kontrolle für Entwickler |
| `editor.webp` | Dashboard oder Inhaltsbearbeitung in `/_editor` | einfache tägliche Pflege für Redakteure |

## Empfohlene Aufbereitung

- einheitliches Seitenverhältnis, bevorzugt 16:9 oder 16:10;
- mindestens 1440 Pixel Breite;
- WebP für eine kleine Dateigröße bei guter Lesbarkeit;
- konsistente Browsergröße und Zoomstufe;
- reale, aber unkritische Beispieldaten;
- keine E-Mail-Adressen, Passwörter, Tokens, lokalen Pfade oder sonstigen privaten Daten;
- Oberfläche nicht zu knapp beschneiden: Navigation und Seitenkontext sollen erkennbar bleiben;
- Cursor, Auswahlmarkierungen und Entwicklerwerkzeuge nur zeigen, wenn sie für die Aussage wichtig sind.

## Vorgesehene Einbindung

```markdown
![Individuelles Nino-Frontend](docs/assets/screenshots/frontend.webp)
![Theme-Auswahl in Nino Install](docs/assets/screenshots/install.webp)
![Technische Projektverwaltung in Nino Admin](docs/assets/screenshots/admin.webp)
![Redaktionelle Inhaltsverwaltung in Nino Editor](docs/assets/screenshots/editor.webp)
```

Die Alt-Texte beschreiben die sichtbare Funktion und wiederholen nicht lediglich den Dateinamen. Sobald die finalen Bilder vorliegen, werden Bildausschnitt und Alt-Text gemeinsam geprüft.