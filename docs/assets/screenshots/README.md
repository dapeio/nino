# Screenshot-Briefing

Die englische und die deutsche Root-README zeigen Nino derzeit in vier Bereichen; der fünfte, Editor, ist noch offen. Die Dateien werden unter diesem Verzeichnis abgelegt und relativ als `docs/assets/screenshots/<datei>.webp` eingebunden.

| Bereich | Dateien | Inhalt und Aussage |
|---|---|---|
| Frontend | `frontend.webp` | vollständig eingerichtete Nino-Webseite; individuelles Ergebnis statt fest vorgegebenem CMS-Layout |
| Install | `_install1.webp`, `_install2.webp` | Routenkonfiguration und Abschlussansicht; geführte, notwendige Ersteinrichtung |
| Admin | `_admin1.webp` | Textfill-Übersicht; technische Kontrolle für Entwickler |
| Editor | `_editor1.webp`, `_editor2.webp` – noch nicht aufgenommen | Elementbearbeitung und Bildplatzverwaltung; einfache tägliche Pflege für Redakteure |
| Templates | `_templates1.webp`, `_templates2.webp`, `_templates3.webp` | Section-Canvas, Preset-Library und Live-Vorschau; visuelle Template-Komposition bei lesbarem Quelltext |

Ältere Dateien wie `admin.webp`, `admin-elements.webp`, `admin-text.webp`, `editor.webp`, `editor-elements.webp`, `editor-text.webp`, `install.webp` und `templates.webp` liegen weiterhin hier, sind aber in keinem Handbuch eingebunden.

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

```html
<a href="docs/assets/screenshots/_install1.webp" target="_blank">
  <img src="docs/assets/screenshots/_install1.webp"
       alt="Routenkonfiguration im Nino-Installer"
       width="49%">
</a>
<a href="docs/assets/screenshots/_install2.webp" target="_blank">
  <img src="docs/assets/screenshots/_install2.webp"
       alt="Abschlussansicht des Nino-Installers"
       width="49%">
</a>
```

Ein einzelner Screenshot kann weiterhin mit normalem Markdown eingebunden werden. Mehrere Bilder erhalten ein `width`-Attribut, damit GitHub sie als kleine, anklickbare Vorschauen nebeneinander darstellt; Inline-CSS über `style` wird dafür nicht verwendet. Die Alt-Texte beschreiben die sichtbare Funktion und wiederholen nicht lediglich den Dateinamen.
