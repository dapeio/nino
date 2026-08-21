<!DOCTYPE html>
<!--[if lt IE 7]>      <html class="no-js lt-ie9 lt-ie8 lt-ie7" lang=""> <![endif]-->
<!--[if IE 7]>         <html class="no-js lt-ie9 lt-ie8" lang=""> <![endif]-->
<!--[if IE 8]>         <html class="no-js lt-ie9" lang=""> <![endif]-->
<!--[if gt IE 8]><!--> <html class="no-js" lang="[[/website/lang]]"> <!--<![endif]-->
		<head>
			<meta charset="utf-8">
			<meta http-equiv="X-UA-Compatible" content="IE=edge,chrome=1">

			<title>[[/webpage[[/nino/http/response/uri]]/title]] | [[/company/name]]</title>
			<meta name="description" content="[[/webpage[[/nino/http/response/uri]]/description]]">
			<meta name="author" content="[[/website/author]]">
			<meta name="viewport" content="width=device-width, initial-scale=1">
			<meta http-equiv="Content-Type" content="text/html;charset=[[/website/charset]]" />
			<link rel="canonical" href="https://[[/website/url]][[/nino/http/request/uri]]">

			<!-- Open Graph / social sharing -->
			<meta property="og:type" content="website">
			<meta property="og:site_name" content="[[/company/name]]">
			<meta property="og:title" content="[[/webpage[[/nino/http/response/uri]]/title]] | [[/company/name]]">
			<meta property="og:description" content="[[/webpage[[/nino/http/response/uri]]/description]]">
			<meta property="og:url" content="https://[[/website/url]][[/nino/http/request/uri]]">
			<meta property="og:locale" content="[[/nino/http/response/locale]]">
			<meta property="og:image" content="https://[[/website/url]][[/nino/public]]/images/logo.png">
			<meta name="twitter:card" content="summary_large_image">
			<meta name="twitter:title" content="[[/webpage[[/nino/http/response/uri]]/title]] | [[/company/name]]">
			<meta name="twitter:description" content="[[/webpage[[/nino/http/response/uri]]/description]]">
			<meta name="twitter:image" content="https://[[/website/url]][[/nino/public]]/images/logo.png">

			<link rel="apple-touch-icon" sizes="180x180" href="[[/nino/public]]/favicon/apple-touch-icon.png">
			<link rel="icon" type="image/png" sizes="32x32" href="[[/nino/public]]/favicon/favicon-32x32.png">
			<link rel="icon" type="image/png" sizes="16x16" href="[[/nino/public]]/favicon/favicon-16x16.png">
			<link rel="manifest" href="[[/nino/public]]/favicon/site.webmanifest">
			[assets /.cache/style.css]

			<!-- The preloader is a full-screen overlay that only Nino.ui.js
			     takes back down, on window.load. Without this rule a visitor
			     with javascript disabled - or one hitting a javascript error
			     raised before that handler is bound, eg. from this project's
			     own assets/script.js - is left looking at a blank page over
			     perfectly good markup -->
			<noscript><style>.nino-preloader { display: none }</style></noscript>

			<!-- Structured data (schema.org). Values go through [json ...]
			     rather than "[[...]]" inside the quotes: a textfill is
			     inserted verbatim, and /company/adress is multi-line by
			     design (a postal address, offered as a <textarea> in
			     /_install's own PersonalInfos step), so a raw newline used to
			     land inside a json string and this whole block failed to
			     parse on every page. [json ...] emits the complete string
			     literal, quotes included - see Html::doJsonShortcode() -->
			<script type="application/ld+json">
			{
				"@context": "https://schema.org",
				"@type": "LocalBusiness",
				"name": [json /company/name],
				"description": [json /company/description],
				"url": "https://[[/website/url]]",
				"telephone": [json /company/phone],
				"email": [json /company/email],
				"address": {
					"@type": "PostalAddress",
					"streetAddress": [json /company/adress],
					"addressCountry": [json /company/country]
				}
			}
			</script>
		</head>
		<body>

		[template /templates/theme.header]
  <main>
