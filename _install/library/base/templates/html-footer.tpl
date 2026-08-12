
		</main>

		<footer>
			<section class="ui-footer-main ui-grid-row ui-pt-2">
	      <div class="ui-grid-100 ui-grid-l-33 ui-pb-2">
					<img src="[[/nino/dir]]/images/logo-invert.png" class="ui-footer-logo ui-mb-2" alt="[[/company/name]]">
					<p>[[/company/description]]</p>
	      </div>
        <div class="ui-grid-100 ui-grid-l-33 ui-pb-2">
        	<h6 class="ui-footer-title">[[/website/footer/title/getintouch]]</h6>
					<ul class="ui-footer-getintouch">
						<li>
							<svg class="ui-icon small" xmlns="http://www.w3.org/2000/svg" viewBox="0 -960 960 960"><path d="M480.09-490q28.91 0 49.41-20.59 20.5-20.59 20.5-49.5t-20.59-49.41q-20.59-20.5-49.5-20.5t-49.41 20.59q-20.5 20.59-20.5 49.5t20.59 49.41q20.59 20.5 49.5 20.5ZM480-159q133-121 196.5-219.5T740-552q0-117.79-75.29-192.9Q589.42-820 480-820t-184.71 75.1Q220-669.79 220-552q0 75 65 173.5T480-159Zm0 79Q319-217 239.5-334.5T160-552q0-150 96.5-239T480-880q127 0 223.5 89T800-552q0 100-79.5 217.5T480-80Zm0-480Z"/></svg>
							<div>[[/company/adress]], [[/company/country]]</div>
						</li>
						<li>
							<svg class="ui-icon small" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24"><path d="M16 1H8C6.34 1 5 2.34 5 4v16c0 1.66 1.34 3 3 3h8c1.66 0 3-1.34 3-3V4c0-1.66-1.34-3-3-3m-2 20h-4v-1h4zm3.25-3H6.75V4h10.5z"></path></svg>
							<div>[[/company/phone]]</div>
						</li>
						<li>
							<svg class="ui-icon small" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24"><path d="M20 4H4c-1.1 0-1.99.9-1.99 2L2 18c0 1.1.9 2 2 2h16c1.1 0 2-.9 2-2V6c0-1.1-.9-2-2-2m0 14H4V8l8 5 8-5zm-8-7L4 6h16z"></path></svg>
							<div>[[/company/email]]</div>
						</li>
					</ul>
        </div>
        <div class="ui-grid-100 ui-grid-l-33 ui-pb-2">
        	[template /templates/html-footer-nav]
        </div>
      </section>
      <section class="ui-footer-legal ui-grid-row">
	      <div class="ui-grid-100 ui-grid-l-50 ui-pb-1">
	        <p>&copy; [[/date/year]] [[/company/name]]</p>
	      </div>
	      <div class="ui-grid-100 ui-grid-l-50 ui-pb-1">
	      	[template /templates/html-footer-legal]
	        [template /templates/html-footer-localepicker]
	      </div>
			</section>
		</footer>

		<div class="js-preloader"></div>

		<div class="js-cookie-banner" id="cookie-banner">
			<p>[[/cookiebanner/info/text]] <a href="[[/website/legal/uri]]">[[/cookiebanner/label/legal]]</a></p>
			<div class="js-cookie-banner-actions">
				<button type="button" class="ui-btn ui-btn--outline ui-btn--small" data-cookie-consent="declined">[[/cookiebanner/label/decline]]</button>
				<button type="button" class="ui-btn ui-btn--primary ui-btn--small" data-cookie-consent="accepted">[[/cookiebanner/label/accept]]</button>
			</div>
		</div>

		[jstext]
		[assets /.cache/script.js]
	</body>
</html>
