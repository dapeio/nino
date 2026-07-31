[template /templates/html-header]

<section class="ui-atf ui-section--black js-cover ui-text-center" data-cover-height="100">
	<div class="js-cover-content">
	<div class="ui-grid-row">
		<div class="ui-grid-100">
			<h2 class="ui-atf-title">Design-System: Viewport-Animationen</h2>
			<p class="ui-atf-subtitle">Grundsätzliche Mechanik, dann eine Galerie zum Vergleichen. Sonstige Bausteine siehe <a href="[[/webpage/.demo-elements/uri]]">demo-elements</a>.</p>
			<a href="[[/webpage/.demo-elements/uri]]" class="ui-btn ui-btn--outline">Zu den Bausteinen &rarr;</a>
		</div>
	</div>
	</div>
</section>

<!-- ==================== Grundsätzliche Mechanik ==================== -->

<section class="ui-section ui-section--alt">
	<div class="ui-grid-row">
		<div class="ui-grid-100">
			<h2 class="ui-section-title">Grundsätzliche Mechanik</h2>
			<p class="ui-section-subtitle">js-vpa blendet ein Element per Fade+Hochgleiten ein, sobald es in den Viewport scrollt. Ohne --repeat passiert das nur einmal.</p>
		</div>
	</div>
	<div class="ui-grid-row">
		<div class="ui-grid-100 ui-grid-m-50 ui-p-2">
			<div class="js-vpa"><div class="ui-alert ui-alert--info">js-vpa</div></div>
		</div>
		<div class="ui-grid-100 ui-grid-m-50 ui-p-2">
			<div class="js-vpa js-vpa--repeat"><div class="ui-alert ui-alert--info">js-vpa js-vpa--repeat (spielt bei jedem erneuten Reinscrollen)</div></div>
		</div>
	</div>
</section>

<section class="ui-section">
	<div class="ui-grid-row">
		<div class="ui-grid-100">
			<h3 class="ui-section-title">Geschwindigkeit</h3>
			<p class="ui-section-subtitle">js-vpa--speed-fast / -medium / -slow - steuert nur die Transitionsdauer (.25s / .5s / 2s), unabhängig vom Effekt</p>
		</div>
	</div>
	<div class="ui-grid-row">
		<div class="ui-grid-100 ui-grid-m-33 ui-p-2"><div class="js-vpa js-vpa--repeat js-vpa--speed-fast"><div class="ui-alert ui-alert--info">--speed-fast</div></div></div>
		<div class="ui-grid-100 ui-grid-m-33 ui-p-2"><div class="js-vpa js-vpa--repeat js-vpa--speed-medium"><div class="ui-alert ui-alert--info">--speed-medium</div></div></div>
		<div class="ui-grid-100 ui-grid-m-33 ui-p-2"><div class="js-vpa js-vpa--repeat js-vpa--speed-slow"><div class="ui-alert ui-alert--info">--speed-slow</div></div></div>
	</div>
</section>

<section class="ui-section ui-section--alt">
	<div class="ui-grid-row">
		<div class="ui-grid-100">
			<h3 class="ui-section-title">Effekt global setzen</h3>
			<p class="ui-section-subtitle">Jede js-vpa--Modifier-Klasse (--zoom-*, --blur-*, --flip-*, --speed-*, ...) setzt nur ein paar CSS-Variablen (--vpa-t-hidden/-visible, --vpa-f-hidden/-visible, --vpa-origin, --vpa-duration). Dadurch reicht es, die Klasse auf &lt;body&gt; zu setzen, um Effekt und/oder Speed für alle js-vpa-Elemente der Seite als Standard zu definieren:</p>
			<pre><code>&lt;body class="js-vpa--zoom-medium js-vpa--speed-slow"&gt;</code></pre>
			<p class="ui-section-subtitle">Ein einzelnes Element mit eigener Modifier-Klasse überschreibt diesen Seiten-Standard trotzdem - eine direkt am Element gesetzte CSS-Variable gewinnt immer gegen eine vom body geerbte, unabhängig von Selektor-Spezifität:</p>
			<pre><code>&lt;div class="js-vpa js-vpa--flip-hard"&gt;</code></pre>
			<p class="ui-section-subtitle">Live-Demo (hier simuliert über einen Wrapper mit js-vpa--slide-left-medium statt body - der Vererbungsmechanismus ist derselbe): die ersten beiden Boxen erben --slide-left-medium vom Wrapper, die dritte überschreibt es lokal mit --flip-medium.</p>
		</div>
	</div>
	<div class="js-vpa--slide-left-medium">
		<div class="ui-grid-row">
			<div class="ui-grid-100 ui-grid-m-33 ui-p-2"><div class="js-vpa js-vpa--repeat"><div class="ui-alert ui-alert--info">js-vpa (erbt --slide-left-medium)</div></div></div>
			<div class="ui-grid-100 ui-grid-m-33 ui-p-2"><div class="js-vpa js-vpa--repeat"><div class="ui-alert ui-alert--info">js-vpa (erbt --slide-left-medium)</div></div></div>
			<div class="ui-grid-100 ui-grid-m-33 ui-p-2"><div class="js-vpa js-vpa--repeat js-vpa--flip-medium"><div class="ui-alert ui-alert--info">js-vpa js-vpa--flip-medium (überschreibt lokal)</div></div></div>
		</div>
	</div>
</section>

<!-- ==================== Effekt-Galerie ==================== -->

<section class="ui-section">
	<div class="ui-grid-row">
		<div class="ui-grid-100">
			<h2 class="ui-section-title">Effekt-Galerie</h2>
			<p class="ui-section-subtitle">Alle Boxen unten laufen mit js-vpa--repeat + js-vpa--speed-slow, damit sie sich beim Scrollen in Ruhe vergleichen lassen - sie spielen bei jedem Reinscrollen erneut ab, und tun das bewusst langsam.</p>
		</div>
	</div>

	<div class="ui-grid-row">
		<div class="ui-grid-100"><h3 class="ui-section-title">--zoom-soft / -medium / -hard</h3></div>
	</div>
	<div class="ui-grid-row">
		<div class="ui-grid-100 ui-grid-m-33 ui-p-2"><div class="js-vpa js-vpa--repeat js-vpa--speed-slow js-vpa--zoom-soft"><div class="ui-alert ui-alert--info">--zoom-soft</div></div></div>
		<div class="ui-grid-100 ui-grid-m-33 ui-p-2"><div class="js-vpa js-vpa--repeat js-vpa--speed-slow js-vpa--zoom-medium"><div class="ui-alert ui-alert--info">--zoom-medium</div></div></div>
		<div class="ui-grid-100 ui-grid-m-33 ui-p-2"><div class="js-vpa js-vpa--repeat js-vpa--speed-slow js-vpa--zoom-hard"><div class="ui-alert ui-alert--info">--zoom-hard</div></div></div>
	</div>

	<div class="ui-grid-row">
		<div class="ui-grid-100"><h3 class="ui-section-title">--zoom-out-soft / -medium / -hard</h3></div>
	</div>
	<div class="ui-grid-row">
		<div class="ui-grid-100 ui-grid-m-33 ui-p-2"><div class="js-vpa js-vpa--repeat js-vpa--speed-slow js-vpa--zoom-out-soft"><div class="ui-alert ui-alert--info">--zoom-out-soft</div></div></div>
		<div class="ui-grid-100 ui-grid-m-33 ui-p-2"><div class="js-vpa js-vpa--repeat js-vpa--speed-slow js-vpa--zoom-out-medium"><div class="ui-alert ui-alert--info">--zoom-out-medium</div></div></div>
		<div class="ui-grid-100 ui-grid-m-33 ui-p-2"><div class="js-vpa js-vpa--repeat js-vpa--speed-slow js-vpa--zoom-out-hard"><div class="ui-alert ui-alert--info">--zoom-out-hard</div></div></div>
	</div>

	<div class="ui-grid-row">
		<div class="ui-grid-100"><h3 class="ui-section-title">--slide-left-soft / -medium / -hard</h3></div>
	</div>
	<div class="ui-grid-row">
		<div class="ui-grid-100 ui-grid-m-33 ui-p-2"><div class="js-vpa js-vpa--repeat js-vpa--speed-slow js-vpa--slide-left-soft"><div class="ui-alert ui-alert--info">--slide-left-soft</div></div></div>
		<div class="ui-grid-100 ui-grid-m-33 ui-p-2"><div class="js-vpa js-vpa--repeat js-vpa--speed-slow js-vpa--slide-left-medium"><div class="ui-alert ui-alert--info">--slide-left-medium</div></div></div>
		<div class="ui-grid-100 ui-grid-m-33 ui-p-2"><div class="js-vpa js-vpa--repeat js-vpa--speed-slow js-vpa--slide-left-hard"><div class="ui-alert ui-alert--info">--slide-left-hard</div></div></div>
	</div>

	<div class="ui-grid-row">
		<div class="ui-grid-100"><h3 class="ui-section-title">--slide-right-soft / -medium / -hard</h3></div>
	</div>
	<div class="ui-grid-row">
		<div class="ui-grid-100 ui-grid-m-33 ui-p-2"><div class="js-vpa js-vpa--repeat js-vpa--speed-slow js-vpa--slide-right-soft"><div class="ui-alert ui-alert--info">--slide-right-soft</div></div></div>
		<div class="ui-grid-100 ui-grid-m-33 ui-p-2"><div class="js-vpa js-vpa--repeat js-vpa--speed-slow js-vpa--slide-right-medium"><div class="ui-alert ui-alert--info">--slide-right-medium</div></div></div>
		<div class="ui-grid-100 ui-grid-m-33 ui-p-2"><div class="js-vpa js-vpa--repeat js-vpa--speed-slow js-vpa--slide-right-hard"><div class="ui-alert ui-alert--info">--slide-right-hard</div></div></div>
	</div>

	<div class="ui-grid-row">
		<div class="ui-grid-100"><h3 class="ui-section-title">--blur-soft / -medium / -hard</h3></div>
	</div>
	<div class="ui-grid-row">
		<div class="ui-grid-100 ui-grid-m-33 ui-p-2"><div class="js-vpa js-vpa--repeat js-vpa--speed-slow js-vpa--blur-soft"><div class="ui-alert ui-alert--info">--blur-soft</div></div></div>
		<div class="ui-grid-100 ui-grid-m-33 ui-p-2"><div class="js-vpa js-vpa--repeat js-vpa--speed-slow js-vpa--blur-medium"><div class="ui-alert ui-alert--info">--blur-medium</div></div></div>
		<div class="ui-grid-100 ui-grid-m-33 ui-p-2"><div class="js-vpa js-vpa--repeat js-vpa--speed-slow js-vpa--blur-hard"><div class="ui-alert ui-alert--info">--blur-hard</div></div></div>
	</div>

	<div class="ui-grid-row">
		<div class="ui-grid-100"><h3 class="ui-section-title">--flip-soft / -medium / -hard</h3></div>
	</div>
	<div class="ui-grid-row">
		<div class="ui-grid-100 ui-grid-m-33 ui-p-2"><div class="js-vpa js-vpa--repeat js-vpa--speed-slow js-vpa--flip-soft"><div class="ui-alert ui-alert--info">--flip-soft</div></div></div>
		<div class="ui-grid-100 ui-grid-m-33 ui-p-2"><div class="js-vpa js-vpa--repeat js-vpa--speed-slow js-vpa--flip-medium"><div class="ui-alert ui-alert--info">--flip-medium</div></div></div>
		<div class="ui-grid-100 ui-grid-m-33 ui-p-2"><div class="js-vpa js-vpa--repeat js-vpa--speed-slow js-vpa--flip-hard"><div class="ui-alert ui-alert--info">--flip-hard</div></div></div>
	</div>
</section>

<!-- ==================== Effekt-Kombinationen ==================== -->

<section class="ui-section ui-section--alt">
	<div class="ui-grid-row">
		<div class="ui-grid-100">
			<h2 class="ui-section-title">Effekt-Kombinationen</h2>
			<p class="ui-section-subtitle">--blur-* setzt nur --vpa-f-hidden/-visible (Filter), nicht --vpa-t-hidden/-visible (Transform) - dadurch lässt es sich mit jedem anderen Effekt kombinieren. Zwei Transform-Effekte gleichzeitig (z.B. --zoom + --flip) überschreiben sich dagegen gegenseitig - hier gewinnt die zuletzt in der Klassenliste... genauer: die im Stylesheet zuletzt definierte Regel.</p>
		</div>
	</div>
	<div class="ui-grid-row">
		<div class="ui-grid-100 ui-grid-m-25 ui-p-2"><div class="js-vpa js-vpa--repeat js-vpa--speed-slow js-vpa--zoom-medium js-vpa--blur-soft"><div class="ui-alert ui-alert--info">--zoom-medium + --blur-soft</div></div></div>
		<div class="ui-grid-100 ui-grid-m-25 ui-p-2"><div class="js-vpa js-vpa--repeat js-vpa--speed-slow js-vpa--zoom-out-hard js-vpa--blur-hard"><div class="ui-alert ui-alert--info">--zoom-out-hard + --blur-hard</div></div></div>
		<div class="ui-grid-100 ui-grid-m-25 ui-p-2"><div class="js-vpa js-vpa--repeat js-vpa--speed-slow js-vpa--slide-left-medium js-vpa--blur-soft"><div class="ui-alert ui-alert--info">--slide-left-medium + --blur-soft</div></div></div>
		<div class="ui-grid-100 ui-grid-m-25 ui-p-2"><div class="js-vpa js-vpa--repeat js-vpa--speed-slow js-vpa--flip-medium js-vpa--blur-medium"><div class="ui-alert ui-alert--info">--flip-medium + --blur-medium</div></div></div>
	</div>
</section>

[template /templates/html-footer]
