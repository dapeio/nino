<?php
// Die eigenen Worte der Workbench-Shell - und nur die. Jeder Bereich ist ein
// Modul unter _admin/Nino/Modules/ und bringt sein eigenes text/<locale>.php
// mit (siehe den Panel-Vertrag in Admin.php); hier bleibt, was die Shell
// selbst rendert - das Login-Formular, die Konto-Leiste, die Überschriften
// der Navigationsgruppen - dazu die beiden Sätze, auf die jedes Panel
// zugreifen darf: die Werkzeugleiste des Rich-Text-Editors
// (_admin/assets/html-editor.js) und /_admin/common/*, die Worte, die sonst
// in jedem Modul einzeln stünden.
return [
	'[[/_admin/login/label/user]]'	=> 'E-Mail',
	'[[/_admin/login/label/pw]]'		=> 'Passwort',
	'[[/_admin/login/label/submit]]'=> 'Anmelden',
	'[[/_admin/login/error/user]]'	=> 'E-Mail muss angegeben werden.',
	'[[/_admin/login/error/pw]]'		=> 'Passwort muss angegeben werden.',
	'[[/_admin/login/error/wrong]]'	=> 'Prüfen Sie Ihre Eingabe oder verständigen Sie den Administrator.',
	'[[/_admin/login/msg/welcome]]'	=> 'Geben Sie Ihre E-Mail und Ihr Passwort an:',
	'[[/_admin/login/msg/pending]]'	=> 'Eingabe wird geprüft.',

	'[[/_admin/user/logout]]'				=> 'Abmelden',
	'[[/_admin/user/theme]]'					=> 'Hell/Dunkel umschalten',
	'[[/_admin/user/settings]]'			=> 'Einstellungen',
	'[[/_admin/user/rail]]'					=> 'Navigation ein- oder ausklappen',

	'[[/_admin/label/rail]]'			=> 'Navigation der Workbench',
	'[[/_admin/label/nav]]'			=> 'Bereiche der Workbench',
	'[[/_admin/nav/group/content]]'		=> 'Inhalt',
	'[[/_admin/nav/group/structure]]'	=> 'Struktur',
	'[[/_admin/nav/group/system]]'		=> 'System',

	'[[/_admin/htmleditor/label/strong]]'	=> 'Fett',
	'[[/_admin/htmleditor/label/em]]'			=> 'Kursiv',
	'[[/_admin/htmleditor/label/span]]'		=> 'Hervorheben',
	'[[/_admin/htmleditor/label/code]]'		=> 'Code',
	'[[/_admin/htmleditor/label/a]]'				=> 'Link',
	'[[/_admin/htmleditor/label/linkplaceholder]]'	=> 'https://…',
	'[[/_admin/htmleditor/label/linkok]]'					=> 'Übernehmen',
	'[[/_admin/htmleditor/label/linkcancel]]'			=> 'Abbrechen',
	'[[/_admin/htmleditor/label/formatting]]'			=> 'Textformatierung',
	'[[/_admin/htmleditor/label/content]]'				=> 'Formatierter Text',

	'[[/_admin/common/label/image-target]]'		=> 'Soll-Maße:',
	'[[/_admin/common/label/locale]]'		=> 'Übersetzung',
	'[[/_admin/common/label/save]]'			=> 'Speichern',
	'[[/_admin/common/label/create]]'		=> 'Anlegen',
	'[[/_admin/common/label/delete]]'		=> 'Löschen',
	'[[/_admin/common/label/remove]]'		=> 'Entfernen',
	'[[/_admin/common/label/rename]]'		=> 'Umbenennen',
	'[[/_admin/common/label/add]]'			=> 'Hinzufügen',
	'[[/_admin/common/label/back]]'			=> 'Zurück zur Liste',
	'[[/_admin/common/label/moveup]]'		=> 'Nach oben',
	'[[/_admin/common/label/movedown]]'	=> 'Nach unten',
	'[[/_admin/common/label/ignore]]'		=> 'Ignorieren',
	'[[/_admin/common/label/uri]]'			=> 'Uri',
	'[[/_admin/common/label/label]]'		=> 'Bezeichnung',
	'[[/_admin/common/label/global]]'		=> 'Global',
	'[[/_admin/common/label/on]]'				=> 'an',
	'[[/_admin/common/label/off]]'			=> 'aus',
	'[[/_admin/common/msg/saving]]'			=> 'Wird gespeichert …',
	'[[/_admin/common/msg/saved]]'			=> 'Gespeichert.',
	'[[/_admin/common/msg/deleting]]'		=> 'Wird gelöscht …',
	'[[/_admin/common/msg/scanning]]'		=> 'Templates werden durchsucht …',
	'[[/_admin/common/msg/creating]]'		=> '%s wird angelegt (%d / %n) …',
	'[[/_admin/common/error/load]]'			=> 'Laden fehlgeschlagen.',
	'[[/_admin/common/error/save]]'			=> 'Speichern fehlgeschlagen.',
	'[[/_admin/common/error/delete]]'		=> 'Löschen fehlgeschlagen.',
	'[[/_admin/common/error/rename]]'		=> 'Umbenennen fehlgeschlagen.',
	'[[/_admin/common/error/scan]]'			=> 'Durchsuchen fehlgeschlagen.',
	'[[/_admin/common/error/request]]'	=> 'Anfrage fehlgeschlagen.',
	'[[/_admin/common/label/width]]'		=> 'Breite (px)',
	'[[/_admin/common/label/height]]'		=> 'Höhe (px)',
	'[[/_admin/common/label/restore]]'	=> 'Wiederherstellen',
	'[[/_admin/common/unit/seconds]]'		=> 'Sekunden',
];
