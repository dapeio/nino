<?php
// Die eigenen Workbench-Texte des Config-Moduls, in seine Fills gemischt,
// solange das Modul aktiv ist (siehe text() des Panels) - dieselben
// Schlüssel und dieselbe Form wie text/<locale>.php der Workbench
return [
	'[[/_admin/nav/config]]'						=> 'Konfiguration',
	'[[/_admin/config/group/diagnostics]]'	=> 'Fehler und Diagnose',
	'[[/_admin/config/intro/diagnostics]]'	=> 'Was passiert, wenn PHP einen Fehler meldet, und wie das Session-Cookie gesetzt wird.',
	'[[/_admin/config/group/editor]]'		=> 'Workbench',
	'[[/_admin/config/intro/editor]]'		=> 'Hintergrundarbeit, die /_admin von selbst erledigt. Beide waren vor 0.12.0-beta in jedem Projekt stillschweigend an – siehe Changelog.',
	'[[/_admin/config/group/cache]]'		=> 'Seiten-Cache',
	'[[/_admin/config/intro/cache]]'		=> 'Eine gespeicherte Kopie auszuliefern spart das Rendern. Standardmäßig aus – schalte ihn ein, wenn die Seite fertig ist, nicht während des Aufbaus.',
	'[[/_admin/config/label/errorlog]]'	=> 'Fehler in ein Log schreiben',
	'[[/_admin/config/hint/errorlog]]'	=> 'Hängt PHP-Fehler an /data/logs.<month>.php an, nach drei Monaten bereinigt.',
	'[[/_admin/config/label/errordisplay]]'	=> 'Fehler im Frontend anzeigen',
	'[[/_admin/config/hint/errordisplay]]'	=> 'Nur für die Entwicklung. Eine Live-Seite muss das aus lassen – die Ausgabe enthält Dateipfade und einen Stacktrace.',
	'[[/_admin/config/label/securecookie]]'	=> 'Session-Cookie immer als secure setzen',
	'[[/_admin/config/hint/securecookie]]'	=> 'Hinter einem TLS-terminierenden Proxy einschalten, wo PHP selbst kein HTTPS sieht und das Flag sonst weglassen würde.',
	'[[/_admin/config/label/backups]]'	=> 'Tägliche verschlüsselte Sicherung',
	'[[/_admin/config/hint/backups]]'		=> 'Läuft einmal täglich bei der ersten Anfrage nach Mitternacht und behält vierzehn Tage. Wiederherstellen unter Backups.',
	'[[/_admin/config/label/logs]]'			=> 'Aktivitätsprotokoll führen',
	'[[/_admin/config/hint/logs]]'			=> 'Eine Zeile je Anmeldung und je Änderung in /_admin, vierzehn Tage aufbewahrt. Wer es lesen darf, bleibt eine Berechtigung.',
	'[[/_admin/config/label/cachestatus]]'	=> 'Gerenderte Seiten cachen',
	'[[/_admin/config/hint/cachestatus]]'	=> 'Nur anonyme GET-Anfragen, und nie eine Seite mit Query-Parametern, eine Werkzeug-Uri oder einen angemeldeten Besucher. Jedes Speichern in der Workbench leert den ganzen Cache.',
	'[[/_admin/config/label/cachettl]]'	=> 'Lebensdauer einer gecachten Seite',
	'[[/_admin/config/hint/cachettl]]'	=> 'Wie lange eine gespeicherte Seite ausgeliefert wird, bevor sie neu gerendert wird. Änderungen warten nicht darauf – sie leeren den Cache sofort.',
	'[[/_admin/config/label/cacheblacklist]]'	=> 'Nie cachen',
	'[[/_admin/config/hint/cacheblacklist]]'	=> 'Eine Uri je Zeile. Ein abschließendes /* deckt einen ganzen Teilbaum ab: /blog/* trifft /blog und alles darunter. Für alles, was je Besuch gerendert werden muss.',
];
