<!doctype html>
<html lang="[[/project/website/html/lang]]">
		<head>
			<meta charset="[[/project/website/html/charset]]">

			<title>[[/_nino/webpage[[/nino/http/response/uri]]/title]] | [[/project/company/general/name]]</title>
			<meta name="description" content="[[/_nino/webpage[[/nino/http/response/uri]]/description]]">
			<meta name="author" content="[[/project/website/general/author]]">
			<meta name="viewport" content="width=device-width, initial-scale=1">
			<link rel="canonical" href="https://[[/project/website/general/url]][[/nino/http/request/uri]]">

			<!-- Open Graph / social sharing -->
			<meta property="og:type" content="website">
			<meta property="og:site_name" content="[[/project/company/general/name]]">
			<meta property="og:title" content="[[/_nino/webpage[[/nino/http/response/uri]]/title]] | [[/project/company/general/name]]">
			<meta property="og:description" content="[[/_nino/webpage[[/nino/http/response/uri]]/description]]">
			<meta property="og:url" content="https://[[/project/website/general/url]][[/nino/http/request/uri]]">
			<meta property="og:locale" content="[[/nino/http/response/locale]]">
			[image /logo]<meta property="og:image" content="https://[[/project/website/general/url]][[src]]">[/image]
			<meta name="twitter:card" content="summary_large_image">
			<meta name="twitter:title" content="[[/_nino/webpage[[/nino/http/response/uri]]/title]] | [[/project/company/general/name]]">
			<meta name="twitter:description" content="[[/_nino/webpage[[/nino/http/response/uri]]/description]]">
			[image /logo]<meta name="twitter:image" content="https://[[/project/website/general/url]][[src]]">[/image]

			<link rel="icon" href="[[/nino/public]]/favicon/favicon.ico" sizes="any">
			<link rel="apple-touch-icon" sizes="180x180" href="[[/nino/public]]/favicon/apple-touch-icon.png">
			<link rel="icon" type="image/png" sizes="32x32" href="[[/nino/public]]/favicon/favicon-32x32.png">
			<link rel="icon" type="image/png" sizes="16x16" href="[[/nino/public]]/favicon/favicon-16x16.png">
			<link rel="manifest" href="[[/nino/public]]/favicon/site.webmanifest">
			[assets /.cache/style.css]

			<!-- The preloader is a full-screen overlay that only Nino.ui.js
			     takes back down, on window.load. Without this rule a visitor
			     with javascript disabled - or one hitting a javascript error
			     raised before that handler is bound - is left looking at a
			     blank page over perfectly good markup -->
			<noscript><style>.nino-preloader { display: none }</style></noscript>

			<!-- Structured data (schema.org). Values go through [json ...]
			     rather than "[[...]]" inside the quotes: a textfill is
			     inserted verbatim, and /project/company/contact/address is multi-line by
			     design (a postal address, offered as a <textarea> in
			     the setup wizard's own Personal Infos step), so a raw newline
			     used to land inside a json string and this whole block failed
			     to parse on every page. [json ...] emits the complete string
			     literal, quotes included - see Html::doJsonShortcode() -->
			<script type="application/ld+json">
			{
				"@context": "https://schema.org",
				"@type": "LocalBusiness",
				"name": [json /project/company/general/name],
				"description": [json /project/company/general/description],
				"url": "https://[[/project/website/general/url]]",
				"telephone": [json /project/company/contact/phone],
				"email": [json /project/company/contact/email],
				"address": {
					"@type": "PostalAddress",
					"streetAddress": [json /project/company/contact/address],
					"addressCountry": [json /project/company/contact/country]
				}
			}
			</script>
		</head>
		<body>

		[template /templates/frame-header]
  <main>
