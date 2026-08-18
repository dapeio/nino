[template /templates/html-header]

<section class="ui-section ui-text-center">
	<div class="ui-grid-row">
		<div class="ui-grid-100">
			<h2 class="ui-atf-title">Design-System: Bausteine</h2>
			<p class="ui-atf-subtitle">Alle Bausteine aus docs/design-system.md, zum Durchklicken und Referenzieren.</p>
			<a href="[[/webpage/demo-sections/uri]]" class="ui-btn ui-btn--outline">Zu den fertigen Sections &rarr;</a>
		</div>
	</div>
</section>

<!-- Grid -->
<section class="ui-section ui-section--alt">
	<div class="ui-grid-row">
		<div class="ui-grid-100">
			<h3 class="ui-section-title">Grid</h3>
			<p class="ui-section-subtitle">ui-grid-row, ui-grid-25/33/50/66/75/100, Breakpoints -s-/-m-/-l-/-xl-</p>
		</div>
	</div>
	<div class="ui-grid-row">
		<div class="ui-grid-25 ui-p-1"><div class="ui-alert ui-alert--info ui-text-center">25%</div></div>
		<div class="ui-grid-25 ui-p-1"><div class="ui-alert ui-alert--info ui-text-center">25%</div></div>
		<div class="ui-grid-25 ui-p-1"><div class="ui-alert ui-alert--info ui-text-center">25%</div></div>
		<div class="ui-grid-25 ui-p-1"><div class="ui-alert ui-alert--info ui-text-center">25%</div></div>
	</div>
	<div class="ui-grid-row">
		<div class="ui-grid-33 ui-p-1"><div class="ui-alert ui-alert--info ui-text-center">33%</div></div>
		<div class="ui-grid-33 ui-p-1"><div class="ui-alert ui-alert--info ui-text-center">33%</div></div>
		<div class="ui-grid-33 ui-p-1"><div class="ui-alert ui-alert--info ui-text-center">33%</div></div>
	</div>
	<div class="ui-grid-row">
		<div class="ui-grid-100 ui-grid-m-50 ui-grid-l-25 ui-p-1"><div class="ui-alert ui-alert--info ui-text-center">100 / 50 / 25</div></div>
		<div class="ui-grid-100 ui-grid-m-50 ui-grid-l-25 ui-p-1"><div class="ui-alert ui-alert--info ui-text-center">100 / 50 / 25</div></div>
		<div class="ui-grid-100 ui-grid-m-50 ui-grid-l-25 ui-p-1"><div class="ui-alert ui-alert--info ui-text-center">100 / 50 / 25</div></div>
		<div class="ui-grid-100 ui-grid-m-50 ui-grid-l-25 ui-p-1"><div class="ui-alert ui-alert--info ui-text-center">100 / 50 / 25</div></div>
	</div>
</section>

<!-- ATF / Hero + Cover -->
<section class="ui-atf ui-section--fullwidth js-cover js-cover-center" data-cover-height="60" style="color:var(--color-primary-text);">
	<img src="data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='1600' height='900'%3E%3Crect width='1600' height='900' fill='%232c3e50'/%3E%3C/svg%3E">
	<div class="js-cover-content">
		<h3 class="ui-atf-title">ATF / Hero (js-cover)</h3>
		<p class="ui-atf-subtitle">data-cover-height="60", js-cover-center zentriert den Inhalt</p>
	</div>
	<button type="button" class="ui-atf-arrowdown" data-arrow-target="#demo-elements-parallax" aria-label="[[/global/scrolldown]]"></button>
</section>

<!-- Parallax -->
<section class="js-parallex" id="demo-elements-parallax" style="color:var(--color-primary-text);">
	<img src="data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='1600' height='900'%3E%3Crect width='1600' height='900' fill='%233498db'/%3E%3C/svg%3E">
	<div class="js-cover-content">
		<div class="ui-grid-row">
			<div class="ui-grid-100">
				<h3 class="ui-section-title">Parallax (js-parallex)</h3>
				<p class="ui-section-subtitle">Scrollen zum Testen - Bild bewegt sich langsamer als der Rest der Seite</p>
			</div>
		</div>
	</div>
</section>

<!-- Sections -->
<section class="ui-section">
	<h3 class="ui-section-title">Section --default</h3>
	<p class="ui-section-subtitle ui-text-center">ui-section (ohne Modifier)</p>
</section>
<section class="ui-section ui-section--alt">
	<h3 class="ui-section-title">Section --alt</h3>
	<p class="ui-section-subtitle ui-text-center">ui-section ui-section--alt</p>
</section>
<section class="ui-section ui-section--dark">
	<h3 class="ui-section-title">Section --dark</h3>
	<p class="ui-section-subtitle ui-text-center">ui-section ui-section--dark</p>
</section>
<section class="ui-section ui-section--black">
	<h3 class="ui-section-title">Section --black</h3>
	<p class="ui-section-subtitle ui-text-center">ui-section ui-section--black</p>
</section>

<!-- Buttons -->
<section class="ui-section ui-section--alt">
	<div class="ui-grid-row">
		<div class="ui-grid-100">
			<h3 class="ui-section-title">Buttons</h3>
			<p class="ui-section-subtitle">ui-btn --primary/--outline/--light/--dark/--big/--small</p>
			<div class="ui-text-center">
				<a href="#" class="ui-btn ui-btn--primary">Primary</a>
				<a href="#" class="ui-btn ui-btn--outline">Outline</a>
				<a href="#" class="ui-btn ui-btn--light">Light</a>
				<a href="#" class="ui-btn ui-btn--dark">Dark</a>
				<a href="#" class="ui-btn ui-btn--primary ui-btn--big">Big Primary</a>
				<a href="#" class="ui-btn ui-btn--primary ui-btn--small">Small Primary</a>
			</div>
		</div>
	</div>
</section>

<!-- Icons -->
<section class="ui-section">
	<div class="ui-grid-row">
		<div class="ui-grid-100">
			<h3 class="ui-section-title">Icons</h3>
			<p class="ui-section-subtitle">ui-icon, ui-icon.small</p>
			<div class="ui-text-center">
				<svg class="ui-icon" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24"><path d="M20 4H4c-1.1 0-1.99.9-1.99 2L2 18c0 1.1.9 2 2 2h16c1.1 0 2-.9 2-2V6c0-1.1-.9-2-2-2m0 14H4V8l8 5 8-5zm-8-7L4 6h16z"></path></svg>
				<svg class="ui-icon small" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24"><path d="M20 4H4c-1.1 0-1.99.9-1.99 2L2 18c0 1.1.9 2 2 2h16c1.1 0 2-.9 2-2V6c0-1.1-.9-2-2-2m0 14H4V8l8 5 8-5zm-8-7L4 6h16z"></path></svg>
			</div>
		</div>
	</div>
</section>

<!-- Badges -->
<section class="ui-section ui-section--alt">
	<div class="ui-grid-row">
		<div class="ui-grid-100">
			<h3 class="ui-section-title">Badge / Pill</h3>
			<p class="ui-section-subtitle">ui-badge --pill/--primary/--success/--error</p>
			<div class="ui-text-center">
				<span class="ui-badge">default</span>
				<span class="ui-badge ui-badge--pill">pill</span>
				<span class="ui-badge ui-badge--primary">primary</span>
				<span class="ui-badge ui-badge--success">success</span>
				<span class="ui-badge ui-badge--error">error</span>
			</div>
		</div>
	</div>
</section>

<!-- Breadcrumbs -->
<section class="ui-section">
	<div class="ui-grid-row">
		<div class="ui-grid-100">
			<h3 class="ui-section-title">Breadcrumbs</h3>
			<p class="ui-section-subtitle">ui-breadcrumbs</p>
			<ul class="ui-breadcrumbs ui-text-center" style="justify-content:center;">
				<li><a href="#">Start</a></li>
				<li><a href="#">Kategorie</a></li>
				<li>Aktuelle Seite</li>
			</ul>
		</div>
	</div>
</section>

<!-- Lists -->
<section class="ui-section ui-section--alt">
	<div class="ui-grid-row">
		<div class="ui-grid-100 ui-grid-m-50">
			<h3 class="ui-section-title">Liste --check</h3>
			<p class="ui-section-subtitle">ui-list ui-list--check</p>
			<ul class="ui-list ui-list--check">
				<li>Persönliche Erstberatung inklusive</li>
				<li>Individueller Fahrplan für Ihre Ziele</li>
				<li>Flexible Terminvereinbarung</li>
			</ul>
		</div>
		<div class="ui-grid-100 ui-grid-m-50">
			<h3 class="ui-section-title">Liste --numbered</h3>
			<p class="ui-section-subtitle">ui-list ui-list--numbered</p>
			<ul class="ui-list ui-list--numbered">
				<li>Kennenlerngespräch vereinbaren</li>
				<li>Gemeinsam den Fahrplan festlegen</li>
				<li>Umsetzung mit regelmäßigem Feedback</li>
			</ul>
		</div>
	</div>
</section>

<!-- Alerts -->
<section class="ui-section ui-section--alt">
	<div class="ui-grid-row">
		<div class="ui-grid-100">
			<h3 class="ui-section-title">Alert / Inline Feedback</h3>
			<p class="ui-section-subtitle">ui-alert --info/--success/--error</p>
			<div class="ui-alert ui-alert--info ui-mb-2">Info-Hinweis</div>
			<div class="ui-alert ui-alert--success ui-mb-2">Erfolgreich gespeichert</div>
			<div class="ui-alert ui-alert--error">Da ist etwas schiefgelaufen</div>
		</div>
	</div>
</section>

<!-- Video embed -->
<section class="ui-section">
	<div class="ui-grid-row">
		<div class="ui-grid-100 ui-grid-m-50">
			<h3 class="ui-section-title">Video Embed</h3>
			<p class="ui-section-subtitle">ui-video (16:9), ui-video--4-3</p>
			<div class="ui-video">
				<iframe src="about:blank" allowfullscreen></iframe>
			</div>
		</div>
	</div>
</section>

<!-- Article / Card via the elements shortcode -->
<section class="ui-section ui-section--alt">
	<div class="ui-grid-row">
		<div class="ui-grid-100">
			<h3 class="ui-section-title">Article / Card</h3>
			<p class="ui-section-subtitle">ui-article --alt, -cols. Inhalte kommen immer über den elements-Shortcode, nie fest verdrahtet</p>
		</div>
	</div>
	<div class="ui-grid-row">
		[elements /demo-services limit="3"]
		<div class="ui-grid-100 ui-grid-m-33">
			<article class="ui-article">
				<div class="ui-article-content">
					<h4 class="ui-article-title">[[title]]</h4>
					<p class="ui-article-descr">[[description]]</p>
					<span class="ui-badge">[[tasks]]</span>
				</div>
			</article>
		</div>
		[/elements]
	</div>
</section>
<section class="ui-section">
	<div class="ui-grid-row">
		<div class="ui-grid-100">
			<p class="ui-section-subtitle">ui-article-cols (Bild neben Text)</p>
		</div>
		[elements /demo-services limit="1"]
		<div class="ui-grid-100">
			<article class="ui-article ui-article-cols">
				<img class="ui-article-img" src="data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='800' height='450'%3E%3Crect width='800' height='450' fill='%23cccccc'/%3E%3C/svg%3E">
				<div class="ui-article-content">
					<h4 class="ui-article-title">[[title]]</h4>
					<p class="ui-article-descr">[[description]]</p>
				</div>
			</article>
		</div>
		[/elements]
	</div>
</section>

<!-- Table -->
<section class="ui-section">
	<div class="ui-grid-row">
		<div class="ui-grid-100">
			<h3 class="ui-section-title">Tabelle --default</h3>
			<p class="ui-section-subtitle">ui-table-wrap &gt; ui-table (horizontal scroll statt Breakout auf schmalen Viewports)</p>
			<div class="ui-table-wrap">
				<table class="ui-table">
					<thead>
						<tr><th>Leistung</th><th>Dauer</th><th>Preis</th></tr>
					</thead>
					<tbody>
						<tr><td>Einzelcoaching</td><td>60 Min.</td><td>90 &euro;</td></tr>
						<tr><td>Coaching-Paket</td><td>5 Tage</td><td>420 &euro;</td></tr>
						<tr><td>Strategie-Workshop</td><td>1 Tag</td><td>890 &euro;</td></tr>
					</tbody>
				</table>
			</div>
		</div>
	</div>
</section>
<section class="ui-section">
	<div class="ui-grid-row">
		<div class="ui-grid-100">
			<h3 class="ui-section-title">Tabelle --striped / --bordered</h3>
			<p class="ui-section-subtitle">ui-table--striped, ui-table--bordered - kombinierbar</p>
			<div class="ui-table-wrap">
				<table class="ui-table ui-table--striped ui-table--bordered">
					<thead>
						<tr><th>Leistung</th><th>Dauer</th><th>Preis</th></tr>
					</thead>
					<tbody>
						<tr><td>Einzelcoaching</td><td>60 Min.</td><td>90 &euro;</td></tr>
						<tr><td>Coaching-Paket</td><td>5 Tage</td><td>420 &euro;</td></tr>
						<tr><td>Beratungstermin</td><td>90 Min.</td><td>150 &euro;</td></tr>
						<tr><td>Strategie-Workshop</td><td>1 Tag</td><td>890 &euro;</td></tr>
					</tbody>
				</table>
			</div>
		</div>
	</div>
</section>

<!-- Pricing table via the elements shortcode -->
<section class="ui-section ui-section--alt">
	<div class="ui-grid-row">
		<div class="ui-grid-100">
			<h3 class="ui-section-title">Pricing Table</h3>
			<p class="ui-section-subtitle">ui-pricing-row / -item(--featured) / -title / -price, gefüllt über die elements-Shortcode-Schleife</p>
		</div>
	</div>
	<div class="ui-grid-row">
		<div class="ui-grid-100">
			<div class="ui-pricing-row">
				[elements /demo-services query="cat=standard"]
				<div class="ui-pricing-item">
					<h4 class="ui-pricing-title">[[title]]</h4>
					<p class="ui-pricing-price">[[price]] &euro;</p>
					<span class="ui-badge">[[cat]]</span>
				</div>
				[/elements]
				[elements /demo-services query="cat=premium"]
				<div class="ui-pricing-item ui-pricing-item--featured">
					<h4 class="ui-pricing-title">[[title]]</h4>
					<p class="ui-pricing-price">[[price]] &euro;</p>
					<span class="ui-badge ui-badge--primary">[[cat]]</span>
				</div>
				[/elements]
			</div>
		</div>
	</div>
</section>

<!-- Form -->
<section class="ui-section">
	<div class="ui-grid-row">
		<div class="ui-grid-100 ui-grid-m-50">
			<h3 class="ui-section-title">Formular</h3>
			<p class="ui-section-subtitle">ui-form, postet an / - siehe page-contact.tpl für die Referenz-Implementierung</p>
			<form class="ui-form">
				<label for="demo-name">Name</label>
				<input type="text" id="demo-name" name="name" class="ui-form-input" placeholder="Ihr Name" required>

				<label for="demo-email">E-Mail</label>
				<input type="email" id="demo-email" name="email" class="ui-form-input" placeholder="Ihre E-Mail" required>

				<label for="demo-message">Nachricht</label>
				<textarea id="demo-message" name="message" class="ui-form-textarea" placeholder="Ihre Nachricht" required></textarea>

				<p class="ui-form-message"></p>

				<button type="submit" class="ui-btn ui-btn--primary ui-form-submit">Absenden</button>
			</form>
		</div>
	</div>
</section>

<!-- Slider -->
<section class="ui-section ui-section--alt">
	<div class="ui-grid-row">
		<div class="ui-grid-100">
			<h3 class="ui-section-title">Slider</h3>
			<p class="ui-section-subtitle">js-slider, touch swipe + js-slider-points (Dot-Pagination)</p>
		</div>
	</div>
	<div class="js-slider" data-slider-pos="0" data-slider-width="60%" data-slider-min="240px">
		<ul>
			<li><img src="data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='800' height='450'%3E%3Crect width='800' height='450' fill='%23e67e22'/%3E%3C/svg%3E"><h4>Folie 1</h4></li>
			<li><img src="data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='800' height='450'%3E%3Crect width='800' height='450' fill='%2327ae60'/%3E%3C/svg%3E"><h4>Folie 2</h4></li>
			<li><img src="data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='800' height='450'%3E%3Crect width='800' height='450' fill='%238e44ad'/%3E%3C/svg%3E"><h4>Folie 3</h4></li>
		</ul>
	</div>
</section>

<!-- Viewport animation -->
<section class="ui-section">
	<div class="ui-grid-row">
		<div class="ui-grid-100">
			<h3 class="ui-section-title">Viewport Animation</h3>
			<p class="ui-section-subtitle">js-vpa, --repeat, --speed-slow - beim Scrollen ins Viewport einblenden. Alle Effekt-Varianten (--zoom-*, --blur-*, --flip-*, ...) siehe <a href="[[/webpage/demo-vpa/uri]]">demo-vpa</a>.</p>
		</div>
		<div class="ui-grid-100 ui-grid-m-33 js-vpa ui-p-2"><div class="ui-alert ui-alert--info">js-vpa</div></div>
		<div class="ui-grid-100 ui-grid-m-33 js-vpa js-vpa--speed-slow ui-p-2" data-vpa-delay="150ms"><div class="ui-alert ui-alert--info">js-vpa --speed-slow, delay 150ms</div></div>
		<div class="ui-grid-100 ui-grid-m-33 js-vpa js-vpa--repeat ui-p-2"><div class="ui-alert ui-alert--info">js-vpa --repeat</div></div>
	</div>
</section>

<!-- Autoheight -->
<section class="ui-section ui-section--alt">
	<div class="ui-grid-row">
		<div class="ui-grid-100">
			<h3 class="ui-section-title">Autoheight</h3>
			<p class="ui-section-subtitle">ui-autoheight, data-autoheight-group - gleicht die Höhe innerhalb einer Gruppe an</p>
		</div>
		<div class="ui-grid-100 ui-grid-m-33 ui-p-1"><div class="ui-alert ui-alert--info ui-autoheight" data-autoheight-group="demo">Kurzer Text.</div></div>
		<div class="ui-grid-100 ui-grid-m-33 ui-p-1"><div class="ui-alert ui-alert--info ui-autoheight" data-autoheight-group="demo">Ein etwas längerer Text, der über zwei Zeilen geht.</div></div>
		<div class="ui-grid-100 ui-grid-m-33 ui-p-1"><div class="ui-alert ui-alert--info ui-autoheight" data-autoheight-group="demo">Kurz.</div></div>
	</div>
</section>

<!-- Stat counter -->
<section class="ui-section">
	<div class="ui-grid-row">
		<div class="ui-grid-100">
			<h3 class="ui-section-title">Stat Counter</h3>
			<p class="ui-section-subtitle">js-stat-counter, data-stat-counter-to/-suffix/-duration - zählt beim Einblenden hoch</p>
		</div>
		<div class="ui-grid-100 ui-grid-m-33 ui-text-center">
			<div class="js-stat-counter" data-stat-counter-to="250" data-stat-counter-suffix="+">0</div>
			<p>Projekte</p>
		</div>
		<div class="ui-grid-100 ui-grid-m-33 ui-text-center">
			<div class="js-stat-counter" data-stat-counter-to="98" data-stat-counter-suffix="%">0</div>
			<p>Zufriedenheit</p>
		</div>
		<div class="ui-grid-100 ui-grid-m-33 ui-text-center">
			<div class="js-stat-counter" data-stat-counter-to="12.5" data-stat-counter-suffix=" Jahre">0</div>
			<p>Erfahrung</p>
		</div>
	</div>
</section>

<!-- Utilities -->
<section class="ui-section ui-section--alt">
	<div class="ui-grid-row">
		<div class="ui-grid-100">
			<h3 class="ui-section-title">Utilities</h3>
			<p class="ui-section-subtitle">Spacing, Text-Align, Opacity, Visibility, Img-Tools. Schriftgrößen sind keine Utility, sondern --quiet/--loud an der jeweiligen Klasse</p>
		</div>
		<div class="ui-grid-100 ui-grid-m-25 ui-p-1"><div class="ui-alert ui-text-left">ui-text-left</div></div>
		<div class="ui-grid-100 ui-grid-m-25 ui-p-1"><div class="ui-alert ui-text-center">ui-text-center</div></div>
		<div class="ui-grid-100 ui-grid-m-25 ui-p-1"><div class="ui-alert ui-text-right">ui-text-right</div></div>
		<div class="ui-grid-100 ui-grid-m-25 ui-p-1"><div class="ui-alert"><small>&lt;small&gt;</small></div></div>
	</div>
	<div class="ui-grid-row">
		<div class="ui-grid-20 ui-p-1"><div class="ui-alert ui-alert--info ui-opacity-30">opacity-30</div></div>
		<div class="ui-grid-20 ui-p-1"><div class="ui-alert ui-alert--info ui-opacity-50">opacity-50</div></div>
		<div class="ui-grid-20 ui-p-1"><div class="ui-alert ui-alert--info ui-opacity-70">opacity-70</div></div>
		<div class="ui-grid-20 ui-p-1"><div class="ui-alert ui-alert--info ui-opacity-90">opacity-90</div></div>
		<div class="ui-grid-20 ui-p-1"><div class="ui-alert ui-alert--info">opacity-100</div></div>
	</div>
</section>

<!-- Accordion -->
<section class="ui-section">
	<div class="ui-grid-row">
		<div class="ui-grid-100">
			<h3 class="ui-section-title">Accordion</h3>
			<p class="ui-section-subtitle">ui-accordion - natives details/summary, kein JS nötig. Gleicher name="..." macht die Gruppe exklusiv</p>
		</div>
	</div>
	<div class="ui-grid-row">
		<div class="ui-grid-100 ui-grid-m-66">
			<details class="ui-accordion" name="demo-accordion" open>
				<summary class="ui-accordion-trigger">Was ist im Preis enthalten?</summary>
				<div class="ui-accordion-panel"><p>Beratung, Konzeption, Umsetzung und drei Monate Support nach Launch.</p></div>
			</details>
			<details class="ui-accordion" name="demo-accordion">
				<summary class="ui-accordion-trigger">Wie lange dauert ein Projekt?</summary>
				<div class="ui-accordion-panel"><p>Je nach Umfang zwischen zwei und acht Wochen.</p></div>
			</details>
			<details class="ui-accordion" name="demo-accordion">
				<summary class="ui-accordion-trigger">Gibt es eine Ratenzahlung?</summary>
				<div class="ui-accordion-panel"><p>Ja, ab einem Projektvolumen von 2.000&nbsp;€.</p></div>
			</details>
		</div>
	</div>
</section>

<!-- Tabs -->
<section class="ui-section">
	<div class="ui-grid-row">
		<div class="ui-grid-100">
			<h3 class="ui-section-title">Tabs</h3>
			<p class="ui-section-subtitle">js-tabs, js-tabs-nav/-tab, js-tabs-panel + data-tabs-target</p>
		</div>
		<div class="ui-grid-100 ui-grid-m-66">
			<div class="js-tabs">
				<div class="js-tabs-nav">
					<button type="button" class="js-tabs-tab active" data-tabs-target="demo-tab-1">Leistungen</button>
					<button type="button" class="js-tabs-tab" data-tabs-target="demo-tab-2">Preise</button>
					<button type="button" class="js-tabs-tab" data-tabs-target="demo-tab-3">Kontakt</button>
				</div>
				<div class="js-tabs-panel active" id="demo-tab-1"><p>Beratung, Entwicklung, Schulungen und Workshops.</p></div>
				<div class="js-tabs-panel" id="demo-tab-2"><p>Ab 80&nbsp;€ pro Stunde, Pakete auf Anfrage.</p></div>
				<div class="js-tabs-panel" id="demo-tab-3"><p>[[/company/email]]</p></div>
			</div>
		</div>
	</div>
</section>

<!-- Modal / Lightbox -->
<section class="ui-section ui-section--alt">
	<div class="ui-grid-row">
		<div class="ui-grid-100">
			<h3 class="ui-section-title">Modal / Lightbox</h3>
			<p class="ui-section-subtitle">js-modal (natives &lt;dialog&gt;), js-modal-trigger + data-modal-target, js-modal--lightbox</p>
			<div class="ui-text-center">
				<button type="button" class="ui-btn ui-btn--primary js-modal-trigger" data-modal-target="demo-modal">Modal öffnen</button>
				<button type="button" class="ui-btn ui-btn--outline js-modal-trigger" data-modal-target="demo-lightbox">Lightbox öffnen</button>
			</div>
		</div>
	</div>
	<dialog class="js-modal" id="demo-modal">
		<button type="button" class="js-modal-close" aria-label="Schließen">&times;</button>
		<h4>Modal-Titel</h4>
		<p>Beliebiger Inhalt - Formular, Bestätigung, Detailansicht. Schließt per Klick auf X, Hintergrund oder Escape.</p>
	</dialog>
	<dialog class="js-modal js-modal--lightbox" id="demo-lightbox">
		<button type="button" class="js-modal-close" aria-label="Schließen">&times;</button>
		<img src="data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='1200' height='800'%3E%3Crect width='1200' height='800' fill='%232c3e50'/%3E%3C/svg%3E">
	</dialog>
</section>

<!-- Pagination -->
<section class="ui-section">
	<div class="ui-grid-row">
		<div class="ui-grid-100">
			<h3 class="ui-section-title">Pagination</h3>
			<p class="ui-section-subtitle">ui-pagination - reine Seitenzahlen-Links, kein JS</p>
			<ul class="ui-pagination ui-text-center" style="justify-content:center;">
				<li><a href="#">&lsaquo;</a></li>
				<li><a href="#">1</a></li>
				<li class="active"><a href="#">2</a></li>
				<li><a href="#">3</a></li>
				<li><a href="#">4</a></li>
				<li><a href="#">&rsaquo;</a></li>
			</ul>
		</div>
	</div>
</section>

<!-- Toast -->
<section class="ui-section ui-section--alt">
	<div class="ui-grid-row">
		<div class="ui-grid-100">
			<h3 class="ui-section-title">Toast</h3>
			<p class="ui-section-subtitle">js-toast-trigger + data-toast-message/-type, oder direkt Nino.ui.toast(message, type) aus eigenem JS</p>
			<div class="ui-text-center">
				<button type="button" class="ui-btn ui-btn--light js-toast-trigger" data-toast-message="Gespeichert." data-toast-type="success">Success-Toast</button>
				<button type="button" class="ui-btn ui-btn--light js-toast-trigger" data-toast-message="Da ist etwas schiefgelaufen." data-toast-type="error">Error-Toast</button>
				<button type="button" class="ui-btn ui-btn--light js-toast-trigger" data-toast-message="Nur eine Info.">Default-Toast</button>
			</div>
		</div>
	</div>
</section>

[template /templates/html-footer]
