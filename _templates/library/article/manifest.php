<?php return [
	'category' 	=> 'Content',
	'tag' 			=> 'wrap',
	'name' 			=> 'Article',
	'match' 		=> [
		'tag' 			=> 'article',
		'classes' 	=> [ 'ui-article' ],
	],
	'children' 	=> [ '*' ],
	'use' 			=> [ 'spacing', 'vpa' ],
	'settings' 	=> [
		// These modifiers deliberately are independent: Nino.css supports
		// combinations such as ui-article--fullwidth.ui-article--alt.
		'alt' 			=> [ 'label' => 'Alt style', 	'type' => 'classtoggle', 'class' => 'ui-article--alt' ],
		'fullwidth' => [ 'label' => 'Full width', 'type' => 'classtoggle', 'class' => 'ui-article--fullwidth' ],
		'wide' 			=> [ 'label' => 'Wide', 			'type' => 'classtoggle', 'class' => 'ui-article--wide' ],
		'columns' 	=> [ 'label' => 'Columns', 		'type' => 'classtoggle', 'class' => 'ui-article-cols' ],
		'columnsS' 	=> [ 'label' => 'Columns (s)', 	'type' => 'classtoggle', 'class' => 'ui-article-cols-s' ],
		'columnsM' 	=> [ 'label' => 'Columns (m)', 	'type' => 'classtoggle', 'class' => 'ui-article-cols-m' ],
		'columnsL' 	=> [ 'label' => 'Columns (l)', 	'type' => 'classtoggle', 'class' => 'ui-article-cols-l' ],
		'columnsXl' => [ 'label' => 'Columns (xl)', 'type' => 'classtoggle', 'class' => 'ui-article-cols-xl' ],
	],
];
