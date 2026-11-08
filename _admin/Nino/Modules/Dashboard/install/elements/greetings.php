<?php
// _admin/Nino/Modules/Dashboard/install/elements/greetings.php - the sentences
// the dashboard's greeting picks from, one element per sentence, in German and
// English, written for this module. Applied by \Nino\Modules\Dashboard\Admin::seedGreetings(),
// which only ever adds (see \Nino\Elements::seed()): a sentence an editor changed
// or deleted is not touched again.
//
// An element says when it fits: 'phase' the hour of the day (any, early 5-7,
// morning 8-10, noon 11-13, afternoon 14-16, evening 17-21, night 22-4), 'day'
// the weekday (any, monday, friday, weekend), 'occasion' a day of the year (none,
// christmas 24-26 December, newyear 31 December and 1 January). The script
// reads the clock of the browser and takes what fits: on a day with an
// occasion only the sentences of the occasion are taken. '%s' stands for the name of the account, and
// a sentence with it is not shown to an account without a name. A field left at
// its default ('any', 'none') is not written (see \Nino\Elements::seed()).
return [
	'title' => [ 'de_DE' => 'Begrüßungen', 'en_US' => 'Greetings' ],
	'model' => [
		'text' => [
			'type' 			=> 'string',
			'locale' 		=> true,
			'required' 	=> true,
			'maxlength' => 200,
		],
		'phase' => [
			'type' 			=> 'string',
			'default' 	=> 'any',
			'options' 	=> [ 'any', 'early', 'morning', 'noon', 'afternoon', 'evening', 'night' ],
		],
		'day' => [
			'type' 			=> 'string',
			'default' 	=> 'any',
			'options' 	=> [ 'any', 'monday', 'friday', 'weekend' ],
		],
		'occasion' => [
			'type' 			=> 'string',
			'default' 	=> 'none',
			'options' 	=> [ 'none', 'christmas', 'newyear' ],
		],
		'hidden' => [
			'type' 			=> 'boolean',
		],
	],
	'*' => [
		'*' => [
			'hidden' => false,
		],
		'early-01' => [
			'phase' => 'early',
		],
		'early-02' => [
			'phase' => 'early',
		],
		'early-03' => [
			'phase' => 'early',
		],
		'early-04' => [
			'phase' => 'early',
		],
		'early-05' => [
			'phase' => 'early',
		],
		'early-06' => [
			'phase' => 'early',
		],
		'early-07' => [
			'phase' => 'early',
		],
		'early-08' => [
			'phase' => 'early',
		],
		'early-09' => [
			'phase' => 'early',
		],
		'early-10' => [
			'phase' => 'early',
		],
		'morning-01' => [
			'phase' => 'morning',
		],
		'morning-02' => [
			'phase' => 'morning',
		],
		'morning-03' => [
			'phase' => 'morning',
		],
		'morning-04' => [
			'phase' => 'morning',
		],
		'morning-05' => [
			'phase' => 'morning',
		],
		'morning-06' => [
			'phase' => 'morning',
		],
		'morning-07' => [
			'phase' => 'morning',
		],
		'morning-08' => [
			'phase' => 'morning',
		],
		'morning-09' => [
			'phase' => 'morning',
		],
		'morning-10' => [
			'phase' => 'morning',
		],
		'morning-11' => [
			'phase' => 'morning',
		],
		'noon-01' => [
			'phase' => 'noon',
		],
		'noon-02' => [
			'phase' => 'noon',
		],
		'noon-03' => [
			'phase' => 'noon',
		],
		'noon-04' => [
			'phase' => 'noon',
		],
		'noon-05' => [
			'phase' => 'noon',
		],
		'noon-06' => [
			'phase' => 'noon',
		],
		'noon-07' => [
			'phase' => 'noon',
		],
		'noon-08' => [
			'phase' => 'noon',
		],
		'noon-09' => [
			'phase' => 'noon',
		],
		'noon-10' => [
			'phase' => 'noon',
		],
		'afternoon-01' => [
			'phase' => 'afternoon',
		],
		'afternoon-02' => [
			'phase' => 'afternoon',
		],
		'afternoon-03' => [
			'phase' => 'afternoon',
		],
		'afternoon-04' => [
			'phase' => 'afternoon',
		],
		'afternoon-05' => [
			'phase' => 'afternoon',
		],
		'afternoon-06' => [
			'phase' => 'afternoon',
		],
		'afternoon-07' => [
			'phase' => 'afternoon',
		],
		'afternoon-08' => [
			'phase' => 'afternoon',
		],
		'afternoon-09' => [
			'phase' => 'afternoon',
		],
		'afternoon-10' => [
			'phase' => 'afternoon',
		],
		'evening-01' => [
			'phase' => 'evening',
		],
		'evening-02' => [
			'phase' => 'evening',
		],
		'evening-03' => [
			'phase' => 'evening',
		],
		'evening-04' => [
			'phase' => 'evening',
		],
		'evening-05' => [
			'phase' => 'evening',
		],
		'evening-06' => [
			'phase' => 'evening',
		],
		'evening-07' => [
			'phase' => 'evening',
		],
		'evening-08' => [
			'phase' => 'evening',
		],
		'evening-09' => [
			'phase' => 'evening',
		],
		'evening-10' => [
			'phase' => 'evening',
		],
		'night-01' => [
			'phase' => 'night',
		],
		'night-02' => [
			'phase' => 'night',
		],
		'night-03' => [
			'phase' => 'night',
		],
		'night-04' => [
			'phase' => 'night',
		],
		'night-05' => [
			'phase' => 'night',
		],
		'night-06' => [
			'phase' => 'night',
		],
		'night-07' => [
			'phase' => 'night',
		],
		'night-08' => [
			'phase' => 'night',
		],
		'night-09' => [
			'phase' => 'night',
		],
		'night-10' => [
			'phase' => 'night',
		],
		'monday-01' => [
			'day' => 'monday',
		],
		'monday-02' => [
			'day' => 'monday',
		],
		'monday-03' => [
			'day' => 'monday',
		],
		'monday-04' => [
			'day' => 'monday',
		],
		'monday-05' => [
			'day' => 'monday',
		],
		'monday-06' => [
			'day' => 'monday',
		],
		'friday-01' => [
			'day' => 'friday',
		],
		'friday-02' => [
			'day' => 'friday',
		],
		'friday-03' => [
			'day' => 'friday',
		],
		'friday-04' => [
			'day' => 'friday',
		],
		'friday-05' => [
			'day' => 'friday',
		],
		'friday-06' => [
			'day' => 'friday',
		],
		'weekend-01' => [
			'day' => 'weekend',
		],
		'weekend-02' => [
			'day' => 'weekend',
		],
		'weekend-03' => [
			'day' => 'weekend',
		],
		'weekend-04' => [
			'day' => 'weekend',
		],
		'weekend-05' => [
			'day' => 'weekend',
		],
		'weekend-06' => [
			'day' => 'weekend',
		],
		'christmas-01' => [
			'occasion' => 'christmas',
		],
		'christmas-02' => [
			'occasion' => 'christmas',
		],
		'christmas-03' => [
			'occasion' => 'christmas',
		],
		'christmas-04' => [
			'occasion' => 'christmas',
		],
		'christmas-05' => [
			'occasion' => 'christmas',
		],
		'christmas-06' => [
			'occasion' => 'christmas',
		],
		'newyear-01' => [
			'occasion' => 'newyear',
		],
		'newyear-02' => [
			'occasion' => 'newyear',
		],
		'newyear-03' => [
			'occasion' => 'newyear',
		],
		'newyear-04' => [
			'occasion' => 'newyear',
		],
		'newyear-05' => [
			'occasion' => 'newyear',
		],
		'newyear-06' => [
			'occasion' => 'newyear',
		],
	],
	'de_DE' => [
		'early-01' => [ 'text' => 'Früh dran heute – der Tag gehört noch ganz Dir.' ],
		'early-02' => [ 'text' => '%s, die Website ist noch ganz still. Eine gute Gelegenheit.' ],
		'early-03' => [ 'text' => 'Noch ist alles ruhig. Fang an, wo Du magst.' ],
		'early-04' => [ 'text' => 'Der Tag fängt gerade erst an – und Du bist schon da.' ],
		'early-05' => [ 'text' => '%s, vor allen anderen. Das hat etwas.' ],
		'early-06' => [ 'text' => 'Ein frischer Morgen und frische Seiten.' ],
		'early-07' => [ 'text' => 'Es ist noch früh. Nimm Dir die Zeit, die Du brauchst.' ],
		'early-08' => [ 'text' => '%s, schön, dass Du den Tag hier beginnst.' ],
		'early-09' => [ 'text' => 'Noch kein Besucher in Sicht – die Bühne gehört Dir.' ],
		'early-10' => [ 'text' => 'Der erste Eintrag des Tages wartet auf Dich.' ],
		'morning-01' => [ 'text' => 'Ein guter Tag, um etwas fertigzustellen.' ],
		'morning-02' => [ 'text' => '%s, schön, Dich zu sehen.' ],
		'morning-03' => [ 'text' => 'Alles an seinem Platz – bereit, wenn Du es bist.' ],
		'morning-04' => [ 'text' => 'Heute darf ruhig etwas Neues entstehen.' ],
		'morning-05' => [ 'text' => '%s, die Website hat schon auf Dich gewartet.' ],
		'morning-06' => [ 'text' => 'Ein Schritt nach dem anderen, dann klappt der Rest von selbst.' ],
		'morning-07' => [ 'text' => 'Die Liste ist lang, aber Du hast den ganzen Tag.' ],
		'morning-08' => [ 'text' => '%s, was steht heute an?' ],
		'morning-09' => [ 'text' => 'Frisch ans Werk.' ],
		'morning-10' => [ 'text' => 'Es ist Zeit für gute Inhalte.' ],
		'morning-11' => [ 'text' => 'Kurz umschauen, dann loslegen.' ],
		'noon-01' => [ 'text' => 'Halbzeit – und es läuft.' ],
		'noon-02' => [ 'text' => '%s, die Tagesmitte ist ein guter Moment für einen Überblick.' ],
		'noon-03' => [ 'text' => 'Was bis jetzt fertig ist, darf sich sehen lassen.' ],
		'noon-04' => [ 'text' => 'Mittagszeit. Die Seiten laufen auch ohne Dich.' ],
		'noon-05' => [ 'text' => '%s, der Tag steht auf seiner Höhe.' ],
		'noon-06' => [ 'text' => 'Zeit, den Stand der Dinge anzusehen.' ],
		'noon-07' => [ 'text' => 'Was heute noch fehlt, passt bestimmt noch hinein.' ],
		'noon-08' => [ 'text' => 'Gut in der Zeit.' ],
		'noon-09' => [ 'text' => 'Ein Blick aufs Dashboard, dann weiter im Takt.' ],
		'noon-10' => [ 'text' => '%s, schön, dass Du zwischendurch vorbeischaust.' ],
		'afternoon-01' => [ 'text' => 'Der Nachmittag gehört den Details.' ],
		'afternoon-02' => [ 'text' => '%s, es ist noch genug Tag übrig.' ],
		'afternoon-03' => [ 'text' => 'Ruhig und konzentriert entsteht das Beste.' ],
		'afternoon-04' => [ 'text' => 'Ein Text hier, ein Bild dort – es kommt zusammen.' ],
		'afternoon-05' => [ 'text' => '%s, wie läuft es?' ],
		'afternoon-06' => [ 'text' => 'Gut, dass Du dranbleibst.' ],
		'afternoon-07' => [ 'text' => 'Der Tag hat noch ein, zwei gute Stunden.' ],
		'afternoon-08' => [ 'text' => 'Hier ist es still – ideal zum Schreiben.' ],
		'afternoon-09' => [ 'text' => '%s, bald ist es geschafft.' ],
		'afternoon-10' => [ 'text' => 'Was jetzt fertig wird, muss morgen nicht mehr getan werden.' ],
		'evening-01' => [ 'text' => 'Feierabend in Sicht – oder noch ein Eintrag?' ],
		'evening-02' => [ 'text' => '%s, schön, dass Du am Abend noch vorbeischaust.' ],
		'evening-03' => [ 'text' => 'Der Tag war lang genug. Was noch fehlt, hat Zeit.' ],
		'evening-04' => [ 'text' => 'Zeit für einen letzten Blick.' ],
		'evening-05' => [ 'text' => '%s, ein guter Moment, den Tag abzuschließen.' ],
		'evening-06' => [ 'text' => 'Was heute geschafft ist, bleibt geschafft.' ],
		'evening-07' => [ 'text' => 'Ein ruhiger Abend auf der Website.' ],
		'evening-08' => [ 'text' => '%s, der Abend gehört Dir.' ],
		'evening-09' => [ 'text' => 'Morgen ist auch noch ein Tag.' ],
		'evening-10' => [ 'text' => 'Noch kurz nachsehen, dann Schluss.' ],
		'night-01' => [ 'text' => 'Es ist spät. Alles Wichtige hat bis morgen Zeit.' ],
		'night-02' => [ 'text' => '%s, so spät noch unterwegs?' ],
		'night-03' => [ 'text' => 'Ganz leise hier um diese Zeit.' ],
		'night-04' => [ 'text' => 'Die Nacht gehört den Nachtschwärmern.' ],
		'night-05' => [ 'text' => '%s, der Server hält Wache.' ],
		'night-06' => [ 'text' => 'Nachts arbeitet es sich ungestört.' ],
		'night-07' => [ 'text' => 'Alles ruhig, kein Besucher in Sicht.' ],
		'night-08' => [ 'text' => '%s, schön, dass Du da bist, auch zu dieser Stunde.' ],
		'night-09' => [ 'text' => 'Noch ein letzter Handgriff?' ],
		'night-10' => [ 'text' => 'Die Seiten sind geduldig – sie warten bis morgen.' ],
		'monday-01' => [ 'text' => 'Montag. Die Woche beginnt, sobald Du hier bist.' ],
		'monday-02' => [ 'text' => '%s, eine neue Woche, ein leeres Blatt.' ],
		'monday-03' => [ 'text' => 'Der Montag darf ruhig langsam beginnen.' ],
		'monday-04' => [ 'text' => 'Neue Woche, neue Möglichkeiten.' ],
		'monday-05' => [ 'text' => '%s, die Woche liegt noch ganz vor Dir.' ],
		'monday-06' => [ 'text' => 'Erst einmal ankommen – die Woche ist lang genug.' ],
		'friday-01' => [ 'text' => 'Freitag – fast geschafft.' ],
		'friday-02' => [ 'text' => '%s, noch ein Eintrag, dann ist Wochenende.' ],
		'friday-03' => [ 'text' => 'Die Woche war gut. Der Freitag macht sie rund.' ],
		'friday-04' => [ 'text' => 'Zum Wochenende alles aufgeräumt?' ],
		'friday-05' => [ 'text' => '%s, der Freitag ist der schönste Arbeitstag.' ],
		'friday-06' => [ 'text' => 'Was heute fertig wird, kostet das Wochenende nichts.' ],
		'weekend-01' => [ 'text' => 'Wochenende – und Du bist trotzdem hier. Alle Achtung.' ],
		'weekend-02' => [ 'text' => '%s, schön, dass Du Dir am Wochenende Zeit dafür nimmst.' ],
		'weekend-03' => [ 'text' => 'Ohne Eile – es ist Wochenende.' ],
		'weekend-04' => [ 'text' => 'Ein ruhiger Tag auf der Website.' ],
		'weekend-05' => [ 'text' => '%s, heute zählt nur, was Dir Freude macht.' ],
		'weekend-06' => [ 'text' => 'Am Wochenende darf alles ein bisschen länger dauern.' ],
		'christmas-01' => [ 'text' => 'Schöne Feiertage – und danke für Deinen Einsatz in diesem Jahr.' ],
		'christmas-02' => [ 'text' => '%s, schöne Feiertage Dir!' ],
		'christmas-03' => [ 'text' => 'Heute darf die Website einfach einmal still sein.' ],
		'christmas-04' => [ 'text' => 'Draußen ist es still, und hier auch.' ],
		'christmas-05' => [ 'text' => '%s, die Feiertage gehören Dir und den Deinen.' ],
		'christmas-06' => [ 'text' => 'Ein ruhiger Tag – nutze ihn, wie Du magst.' ],
		'newyear-01' => [ 'text' => 'Jahreswechsel: ein Jahr hinter Dir, eins vor Dir.' ],
		'newyear-02' => [ 'text' => '%s, auf ein gutes neues Jahr.' ],
		'newyear-03' => [ 'text' => 'Ein Jahr voller Einträge – Zeit für einen frischen Anfang.' ],
		'newyear-04' => [ 'text' => 'Alles auf Anfang, und Du bist schon dabei.' ],
		'newyear-05' => [ 'text' => '%s, was nimmst Du aus dem Jahr mit?' ],
		'newyear-06' => [ 'text' => 'Der Jahreswechsel ist ein guter Moment für einen Rückblick.' ],
	],
	'en_US' => [
		'early-01' => [ 'text' => 'Early start today. The day is still all yours.' ],
		'early-02' => [ 'text' => '%s, the website is still quiet. A good moment to begin.' ],
		'early-03' => [ 'text' => 'Everything is quiet still. Start wherever you like.' ],
		'early-04' => [ 'text' => 'The day has only just begun, and you are already here.' ],
		'early-05' => [ 'text' => '%s, ahead of everyone else. There is something to that.' ],
		'early-06' => [ 'text' => 'A fresh morning and fresh pages.' ],
		'early-07' => [ 'text' => 'It is early. Take the time you need.' ],
		'early-08' => [ 'text' => '%s, good to see you start the day here.' ],
		'early-09' => [ 'text' => 'Not a visitor in sight yet. The stage is yours.' ],
		'early-10' => [ 'text' => 'The first entry of the day is waiting for you.' ],
		'morning-01' => [ 'text' => 'A good day to finish something.' ],
		'morning-02' => [ 'text' => '%s, good to see you.' ],
		'morning-03' => [ 'text' => 'Everything in its place, ready when you are.' ],
		'morning-04' => [ 'text' => 'Today is a fine day for something new.' ],
		'morning-05' => [ 'text' => '%s, the website has been waiting for you.' ],
		'morning-06' => [ 'text' => 'One step at a time, and the rest follows.' ],
		'morning-07' => [ 'text' => 'The list is long, but you have the whole day.' ],
		'morning-08' => [ 'text' => '%s, what is on today?' ],
		'morning-09' => [ 'text' => 'Off to work, then.' ],
		'morning-10' => [ 'text' => 'Time for good content.' ],
		'morning-11' => [ 'text' => 'A quick look around, then off you go.' ],
		'noon-01' => [ 'text' => 'Halfway through the day, and it is going well.' ],
		'noon-02' => [ 'text' => '%s, midday is a good moment to take stock.' ],
		'noon-03' => [ 'text' => 'What is done so far is worth seeing.' ],
		'noon-04' => [ 'text' => 'Lunchtime. The pages run fine without you.' ],
		'noon-05' => [ 'text' => '%s, the day is at its height.' ],
		'noon-06' => [ 'text' => 'Time to look at where things stand.' ],
		'noon-07' => [ 'text' => 'Whatever is still missing will surely fit into today.' ],
		'noon-08' => [ 'text' => 'Well on schedule.' ],
		'noon-09' => [ 'text' => 'A glance at the dashboard, then back to the rhythm.' ],
		'noon-10' => [ 'text' => '%s, nice of you to drop in.' ],
		'afternoon-01' => [ 'text' => 'The afternoon belongs to the details.' ],
		'afternoon-02' => [ 'text' => '%s, there is plenty of day left.' ],
		'afternoon-03' => [ 'text' => 'Calm and focused is how the best work gets made.' ],
		'afternoon-04' => [ 'text' => 'A text here, an image there: it all comes together.' ],
		'afternoon-05' => [ 'text' => '%s, how is it going?' ],
		'afternoon-06' => [ 'text' => 'Good that you keep at it.' ],
		'afternoon-07' => [ 'text' => 'The day still has a good hour or two.' ],
		'afternoon-08' => [ 'text' => 'It is quiet here, ideal for writing.' ],
		'afternoon-09' => [ 'text' => '%s, nearly there.' ],
		'afternoon-10' => [ 'text' => 'What gets finished now does not need doing tomorrow.' ],
		'evening-01' => [ 'text' => 'Closing time is in sight, or one more entry?' ],
		'evening-02' => [ 'text' => '%s, good of you to stop by this evening.' ],
		'evening-03' => [ 'text' => 'The day was long enough. What is missing can wait.' ],
		'evening-04' => [ 'text' => 'Time for one last look.' ],
		'evening-05' => [ 'text' => '%s, a good moment to round off the day.' ],
		'evening-06' => [ 'text' => 'What is done today stays done.' ],
		'evening-07' => [ 'text' => 'A quiet evening on the website.' ],
		'evening-08' => [ 'text' => '%s, the evening is yours.' ],
		'evening-09' => [ 'text' => 'Tomorrow is another day.' ],
		'evening-10' => [ 'text' => 'A quick check, then done.' ],
		'night-01' => [ 'text' => 'It is late. Anything important can wait until tomorrow.' ],
		'night-02' => [ 'text' => '%s, still around this late?' ],
		'night-03' => [ 'text' => 'Very quiet here at this hour.' ],
		'night-04' => [ 'text' => 'The night belongs to the night owls.' ],
		'night-05' => [ 'text' => '%s, the server keeps watch.' ],
		'night-06' => [ 'text' => 'Nights are good for undisturbed work.' ],
		'night-07' => [ 'text' => 'All quiet, not a visitor in sight.' ],
		'night-08' => [ 'text' => '%s, good to have you here, even at this hour.' ],
		'night-09' => [ 'text' => 'One last touch?' ],
		'night-10' => [ 'text' => 'The pages are patient and will wait until tomorrow.' ],
		'monday-01' => [ 'text' => 'Monday. The week has begun once you are here.' ],
		'monday-02' => [ 'text' => '%s, a new week, a clean sheet.' ],
		'monday-03' => [ 'text' => 'Monday may well start slowly.' ],
		'monday-04' => [ 'text' => 'New week, new possibilities.' ],
		'monday-05' => [ 'text' => '%s, the whole week is still ahead of you.' ],
		'monday-06' => [ 'text' => 'Arrive first. The week is long enough.' ],
		'friday-01' => [ 'text' => 'Friday, almost there.' ],
		'friday-02' => [ 'text' => '%s, one more entry and it is the weekend.' ],
		'friday-03' => [ 'text' => 'The week has been good. Friday rounds it off.' ],
		'friday-04' => [ 'text' => 'Everything tidy for the weekend?' ],
		'friday-05' => [ 'text' => '%s, Friday is the nicest working day.' ],
		'friday-06' => [ 'text' => 'What gets finished today costs the weekend nothing.' ],
		'weekend-01' => [ 'text' => 'The weekend, and you are here anyway. Respect.' ],
		'weekend-02' => [ 'text' => '%s, nice that you make time for this at the weekend.' ],
		'weekend-03' => [ 'text' => 'No hurry, it is the weekend.' ],
		'weekend-04' => [ 'text' => 'A quiet day on the website.' ],
		'weekend-05' => [ 'text' => '%s, today only what you enjoy counts.' ],
		'weekend-06' => [ 'text' => 'At the weekend everything may take a little longer.' ],
		'christmas-01' => [ 'text' => 'Happy holidays, and thank you for all you did this year.' ],
		'christmas-02' => [ 'text' => '%s, happy holidays to you!' ],
		'christmas-03' => [ 'text' => 'Today the website may simply be quiet.' ],
		'christmas-04' => [ 'text' => 'It is quiet outside, and quiet here.' ],
		'christmas-05' => [ 'text' => '%s, the holidays belong to you and yours.' ],
		'christmas-06' => [ 'text' => 'A quiet day. Use it as you like.' ],
		'newyear-01' => [ 'text' => 'The turn of the year: one year behind you, one ahead.' ],
		'newyear-02' => [ 'text' => '%s, here is to a good new year.' ],
		'newyear-03' => [ 'text' => 'A year full of entries, and time for a fresh start.' ],
		'newyear-04' => [ 'text' => 'Back to the start, and you are already at it.' ],
		'newyear-05' => [ 'text' => '%s, what do you take with you from the year?' ],
		'newyear-06' => [ 'text' => 'The turn of the year is a good moment to look back.' ],
	],
];
