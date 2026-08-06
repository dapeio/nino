# Nino — Admin-Handbuch
*[English](_admin.md)*

**Links:**
[README](../README.de.md) · [Design-Handbuch](design.de.md) · [Entwickler-Handbuch](development.de.md) · [_editor-Handbuch](_editor.de.md) · [_install-Handbuch](_install.de.md) · [Security Policy](../SECURITY.md) · [Changelog](../CHANGELOG.md)

## Folgt.

Ein Modul ist vorab dokumentiert, da es direkt mit dem Webpages-Schritt von `/_install` zusammenspielt.

### Pages

Seiten-Routen des Projekts anlegen, bearbeiten und löschen, ohne `/nino/http/routes` von Hand als rohes json zu bearbeiten (das Config-Modul von `_admin` deckt weiterhin alles ab, was dieses Modul nicht abdeckt - Routen außerhalb seiner Zuständigkeit, oder jeden anderen whitelisted "weichen" Config-Wert). Eine komfortablere Fortsetzung des Webpages-Schritts von `/_install` für die Zeit, nachdem `_install` gelöscht wurde: Die Template-Auswahl bietet ausschließlich bereits auf der Platte vorhandene `templates/page-*.tpl`-Dateien an - kein Kopieren, keine Library-Einheiten, nur das Verdrahten eines bestehenden Templates mit einer URI.

Jeder Eintrag hat:

- **Element URI** - ein stabiler, frei wählbarer Bezeichner, der nicht wie ein echter Pfad aussehen muss. Er ist der Namensraum für die `/webpage<uri>/*`-Textschlüssel dieses Eintrags und wird zum eigenen `uri`-Datenfeld der Route.
- **Http URI** - der echte, erreichbare Browser-Pfad. Nur dieser bestimmt den Array-Key in `/nino/http/routes` - die vollständige Begründung steht im Webpages-Abschnitt von `docs/_install.de.md` (`\Nino\Http::requestRoute()` matcht über den exakten Schlüssel, nicht über einen Scan nach einer Route mit passendem `uri`-Feld).
- **Template** - eingeschränkt auf die tatsächlich vorhandenen `templates/page-*.tpl`-Dateien.
- **Status Code** - standardmäßig 200; z. B. 404 für eine Nicht-gefunden-Seite.
- **Nav** - erscheint nur, sobald das Navigation-Modul aktiv ist; regeneriert `[[/website/navigation/main]]` genauso wie der Webpages-Schritt von `/_install`.
- **Name/Titel/Beschreibung**, pro aktiver Sprache - geschrieben in die `/webpage<uri>/*`-Textschlüssel, gleicher Fallback auf einen generischen Platzhalter wie bei Webpages.

Beide URIs werden unabhängig voneinander validiert und auf Eindeutigkeit geprüft. Das Löschen einer Seite entfernt ihre Route, lässt aber die `/webpage<uri>/*`-Textschlüssel bestehen - dieselbe Nur-hinzufügen-Regel, der jedes Anwenden/Speichern in diesem Codebase folgt.

Die ↑/↓-Buttons der Liste tauschen einen Eintrag mit seinem Nachbarn und speichern die neue Reihenfolge - genau in dieser Reihenfolge wird `[[/website/navigation/main]]` generiert. Über die Liste zu sortieren ist also der Weg, das generierte Hauptmenü neu zu ordnen, sobald `/_install` (dessen eigener Webpages-Schritt dieselbe Liste-plus-Editor-Form und aus demselben Grund dieselben ↑/↓-Buttons hat) nicht mehr da ist.

Die gespeicherte Liste liegt unter dem `/nino/install/webpages`-Schlüssel in `config.php` - genau dasselbe Array, das der Webpages-Schritt von `/_install` verwaltet, synchron gehalten, da beide Werkzeuge dieselbe Form (`{ uri, httpUri, template, libraryKey, nav, statusCode, body, text }`) zurückschreiben. Das heißt, beide Werkzeuge können koexistieren: Eine hier bearbeitete Seite zeigt sich später beim erneuten Aufruf des Webpages-Schritts von `/_install` (vor dem Löschen) als aktuelle, echte Liste, nicht als veraltete.

Zwei dieser Felder gehören dem jeweils anderen Werkzeug und werden hier nur durchgereicht, nie bearbeitet:

- **`libraryKey`** - von welcher `_install/library/pages`-Einheit der Eintrag ausgegangen ist. Sobald `_install` weg ist, bedeutet das Feld nichts mehr, aber ein Speichern hier erhält es, sodass ein vorheriger Aufruf des Webpages-Schritts diese Einheit weiterhin neu anwenden kann.
- **`body`** - der Route-Body wörtlich. Normalerweise ist das schlicht `[template /templates/<template>]`, und das Template-`<select>` bestimmt ihn. Eine Library-Einheit kann aber mehr mitbringen - die "legal"-Einheit von `/_install` löst ihre Template-Datei pro Sprache über `[[/nino/http/response/locale]]` auf - und ein `<select>` konkreter Dateinamen kann das nicht abbilden. Bei solchen Einträgen ist das Select deaktiviert und zeigt stattdessen den Body; beim Speichern bleibt der Body wie er ist, statt die Seite auf die Datei einer einzelnen Sprache zu reduzieren.

`statusCode` ist aus demselben Grund wichtig: Er liegt sonst nur in der Route, wo dieses Modul ihn nicht sieht - eine z. B. als 404 angelegte Seite käme beim ersten Speichern hier stillschweigend als 200 zurück.
