[template /templates/html-header]

<section class="nino-atf nino-section--black nino-cover nino-text-center" data-cover-height="100">
	<div class="nino-cover-content">
		<div class="nino-grid-row">
			<div class="nino-grid-100">
				<h2 class="nino-atf-title">Design-System: Sections</h2>
				<p class="nino-atf-subtitle">Fertige, realistische Section-Typen zum Copy/Paste für neue Seiten - Markup direkt aus den Bausteinen in docs/design-system.md zusammengesetzt.</p>
				<a href="[[/webpage/demo-elements/uri]]" class="nino-btn nino-btn--outline">Zu den einzelnen Bausteinen &rarr;</a>
			</div>
		</div>
	</div>
</section>

<section class="nino-section nino-section--primary">
	<div class="nino-grid-row">
		<div class="nino-grid-100">
			<h2 class="nino-section-title">ATF Heros</h2>
			<p class="nino-section-subtitle">Elemente für den Seitenstart</p>
		</div>
	</div>
</section>

<section class="nino-atf nino-section--fullwidth nino-cover nino-cover--dim" data-cover-height="100">
	<img src="[[/nino/public]]/images/demo-00.jpg">
	<div class="nino-cover-content">
		<div class="nino-grid-row">
			<div class="nino-grid-100 nino-vpa nino-vpa--speed-slow">
				<h3 class="nino-atf-title">ATF Hero mit Cover-Bild</h3>
				<p class="nino-atf-subtitle">Vollflächiges Bild, linksbündig, zwei Call-to-Actions</p>
				<a href="#" class="nino-btn nino-btn--primary">Jetzt starten</a>
				<a href="#" class="nino-btn nino-btn--outline nino-mt-2">Mehr erfahren</a>
			</div>
		</div>
	</div>
	<button type="button" class="nino-atf-arrowdown" data-arrow-target="#page-home-services" aria-label="[[/global/scrolldown]]"></button>
</section>

<section class="nino-atf nino-section--fullwidth nino-cover nino-cover-center nino-cover--dim" data-cover-height="100">
	<img src="[[/nino/public]]/images/demo-00.jpg">
	<div class="nino-cover-content">
		<div class="nino-grid-row">
			<div class="nino-grid-100 nino-vpa nino-vpa--speed-slow">
				<h3 class="nino-atf-title">ATF Hero mit Cover-Bild</h3>
				<p class="nino-atf-subtitle">Vollflächiges Bild, Text zentriert, zwei Call-to-Actions</p>
				<a href="#" class="nino-btn nino-btn--primary">Jetzt starten</a>
				<a href="#" class="nino-btn nino-btn--outline nino-mt-2">Mehr erfahren</a>
			</div>
		</div>
	</div>
	<button type="button" class="nino-atf-arrowdown" data-arrow-target="#page-home-services" aria-label="[[/global/scrolldown]]"></button>
</section>

<!-- ============================================================
     Hero: Ohne Bild, reiner Section-Hintergrund (schneller/leichter
     als ein Cover-Bild, wenn kein passendes Bild vorhanden ist)
     ============================================================ -->
<section class="nino-atf nino-atf--fullscreen nino-section--fullwidth nino-section--dark nino-text-center" id="demo-sections-hero-nobg">
	<div class="nino-grid-row">
		<div class="nino-grid-100 nino-vpa nino-vpa--speed-slow">
			<h3 class="nino-atf-title">ATF Hero ohne Bild</h3>
			<p class="nino-atf-subtitle">Gleiches Layout, aber nur Section-Hintergrundfarbe statt Bild</p>
			<a href="#" class="nino-btn nino-btn--primary">Jetzt starten</a>
		</div>
	</div>
	<button type="button" class="nino-atf-arrowdown" data-arrow-target="#demo-sections-parallax" aria-label="[[/global/scrolldown]]"></button>
</section>


<section class="nino-section nino-section--primary">
	<div class="nino-grid-row">
		<div class="nino-grid-100">
			<h2 class="nino-section-title">Mono-Articles</h2>
			<p class="nino-section-subtitle">Nicht wiederkehrende Article-Inhalte.</p>
		</div>
	</div>
</section>


<!-- ============================================================
     50/50 Grid: Bild links, Text rechts
     ============================================================ -->
<section class="nino-section">
	<div class="nino-grid-row nino-grid-middle">
		<div class="nino-grid-100 nino-grid-m-50 nino-img-cover">
			<img class="nino-article-img" src="[[/nino/public]]/images/demo-00.jpg" style="height:320px;">
		</div>
		<div class="nino-grid-100 nino-grid-m-50">
			<article class="nino-article">
				<div class="nino-article-content">
					<h3 class="nino-article-title">Bild links, Text rechts</h3>
					<p class="nino-article-descr">Klassisches 50/50-Layout - auf Mobile stapeln sich beide Spalten automatisch (nino-grid-100, ab "m" nebeneinander via nino-grid-m-50).</p>
					<a href="#" class="nino-btn nino-btn--primary">Mehr erfahren</a>
				</div>
			</article>
		</div>
	</div>
</section>

<!-- ============================================================
     50/50 Grid: Text links, Bild rechts (gespiegelt - im Markup
     einfach die Reihenfolge der zwei Grid-Divs tauschen)
     ============================================================ -->
<section class="nino-section nino-section--alt">
	<div class="nino-grid-row nino-grid-middle">
		<div class="nino-grid-100 nino-grid-m-50">
			<article class="nino-article">
				<div class="nino-article-content">
					<h3 class="nino-article-title">Text links, Bild rechts</h3>
					<p class="nino-article-descr">Gleiches Layout gespiegelt - einfach die beiden Grid-Divs im Markup vertauschen. Gut geeignet, um bei mehreren aufeinanderfolgenden Sections abzuwechseln.</p>
					<a href="#" class="nino-btn nino-btn--primary">Mehr erfahren</a>
				</div>
			</article>
		</div>
		<div class="nino-grid-100 nino-grid-m-50 nino-img-cover">
			<img class="nino-article-img" src="[[/nino/public]]/images/demo-00.jpg" style="height:320px;">
		</div>
	</div>
</section>

<!-- ============================================================
     50/50 Grid, randlos: Bild links, Text rechts - die nino-grid-row
     bekommt zusätzlich nino-grid--fullwidth, wodurch das Bild bis zum
     Viewport-Rand ausbricht statt in der gepolsterten Row zu sitzen.
     Der Text behält seinen eigenen Innenabstand über eine eigene
     Innenblase statt der Row-Polsterung.
     ============================================================ -->
<section class="nino-section nino-section--fullwidth nino-section--fullheight">
	<div class="nino-grid-row nino-grid--fullwidth nino-grid-middle">
		<div class="nino-grid-100 nino-grid-m-50 nino-img-cover">
			<img src="[[/nino/public]]/images/demo-00.jpg" style="height:480px;">
		</div>
		<div class="nino-grid-100 nino-grid-m-50">
			<article class="nino-article">
				<div class="nino-article-content">
					<h3 class="nino-article-title">Randlos: Bild links, Text rechts</h3>
					<p class="nino-article-descr">Gleiches Layout, aber das Bild bricht bis zum Viewport-Rand aus statt in der gepolsterten Row zu sitzen - gut für großformatige Fotos ohne sichtbaren Rand.</p>
					<a href="#" class="nino-btn nino-btn--primary">Mehr erfahren</a>
				</div>
			</article>
		</div>
	</div>
</section>

<!-- ============================================================
     50/50 Grid, randlos: Text links, Bild rechts (gespiegelt)
     ============================================================ -->
<section class="nino-section nino-section--fullwidth nino-section--fullheight nino-section--alt">
	<div class="nino-grid-row nino-grid--fullwidth nino-grid-middle">
		<div class="nino-grid-100 nino-grid-m-50">
			<article class="nino-article">
				<div class="nino-article-content">
					<h3 class="nino-article-title">Randlos: Text links, Bild rechts</h3>
					<p class="nino-article-descr">Gleiches randlose Layout gespiegelt - einfach die beiden Grid-Divs im Markup vertauschen, wie bei der nicht-randlosen Variante oben.</p>
					<a href="#" class="nino-btn nino-btn--primary">Mehr erfahren</a>
				</div>
			</article>
		</div>
		<div class="nino-grid-100 nino-grid-m-50 nino-img-cover">
			<img src="[[/nino/public]]/images/demo-00.jpg" style="height:480px;">
		</div>
	</div>
</section>

<!-- ============================================================
     Zitat-Karte - Einzelnes Testimonial als eigenstaendige Karte,
     anders als das Parallax-Zitat weiter unten (dort Vollbild-
     Hintergrund statt einer Content-Karte)
     ============================================================ -->
<section class="nino-section nino-section--alt">
	<div class="nino-grid-row">
		<div class="nino-grid-100 nino-grid-m-66" style="margin:0 auto;">
			<article class="nino-article nino-text-center">
				<div class="nino-article-content">
					<p class="nino-article-title" style="font-weight:normal;">&bdquo;Die Zusammenarbeit war unkompliziert und das Ergebnis hat unsere Erwartungen übertroffen.&ldquo;</p>
					<div class="nino-mt-2" style="display:flex; align-items:center; justify-content:center; gap:var(--space-1);">
						<img src="[[/nino/public]]/images/demo-00.jpg" style="width:3rem; height:3rem; border-radius:50%; object-fit:cover;">
						<div class="nino-text-left">
							<strong>Julia Berger</strong><br>
							<small>Geschäftsführerin, Nordwind GmbH</small>
						</div>
					</div>
				</div>
			</article>
		</div>
	</div>
</section>

<!-- ============================================================
     Person-Spotlight - Eine einzelne Person vorgestellt, ueber
     nino-article-cols (Bild neben Text) statt eines wiederholten
     Team-Grids - die Bildbreite wird hier bewusst per Inline-Style
     ueberschrieben, da nino-article-cols' 12vw-Standardbreite fuer ein
     Grid aus mehreren Karten gedacht ist, nicht fuer einen Solo-Auftritt
     ============================================================ -->
<section class="nino-section">
	<div class="nino-grid-row">
		<div class="nino-grid-100 nino-grid-m-66" style="margin:0 auto;">
			<article class="nino-article nino-article-cols-m">
				<img class="nino-article-img" src="[[/nino/public]]/images/demo-00.jpg">
				<div class="nino-article-content">
					<h3 class="nino-article-title">Max Mustermann</h3>
					<p class="nino-article-subtitle">Gründer &amp; Berater</p>
					<p class="nino-article-descr">Über zehn Jahre Erfahrung in der Beratung mittelständischer Unternehmen - von der ersten Idee bis zur Umsetzung.</p>
					<div style="display:flex; gap:var(--space-1);">
						<svg class="nino-icon--small" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24"><path d="M20 4H4c-1.1 0-1.99.9-1.99 2L2 18c0 1.1.9 2 2 2h16c1.1 0 2-.9 2-2V6c0-1.1-.9-2-2-2m0 14H4V8l8 5 8-5zm-8-7L4 6h16z"></path></svg>
						<svg class="nino-icon--small" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24"><path d="M16 1H8C6.34 1 5 2.34 5 4v16c0 1.66 1.34 3 3 3h8c1.66 0 3-1.34 3-3V4c0-1.66-1.34-3-3-3m-2 20h-4v-1h4zm3.25-3H6.75V4h10.5z"></path></svg>
					</div>
				</div>
			</article>
		</div>
	</div>
</section>

<!-- ============================================================
     Bild mit Badge-Overlay - freistehendes nino-badge ueber dem Bild
     (nino-img-cover > nino-badge), zB fuer "Neu"/Rabatt/Kategorie
     ============================================================ -->
<section class="nino-section nino-section--alt">
	<div class="nino-grid-row">
		<div class="nino-grid-100 nino-grid-m-66" style="margin:0 auto;">
			<article class="nino-article">
				<div class="nino-img-cover" style="height:320px;">
					<img class="nino-article-img" src="[[/nino/public]]/images/demo-00.jpg">
					<span class="nino-badge nino-badge--primary">Neu</span>
				</div>
				<div class="nino-article-content nino-text-center">
					<h3 class="nino-article-title">Bild mit Badge-Overlay</h3>
					<p class="nino-article-descr">Ein freistehendes nino-badge über dem Bild - zB für "Neu", einen Rabatt oder eine Kategorie.</p>
				</div>
			</article>
		</div>
	</div>
</section>

<!-- ============================================================
     Feature-Callout - Eine einzelne zentrierte Karte als Blickfang,
     anders als die wiederholte nino-feature-list weiter unten
     ============================================================ -->
<section class="nino-section">
	<div class="nino-grid-row">
		<div class="nino-grid-100 nino-grid-m-50 nino-text-center" style="margin:0 auto;">
			<svg class="nino-icon" style="width:48px; height:48px;" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24"><path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm-2 15l-5-5 1.41-1.41L10 14.17l7.59-7.59L19 8l-9 9z"></path></svg>
			<h3 class="nino-article-title nino-mt-2">Kostenlose Erstberatung</h3>
			<p class="nino-article-descr">30 Minuten, unverbindlich - wir klären gemeinsam, ob und wie wir zusammenpassen.</p>
			<a href="#" class="nino-btn nino-btn--primary">Termin vereinbaren</a>
		</div>
	</div>
</section>


<section class="nino-section nino-section--primary">
	<div class="nino-grid-row">
		<div class="nino-grid-100">
			<h2 class="nino-section-title">Poly-Articles</h2>
			<p class="nino-section-subtitle">Artikel-Inhalte mit mehreren Beiträgen.</p>
		</div>
	</div>
</section>

<!-- ============================================================
     Article-Grid, 3-spaltig - Inhalte immer über die elements-Shortcode-
     Schleife, nie fest verdrahtet (siehe docs/design-system.md)
     ============================================================ -->
<section class="nino-section">
	<div class="nino-grid-row">
		<div class="nino-grid-100 nino-text-center nino-mb-3">
			<h3 class="nino-section-title">Grid ohne Bild</h3>
			<p class="nino-section-subtitle">Einfacher 33% Article-Grid, ohne Bild, mit Button</p>
		</div>
		[elements /demo-services limit="3"]
		<div class="nino-grid-100 nino-grid-m-33 nino-mb-3">
			<article class="nino-article">
				<h4 class="nino-article-title">[[title]]</h4>
				<p class="nino-article-descr">[[description]]</p>
				<a href="#[[.uri]]" class="nino-btn nino-btn--light">Details</a>
			</article>
		</div>
		[/elements]
	</div>
</section>

<!-- ============================================================
     Article-Grid, alt-Variante mit Badge (zB Portfolio/Referenzen)
     ============================================================ -->
<section class="nino-section nino-section--alt">
	<div class="nino-grid-row">
		<div class="nino-grid-100 nino-text-center nino-mb-3">
			<h3 class="nino-section-title">Grid ohne Bild</h3>
			<p class="nino-section-subtitle">Article-Grid, alt-Variante mit Badge</p>
		</div>
		[elements /demo-services limit="3"]
		<div class="nino-grid-100 nino-grid-m-33 nino-mb-3">
			<article class="nino-article nino-article--alt">
				<h4 class="nino-article-title">[[title]]</h4>
				<p class="nino-article-descr">[[description]]</p>
				<span class="nino-badge nino-badge--primary">[[tasks]]</span>
			</article>
		</div>
		[/elements]
	</div>
</section>

<!-- ============================================================
     Article-Grid, alt-Variante mit Badge (zB Portfolio/Referenzen)
     ============================================================ -->
<section class="nino-section nino-section--alt">
	<div class="nino-grid-row">
		<div class="nino-grid-100 nino-text-center nino-mb-3">
			<h3 class="nino-section-title">Grid mit Bild</h3>
			<p class="nino-section-subtitle">Article-Grid, primary-Variante mit Preis</p>
		</div>
		[elements /demo-services limit="3"]
		<div class="nino-grid-100 nino-grid-m-33 nino-mb-3">
			<article class="nino-article nino-article--alt nino-article--wide">
				<img class="nino-article-img nino-article-img--dim" src="[[/nino/public]]/images/.demo/demo-0[[.id]].jpg">
				<div class="nino-article-content">
					<h4 class="nino-article-title">[[title]]</h4>
					<p class="nino-article-descr">[[description]]</p>
					<div class="nino-article-price">[[price]] €</div>
				</div>
			</article>
		</div>
		[/elements]
	</div>
</section>

<section class="nino-section">
	<div class="nino-grid-row">
		<div class="nino-grid-100 nino-text-center nino-mb-3">
			<h3 class="nino-section-title">Grid mit Bild</h3>
			<p class="nino-section-subtitle">Fullwidth Article-Grid, primary-Variante mit Preis</p>
		</div>
		[elements /demo-services limit="3"]
		<div class="nino-grid-100 nino-grid-m-33 nino-mb-3">
			<article class="nino-article nino-article--alt nino-article--fullwidth">
				<img class="nino-article-img" src="[[/nino/public]]/images/.demo/demo-0[[.id]].jpg">
				<div class="nino-article-content">
					<h4 class="nino-article-title">[[title]]</h4>
					<p class="nino-article-descr">[[description]]</p>
				</div>
			</article>
		</div>
		[/elements]
	</div>
</section>


<section class="nino-section nino-section--primary">
	<div class="nino-grid-row">
		<div class="nino-grid-100">
			<h2 class="nino-section-title">Andere Inhalte</h2>
			<p class="nino-section-subtitle">Elemente zur Auflockerung.</p>
		</div>
	</div>
</section>

<!-- ============================================================
     Parallax-Zitat: Bild mit Parallax-Effekt, zentriertes Statement
     ============================================================ -->
<section class="nino-parallex nino-parallex--dim" id="demo-sections-parallax" style="color:var(--color-primary-text);">
	<img src="[[/nino/public]]/images/demo-00.jpg">
	<div class="nino-cover-content nino-text-center">
		<div class="nino-grid-row">
			<div class="nino-grid-100">
				<h3>&bdquo;Ein prägnantes Zitat oder Statement, das beim Scrollen im Hintergrund mitzieht.&ldquo;</h3>
				<p>Kundenname, Position</p>
			</div>
		</div>
	</div>
</section>

<!-- ============================================================
     Stats-Reihe
     ============================================================ -->
<section class="nino-section nino-section--dark">
	<div class="nino-grid-row">
		<div class="nino-grid-100 nino-grid-m-33 nino-text-center">
			<div class="nino-stat-counter" data-stat-counter-to="250" data-stat-counter-suffix="+">0</div>
			<p>Projekte</p>
		</div>
		<div class="nino-grid-100 nino-grid-m-33 nino-text-center">
			<div class="nino-stat-counter" data-stat-counter-to="98" data-stat-counter-suffix="%">0</div>
			<p>Zufriedenheit</p>
		</div>
		<div class="nino-grid-100 nino-grid-m-33 nino-text-center">
			<div class="nino-stat-counter" data-stat-counter-to="12" data-stat-counter-suffix=" Jahre">0</div>
			<p>Erfahrung</p>
		</div>
	</div>
</section>

<!-- ============================================================
     Testimonial-Slider - nino-slider mit Zitat-Karten statt Bildern.
     Nino.ui.js macht keine Annahmen über den Inhalt der <li>s (die
     Höhe wird aus dem tatsächlich gerenderten Inhalt berechnet), das
     ist also derselbe Mechanismus wie ein Bild-Slider
     ============================================================ -->
<section class="nino-section nino-section--alt">
	<div class="nino-grid-row">
		<div class="nino-grid-100 nino-text-center nino-mb-3">
			<h3 class="nino-section-title">Das sagen unsere Kunden</h3>
			<p class="nino-section-subtitle">nino-slider mit Zitat-Karten statt Bildern</p>
		</div>
	</div>
	<div class="nino-slider" data-slider-pos="0" data-slider-width="60%" data-slider-min="280px">
		<ul>
			<li>
				<article class="nino-article nino-text-center">
					<div class="nino-article-content">
						<p class="nino-article-title">&bdquo;Schnelle Umsetzung, klare Kommunikation - genau das, was wir gebraucht haben.&ldquo;</p>
						<strong>Julia Berger</strong>, Nordwind GmbH
					</div>
				</article>
			</li>
			<li>
				<article class="nino-article nino-text-center">
					<div class="nino-article-content">
						<p class="nino-article-title">&bdquo;Von der ersten Idee bis zum Launch bestens betreut.&ldquo;</p>
						<strong>Tom Weber</strong>, Studio Elf
					</div>
				</article>
			</li>
			<li>
				<article class="nino-article nino-text-center">
					<div class="nino-article-content">
						<p class="nino-article-title">&bdquo;Absolut empfehlenswert - kompetent und zuverlässig.&ldquo;</p>
						<strong>Anna Klein</strong>, Bergmann &amp; Co
					</div>
				</article>
			</li>
		</ul>
	</div>
</section>

<!-- ============================================================
     Pricing-Sektion - Inhalte über die elements-Shortcode-Schleife
     ============================================================ -->
<section class="nino-section">
	<div class="nino-grid-row">
		<div class="nino-grid-100 nino-text-center nino-mb-3">
			<h3 class="nino-section-title">Preise</h3>
			<p class="nino-section-subtitle">Über die elements-Shortcode-Schleife befüllt</p>
		</div>
		<div class="nino-grid-100">
			<div class="nino-pricing-row">
				[elements /demo-services query="cat=standard"]
				<div class="nino-pricing-item">
					<h4 class="nino-pricing-title">[[title]]</h4>
					<p class="nino-pricing-price">[[price]] &euro;</p>
					<span class="nino-badge">[[cat]]</span>
				</div>
				[/elements]
				[elements /demo-services query="cat=premium" limit="1"]
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

<!-- ============================================================
     Vergleichstabelle - Standard vs. Premium im Detail, nino-table
     --striped --bordered statt Karten, wenn Zeilen 1:1 vergleichbar sind.
     Bewusst nino-section (nicht --alt) - --striped tönt Zeilen mit
     --color-section-alt-bg ein, das würde auf einer --alt-Section selbst
     mit deren eigenem Hintergrund verschwimmen
     ============================================================ -->
<section class="nino-section">
	<div class="nino-grid-row">
		<div class="nino-grid-100 nino-text-center nino-mb-3">
			<h3 class="nino-section-title">Im Detail verglichen</h3>
			<p class="nino-section-subtitle">nino-table-wrap &gt; nino-table --striped --bordered</p>
		</div>
		<div class="nino-grid-100">
			<div class="nino-table-wrap">
				<table class="nino-table nino-table--striped nino-table--bordered">
					<thead>
						<tr><th>Leistung</th><th>Standard</th><th>Premium</th></tr>
					</thead>
					<tbody>
						<tr><td>Persönliche Beratung</td><td>&check;</td><td>&check;</td></tr>
						<tr><td>Individueller Fahrplan</td><td>&check;</td><td>&check;</td></tr>
						<tr><td>E-Mail-Support</td><td>&check;</td><td>&check;</td></tr>
						<tr><td>Priorisierte Termine</td><td>&ndash;</td><td>&check;</td></tr>
						<tr><td>Strategie-Workshop</td><td>&ndash;</td><td>&check;</td></tr>
					</tbody>
				</table>
			</div>
		</div>
	</div>
</section>

<!-- ============================================================
     Leistungsumfang - Bild neben nino-list --check, gleiches 50/50-Grid
     wie Person-Spotlight oben, nur mit Liste statt Fließtext
     ============================================================ -->
<section class="nino-section nino-section--alt">
	<div class="nino-grid-row nino-grid-middle">
		<div class="nino-grid-100 nino-grid-m-50 nino-mb-2">
			<img src="[[/nino/public]]/images/demo-00.jpg" class="nino-article-img" style="border-radius:var(--radius-small);">
		</div>
		<div class="nino-grid-100 nino-grid-m-50">
			<article class="nino-article">
				<div class="nino-article-content">
					<h3 class="nino-article-title">Was inklusive ist</h3>
					<p class="nino-article-subtitle">nino-list nino-list--check</p>
					<ul class="nino-list nino-list--check">
						<li>Persönliches Kennenlerngespräch</li>
						<li>Individueller Fahrplan für Ihre Ziele</li>
						<li>E-Mail-Support zwischen den Terminen</li>
						<li>Flexible Terminverschiebung bis 24h vorher</li>
					</ul>
				</div>
			</article>
		</div>
	</div>
</section>

<!-- ============================================================
     Online-Formular - Zweispaltig, Kontaktinfo + nino-form
     ============================================================ -->
<section class="nino-section nino-section--alt">
	<div class="nino-grid-row">
		<div class="nino-grid-100 nino-mb-3">
			<h3 class="nino-section-title">Kontakt</h3>
			<p class="nino-section-subtitle">Schreiben Sie uns eine Nachricht</p>
		</div>
		<div class="nino-grid-100 nino-grid-m-50">
			<h4>Kontaktinformationen</h4>
			<p><strong>Adresse:</strong><br>Musterstraße 123<br>12345 Berlin</p>
			<p><strong>Telefon:</strong><br>+49 123 456789</p>
			<p><strong>E-Mail:</strong><br>info@example.com</p>
			<div class="nino-mt-3">
				<a href="tel:+49123456789" class="nino-btn nino-btn--outline">Anrufen</a>
			</div>
		</div>
		<div class="nino-grid-100 nino-grid-m-50">
			<form class="nino-form">
				<label for="ds-name">Name</label>
				<input type="text" id="ds-name" name="name" class="nino-form-input" placeholder="Ihr Name" required>

				<label for="ds-email">E-Mail</label>
				<input type="email" id="ds-email" name="email" class="nino-form-input" placeholder="Ihre E-Mail" required>

				<label for="ds-message">Nachricht</label>
				<textarea id="ds-message" name="message" class="nino-form-textarea" placeholder="Ihre Nachricht" required></textarea>

				<p class="nino-form-message"></p>

				<button type="submit" class="nino-btn nino-btn--primary nino-form-submit">Nachricht senden</button>
			</form>
		</div>
	</div>
</section>

<!-- ============================================================
     CTA-Banner - Dunkle Section, zentrierter Text + Button
     ============================================================ -->
<section class="nino-section nino-section--black nino-text-center">
	<div class="nino-grid-row">
		<div class="nino-grid-100">
			<h3 class="nino-section-title">Bereit loszulegen?</h3>
			<p class="nino-section-subtitle">Kurzer, prägnanter Aufruf zur Handlung am Ende einer Seite</p>
			<a href="#" class="nino-btn nino-btn--primary nino-btn--big">Jetzt Kontakt aufnehmen</a>
		</div>
	</div>
</section>

<!-- ============================================================
     Split-CTA - Zwei gleichwertige Handlungsoptionen nebeneinander
     ============================================================ -->
<section class="nino-section">
	<div class="nino-grid-row">
		<div class="nino-grid-100 nino-grid-m-50">
			<article class="nino-article nino-article--alt nino-text-center nino-article--fullwidth">
				<img src="[[/nino/public]]/images/demo-00.jpg" class="nino-article-img nino-article-img--maxheight">
				<div class="nino-article-content">
					<h3 class="nino-article-title">Für Unternehmen</h3>
					<p class="nino-article-descr">Individuelle Beratung für Ihr Projekt, von der Konzeption bis zum Launch.</p>
					<a href="#" class="nino-btn nino-btn--primary">Projekt anfragen</a>
				</div>
			</article>
		</div>
		<div class="nino-grid-100 nino-grid-m-50">
			<article class="nino-article nino-article--alt nino-text-center nino-article--fullwidth">
				<img src="[[/nino/public]]/images/demo-00.jpg" class="nino-article-img nino-article-img--maxheight">
				<div class="nino-article-content">
					<h3 class="nino-article-title">Für Privatpersonen</h3>
					<p class="nino-article-descr">Persönliches Coaching, das zu Ihrem Alltag passt.</p>
					<a href="#" class="nino-btn nino-btn--outline">Termin vereinbaren</a>
				</div>
			</article>
		</div>
	</div>
</section>

<!-- ============================================================
     Newsletter-Signup - Funktionierendes Formular, eigener Submit-
     Handler in _nino/Nino.ui.js (nino-newsletter-form) statt der
     generischen nino-form-Behandlung, da Neu-/Bereits-angemeldet
     unterschiedliche Meldungen brauchen. Postet an /.newsletter
     (Shortcodes\Newsletter, siehe _nino/Nino.php) - die Anmeldung
     wird erst mit dem Klick auf den Bestätigungslink in der Mail
     wirksam (Double-Opt-in).
     ============================================================ -->
<section class="nino-section nino-section--dark nino-text-center">
	<div class="nino-grid-row">
		<div class="nino-grid-100 nino-grid-m-66" style="margin:0 auto;">
			<h3 class="nino-section-title">Newsletter</h3>
			<p class="nino-section-subtitle">Gelegentliche Updates, kein Spam - jederzeit abbestellbar</p>
			<form class="nino-form nino-newsletter-form" action="/.newsletter" style="display:flex; gap:var(--space-1); flex-wrap:wrap; justify-content:center; align-items:flex-start;">
				[csrf]
				<input type="text" name="location" value="" tabindex="-1" autocomplete="off" aria-hidden="true" style="position:absolute; left:-9999px; width:1px; height:1px;">
				<label for="ds-newsletter-email" class="nino-sr-only">[[/newsletter/label/email]]</label>
				<input type="email" id="ds-newsletter-email" name="email" class="nino-form-input" placeholder="[[/newsletter/label/email]]" required>
				<button type="submit" class="nino-btn nino-btn--primary nino-form-submit">[[/newsletter/label/submit]]</button>
				<p class="nino-form-message nino-grid-100"></p>
			</form>
		</div>
	</div>
</section>

<!-- ============================================================
     FAQ - Accordion mit mehreren Fragen
     ============================================================ -->
<section class="nino-section">
	<div class="nino-grid-row">
		<div class="nino-grid-100 nino-text-center nino-mb-3">
			<h3 class="nino-section-title">Häufige Fragen</h3>
		</div>
		<div class="nino-grid-100 nino-grid-m-66" style="margin:0 auto;">
			<details class="nino-accordion" name="ds-faq" open>
				<summary class="nino-accordion-trigger">Was ist im Preis enthalten?</summary>
				<div class="nino-accordion-panel"><p>Beratung, Konzeption, Umsetzung und drei Monate Support nach Launch.</p></div>
			</details>
			<details class="nino-accordion" name="ds-faq">
				<summary class="nino-accordion-trigger">Wie lange dauert ein Projekt?</summary>
				<div class="nino-accordion-panel"><p>Je nach Umfang zwischen zwei und acht Wochen.</p></div>
			</details>
			<details class="nino-accordion" name="ds-faq">
				<summary class="nino-accordion-trigger">Gibt es eine Ratenzahlung?</summary>
				<div class="nino-accordion-panel"><p>Ja, ab einem Projektvolumen von 2.000&nbsp;€.</p></div>
			</details>
		</div>
	</div>
</section>

<!-- ============================================================
     Logo-/Partner-Leiste - Platzhalter als Text, in echt Logo-SVGs/PNGs
     ============================================================ -->
<section class="nino-section nino-section--alt nino-text-center">
	<div class="nino-grid-row">
		<div class="nino-grid-100 nino-mb-3">
			<p class="nino-section-subtitle">Vertrauen von</p>
		</div>
		<div class="nino-grid-100">
			<div class="nino-logos">
				<span class="nino-logos-item">Nordwind</span>
				<span class="nino-logos-item">Bergmann &amp; Co</span>
				<span class="nino-logos-item">Studio Elf</span>
				<span class="nino-logos-item">Kleinstadt</span>
				<span class="nino-logos-item">Rautenberg</span>
			</div>
		</div>
	</div>
</section>

<!-- ============================================================
     Bildergalerie - Mosaic-Grid mit gemischten Bildformaten, Klick öffnet
     das jeweilige Bild in der bereits bestehenden nino-modal--lightbox (kein
     neuer Mechanismus, gleiches Prinzip wie beim Video-Poster unten)
     ============================================================ -->
<section class="nino-section">
	<div class="nino-grid-row">
		<div class="nino-grid-100 nino-text-center nino-mb-3">
			<h3 class="nino-section-title">Bildergalerie</h3>
			<p class="nino-section-subtitle">Mosaic-Grid, einzelne Kacheln über nino-gallery-item--wide/--tall vergrößert, Klick öffnet die Lightbox</p>
		</div>
	</div>
	<div class="nino-gallery">
		<div class="nino-gallery-item nino-gallery-item--wide nino-gallery-item--tall">
			<button type="button" class="nino-modal-trigger" data-modal-target="ds-gallery-modal-1" aria-label="Bild vergrößern">
				<img src="[[/nino/public]]/images/demo-00.jpg" loading="lazy">
			</button>
		</div>
		<div class="nino-gallery-item">
			<button type="button" class="nino-modal-trigger" data-modal-target="ds-gallery-modal-2" aria-label="Bild vergrößern">
				<img src="[[/nino/public]]/images/demo-00.jpg" loading="lazy">
			</button>
		</div>
		<div class="nino-gallery-item">
			<button type="button" class="nino-modal-trigger" data-modal-target="ds-gallery-modal-3" aria-label="Bild vergrößern">
				<img src="[[/nino/public]]/images/demo-00.jpg" loading="lazy">
			</button>
		</div>
		<div class="nino-gallery-item nino-gallery-item--wide">
			<button type="button" class="nino-modal-trigger" data-modal-target="ds-gallery-modal-4" aria-label="Bild vergrößern">
				<img src="[[/nino/public]]/images/demo-00.jpg" loading="lazy">
			</button>
		</div>
		<div class="nino-gallery-item">
			<button type="button" class="nino-modal-trigger" data-modal-target="ds-gallery-modal-5" aria-label="Bild vergrößern">
				<img src="[[/nino/public]]/images/demo-00.jpg" loading="lazy">
			</button>
		</div>
		<div class="nino-gallery-item">
			<button type="button" class="nino-modal-trigger" data-modal-target="ds-gallery-modal-6" aria-label="Bild vergrößern">
				<img src="[[/nino/public]]/images/demo-00.jpg" loading="lazy">
			</button>
		</div>
	</div>
	<dialog class="nino-modal nino-modal--lightbox" id="ds-gallery-modal-1">
		<button type="button" class="nino-modal-close" aria-label="Schließen">&times;</button>
		<img src="[[/nino/public]]/images/demo-00.jpg">
	</dialog>
	<dialog class="nino-modal nino-modal--lightbox" id="ds-gallery-modal-2">
		<button type="button" class="nino-modal-close" aria-label="Schließen">&times;</button>
		<img src="[[/nino/public]]/images/demo-00.jpg">
	</dialog>
	<dialog class="nino-modal nino-modal--lightbox" id="ds-gallery-modal-3">
		<button type="button" class="nino-modal-close" aria-label="Schließen">&times;</button>
		<img src="[[/nino/public]]/images/demo-00.jpg">
	</dialog>
	<dialog class="nino-modal nino-modal--lightbox" id="ds-gallery-modal-4">
		<button type="button" class="nino-modal-close" aria-label="Schließen">&times;</button>
		<img src="[[/nino/public]]/images/demo-00.jpg">
	</dialog>
	<dialog class="nino-modal nino-modal--lightbox" id="ds-gallery-modal-5">
		<button type="button" class="nino-modal-close" aria-label="Schließen">&times;</button>
		<img src="[[/nino/public]]/images/demo-00.jpg">
	</dialog>
	<dialog class="nino-modal nino-modal--lightbox" id="ds-gallery-modal-6">
		<button type="button" class="nino-modal-close" aria-label="Schließen">&times;</button>
		<img src="[[/nino/public]]/images/demo-00.jpg">
	</dialog>
</section>

<!-- ============================================================
     Feature-Split - Icon-Liste links, großformatiges Bild rechts
     ============================================================ -->
<section class="nino-section nino-section--alt">
	<div class="nino-grid-row nino-grid-middle">
		<div class="nino-grid-100 nino-grid-m-50 nino-p-2">
			<h3>Warum mit uns arbeiten</h3>
			<p class="nino-section-subtitle">Icon-Liste, gekoppelt an ein Bild per 50/50-Grid</p>
			<ul class="nino-feature-list">
				<li class="nino-feature-item">
					<svg class="nino-icon" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24"><path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm-2 15l-5-5 1.41-1.41L10 14.17l7.59-7.59L19 8l-9 9z"></path></svg>
					<div>
						<h4>Geprüfte Qualität</h4>
						<p>Jedes Projekt durchläuft die gleiche sorgfältige Qualitätskontrolle.</p>
					</div>
				</li>
				<li class="nino-feature-item">
					<svg class="nino-icon" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24"><path d="M7 2v11h3v9l7-12h-4l4-8z"></path></svg>
					<div>
						<h4>Schnelle Umsetzung</h4>
						<p>Klare Prozesse statt langer Abstimmungsschleifen.</p>
					</div>
				</li>
				<li class="nino-feature-item">
					<svg class="nino-icon" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24"><path d="M12 1L3 5v6c0 5.55 3.84 10.74 9 12 5.16-1.26 9-6.45 9-12V5l-9-4zm-1.98 15L6 12l1.41-1.41 2.61 2.6 5.57-5.58L17 9l-6.98 7z"></path></svg>
					<div>
						<h4>Verlässlicher Support</h4>
						<p>Auch nach dem Launch für Sie erreichbar.</p>
					</div>
				</li>
			</ul>
		</div>
		<div class="nino-grid-100 nino-grid-m-50 nino-img-cover">
			<img src="[[/nino/public]]/images/demo-00.jpg" style="height:420px;">
		</div>
	</div>
</section>

<!-- ============================================================
     Prozess-Timeline - Nummerierte Schritte, verbunden per Linie
     ============================================================ -->
<section class="nino-section">
	<div class="nino-grid-row">
		<div class="nino-grid-100 nino-text-center nino-mb-3">
			<h3 class="nino-section-title">So arbeiten wir</h3>
			<p class="nino-section-subtitle">Prozess in vier Schritten</p>
		</div>
		<div class="nino-grid-100">
			<ol class="nino-timeline">
				<li class="nino-timeline-step">
					<div class="nino-timeline-number">1</div>
					<h4>Erstgespräch</h4>
					<p>Ziele, Umfang und Budget klären.</p>
				</li>
				<li class="nino-timeline-step">
					<div class="nino-timeline-number">2</div>
					<h4>Konzeption</h4>
					<p>Struktur und Design entwerfen.</p>
				</li>
				<li class="nino-timeline-step">
					<div class="nino-timeline-number">3</div>
					<h4>Umsetzung</h4>
					<p>Entwicklung mit regelmäßigen Zwischenständen.</p>
				</li>
				<li class="nino-timeline-step">
					<div class="nino-timeline-number">4</div>
					<h4>Launch</h4>
					<p>Übergabe und Support nach dem Start.</p>
				</li>
			</ol>
		</div>
	</div>
</section>

<!-- ============================================================
     Video-Sektion - Poster-Bild mit Play-Button, öffnet Video in der
     bereits bestehenden nino-modal--lightbox (kein neuer Mechanismus)
     ============================================================ -->
<section class="nino-section nino-section--alt">
	<div class="nino-grid-row">
		<div class="nino-grid-100 nino-text-center nino-mb-3">
			<h3 class="nino-section-title">Einblick in unsere Arbeit</h3>
			<p class="nino-section-subtitle">Video-Poster, öffnet den Embed im Lightbox-Modal</p>
		</div>
		<div class="nino-grid-100 nino-grid-m-75" style="margin:0 auto;">
			<button type="button" class="nino-video-poster nino-modal-trigger" data-modal-target="ds-video-modal" aria-label="Video abspielen">
				<img src="[[/nino/public]]/images/demo-01.jpg">
				<svg class="nino-video-play" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24"><path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm-2 14.5v-9l6 4.5-6 4.5z"></path></svg>
			</button>
		</div>
	</div>
	<dialog class="nino-modal nino-modal--video" id="ds-video-modal">
		<button type="button" class="nino-modal-close" aria-label="Schließen">&times;</button>
		<div class="nino-video">
			<iframe src="about:blank" allowfullscreen></iframe>
		</div>
	</dialog>
</section>

<!-- ============================================================
     Vollbild-Textbanner - Statisches Hintergrundbild mit dunklem
     Overlay, ohne Parallax-Scroll-Effekt (Unterschied zum Zitat oben)
     ============================================================ -->
<section class="nino-section nino-section--fullwidth nino-img-background nino-img-background--dim nino-text-center" style="padding:var(--space-6) var(--space-1); color:var(--color-primary-text);">
	<img src="[[/nino/public]]/images/demo-00.jpg">
	<div class="nino-grid-row nino-img-background-content">
		<div class="nino-grid-100">
			<h3 class="nino-atf-title">Vollbild-Textbanner</h3>
			<p class="nino-atf-subtitle">Statisches Bild statt Parallax, gut geeignet als ruhiger Abschluss vor dem Footer</p>
			<a href="#" class="nino-btn nino-btn--primary">Jetzt entdecken</a>
		</div>
	</div>
</section>

[template /templates/html-footer]