[template /_admin/templates/html-header]

		<div id="admin-follow-wrap" class="nino-admin nino-admin-follow" data-dir="[[/nino/dir]]">
			<div class="nino-admin-follow-bar">
				<a id="admin-follow-back" class="nino-admin-follow-back" href="[[/nino/dir]]/_admin/">[[/_admin/follow/label/back]]</a>
				<span id="admin-follow-path" class="nino-admin-follow-path" title="[[/_admin/follow/label/path]]"></span>
				<button type="button" id="admin-follow-reload">[[/_admin/follow/label/reload]]</button>
				<span id="admin-follow-switch" class="nino-admin-follow-switch"></span>
			</div>
			<main class="nino-admin-follow-main">
				<iframe id="admin-follow-frame" class="nino-admin-follow-frame" title="[[/_admin/follow/label/frame]]" data-src="[[/nino/dir]][[/_admin/follow/uri]]"></iframe>
			</main>
		</div>
		[jstext]
		[assets /_admin/.cache/follow.js]
	</body>
</html>
