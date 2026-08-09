# Stil- und Sprachleitfaden

Dieser Leitfaden hält den gemeinsamen Ton der Nino-Dokumentation fest. Sprachliche Referenz ist das bestehende deutsche Entwickler-Handbuch: technisch präzise, erklärend und offen über bewusste Entscheidungen und Grenzen.

## Grundton

- sachlich, ruhig und direkt;
- technisch genau, ohne unnötige Fachsprache;
- erklärend: nicht nur beschreiben, was geschieht, sondern auch warum;
- selbstbewusst, ohne andere Systeme abzuwerten;
- konkret durch reale Pfade, Abläufe und Beispiele;
- freundlich, aber nicht werblich überladen.

Die README darf stärker positionieren und Interesse wecken. Handbücher und Referenzen priorisieren dagegen Klarheit und Verlässlichkeit.

## Aufbau eines Abschnitts

Ein erklärender Abschnitt folgt möglichst dieser Reihenfolge:

1. **Ziel:** Was kann der Leser nach diesem Abschnitt?
2. **Kontext:** Warum existiert dieser Schritt oder dieses Konzept?
3. **Vorgehen:** Was ist konkret zu tun?
4. **Beispiel:** Wie sieht ein realistischer Fall aus?
5. **Grenzen oder Folgen:** Was ist bewusst nicht enthalten oder besonders zu beachten?

Kurze Absätze sind langen Einschüben vorzuziehen. Klammern bleiben für echte Zusatzinformationen reserviert. Wenn ein Gedanke für das Verständnis notwendig ist, gehört er in einen eigenen Satz.

## Begriffe und Rollen

| Begriff | Bedeutung |
|---|---|
| Nino | das gesamte Framework und die mitgelieferten Oberflächen |
| `_nino` | Kernel, Module und Frontend-Grundlagen |
| `/_install` | notwendiger Assistent, der aus einem frischen Checkout die Projektverzeichnisse und den ersten lauffähigen Stand erzeugt |
| `/_admin` | technische Verwaltung für Entwickler |
| `/_editor` | redaktionelle Verwaltung für Betreiber und Redakteure |
| Entwickler | richtet Struktur, Design und technische Funktionen ein |
| Redakteur | pflegt Texte, Elemente und Bilder |
| Betreiber | verantwortet den laufenden Betrieb; kann zugleich Redakteur sein |
| Textfill | sprachabhängiger oder globaler Textplatzhalter |
| Element | wiederkehrender Inhalt nach einem festgelegten Typmodell |
| Shortcode | Verbindung zwischen Template und dynamischer Logik |
| Template | HTML-basierte `.tpl`-Datei ohne PHP-Logik |

Pfade wie `templates/`, `text/`, `elements/` und `images/` bezeichnen immer den eingerichteten Projektstand. Sie existieren in einem frischen Checkout noch nicht, sondern werden von `/_install` erzeugt und befüllt.

`Admin` allein wird vermieden, weil es sowohl eine Rolle als auch den technischen Bereich `/_admin` meinen kann. Pfade und Nino-Begriffe stehen in Codeformat.

## Schreibweise

- `JavaScript`, nicht `Javascript`;
- `PHP-Datei`, `HTML-Struktur` und `CSS-Klasse` mit Bindestrich;
- `Webseite` für das konkrete Projekt, `Webanwendung` nur für echte Anwendungen;
- `Dateisystem` oder `dateibasiert` statt unnötiger englischer Mischformen;
- Funktions- und Klassennamen exakt wie im Code;
- Befehle und längere Beispiele in Codeblöcken;
- UI-Beschriftungen in Anführungszeichen, Pfade in Codeformat.

Direkte Ansprache ist in Arbeitsanleitungen erlaubt. Referenzen bleiben neutral und beschreibend.

## Hinweise und Warnungen

Besondere Absätze beginnen mit einem eindeutigen Signalwort:

- **Hinweis:** nützliche Zusatzinformation;
- **Wichtig:** Voraussetzung oder Folge, die den Arbeitsweg beeinflusst;
- **Sicherheit:** sicherheitsrelevante Handlung oder Grenze;
- **Warum?** Begründung für eine zunächst ungewöhnlich wirkende Entscheidung.

Signalwörter werden sparsam eingesetzt. Eine normale Erklärung braucht keinen hervorgehobenen Kasten.

## Deutsche Arbeitsfassung

- Zuerst wird der vollständige deutsche Gedankengang geschrieben.
- Sprachliche Präzision hat Vorrang vor einer später möglichst einfachen Übersetzung.
- Unklare technische Aussagen werden vor der Übersetzung im Repository oder Code geprüft.
- Wiederholungen zwischen Dokumenten werden durch Links ersetzt, sofern der Leser dadurch nicht seinen aktuellen Arbeitsweg verliert.

## Englische Hauptfassung

- Die Übersetzung ist sinngemäß und idiomatisch.
- Deutsche Satzstellung und Komposita werden nicht mechanisch übernommen.
- Ton, Informationsumfang, Beispiele und Einschränkungen bleiben erhalten.
- Nino-spezifische Begriffe und Codebezeichner werden nicht übersetzt.
- Beide Fassungen erhalten dieselben Überschriftenebenen und Linkziele.

## Redaktionelle Prüfung

Vor der Freigabe wird jedes Dokument auf diese Fragen geprüft:

- Ist sofort erkennbar, für wen der Text gedacht ist?
- Führt der Text zu einem klaren Ergebnis?
- Stimmen Pfade, Anforderungen und Funktionsnamen mit dem aktuellen Repository überein?
- Sind Aufgabe, Erklärung und Referenz voneinander unterscheidbar?
- Werden bewusste Grenzen ehrlich benannt?
- Entsprechen sich deutsche und englische Fassung inhaltlich?