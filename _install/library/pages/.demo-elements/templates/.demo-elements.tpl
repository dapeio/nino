[template /templates/html-header]

<section class="nino-section nino-text-center">
	<div class="nino-grid-row">
		<div class="nino-grid-100">
			<h2 class="nino-atf-title">Design-System: Bausteine</h2>
			<p class="nino-atf-subtitle">Alle Bausteine aus docs/design-system.md, zum Durchklicken und Referenzieren.</p>
			<a href="[[/webpage/demo-sections/uri]]" class="nino-btn nino-btn--outline">Zu den fertigen Sections &rarr;</a>
		</div>
	</div>
</section>

<!-- Grid -->
<section class="nino-section nino-section--alt">
	<div class="nino-grid-row">
		<div class="nino-grid-100">
			<h3 class="nino-section-title">Grid</h3>
			<p class="nino-section-subtitle">nino-grid-row, nino-grid-25/33/50/66/75/100, Breakpoints -s-/-m-/-l-/-xl-</p>
		</div>
	</div>
	<div class="nino-grid-row">
		<div class="nino-grid-25 nino-p-1"><div class="nino-alert nino-alert--info nino-text-center">25%</div></div>
		<div class="nino-grid-25 nino-p-1"><div class="nino-alert nino-alert--info nino-text-center">25%</div></div>
		<div class="nino-grid-25 nino-p-1"><div class="nino-alert nino-alert--info nino-text-center">25%</div></div>
		<div class="nino-grid-25 nino-p-1"><div class="nino-alert nino-alert--info nino-text-center">25%</div></div>
	</div>
	<div class="nino-grid-row">
		<div class="nino-grid-33 nino-p-1"><div class="nino-alert nino-alert--info nino-text-center">33%</div></div>
		<div class="nino-grid-33 nino-p-1"><div class="nino-alert nino-alert--info nino-text-center">33%</div></div>
		<div class="nino-grid-33 nino-p-1"><div class="nino-alert nino-alert--info nino-text-center">33%</div></div>
	</div>
	<div class="nino-grid-row">
		<div class="nino-grid-100 nino-grid-m-50 nino-grid-l-25 nino-p-1"><div class="nino-alert nino-alert--info nino-text-center">100 / 50 / 25</div></div>
		<div class="nino-grid-100 nino-grid-m-50 nino-grid-l-25 nino-p-1"><div class="nino-alert nino-alert--info nino-text-center">100 / 50 / 25</div></div>
		<div class="nino-grid-100 nino-grid-m-50 nino-grid-l-25 nino-p-1"><div class="nino-alert nino-alert--info nino-text-center">100 / 50 / 25</div></div>
		<div class="nino-grid-100 nino-grid-m-50 nino-grid-l-25 nino-p-1"><div class="nino-alert nino-alert--info nino-text-center">100 / 50 / 25</div></div>
	</div>
</section>

<!-- ATF / Hero + Cover -->
<section class="nino-atf nino-section--fullwidth nino-cover nino-cover-center" data-cover-height="60" style="color:var(--color-primary-text);">
	<img src="data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='1600' height='900'%3E%3Crect width='1600' height='900' fill='%232c3e50'/%3E%3C/svg%3E">
	<div class="nino-cover-content">
		<h3 class="nino-atf-title">ATF / Hero (nino-cover)</h3>
		<p class="nino-atf-subtitle">data-cover-height="60", nino-cover-center zentriert den Inhalt</p>
	</div>
	<button type="button" class="nino-atf-arrowdown" data-arrow-target="#demo-elements-parallax" aria-label="[[/global/scrolldown]]"></button>
</section>

<!-- Parallax -->
<section class="nino-parallex" id="demo-elements-parallax" style="color:var(--color-primary-text);">
	<img src="data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='1600' height='900'%3E%3Crect width='1600' height='900' fill='%233498db'/%3E%3C/svg%3E">
	<div class="nino-cover-content">
		<div class="nino-grid-row">
			<div class="nino-grid-100">
				<h3 class="nino-section-title">Parallax (nino-parallex)</h3>
				<p class="nino-section-subtitle">Scrollen zum Testen - Bild bewegt sich langsamer als der Rest der Seite</p>
			</div>
		</div>
	</div>
</section>

<!-- Sections -->
<section class="nino-section">
	<h3 class="nino-section-title">Section --default</h3>
	<p class="nino-section-subtitle nino-text-center">nino-section (ohne Modifier)</p>
</section>
<section class="nino-section nino-section--alt">
	<h3 class="nino-section-title">Section --alt</h3>
	<p class="nino-section-subtitle nino-text-center">nino-section nino-section--alt</p>
</section>
<section class="nino-section nino-section--dark">
	<h3 class="nino-section-title">Section --dark</h3>
	<p class="nino-section-subtitle nino-text-center">nino-section nino-section--dark</p>
</section>
<section class="nino-section nino-section--black">
	<h3 class="nino-section-title">Section --black</h3>
	<p class="nino-section-subtitle nino-text-center">nino-section nino-section--black</p>
</section>

<!-- Buttons -->
<section class="nino-section nino-section--alt">
	<div class="nino-grid-row">
		<div class="nino-grid-100">
			<h3 class="nino-section-title">Buttons</h3>
			<p class="nino-section-subtitle">nino-btn --primary/--outline/--light/--dark/--big/--small</p>
			<div class="nino-text-center">
				<a href="#" class="nino-btn nino-btn--primary">Primary</a>
				<a href="#" class="nino-btn nino-btn--outline">Outline</a>
				<a href="#" class="nino-btn nino-btn--light">Light</a>
				<a href="#" class="nino-btn nino-btn--dark">Dark</a>
				<a href="#" class="nino-btn nino-btn--primary nino-btn--big">Big Primary</a>
				<a href="#" class="nino-btn nino-btn--primary nino-btn--small">Small Primary</a>
			</div>
		</div>
	</div>
</section>

<!-- Icons -->
<section class="nino-section">
	<div class="nino-grid-row">
		<div class="nino-grid-100">
			<h3 class="nino-section-title">Icons</h3>
			<p class="nino-section-subtitle">nino-icon, nino-icon.small</p>
			<div class="nino-text-center">
				<svg class="nino-icon" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24"><path d="M20 4H4c-1.1 0-1.99.9-1.99 2L2 18c0 1.1.9 2 2 2h16c1.1 0 2-.9 2-2V6c0-1.1-.9-2-2-2m0 14H4V8l8 5 8-5zm-8-7L4 6h16z"></path></svg>
				<svg class="nino-icon--small" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24"><path d="M20 4H4c-1.1 0-1.99.9-1.99 2L2 18c0 1.1.9 2 2 2h16c1.1 0 2-.9 2-2V6c0-1.1-.9-2-2-2m0 14H4V8l8 5 8-5zm-8-7L4 6h16z"></path></svg>
			</div>
		</div>
	</div>
</section>

<!-- Badges -->
<section class="nino-section nino-section--alt">
	<div class="nino-grid-row">
		<div class="nino-grid-100">
			<h3 class="nino-section-title">Badge / Pill</h3>
			<p class="nino-section-subtitle">nino-badge --pill/--primary/--success/--error</p>
			<div class="nino-text-center">
				<span class="nino-badge">default</span>
				<span class="nino-badge nino-badge--pill">pill</span>
				<span class="nino-badge nino-badge--primary">primary</span>
				<span class="nino-badge nino-badge--success">success</span>
				<span class="nino-badge nino-badge--error">error</span>
			</div>
		</div>
	</div>
</section>

<!-- Breadcrumbs -->
<section class="nino-section">
	<div class="nino-grid-row">
		<div class="nino-grid-100">
			<h3 class="nino-section-title">Breadcrumbs</h3>
			<p class="nino-section-subtitle">nino-breadcrumbs</p>
			<ul class="nino-breadcrumbs nino-text-center" style="justify-content:center;">
				<li><a href="#">Start</a></li>
				<li><a href="#">Kategorie</a></li>
				<li>Aktuelle Seite</li>
			</ul>
		</div>
	</div>
</section>

<!-- Lists -->
<section class="nino-section nino-section--alt">
	<div class="nino-grid-row">
		<div class="nino-grid-100 nino-grid-m-50">
			<h3 class="nino-section-title">Liste --check</h3>
			<p class="nino-section-subtitle">nino-list nino-list--check</p>
			<ul class="nino-list nino-list--check">
				<li>Persönliche Erstberatung inklusive</li>
				<li>Individueller Fahrplan für Ihre Ziele</li>
				<li>Flexible Terminvereinbarung</li>
			</ul>
		</div>
		<div class="nino-grid-100 nino-grid-m-50">
			<h3 class="nino-section-title">Liste --numbered</h3>
			<p class="nino-section-subtitle">nino-list nino-list--numbered</p>
			<ul class="nino-list nino-list--numbered">
				<li>Kennenlerngespräch vereinbaren</li>
				<li>Gemeinsam den Fahrplan festlegen</li>
				<li>Umsetzung mit regelmäßigem Feedback</li>
			</ul>
		</div>
	</div>
</section>

<!-- Alerts -->
<section class="nino-section nino-section--alt">
	<div class="nino-grid-row">
		<div class="nino-grid-100">
			<h3 class="nino-section-title">Alert / Inline Feedback</h3>
			<p class="nino-section-subtitle">nino-alert --info/--success/--error</p>
			<div class="nino-alert nino-alert--info nino-mb-2">Info-Hinweis</div>
			<div class="nino-alert nino-alert--success nino-mb-2">Erfolgreich gespeichert</div>
			<div class="nino-alert nino-alert--error">Da ist etwas schiefgelaufen</div>
		</div>
	</div>
</section>

<!-- Video embed -->
<section class="nino-section">
	<div class="nino-grid-row">
		<div class="nino-grid-100 nino-grid-m-50">
			<h3 class="nino-section-title">Video Embed</h3>
			<p class="nino-section-subtitle">nino-video (16:9), nino-video--4-3</p>
			<div class="nino-video">
				<iframe src="about:blank" allowfullscreen></iframe>
			</div>
		</div>
	</div>
</section>

<!-- Article / Card via the elements shortcode -->
<section class="nino-section nino-section--alt">
	<div class="nino-grid-row">
		<div class="nino-grid-100">
			<h3 class="nino-section-title">Article / Card</h3>
			<p class="nino-section-subtitle">nino-article --alt, -cols. Inhalte kommen immer über den elements-Shortcode, nie fest verdrahtet</p>
		</div>
	</div>
	<div class="nino-grid-row">
		[elements /demo-services limit="3"]
		<div class="nino-grid-100 nino-grid-m-33">
			<article class="nino-article">
				<div class="nino-article-content">
					<h4 class="nino-article-title">[[title]]</h4>
					<p class="nino-article-descr">[[description]]</p>
					<span class="nino-badge">[[tasks]]</span>
				</div>
			</article>
		</div>
		[/elements]
	</div>
</section>
<section class="nino-section">
	<div class="nino-grid-row">
		<div class="nino-grid-100">
			<p class="nino-section-subtitle">nino-article-cols (Bild neben Text)</p>
		</div>
		[elements /demo-services limit="1"]
		<div class="nino-grid-100">
			<article class="nino-article nino-article-cols">
				<img class="nino-article-img" src="data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='800' height='450'%3E%3Crect width='800' height='450' fill='%23cccccc'/%3E%3C/svg%3E">
				<div class="nino-article-content">
					<h4 class="nino-article-title">[[title]]</h4>
					<p class="nino-article-descr">[[description]]</p>
				</div>
			</article>
		</div>
		[/elements]
	</div>
</section>

<!-- Table -->
<section class="nino-section">
	<div class="nino-grid-row">
		<div class="nino-grid-100">
			<h3 class="nino-section-title">Tabelle --default</h3>
			<p class="nino-section-subtitle">nino-table-wrap &gt; nino-table (horizontal scroll statt Breakout auf schmalen Viewports)</p>
			<div class="nino-table-wrap">
				<table class="nino-table">
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
<section class="nino-section">
	<div class="nino-grid-row">
		<div class="nino-grid-100">
			<h3 class="nino-section-title">Tabelle --striped / --bordered</h3>
			<p class="nino-section-subtitle">nino-table--striped, nino-table--bordered - kombinierbar</p>
			<div class="nino-table-wrap">
				<table class="nino-table nino-table--striped nino-table--bordered">
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
<section class="nino-section nino-section--alt">
	<div class="nino-grid-row">
		<div class="nino-grid-100">
			<h3 class="nino-section-title">Pricing Table</h3>
			<p class="nino-section-subtitle">nino-pricing-row / -item(--featured) / -title / -price, gefüllt über die elements-Shortcode-Schleife</p>
		</div>
	</div>
	<div class="nino-grid-row">
		<div class="nino-grid-100">
			<div class="nino-pricing-row">
				[elements /demo-services query="cat=standard"]
				<div class="nino-pricing-item">
					<h4 class="nino-pricing-title">[[title]]</h4>
					<p class="nino-pricing-price">[[price]] &euro;</p>
					<span class="nino-badge">[[cat]]</span>
				</div>
				[/elements]
				[elements /demo-services query="cat=premium"]
				<div class="nino-pricing-item nino-pricing-item--featured">
					<h4 class="nino-pricing-title">[[title]]</h4>
					<p class="nino-pricing-price">[[price]] &euro;</p>
					<span class="nino-badge nino-badge--primary">[[cat]]</span>
				</div>
				[/elements]
			</div>
		</div>
	</div>
</section>

<!-- Form -->
<section class="nino-section">
	<div class="nino-grid-row">
		<div class="nino-grid-100 nino-grid-m-50">
			<h3 class="nino-section-title">Formular</h3>
			<p class="nino-section-subtitle">nino-form, postet an / - siehe page-contact.tpl für die Referenz-Implementierung</p>
			<form class="nino-form">
				<label for="demo-name">Name</label>
				<input type="text" id="demo-name" name="name" class="nino-form-input" placeholder="Ihr Name" required>

				<label for="demo-email">E-Mail</label>
				<input type="email" id="demo-email" name="email" class="nino-form-input" placeholder="Ihre E-Mail" required>

				<label for="demo-message">Nachricht</label>
				<textarea id="demo-message" name="message" class="nino-form-textarea" placeholder="Ihre Nachricht" required></textarea>

				<p class="nino-form-message"></p>

				<button type="submit" class="nino-btn nino-btn--primary nino-form-submit">Absenden</button>
			</form>
		</div>
	</div>
</section>

<!-- Slider -->
<section class="nino-section nino-section--alt">
	<div class="nino-grid-row">
		<div class="nino-grid-100">
			<h3 class="nino-section-title">Slider</h3>
			<p class="nino-section-subtitle">nino-slider, touch swipe + nino-slider-points (Dot-Pagination)</p>
		</div>
	</div>
	<div class="nino-slider" data-slider-pos="0" data-slider-width="60%" data-slider-min="240px">
		<ul>
			<li><img src="data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='800' height='450'%3E%3Crect width='800' height='450' fill='%23e67e22'/%3E%3C/svg%3E"><h4>Folie 1</h4></li>
			<li><img src="data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='800' height='450'%3E%3Crect width='800' height='450' fill='%2327ae60'/%3E%3C/svg%3E"><h4>Folie 2</h4></li>
			<li><img src="data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='800' height='450'%3E%3Crect width='800' height='450' fill='%238e44ad'/%3E%3C/svg%3E"><h4>Folie 3</h4></li>
		</ul>
	</div>
</section>

<!-- Viewport animation -->
<section class="nino-section">
	<div class="nino-grid-row">
		<div class="nino-grid-100">
			<h3 class="nino-section-title">Viewport Animation</h3>
			<p class="nino-section-subtitle">nino-vpa, --repeat, --speed-slow - beim Scrollen ins Viewport einblenden. Alle Effekt-Varianten (--zoom-*, --blur-*, --flip-*, ...) siehe <a href="[[/webpage/demo-vpa/uri]]">demo-vpa</a>.</p>
		</div>
		<div class="nino-grid-100 nino-grid-m-33 nino-vpa nino-p-2"><div class="nino-alert nino-alert--info">nino-vpa</div></div>
		<div class="nino-grid-100 nino-grid-m-33 nino-vpa nino-vpa--speed-slow nino-p-2" data-vpa-delay="150ms"><div class="nino-alert nino-alert--info">nino-vpa --speed-slow, delay 150ms</div></div>
		<div class="nino-grid-100 nino-grid-m-33 nino-vpa nino-vpa--repeat nino-p-2"><div class="nino-alert nino-alert--info">nino-vpa --repeat</div></div>
	</div>
</section>

<!-- Autoheight -->
<section class="nino-section nino-section--alt">
	<div class="nino-grid-row">
		<div class="nino-grid-100">
			<h3 class="nino-section-title">Autoheight</h3>
			<p class="nino-section-subtitle">nino-autoheight, data-autoheight-group - gleicht die Höhe innerhalb einer Gruppe an</p>
		</div>
		<div class="nino-grid-100 nino-grid-m-33 nino-p-1"><div class="nino-alert nino-alert--info nino-autoheight" data-autoheight-group="demo">Kurzer Text.</div></div>
		<div class="nino-grid-100 nino-grid-m-33 nino-p-1"><div class="nino-alert nino-alert--info nino-autoheight" data-autoheight-group="demo">Ein etwas längerer Text, der über zwei Zeilen geht.</div></div>
		<div class="nino-grid-100 nino-grid-m-33 nino-p-1"><div class="nino-alert nino-alert--info nino-autoheight" data-autoheight-group="demo">Kurz.</div></div>
	</div>
</section>

<!-- Stat counter -->
<section class="nino-section">
	<div class="nino-grid-row">
		<div class="nino-grid-100">
			<h3 class="nino-section-title">Stat Counter</h3>
			<p class="nino-section-subtitle">nino-stat-counter, data-stat-counter-to/-suffix/-duration - zählt beim Einblenden hoch</p>
		</div>
		<div class="nino-grid-100 nino-grid-m-33 nino-text-center">
			<div class="nino-stat-counter" data-stat-counter-to="250" data-stat-counter-suffix="+">0</div>
			<p>Projekte</p>
		</div>
		<div class="nino-grid-100 nino-grid-m-33 nino-text-center">
			<div class="nino-stat-counter" data-stat-counter-to="98" data-stat-counter-suffix="%">0</div>
			<p>Zufriedenheit</p>
		</div>
		<div class="nino-grid-100 nino-grid-m-33 nino-text-center">
			<div class="nino-stat-counter" data-stat-counter-to="12.5" data-stat-counter-suffix=" Jahre">0</div>
			<p>Erfahrung</p>
		</div>
	</div>
</section>

<!-- Utilities -->
<section class="nino-section nino-section--alt">
	<div class="nino-grid-row">
		<div class="nino-grid-100">
			<h3 class="nino-section-title">Utilities</h3>
			<p class="nino-section-subtitle">Spacing, Text-Align, Opacity, Visibility, Img-Tools. Schriftgrößen sind keine Utility, sondern --quiet/--loud an der jeweiligen Klasse</p>
		</div>
		<div class="nino-grid-100 nino-grid-m-25 nino-p-1"><div class="nino-alert nino-text-left">nino-text-left</div></div>
		<div class="nino-grid-100 nino-grid-m-25 nino-p-1"><div class="nino-alert nino-text-center">nino-text-center</div></div>
		<div class="nino-grid-100 nino-grid-m-25 nino-p-1"><div class="nino-alert nino-text-right">nino-text-right</div></div>
		<div class="nino-grid-100 nino-grid-m-25 nino-p-1"><div class="nino-alert"><small>&lt;small&gt;</small></div></div>
	</div>
	<div class="nino-grid-row">
		<div class="nino-grid-20 nino-p-1"><div class="nino-alert nino-alert--info nino-opacity-30">opacity-30</div></div>
		<div class="nino-grid-20 nino-p-1"><div class="nino-alert nino-alert--info nino-opacity-50">opacity-50</div></div>
		<div class="nino-grid-20 nino-p-1"><div class="nino-alert nino-alert--info nino-opacity-70">opacity-70</div></div>
		<div class="nino-grid-20 nino-p-1"><div class="nino-alert nino-alert--info nino-opacity-90">opacity-90</div></div>
		<div class="nino-grid-20 nino-p-1"><div class="nino-alert nino-alert--info">opacity-100</div></div>
	</div>
</section>

<!-- Accordion -->
<section class="nino-section">
	<div class="nino-grid-row">
		<div class="nino-grid-100">
			<h3 class="nino-section-title">Accordion</h3>
			<p class="nino-section-subtitle">nino-accordion - natives details/summary, kein JS nötig. Gleicher name="..." macht die Gruppe exklusiv</p>
		</div>
	</div>
	<div class="nino-grid-row">
		<div class="nino-grid-100 nino-grid-m-66">
			<details class="nino-accordion" name="demo-accordion" open>
				<summary class="nino-accordion-trigger">Was ist im Preis enthalten?</summary>
				<div class="nino-accordion-panel"><p>Beratung, Konzeption, Umsetzung und drei Monate Support nach Launch.</p></div>
			</details>
			<details class="nino-accordion" name="demo-accordion">
				<summary class="nino-accordion-trigger">Wie lange dauert ein Projekt?</summary>
				<div class="nino-accordion-panel"><p>Je nach Umfang zwischen zwei und acht Wochen.</p></div>
			</details>
			<details class="nino-accordion" name="demo-accordion">
				<summary class="nino-accordion-trigger">Gibt es eine Ratenzahlung?</summary>
				<div class="nino-accordion-panel"><p>Ja, ab einem Projektvolumen von 2.000&nbsp;€.</p></div>
			</details>
		</div>
	</div>
</section>

<!-- Tabs -->
<section class="nino-section">
	<div class="nino-grid-row">
		<div class="nino-grid-100">
			<h3 class="nino-section-title">Tabs</h3>
			<p class="nino-section-subtitle">nino-tabs, nino-tabs-nav/-tab, nino-tabs-panel + data-tabs-target</p>
		</div>
		<div class="nino-grid-100 nino-grid-m-66">
			<div class="nino-tabs">
				<div class="nino-tabs-nav">
					<button type="button" class="nino-tabs-tab nino-is-active" data-tabs-target="demo-tab-1">Leistungen</button>
					<button type="button" class="nino-tabs-tab" data-tabs-target="demo-tab-2">Preise</button>
					<button type="button" class="nino-tabs-tab" data-tabs-target="demo-tab-3">Kontakt</button>
				</div>
				<div class="nino-tabs-panel nino-is-active" id="demo-tab-1"><p>Beratung, Entwicklung, Schulungen und Workshops.</p></div>
				<div class="nino-tabs-panel" id="demo-tab-2"><p>Ab 80&nbsp;€ pro Stunde, Pakete auf Anfrage.</p></div>
				<div class="nino-tabs-panel" id="demo-tab-3"><p>[[/company/email]]</p></div>
			</div>
		</div>
	</div>
</section>

<!-- Modal / Lightbox -->
<section class="nino-section nino-section--alt">
	<div class="nino-grid-row">
		<div class="nino-grid-100">
			<h3 class="nino-section-title">Modal / Lightbox</h3>
			<p class="nino-section-subtitle">nino-modal (natives &lt;dialog&gt;), nino-modal-trigger + data-modal-target, nino-modal--lightbox</p>
			<div class="nino-text-center">
				<button type="button" class="nino-btn nino-btn--primary nino-modal-trigger" data-modal-target="demo-modal">Modal öffnen</button>
				<button type="button" class="nino-btn nino-btn--outline nino-modal-trigger" data-modal-target="demo-lightbox">Lightbox öffnen</button>
			</div>
		</div>
	</div>
	<dialog class="nino-modal" id="demo-modal">
		<button type="button" class="nino-modal-close" aria-label="Schließen">&times;</button>
		<h4>Modal-Titel</h4>
		<p>Beliebiger Inhalt - Formular, Bestätigung, Detailansicht. Schließt per Klick auf X, Hintergrund oder Escape.</p>
	</dialog>
	<dialog class="nino-modal nino-modal--lightbox" id="demo-lightbox">
		<button type="button" class="nino-modal-close" aria-label="Schließen">&times;</button>
		<img src="data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='1200' height='800'%3E%3Crect width='1200' height='800' fill='%232c3e50'/%3E%3C/svg%3E">
	</dialog>
</section>

<!-- Pagination -->
<section class="nino-section">
	<div class="nino-grid-row">
		<div class="nino-grid-100">
			<h3 class="nino-section-title">Pagination</h3>
			<p class="nino-section-subtitle">nino-pagination - reine Seitenzahlen-Links, kein JS</p>
			<ul class="nino-pagination nino-text-center" style="justify-content:center;">
				<li><a href="#">&lsaquo;</a></li>
				<li><a href="#">1</a></li>
				<li class="nino-is-active"><a href="#">2</a></li>
				<li><a href="#">3</a></li>
				<li><a href="#">4</a></li>
				<li><a href="#">&rsaquo;</a></li>
			</ul>
		</div>
	</div>
</section>

<!-- Toast -->
<section class="nino-section nino-section--alt">
	<div class="nino-grid-row">
		<div class="nino-grid-100">
			<h3 class="nino-section-title">Toast</h3>
			<p class="nino-section-subtitle">nino-toast-trigger + data-toast-message/-type, oder direkt Nino.ui.toast(message, type) aus eigenem JS</p>
			<div class="nino-text-center">
				<button type="button" class="nino-btn nino-btn--light nino-toast-trigger" data-toast-message="Gespeichert." data-toast-type="success">Success-Toast</button>
				<button type="button" class="nino-btn nino-btn--light nino-toast-trigger" data-toast-message="Da ist etwas schiefgelaufen." data-toast-type="error">Error-Toast</button>
				<button type="button" class="nino-btn nino-btn--light nino-toast-trigger" data-toast-message="Nur eine Info.">Default-Toast</button>
			</div>
		</div>
	</div>
</section>

[template /templates/html-footer]
