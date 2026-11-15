# Nino – Handbuch für Entwickler

Dieses Handbuch ist der Einstieg. Du kannst HTML, CSS und etwas PHP? Dann baust Du nach dem Lesen ein eigenes Feature. Ein Feature ist ein Paket, das eine Nino-Webseite um eine Funktion erweitert.

Das Handbuch hat zwei Teile. Teil 1 erklärt, wie Nino aufgebaut ist. Teil 2 baut in dreizehn Tutorials ein echtes Feature: die Treffen eines Vereins, mit Anmeldung.

Das Handbuch ist kurz. Es ersetzt die Nachschlagewerke nicht. Jede Stelle nennt das Nachschlagewerk, in dem die Einzelheiten stehen. Die Nachschlagewerke sind englisch.

## Inhalt

**Teil 1 – Grundübersicht** (diese Seite)

**Teil 2 – Tutorials**

1. [Ein Feature anlegen](01-feature.md)
2. [Einen Shortcode erstellen](02-shortcode.md)
3. [Eine Komponente erstellen](03-component.md)
4. [Eine Seite mit Texten in zwei Sprachen](04-texts.md)
5. [Daten halten und in einer Schleife ausgeben](05-data.md)
6. [Ein Formular für Besucher](06-form.md)
7. [Eine Route, die Anmeldungen speichert und drosselt](07-route.md)
8. [Einstellung, Backup und Datenschutz](08-settings-privacy.md)
9. [Einen Bereich in der Workbench anlegen](09-panel.md)
10. [Eine Aktion im Bereich: löschen](10-action.md)
11. [CSS und JavaScript einbinden](11-assets.md)
12. [Auf dem Dashboard erscheinen](12-dashboard.md)
13. [Testen und veröffentlichen](13-test-publish.md)

Die Tutorials bauen aufeinander auf. Lies sie in dieser Reihenfolge.

## Was Nino ist

Nino ist ein PHP-Framework für Webseiten. Es speichert alles in Dateien. Es gibt keine Datenbank. Außer PHP installierst Du nichts, und nichts muss vorab gebaut werden.

Du brauchst PHP und eine Kopie des Nino-Repositorys auf Deinem Rechner. Dieses Handbuch nennt sie „Checkout“. So startest Du lokal:

```bash
php -S 127.0.0.1:8000 router.php
```

Öffne dann `http://127.0.0.1:8000/_admin`. Dort liegt die **Workbench**, die Verwaltung der Webseite. Beim ersten Mal läuft der Einrichtungsassistent. Er legt das Projekt an. Die Schritte stehen im [Setup-Handbuch](../setup.md).

Die Workbench besteht aus **Bereichen**. Ein Bereich ist ein Bildschirm mit einem Eintrag im Menü links, zum Beispiel **Texte** oder **Elemente**. In Nino heißt ein Bereich „Panel“.

## Die Verzeichnisse und wem sie gehören

| Verzeichnis | Inhalt | Gehört |
|---|---|---|
| `_nino/` | Der Kernel: die Klassen von Nino, `Nino.css`, `Nino.js` | Nino |
| `_admin/` | Die Workbench | Nino |
| `docs/`, `tests/`, `history/` | Handbücher, Tests und Chronik von Nino | Nino |
| `features/` | Installierte Features, je ein Verzeichnis | dem Projekt |
| `app/` | Eigene PHP-Klassen des Projekts | dem Projekt |
| `private/` | Der Stand des Projekts: `config.php`, `templates/`, `text/`, `elements/`, `assets/`, `data/` | dem Projekt |
| `public/` | Was der Browser direkt lädt: Bilder, Schriften, der Cache | dem Projekt |

Ein Update von Nino ersetzt alles, was Nino gehört. Ein Update ändert nie `private/` und `public/`. Deshalb gilt: Lege nie eigene Dateien in ein Verzeichnis von Nino. Der Test eines Features liegt im Feature, nicht in `tests/`.

`private/` liefert der Webserver nie aus. Die Verzeichnisse `private/` und `public/` legt erst der Einrichtungsassistent an.

Nino benutzt virtuelle Pfade. Ein virtueller Pfad ist ein kurzer Name, den Nino in einen echten Pfad übersetzt. `/templates/page-home` ist die Datei `private/templates/page-home.tpl`. `/data/meetups.php` ist `private/data/meetups.php`.

## Der Weg einer Anfrage

Jede Anfrage läuft durch dieselben drei Aufrufe: `\Nino\init()`, `\Nino\request()` und `\Nino\output()`.

```text
Browser fragt /meetups
      │
      ▼
Route    'GET://meetups' in config.php  →  body: '[template /templates/page-meetups]'
      │
      ▼
Vorlage  private/templates/page-meetups.tpl   (HTML+)
      │
      ▼
1. Textfills    [[/template/page-meetups/intro/title]]  →  "Treffen"
2. Shortcodes   [meetup-date text="2026-12-24"]         →  <time …>24.12.2026</time>
      │
      ▼
Ausgabe  fertiges HTML an den Browser
```

Eine **Route** verbindet eine Adresse mit einer Antwort. Der Schlüssel ist `METHODE://adresse`. Die Routen stehen in `config.php` unter `/nino/http/routes`. Ein Feature kann eigene Routen anmelden.

Zwei Arrays enthalten alles, was Dein Code braucht. `$appData` hält die Konfiguration und den Zustand der Anwendung. `$request` hält eine Anfrage und ihre Antwort. Nino übergibt beide per Referenz. Das heißt: Deine Funktion bekommt keine Kopie. Was sie ändert, ist für alle geändert.

PHP-Code gibt nie selbst aus. Kein `echo`, kein `header()`, kein `exit`. Dein Code ändert die Antwort in `$request` oder ruft `\Nino\Http::ok()`, `\Nino\Http::fail()` oder `\Nino\Http::redirect()`.

Nachschlagen: [Request/Response Lifecycle](../development.md#requestresponse-lifecycle-in-detail), [Routing](../development.md#routing-and-responses).

## Callbacks

Ein **Callback** ist eine Funktion, die Nino unter einem Namen aufruft. Callbacks sind der wichtigste Weg, Nino zu erweitern. Dein Code meldet eine Funktion unter einem Namen an. Nino ruft sie auf, sobald das Ereignis mit diesem Namen eintritt:

```php
\Nino\Callbacks::registerCallback( $appData, '/nino/http/response/POST://meetups', [ self::class, 'callbackSignup' ] );
```

Hier ruft Nino die Methode auf, sobald jemand ein Formular an `/meetups` sendet. Auch ein Shortcode ist ein Callback. Die Tutorials 5, 7 und 12 benutzen Callbacks.

Nachschlagen: [Callbacks](../development.md#callbacks-the-common-extension-mechanism).

## HTML+

Eine Vorlage ist eine `.tpl`-Datei. Sie enthält HTML+. HTML+ ist HTML mit zwei Zusätzen:

- Ein **Textfill** ist ein Platzhalter für einen Text: `[[/template/page-home/hero/title]]`.
- Ein **Shortcode** ruft PHP-Code auf und setzt dessen Ausgabe ein: `[template /templates/html-header]`.

Eine Vorlage enthält nie PHP. Umgekehrt gilt: PHP enthält kein Markup. Das Markup liegt in einer Vorlage, PHP füllt sie.

Nino liest eine Vorlage einmal und setzt dann der Reihe nach ein, was dasteht: Text bleibt Text, ein Textfill wird durch seinen Text ersetzt, ein Shortcode durch seine Ausgabe. Was ein Shortcode zurückgibt, ist fertig. Nino liest es nicht noch einmal, und der Wert eines Textfills wird nie als Shortcode gelesen. Dieses Einsetzen heißt in diesem Handbuch „rendern“.

Eine **Komponente** ist ein Shortcode mit Schema. Das Schema sagt, woher der Wert kommt und welche Attribute es gibt. Werkzeuge wie der Builder können eine Komponente deshalb anbieten.

Nachschlagen: [Rendering](../development.md#rendering-from-html-to-html), [HTML+ - the notation](../development.md#html---the-notation), [Components](../development.md#components).

## Texte und Sprachen

Texte stehen nicht in der Vorlage. Sie stehen in `private/text/`: je Sprache eine Datei (`de_DE.php`, `en_US.php`) und `global.php` für alle Sprachen. Die Redaktion pflegt sie im Bereich **Texte**.

Ein Textschlüssel hat genau vier Teile: `/<namensraum>/<kategorie>/<teil>/<name>`.

| Namensraum | Wofür | Beispiel |
|---|---|---|
| `template` | Worte einer Vorlage | `/template/page-meetups/intro/title` |
| `feature` | Worte, die ein Feature ausgibt | `/feature/meetups/signup/submit` |
| `project` | Fakten des Projekts | `/project/company/contact/email` |
| `module` | Worte eines Kernel-Moduls | `/module/form/info/required` |

Fehlt ein Text in der aktuellen Sprache, zeigt Nino den Text der Hauptsprache. Die Hauptsprache heißt in Nino „native locale“.

Ein Besucher wechselt die Sprache über die Sprachwahl im Fuß jeder Seite. Zum Prüfen geht es auch über die Adresse: `/?/_nino/localepicker/current=en_US`.

Nachschlagen: [Textfills](../development.md#textfills), [The key grammar](../development.md#the-key-grammar).

## Bildplätze

Ein **Bildplatz** (image slot) ist ein benannter Platz für ein Bild mit fester Größe. Die Vorlage schreibt `[image /template/page-home/hero/background alt=""]`. Die Redaktion lädt das Bild im Bereich **Bilder** hoch. Nino schneidet es auf die Größe des Platzes zu.

Die Bildplätze stehen in `config.php` unter `/nino/html/images`. Die Bilder liegen in `public/images/`. Ein Feature legt seine Plätze mit `imageSlots` im Manifest seiner Einheit an: `'/template/page-meetups/intro/banner' => [ 'label' => [ 'en_US' => 'Meetups banner', 'de_DE' => 'Banner der Treffen' ], 'width' => 1200, 'height' => 400 ]`. Nino fügt den Platz hinzu, wo das Projekt keinen unter dieser Adresse hat. Ein Platz, den das Projekt hat, mit dem Bild der Redaktion, bleibt, wie er ist.

Nachschlagen: [The starter pages](../setup.md#the-starter-pages).

## Elemente

Ein **Element** ist ein Datensatz mit Feldern: ein Teammitglied, ein Treffen, ein Abschnitt der Datenschutzerklärung. Ein **Elementtyp** legt die Felder fest. Jeder Typ ist eine Datei in `private/elements/`.

Die Redaktion pflegt Elemente im Bereich **Elemente**. Eine Vorlage gibt sie in einer Schleife aus:

```html
[elements /meetups sort="date"]
	<li>[[title]]</li>
[/elements]
```

Elemente sind richtig für Inhalte, die die Redaktion pflegt. Was das laufende System schreibt, gehört in eine Datei unter `private/data/`. Ein Beispiel dafür sind Anmeldungen von Besuchern.

Nachschlagen: [Element types](../recipes/element-types.md).

## Workbench, Bereiche und Rechte

Die Workbench spricht die Hauptsprache des Projekts, bis ein Konto eine andere wählt. Dieses Handbuch nennt die deutschen Namen. In einer englischen Workbench heißen die Bereiche **Elements**, **Text**, **Images**, **Routes**, **Users** und **Features**, die Knöpfe **Activate**, **Deactivate**, **Update to …** und **Save**.

Ein Feature kann einen eigenen Bereich mitbringen. Der Bereich ist eine PHP-Klasse mit wenigen Methoden und ein Skript für den Browser.

Jeder Bereich hat ein **Recht**. Ein Recht ist ein Name wie `/_admin/meetups/manage`. Ein Konto sieht einen Bereich nur mit dessen Recht. Der Assistent legt zwei Rollen an: **Developer** darf alles, **Editor** darf die Inhalte pflegen. Rechte vergibst Du im Bereich **Nutzer**, Reiter **Nutzerrollen**.

Nachschlagen: [Workbench](../_admin.md), [Panel-Rezept](../recipes/admin-panel.md).

## Was ein Feature ist

Ein **Feature** ist ein Paket in einem Verzeichnis unter `features/`. Es bringt eine PHP-Klasse mit. Dazu kommen bei Bedarf Vorlagen, Texte, ein Bereich für die Workbench und ein Test.

- Das **Manifest** ist die Datei `feature.php`. Sie nennt Schlüssel, Name, Version und was das Feature mitbringt.
- **Aktivieren** schaltet ein Feature ein. Das geht im Bereich **Features**. Nino trägt die Klasse dann in `config.php` ein und ruft bei jeder Anfrage ihre Methode `init()` auf.
- Die **Installationseinheit** ist das Verzeichnis `install/` im Feature. Beim Aktivieren kopiert Nino daraus Vorlagen, Texte und Elementtypen ins Projekt. Nino überschreibt dabei nie etwas.
- Ein **Katalog** ist eine signierte Liste von Features im Netz. Der Bereich **Features** installiert daraus. Du kannst ein Feature auch von Hand nach `features/` kopieren.
- Eine **Anwendung** ist ein Eintrag im Katalog, der mehrere Features zusammenfasst.

Ein Checkout bringt kein Feature mit. Die Features von Nino liegen im Katalog [dapeio/nino-features](https://github.com/dapeio/nino-features).

Nachschlagen: [Developing a feature](../features.md), [Features in der Workbench](../_admin.md#features).

## Sicherheit in fünf Regeln

1. **Escaping.** Schreibe jeden fremden Wert nur maskiert ins HTML. Fremd ist alles, was aus einer Anfrage, einer Datei oder von der Redaktion kommt. In einem Feature maskiert `\Nino\Modules\Components::escape()`.
2. **CSRF.** Nino prüft jede schreibende Anfrage auf ein Token. Ein Token ist ein geheimer Wert der Sitzung. Jedes Formular enthält deshalb `[csrf]`. Schalte die Prüfung nicht ab.
3. **Rechte.** Prüfe in jeder Aktion der Workbench als Erstes das Recht: `\Nino\Admin\Admin::guardPerm()`. Die Workbench verlangt nur, dass ein Konto angemeldet ist. Das Recht prüft sie nicht. Ohne diese Zeile kann jedes angemeldete Konto die Aktion aufrufen.
4. **Uploads.** Nimm Bilder nur über die Bild-Funktionen von Nino an (`\Nino\Images`). Baue nie einen Dateipfad aus einem Wert der Anfrage.
5. **Drosseln.** Begrenze, wie oft ein Besucher ein öffentliches Formular absenden kann: `\Nino\Feature::count()`.

Nachschlagen: [Security Model](../development.md#security-model-for-developers), [Agent guide, Abschnitt 8](../../AGENTS.md#8-security-review-required-for-every-extension).

## Beim Entwickeln

- **Wo der Grund eines Fehlers steht.** Nino schreibt Warnungen und Fehler in `private/data/logs.<Jahr>-<Monat>.php`. Sieh dort zuerst nach.
- **Fehler anzeigen.** Setze in `private/config.php` den Wert `'/nino/error/display' => true`. Dann zeigt die Seite den Fehler mit Datei und Zeile. Nimm den Wert vor dem Livegang wieder heraus.
- **Ein Tippfehler in der Klasse eines Features legt alles lahm.** Die Webseite und `/_admin` antworten dann mit dem Status 500 und einer leeren Seite. Behebe den Fehler in der Datei. Kommst Du nicht weiter, lösche die Zeile der Klasse in `private/config.php` unter `/nino/modules`. Dann läuft alles wieder, das Feature ist aus.
- **Der Seitencache.** Ist `'/nino/cache/status'` in `config.php` an, merkt sich Nino fertige Seiten. Eine Änderung von Hand an einer Vorlage siehst Du dann nicht. Lass den Cache beim Entwickeln aus. Der Assistent lässt ihn aus.
- **Der Cache von PHP.** PHP merkt sich übersetzte Dateien (OPcache). In der Grundeinstellung (`opcache.revalidate_freq = 2`) prüft PHP eine Datei alle zwei Sekunden. Siehst Du eine Änderung nicht, lade nach zwei Sekunden noch einmal. Ob OPcache läuft, zeigt `php -i | grep opcache`.

Nachschlagen: [Error Handling and Logs](../development.md#error-handling-and-logs).

## Wie man testet

Nino testet mit einzelnen PHP-Skripten, ohne PHPUnit. Jedes Skript legt ein eigenes Wegwerf-Projekt an und räumt es wieder weg.

```bash
php tests/features-smoke.php
php features/Meetups/tests/meetups-smoke.php
```

Das erste Skript prüft den Kernel. Das zweite ist der Test eines Features. Er liegt im Feature und wird mit ihm kopiert. [Tutorial 13](13-test-publish.md) schreibt einen.

Prüfe zusätzlich im Browser. Ein Test sieht nicht, ob eine Seite gut aussieht.

Nachschlagen: [Tests Before Go-Live](../deployment.md#tests-before-go-live).

## Die Nachschlagewerke

| Du suchst | Lies |
|---|---|
| wie Nino im Detail läuft | [Developer Manual](../development.md) |
| alle Regeln für ein Feature | [Developing a feature](../features.md) |
| eine Erweiterung Schritt für Schritt | [Recipes](../recipes/README.md) |
| die Workbench | [Workbench](../_admin.md) |
| den Einrichtungsassistenten | [Setup Wizard](../setup.md) |
| den Betrieb auf einem Server | [Deployment](../deployment.md) |
| die Regeln für jede Änderung an Nino | [Agent guide](../../AGENTS.md) |
