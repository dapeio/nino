<!doctype html>
<html lang="en">
	<head>
		<meta charset="utf-8">
		<meta name="viewport" content="width=device-width, initial-scale=1">
		<meta name="robots" content="noindex, nofollow">
		<link rel="icon" type="image/png" sizes="32x32" href="[[/nino/dir]]/_admin/assets/favicon-32x32.png">
		<link rel="icon" href="[[/nino/dir]]/_admin/assets/favicon.ico">
		<title>Template Builder</title>
		<link rel="stylesheet" href="[[/nino/dir]]/_editor/assets/style.css">
		<link rel="stylesheet" href="[[/nino/dir]]/_admin/assets/style.css">
		<link rel="stylesheet" href="[[/nino/dir]]/_templates/assets/style.css">
	</head>
	<body>
		[csrf]
		<div id="tb-page-wrap">
			<div id="tb-bar-wrap">
				<div id="tb-bar-title">Template Builder</div>
				<span id="tb-bar-document"></span>
				<a href="[[/nino/dir]]/_admin/" id="tb-bar-admin">← Admin</a>
			</div>

			<div id="tb-body">

				<!-- Left rail: the project's own /templates/*.tpl files -->
				<div id="tb-documents">
					<h3>Templates</h3>
					<div id="tb-documents-list"></div>
				</div>

				<!-- Middle: the parsed block tree. Grid widths and spacing are
				     drawn to scale, everything else is a labelled box - see
				     docs/_templates.md -->
				<div id="tb-canvas">
					<p class="admin-hint" id="tb-canvas-hint">Pick a template on the left.</p>
					<div id="tb-canvas-notice"></div>
					<div id="tb-canvas-tree"></div>
				</div>

				<!-- Right rail: the block palette, grouped by manifest category -->
				<div id="tb-palette">
					<h3>Blocks</h3>
					<div id="tb-palette-list"></div>
				</div>

			</div>
		</div>
		<script src="[[/nino/dir]]/_nino/Nino.js"></script>
		<script src="[[/nino/dir]]/_templates/assets/script.js"></script>
		<script src="[[/nino/dir]]/_templates/assets/blocks.js"></script>
		<script src="[[/nino/dir]]/_templates/assets/canvas.js"></script>
	</body>
</html>
