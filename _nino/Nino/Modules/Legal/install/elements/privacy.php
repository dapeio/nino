<?php
// _nino/Nino/Modules/Legal/install/elements/privacy.php - the privacy policy: one
// element per section, in German and English, written for this module. Applied
// by \Nino\Features::applyUnit() through the unit's 'elements' key, which
// only ever adds (see \Nino\Elements::seed()): an element an editor changed
// or deleted is not touched again.
//
// 'order' is the position, a hundred apart so a section fits between two.
// The texts are in the form the sanitizer gives a field with 'blocks' -
// paragraphs, lists, strong, br, and links to the anchor of another section -
// and carry no '&', no entity and no '[': what #/project/...# names is put in
// when a page is drawn (see \Nino\Modules\Legal::placeholders()).
return [
	'title' => [ 'de_DE' => 'Datenschutzerklärung', 'en_US' => 'Privacy policy' ],
	'model' => [
		'title' => [
			'type' 			=> 'string',
			'locale' 		=> true,
			'required' 	=> true,
			'maxlength' => 150,
		],
		'text' => [
			'type' 			=> 'string',
			'locale' 		=> true,
			'required' 	=> true,
			'html' 			=> true,
			'blocks' 		=> true,
			'maxlength' => 8000,
			'inputsize' => 12,
		],
		'order' => [
			'type' 			=> 'integer',
		],
		'hidden' => [
			'type' 			=> 'boolean',
		],
	],
	'*' => [
		'*' => [
			'hidden' => false,
		],
		'overview' => [
			'order' => 100,
		],
		'collection' => [
			'order' => 110,
		],
		'hosting' => [
			'order' => 200,
		],
		'general' => [
			'order' => 300,
		],
		'responsible' => [
			'order' => 310,
		],
		'storage' => [
			'order' => 320,
		],
		'rights' => [
			'order' => 330,
		],
		'objection' => [
			'order' => 340,
		],
		'cookies' => [
			'order' => 400,
		],
		'server-logs' => [
			'order' => 410,
		],
		'contact-form' => [
			'order' => 500,
		],
		'contact' => [
			'order' => 510,
		],
		'tls' => [
			'order' => 900,
		],
	],
	'de_DE' => [
		'overview' => [
			'title' => 'Worum es hier geht',
			'text' 	=> '<p>Diese Erklärung informiert Dich nach der Datenschutz-Grundverordnung (DSGVO) darüber, welche personenbezogenen Daten beim Besuch dieser Website anfallen, wozu wir sie verwenden und welche Rechte Du hast. Personenbezogen ist jede Angabe, die sich Dir als Person zuordnen lässt, etwa Dein Name, Deine E-Mail-Adresse oder die IP-Adresse Deines Geräts.</p>',
		],
		'collection' => [
			'title' => 'Das Wichtigste in Kürze',
			'text' 	=> '<ul><li><strong>Verantwortlich:</strong> der Betreiber dieser Website. Wie Du ihn erreichst, steht unter „<a href="#privacy-responsible">Verantwortlicher</a>“.</li><li><strong>Von Dir:</strong> alles, was Du uns schickst, zum Beispiel eine Nachricht über das Kontaktformular.</li><li><strong>Automatisch:</strong> technische Angaben, die bei jedem Seitenaufruf anfallen, etwa Deine IP-Adresse, Browser, Betriebssystem und Zeitpunkt.</li><li><strong>Wofür:</strong> Die technischen Angaben brauchen wir, um die Website fehlerfrei und sicher auszuliefern, Deine Nachrichten, um Dein Anliegen zu bearbeiten. Weitere Zwecke nennen die folgenden Abschnitte.</li><li><strong>Deine Rechte:</strong> von Auskunft, Berichtigung und Löschung über Einschränkung, Datenübertragbarkeit und Widerspruch bis zum Widerruf einer Einwilligung und zur Beschwerde bei einer Aufsichtsbehörde. Näheres unter „<a href="#privacy-rights">Deine Rechte</a>“ und „<a href="#privacy-objection">Widerspruchsrecht</a>“.</li></ul>',
		],
		'hosting' => [
			'title' => 'Hosting',
			'text' 	=> '<p>Diese Website läuft auf Servern eines Hosting-Dienstleisters. Er verarbeitet in unserem Auftrag alles, was beim Besuch der Website anfällt: vor allem IP-Adressen und andere technische Angaben zu den Aufrufen, aber auch Nachrichten, die Du uns über die Website schickst.</p><p>Wir setzen den Dienstleister ein, damit die Website zuverlässig, schnell und sicher erreichbar ist. Rechtsgrundlage ist unser berechtigtes Interesse daran (Art. 6 Abs. 1 lit. f DSGVO); geht es um die Anbahnung oder Erfüllung eines Vertrags mit Dir, außerdem Art. 6 Abs. 1 lit. b DSGVO.</p><p>Mit dem Dienstleister haben wir vereinbart, dass er die Daten nur nach unseren Weisungen und nur für seine Leistung verarbeitet (Auftragsverarbeitung nach Art. 28 DSGVO).</p><p><strong>Hosting-Dienstleister:</strong><br>#/project/website/general/host#</p>',
		],
		'general' => [
			'title' => 'Grundsätze',
			'text' 	=> '<p>Wir verarbeiten personenbezogene Daten nur, soweit eine Rechtsgrundlage es erlaubt, und behandeln sie vertraulich. In dieser Erklärung steht, welche Daten wir erheben, wozu wir sie verwenden und auf welcher Grundlage das geschieht.</p><p>Keine Übertragung im Internet ist vollkommen sicher. Gerade eine unverschlüsselte E-Mail können Dritte unterwegs mitlesen; vertrauliche Angaben schickst Du uns deshalb besser auf einem geschützten Weg.</p>',
		],
		'responsible' => [
			'title' => 'Verantwortlicher',
			'text' 	=> '<p>Verantwortlich für die Verarbeitung Deiner Daten auf dieser Website im Sinne der DSGVO ist:</p><p>#/project/website/general/author#<br>#/project/company/general/name#<br>#/project/company/contact/address#</p><p>Telefon: #/project/company/contact/phone#<br>E-Mail: #/project/company/contact/email#</p>',
		],
		'storage' => [
			'title' => 'Speicherdauer',
			'text' 	=> '<p>Wir bewahren personenbezogene Daten nur so lange auf, wie wir sie für den jeweiligen Zweck brauchen, außer ein Abschnitt dieser Erklärung nennt eine eigene Frist. Widerrufst Du eine Einwilligung oder verlangst Du berechtigt die Löschung, löschen wir die Daten, sofern wir sie nicht aus einem anderen rechtlichen Grund weiter aufbewahren müssen oder dürfen, etwa wegen steuer- oder handelsrechtlicher Aufbewahrungsfristen. Dann löschen wir sie, sobald dieser Grund entfällt.</p>',
		],
		'rights' => [
			'title' => 'Deine Rechte',
			'text' 	=> '<p>Soweit die gesetzlichen Voraussetzungen erfüllt sind, hast Du uns gegenüber diese Rechte:</p><ul><li><strong>Auskunft</strong>, ob und welche Daten wir über Dich verarbeiten (Art. 15 DSGVO)</li><li><strong>Berichtigung</strong> unrichtiger und Vervollständigung unvollständiger Daten (Art. 16 DSGVO)</li><li><strong>Löschung</strong> Deiner Daten (Art. 17 DSGVO)</li><li><strong>Einschränkung</strong> der Verarbeitung (Art. 18 DSGVO)</li><li><strong>Datenübertragbarkeit</strong>: Daten, die Du uns bereitgestellt hast, in einem gängigen, maschinenlesbaren Format zu erhalten oder an einen anderen Verantwortlichen übermitteln zu lassen (Art. 20 DSGVO)</li><li><strong>Widerspruch</strong> gegen eine Verarbeitung, siehe „<a href="#privacy-objection">Widerspruchsrecht</a>“ (Art. 21 DSGVO)</li><li><strong>Widerruf</strong> einer Einwilligung mit Wirkung für die Zukunft; was bis dahin geschehen ist, bleibt rechtmäßig (Art. 7 Abs. 3 DSGVO)</li></ul><p>Um ein Recht geltend zu machen, genügt eine Nachricht an die Kontaktdaten unter „<a href="#privacy-responsible">Verantwortlicher</a>“; Kosten entstehen Dir dafür nicht. Außerdem kannst Du Dich bei einer Datenschutz-Aufsichtsbehörde beschweren, insbesondere in dem Mitgliedstaat, in dem Du wohnst oder arbeitest oder in dem der mutmaßliche Verstoß geschah (Art. 77 DSGVO).</p>',
		],
		'objection' => [
			'title' => 'Widerspruchsrecht',
			'text' 	=> '<p><strong>Stützen wir eine Verarbeitung auf unser berechtigtes Interesse (Art. 6 Abs. 1 lit. f DSGVO), kannst Du ihr jederzeit aus Gründen widersprechen, die sich aus Deiner besonderen Situation ergeben.</strong> Wir verarbeiten die Daten dann nicht weiter, es sei denn, wir weisen zwingende schutzwürdige Gründe nach, die Deine Interessen, Rechte und Freiheiten überwiegen, oder die Verarbeitung dient der Geltendmachung, Ausübung oder Verteidigung von Rechtsansprüchen (Art. 21 Abs. 1 DSGVO).</p><p><strong>Verarbeiten wir Daten für Direktwerbung, kannst Du dem jederzeit ohne Begründung widersprechen.</strong> Danach verwenden wir sie dafür nicht mehr (Art. 21 Abs. 2 und 3 DSGVO).</p><p>Für einen Widerspruch genügt eine formlose Nachricht an die Kontaktdaten unter „<a href="#privacy-responsible">Verantwortlicher</a>“.</p>',
		],
		'cookies' => [
			'title' => 'Cookies und lokale Speicherung',
			'text' 	=> '<p>Cookies sind kleine Dateien, die eine Website in Deinem Browser ablegt. Diese Website setzt von sich aus nur ein einziges: ein Sitzungs-Cookie, das Deine Sprachwahl, den Schutz von Formularen vor gefälschten Absendungen und eine Anmeldung zusammenhält. Es entsteht erst, wenn Du eine Sprache wählst (auch, indem Du eine Seite in einer anderen Sprache aufrufst), Dich anmeldest oder eine Seite mit einem Formular öffnest, und es wird gelöscht, wenn Du den Browser schließt.</p><p>Bietet die Website Einstellungen an, die Du selbst wählst, etwa ein dunkles Farbschema, merkt sie sich Deine Wahl nur in Deinem Browser (lokaler Speicher). Diese Angabe wird nicht an uns übertragen.</p><p>Diese Speicherungen sind technisch erforderlich, damit die Website die Funktionen bereitstellen kann, die Du nutzt. Rechtsgrundlage ist § 25 Abs. 2 Nr. 2 Telekommunikation-Digitale-Dienste-Datenschutz-Gesetz (TDDDG), für die damit verbundene Verarbeitung unser berechtigtes Interesse an einer funktionierenden Website (Art. 6 Abs. 1 lit. f DSGVO).</p><p>Weitere Cookies oder Speicherungen gibt es nur, wo ein folgender Abschnitt sie beschreibt, und dann mit Deiner Einwilligung, soweit sie nötig ist.</p>',
		],
		'server-logs' => [
			'title' => 'Zugriffsprotokolle',
			'text' 	=> '<p>Bei jedem Aufruf einer Seite übermittelt Dein Browser technische Angaben, die der Server in Protokolldateien festhält:</p><ul><li>der Browser und seine Version</li><li>das Betriebssystem</li><li>die Seite, von der Du kommst (Referrer)</li><li>der Name des anfragenden Rechners (Hostname)</li><li>Datum und Uhrzeit des Aufrufs</li><li>die IP-Adresse</li></ul><p>Wir führen diese Angaben nicht mit anderen Datenquellen zusammen. Wir brauchen sie, um die Website technisch fehlerfrei auszuliefern, Störungen zu finden und Angriffe abzuwehren. Rechtsgrundlage ist unser berechtigtes Interesse daran (Art. 6 Abs. 1 lit. f DSGVO).</p>',
		],
		'contact-form' => [
			'title' => 'Kontaktformular',
			'text' 	=> '<p>Schreibst Du uns über das Kontaktformular, speichern wir Deine Angaben einschließlich der Kontaktdaten, die Du dort einträgst, um Deine Anfrage und mögliche Rückfragen zu bearbeiten. Zusammen mit der Nachricht halten wir Datum, Uhrzeit und Deine IP-Adresse fest. Zur Bestätigung erhältst Du eine E-Mail mit einer Zusammenfassung Deiner Angaben an die Adresse, die Du eingetragen hast. An andere geben wir Deine Angaben nur mit Deiner Einwilligung weiter.</p><p>Damit das Formular nicht missbraucht wird, zählt die Website eine Stunde lang, wie oft von Deiner IP-Adresse aus gesendet wurde.</p><p>Hängt Deine Anfrage mit einem Vertrag zusammen oder dient sie vorvertraglichen Schritten, ist Rechtsgrundlage Art. 6 Abs. 1 lit. b DSGVO. Sonst stützen wir uns auf unser berechtigtes Interesse, Anfragen zu beantworten und das Formular vor Missbrauch zu schützen (Art. 6 Abs. 1 lit. f DSGVO), oder auf Deine Einwilligung, wenn wir Dich darum gebeten haben (Art. 6 Abs. 1 lit. a DSGVO). Eine Einwilligung kannst Du jederzeit widerrufen.</p><p>Die auf der Website gespeicherten Anfragen löschen wir nach drei Monaten automatisch, auf Deinen Wunsch oder nach einem Widerruf auch früher. Was wir aus gesetzlichen Gründen länger aufbewahren müssen, etwa wegen handels- oder steuerrechtlicher Fristen, löschen wir nach deren Ablauf. Die Nachricht erreicht uns außerdem per E-Mail; für diese Kopie gilt der Abschnitt „<a href="#privacy-contact">Kontakt per E-Mail oder Telefon</a>“.</p>',
		],
		'contact' => [
			'title' => 'Kontakt per E-Mail oder Telefon',
			'text' 	=> '<p>Schreibst Du uns eine E-Mail oder rufst Du an, verarbeiten wir Deine Anfrage mit allen Angaben, die Du dabei machst, etwa Deinen Namen und Dein Anliegen, um sie zu beantworten. An andere geben wir diese Angaben nur mit Deiner Einwilligung weiter.</p><p>Als Rechtsgrundlage gilt dasselbe wie beim Kontaktformular: Art. 6 Abs. 1 lit. b DSGVO, wenn es um einen Vertrag oder vorvertragliche Schritte geht, sonst unser berechtigtes Interesse, Anfragen zu beantworten (Art. 6 Abs. 1 lit. f DSGVO), oder Deine Einwilligung (Art. 6 Abs. 1 lit. a DSGVO), die Du jederzeit widerrufen kannst.</p><p>Wir löschen Deine Anfrage, sobald sie erledigt ist und keine Rückfragen mehr zu erwarten sind, auf Deinen Wunsch oder nach einem Widerruf auch früher. Unterliegt sie einer gesetzlichen Aufbewahrungspflicht, löschen wir sie erst nach deren Ablauf.</p>',
		],
		'tls' => [
			'title' => 'Verschlüsselte Übertragung',
			'text' 	=> '<p>Diese Website überträgt ihre Inhalte verschlüsselt (TLS, früher SSL genannt). Dadurch bleibt für Dritte unlesbar, was Du uns schickst, etwa eine Nachricht über das Kontaktformular. Ob die Verbindung verschlüsselt ist, zeigt Dir Dein Browser: Die Adresse beginnt mit „https://“, und in der Adresszeile erscheint ein Schlosssymbol.</p>',
		],
	],
	'en_US' => [
		'overview' => [
			'title' => 'About this policy',
			'text' 	=> '<p>This policy tells you, as the General Data Protection Regulation (GDPR) requires, which personal data arise when you use this website, what we use them for and which rights you have. Personal data means any information that can be linked to you as a person, such as your name, your email address or the IP address of your device.</p>',
		],
		'collection' => [
			'title' => 'In brief',
			'text' 	=> '<ul><li><strong>Responsible:</strong> the operator of this website. How to reach them is set out under “<a href="#privacy-responsible">Controller</a>”.</li><li><strong>From you:</strong> anything you send us, for example a message through the contact form.</li><li><strong>Automatically:</strong> technical details produced by every page request, such as your IP address, browser, operating system and the time.</li><li><strong>What for:</strong> we need the technical details to deliver the website reliably and securely, and your messages to deal with your request. The following sections name any further purposes.</li><li><strong>Your rights:</strong> from access, rectification and erasure through restriction, data portability and objection to withdrawing consent and complaining to a supervisory authority. Details under “<a href="#privacy-rights">Your rights</a>” and “<a href="#privacy-objection">Right to object</a>”.</li></ul>',
		],
		'hosting' => [
			'title' => 'Hosting',
			'text' 	=> '<p>This website runs on servers rented from a hosting provider. On our behalf, the provider processes everything that arises when the website is visited: above all IP addresses and other technical details of the requests, but also messages you send us through the website.</p><p>We use the provider so that the website is reliable, fast and secure. The legal basis is our legitimate interest in this (Art. 6(1)(f) GDPR); where a contract with you is being prepared or performed, also Art. 6(1)(b) GDPR.</p><p>We have agreed with the provider that it processes the data only on our instructions and only for its service (processing on our behalf under Art. 28 GDPR).</p><p><strong>Hosting provider:</strong><br>#/project/website/general/host#</p>',
		],
		'general' => [
			'title' => 'Principles',
			'text' 	=> '<p>We process personal data only where a legal basis allows it, and we treat them confidentially. This policy sets out which data we collect, what we use them for and on which basis.</p><p>No transmission over the internet is completely secure. An unencrypted email in particular can be intercepted by others on its way; it is better to send us confidential information through a protected channel.</p>',
		],
		'responsible' => [
			'title' => 'Controller',
			'text' 	=> '<p>The controller responsible for processing your data on this website within the meaning of the GDPR is:</p><p>#/project/website/general/author#<br>#/project/company/general/name#<br>#/project/company/contact/address#</p><p>Phone: #/project/company/contact/phone#<br>Email: #/project/company/contact/email#</p>',
		],
		'storage' => [
			'title' => 'Storage period',
			'text' 	=> '<p>We keep personal data only as long as we need them for the purpose in question, unless a section of this policy names a period of its own. If you withdraw consent or rightly request erasure, we delete the data unless another legal reason requires or allows us to keep them, such as retention periods under tax or commercial law. In that case we delete them as soon as that reason no longer applies.</p>',
		],
		'rights' => [
			'title' => 'Your rights',
			'text' 	=> '<p>Where the legal requirements are met, you have these rights towards us:</p><ul><li><strong>Access</strong> to whether and which data we process about you (Art. 15 GDPR)</li><li><strong>Rectification</strong> of inaccurate data and completion of incomplete data (Art. 16 GDPR)</li><li><strong>Erasure</strong> of your data (Art. 17 GDPR)</li><li><strong>Restriction</strong> of processing (Art. 18 GDPR)</li><li><strong>Data portability</strong>: receiving data you have provided to us in a common, machine-readable format, or having them sent to another controller (Art. 20 GDPR)</li><li><strong>Objection</strong> to processing, see “<a href="#privacy-objection">Right to object</a>” (Art. 21 GDPR)</li><li><strong>Withdrawal</strong> of consent with effect for the future; anything done before then remains lawful (Art. 7(3) GDPR)</li></ul><p>To exercise a right, a message to the contact details under “<a href="#privacy-responsible">Controller</a>” is enough; it costs you nothing. You can also complain to a data protection supervisory authority, in particular in the member state where you live or work or where the alleged infringement took place (Art. 77 GDPR).</p>',
		],
		'objection' => [
			'title' => 'Right to object',
			'text' 	=> '<p><strong>Where we base processing on our legitimate interest (Art. 6(1)(f) GDPR), you may object to it at any time on grounds relating to your particular situation.</strong> We will then no longer process the data unless we demonstrate compelling legitimate grounds that override your interests, rights and freedoms, or the processing serves the establishment, exercise or defence of legal claims (Art. 21(1) GDPR).</p><p><strong>Where we process data for direct marketing, you may object to this at any time without giving reasons.</strong> We will then no longer use them for that purpose (Art. 21(2) and (3) GDPR).</p><p>An informal message to the contact details under “<a href="#privacy-responsible">Controller</a>” is enough to object.</p>',
		],
		'cookies' => [
			'title' => 'Cookies and local storage',
			'text' 	=> '<p>Cookies are small files a website stores in your browser. On its own, this website sets a single one: a session cookie that keeps your language choice, the protection of forms against forged submissions and a sign-in together. It is only created when you choose a language (including by opening a page in another language), sign in or open a page with a form, and it is deleted when you close your browser.</p><p>Where the website offers settings you choose yourself, such as a dark colour scheme, it remembers your choice only in your browser (local storage). This information is not sent to us.</p><p>This storage is technically necessary for the website to provide the functions you use. The legal basis is Section 25(2) no. 2 of the German Telecommunications Digital Services Data Protection Act (TDDDG), and for the related processing our legitimate interest in a working website (Art. 6(1)(f) GDPR).</p><p>Further cookies or storage exist only where a following section describes them, and then with your consent where it is required.</p>',
		],
		'server-logs' => [
			'title' => 'Access logs',
			'text' 	=> '<p>Every time a page is requested, your browser transmits technical details that the server records in log files:</p><ul><li>the browser and its version</li><li>the operating system</li><li>the page you came from (referrer)</li><li>the name of the requesting computer (host name)</li><li>the date and time of the request</li><li>the IP address</li></ul><p>We do not combine these details with other data sources. We need them to deliver the website without technical errors, to find faults and to fend off attacks. The legal basis is our legitimate interest in this (Art. 6(1)(f) GDPR).</p>',
		],
		'contact-form' => [
			'title' => 'Contact form',
			'text' 	=> '<p>If you write to us through the contact form, we store your details, including the contact information you enter there, to deal with your request and any follow-up questions. Together with the message we record the date, the time and your IP address. As a confirmation, you receive an email summarising your details at the address you entered. We pass your details on to others only with your consent.</p><p>To prevent misuse of the form, the website counts for one hour how often messages were sent from your IP address.</p><p>If your request relates to a contract or to steps prior to entering into one, the legal basis is Art. 6(1)(b) GDPR. Otherwise the basis is our legitimate interest in answering requests and protecting the form against misuse (Art. 6(1)(f) GDPR), or your consent where we have asked for it (Art. 6(1)(a) GDPR). You can withdraw consent at any time.</p><p>Requests stored on the website are deleted automatically after three months, or earlier at your request or after you withdraw consent. Anything we have to keep longer for legal reasons, such as under retention periods of commercial or tax law, is deleted once those periods have expired. The message also reaches us by email; for that copy, the section “<a href="#privacy-contact">Contact by email or phone</a>” applies.</p>',
		],
		'contact' => [
			'title' => 'Contact by email or phone',
			'text' 	=> '<p>If you email or call us, we process your request with all the details you give, such as your name and what it is about, in order to answer it. We pass these details on to others only with your consent.</p><p>The legal basis is the same as for the contact form: Art. 6(1)(b) GDPR where a contract or steps prior to one are concerned, otherwise our legitimate interest in answering requests (Art. 6(1)(f) GDPR) or your consent (Art. 6(1)(a) GDPR), which you can withdraw at any time.</p><p>We delete your request once it has been dealt with and no follow-up questions are to be expected, or earlier at your request or after you withdraw consent. If it is subject to a statutory retention obligation, we delete it only once that period has expired.</p>',
		],
		'tls' => [
			'title' => 'Encrypted transmission',
			'text' 	=> '<p>This website transmits its content in encrypted form (TLS, formerly called SSL). This keeps what you send us unreadable for third parties, such as a message through the contact form. Your browser shows you whether the connection is encrypted: the address starts with “https://”, and a padlock symbol appears in the address bar.</p>',
		],
	],
];
