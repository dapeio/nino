<?php
// Die eigenen Workbench-Texte des Navigation-Moduls, in seine Fills
// gemischt, solange das Modul aktiv ist (siehe text() des Panels) -
// dieselben Schlüssel und dieselbe Form wie text/<locale>.php der Workbench
return [
	'[[/_admin/nav/navs]]'							=> 'Navigationen',
	'[[/_admin/navs/inactive]]'					=> 'Das Navigation-Modul ist nicht aktiv – diese Menüs sind gespeichert, aber nichts rendert sie. Aktiviere \\Nino\\Modules\\Navigation unter Konfiguration.',
	'[[/_admin/navs/empty]]'						=> 'Noch keine Navigationen – lege unten eine an.',
	'[[/_admin/navs/empty-entries]]'		=> 'Noch keine Einträge – füge unten eine Route hinzu.',
	'[[/_admin/navs/label/new]]'				=> 'Neue Navigation',
	'[[/_admin/navs/label/navigation]]'	=> 'Navigation',
	'[[/_admin/navs/label/key]]'				=> 'Navigations-Kennung – der Name, den ein Template mit dem navigation-Shortcode abfragt, z. B. „main“',
	'[[/_admin/navs/placeholder/key]]'	=> 'Navigations-Kennung, z. B. „footer“',
	'[[/_admin/navs/hint/rename]]'			=> 'Umbenennen aktualisiert die Routen in dieser Navigation, nicht die Templates, die sie rendern – passe das nav-Argument ihres navigation-Shortcodes selbst an.',
	'[[/_admin/navs/label/delete]]'			=> 'Navigation löschen',
	'[[/_admin/navs/label/entries]]'		=> 'Einträge',
	'[[/_admin/navs/label/unnamed]]'		=> '– noch ohne Namen, bleibt deshalb aus dem Menü',
	'[[/_admin/navs/label/unnamed-short]]'	=> '– noch ohne Namen',
	'[[/_admin/navs/label/moveup]]'			=> 'In dieser Navigation nach oben',
	'[[/_admin/navs/label/movedown]]'		=> 'In dieser Navigation nach unten',
	'[[/_admin/navs/label/remove]]'			=> 'Aus dieser Navigation entfernen – die Route selbst bleibt',
	'[[/_admin/navs/label/add]]'				=> 'Eine Route hinzufügen – sie kommt ans Ende',
	'[[/_admin/navs/label/addbtn]]'			=> 'Zur Navigation hinzufügen',
	'[[/_admin/navs/confirm/delete]]'		=> 'Die Navigation „%s“ wirklich löschen? Ihre Routen bleiben, nur die Zugehörigkeit geht.',
];
