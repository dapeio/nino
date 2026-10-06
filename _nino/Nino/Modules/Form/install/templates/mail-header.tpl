<!DOCTYPE html>
<html lang="[[/project/website/html/lang]]">
<head>
	<meta charset="[[/project/website/html/charset]]">
	<meta name="viewport" content="width=device-width, initial-scale=1.0">
	<style>
		body {
			margin: 0;
			padding: 0;
			background-color: [[/project/mail/color/backdrop]];
			color: [[/project/mail/color/text]];
			font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
		}
		.mail-container {
			width: 100%;
			max-width: 600px;
			margin: 0 auto;
			background: [[/project/mail/color/background]];
		}
		.mail-header {
			width: 100%;
			text-align: center;
			background: [[/project/mail/color/primary]];
			padding: [[/project/mail/spacing/medium]] 0;
		}
		.mail-header img {
			max-width: 180px;
			height: auto;
		}
		.mail-body {
			padding: [[/project/mail/spacing/large]] [[/project/mail/spacing/medium]];
		}
		h1 {
			font-size: [[/project/mail/font/large]];
			color: [[/project/mail/color/text]];
			margin: 0 0 [[/project/mail/spacing/medium]] 0;
		}
		p {
			font-size: 1em;
			line-height: [[/project/mail/font/line-height]];
			margin: 0 0 [[/project/mail/spacing/medium]] 0;
		}
		table {
			width: 100%;
			border-collapse: collapse;
			margin: 0 0 [[/project/mail/spacing/medium]] 0;
		}
		th, td {
			text-align: left;
			padding: [[/project/mail/spacing/small]] 0;
			border-bottom: 1px solid [[/project/mail/color/border]];
			font-size: 1em;
			vertical-align: top;
		}
		th {
			width: 30%;
			color: [[/project/mail/color/text]];
			font-weight: bold;
			padding-right: [[/project/mail/spacing/medium]];
		}
		.mail-note {
			font-size: [[/project/mail/font/small]];
			color: [[/project/mail/color/text]];
		}
	</style>
</head>
<body>
	<div class="mail-container">
		<div class="mail-header">
			[image /logo]<img src="https://[[/project/website/general/url]][[src]]" width="180" alt="[[/project/company/general/name]]">[/image]
		</div>
		<div class="mail-body">
