[template /templates/html-header]

<section class="nino-atf nino-section--black nino-cover nino-text-center" data-cover-height="100">
	<div class="nino-cover-content">
	<div class="nino-grid-row">
		<div class="nino-grid-100">
			<h2 class="nino-atf-title">Design-System: Viewport-Animationen</h2>
			<p class="nino-atf-subtitle">Grundsätzliche Mechanik, dann eine Galerie zum Vergleichen. Sonstige Bausteine siehe <a href="[[/webpage/demo-elements/uri]]">demo-elements</a>.</p>
			<a href="[[/webpage/demo-elements/uri]]" class="nino-btn nino-btn--outline">Zu den Bausteinen &rarr;</a>
		</div>
	</div>
	</div>
</section>

<!-- ==================== Grundsätzliche Mechanik ==================== -->

<section class="nino-section nino-section--alt">
	<div class="nino-grid-row">
		<div class="nino-grid-100">
			<h2 class="nino-section-title">Grundsätzliche Mechanik</h2>
			<p class="nino-section-subtitle">nino-vpa blendet ein Element per Fade+Hochgleiten ein, sobald es in den Viewport scrollt. Ohne --repeat passiert das nur einmal.</p>
		</div>
	</div>
	<div class="nino-grid-row">
		<div class="nino-grid-100 nino-grid-m-50 nino-p-2">
			<div class="nino-vpa"><div class="nino-alert nino-alert--info">nino-vpa</div></div>
		</div>
		<div class="nino-grid-100 nino-grid-m-50 nino-p-2">
			<div class="nino-vpa nino-vpa--repeat"><div class="nino-alert nino-alert--info">nino-vpa nino-vpa--repeat (spielt bei jedem erneuten Reinscrollen)</div></div>
		</div>
	</div>
</section>

<section class="nino-section">
	<div class="nino-grid-row">
		<div class="nino-grid-100">
			<h3 class="nino-section-title">Geschwindigkeit</h3>
			<p class="nino-section-subtitle">nino-vpa--speed-fast / -medium / -slow - steuert nur die Transitionsdauer (.25s / .5s / 2s), unabhängig vom Effekt</p>
		</div>
	</div>
	<div class="nino-grid-row">
		<div class="nino-grid-100 nino-grid-m-33 nino-p-2"><div class="nino-vpa nino-vpa--repeat nino-vpa--speed-fast"><div class="nino-alert nino-alert--info">--speed-fast</div></div></div>
		<div class="nino-grid-100 nino-grid-m-33 nino-p-2"><div class="nino-vpa nino-vpa--repeat nino-vpa--speed-medium"><div class="nino-alert nino-alert--info">--speed-medium</div></div></div>
		<div class="nino-grid-100 nino-grid-m-33 nino-p-2"><div class="nino-vpa nino-vpa--repeat nino-vpa--speed-slow"><div class="nino-alert nino-alert--info">--speed-slow</div></div></div>
	</div>
</section>

<section class="nino-section nino-section--alt">
	<div class="nino-grid-row">
		<div class="nino-grid-100">
			<h3 class="nino-section-title">Effekt global setzen</h3>
			<p class="nino-section-subtitle">Jede nino-vpa--Modifier-Klasse (--zoom-*, --blur-*, --flip-*, --speed-*, ...) setzt nur ein paar CSS-Variablen (--vpa-t-hidden/-visible, --vpa-f-hidden/-visible, --vpa-origin, --vpa-duration). Dadurch reicht es, die Klasse auf &lt;body&gt; zu setzen, um Effekt und/oder Speed für alle nino-vpa-Elemente der Seite als Standard zu definieren:</p>
			<pre><code>&lt;body class="nino-vpa--zoom-medium nino-vpa--speed-slow"&gt;</code></pre>
			<p class="nino-section-subtitle">Ein einzelnes Element mit eigener Modifier-Klasse überschreibt diesen Seiten-Standard trotzdem - eine direkt am Element gesetzte CSS-Variable gewinnt immer gegen eine vom body geerbte, unabhängig von Selektor-Spezifität:</p>
			<pre><code>&lt;div class="nino-vpa nino-vpa--flip-hard"&gt;</code></pre>
			<p class="nino-section-subtitle">Live-Demo (hier simuliert über einen Wrapper mit nino-vpa--slide-left-medium statt body - der Vererbungsmechanismus ist derselbe): die ersten beiden Boxen erben --slide-left-medium vom Wrapper, die dritte überschreibt es lokal mit --flip-medium.</p>
		</div>
	</div>
	<div class="nino-vpa--slide-left-medium">
		<div class="nino-grid-row">
			<div class="nino-grid-100 nino-grid-m-33 nino-p-2"><div class="nino-vpa nino-vpa--repeat"><div class="nino-alert nino-alert--info">nino-vpa (erbt --slide-left-medium)</div></div></div>
			<div class="nino-grid-100 nino-grid-m-33 nino-p-2"><div class="nino-vpa nino-vpa--repeat"><div class="nino-alert nino-alert--info">nino-vpa (erbt --slide-left-medium)</div></div></div>
			<div class="nino-grid-100 nino-grid-m-33 nino-p-2"><div class="nino-vpa nino-vpa--repeat nino-vpa--flip-medium"><div class="nino-alert nino-alert--info">nino-vpa nino-vpa--flip-medium (überschreibt lokal)</div></div></div>
		</div>
	</div>
</section>

<!-- ==================== Effekt-Galerie ==================== -->

<section class="nino-section">
	<div class="nino-grid-row">
		<div class="nino-grid-100">
			<h2 class="nino-section-title">Effekt-Galerie</h2>
			<p class="nino-section-subtitle">Alle Boxen unten laufen mit nino-vpa--repeat + nino-vpa--speed-slow, damit sie sich beim Scrollen in Ruhe vergleichen lassen - sie spielen bei jedem Reinscrollen erneut ab, und tun das bewusst langsam.</p>
		</div>
	</div>

	<div class="nino-grid-row">
		<div class="nino-grid-100"><h3 class="nino-section-title">--zoom-soft / -medium / -hard</h3></div>
	</div>
	<div class="nino-grid-row">
		<div class="nino-grid-100 nino-grid-m-33 nino-p-2"><div class="nino-vpa nino-vpa--repeat nino-vpa--speed-slow nino-vpa--zoom-soft"><div class="nino-alert nino-alert--info">--zoom-soft</div></div></div>
		<div class="nino-grid-100 nino-grid-m-33 nino-p-2"><div class="nino-vpa nino-vpa--repeat nino-vpa--speed-slow nino-vpa--zoom-medium"><div class="nino-alert nino-alert--info">--zoom-medium</div></div></div>
		<div class="nino-grid-100 nino-grid-m-33 nino-p-2"><div class="nino-vpa nino-vpa--repeat nino-vpa--speed-slow nino-vpa--zoom-hard"><div class="nino-alert nino-alert--info">--zoom-hard</div></div></div>
	</div>

	<div class="nino-grid-row">
		<div class="nino-grid-100"><h3 class="nino-section-title">--zoom-out-soft / -medium / -hard</h3></div>
	</div>
	<div class="nino-grid-row">
		<div class="nino-grid-100 nino-grid-m-33 nino-p-2"><div class="nino-vpa nino-vpa--repeat nino-vpa--speed-slow nino-vpa--zoom-out-soft"><div class="nino-alert nino-alert--info">--zoom-out-soft</div></div></div>
		<div class="nino-grid-100 nino-grid-m-33 nino-p-2"><div class="nino-vpa nino-vpa--repeat nino-vpa--speed-slow nino-vpa--zoom-out-medium"><div class="nino-alert nino-alert--info">--zoom-out-medium</div></div></div>
		<div class="nino-grid-100 nino-grid-m-33 nino-p-2"><div class="nino-vpa nino-vpa--repeat nino-vpa--speed-slow nino-vpa--zoom-out-hard"><div class="nino-alert nino-alert--info">--zoom-out-hard</div></div></div>
	</div>

	<div class="nino-grid-row">
		<div class="nino-grid-100"><h3 class="nino-section-title">--slide-left-soft / -medium / -hard</h3></div>
	</div>
	<div class="nino-grid-row">
		<div class="nino-grid-100 nino-grid-m-33 nino-p-2"><div class="nino-vpa nino-vpa--repeat nino-vpa--speed-slow nino-vpa--slide-left-soft"><div class="nino-alert nino-alert--info">--slide-left-soft</div></div></div>
		<div class="nino-grid-100 nino-grid-m-33 nino-p-2"><div class="nino-vpa nino-vpa--repeat nino-vpa--speed-slow nino-vpa--slide-left-medium"><div class="nino-alert nino-alert--info">--slide-left-medium</div></div></div>
		<div class="nino-grid-100 nino-grid-m-33 nino-p-2"><div class="nino-vpa nino-vpa--repeat nino-vpa--speed-slow nino-vpa--slide-left-hard"><div class="nino-alert nino-alert--info">--slide-left-hard</div></div></div>
	</div>

	<div class="nino-grid-row">
		<div class="nino-grid-100"><h3 class="nino-section-title">--slide-right-soft / -medium / -hard</h3></div>
	</div>
	<div class="nino-grid-row">
		<div class="nino-grid-100 nino-grid-m-33 nino-p-2"><div class="nino-vpa nino-vpa--repeat nino-vpa--speed-slow nino-vpa--slide-right-soft"><div class="nino-alert nino-alert--info">--slide-right-soft</div></div></div>
		<div class="nino-grid-100 nino-grid-m-33 nino-p-2"><div class="nino-vpa nino-vpa--repeat nino-vpa--speed-slow nino-vpa--slide-right-medium"><div class="nino-alert nino-alert--info">--slide-right-medium</div></div></div>
		<div class="nino-grid-100 nino-grid-m-33 nino-p-2"><div class="nino-vpa nino-vpa--repeat nino-vpa--speed-slow nino-vpa--slide-right-hard"><div class="nino-alert nino-alert--info">--slide-right-hard</div></div></div>
	</div>

	<div class="nino-grid-row">
		<div class="nino-grid-100"><h3 class="nino-section-title">--blur-soft / -medium / -hard</h3></div>
	</div>
	<div class="nino-grid-row">
		<div class="nino-grid-100 nino-grid-m-33 nino-p-2"><div class="nino-vpa nino-vpa--repeat nino-vpa--speed-slow nino-vpa--blur-soft"><div class="nino-alert nino-alert--info">--blur-soft</div></div></div>
		<div class="nino-grid-100 nino-grid-m-33 nino-p-2"><div class="nino-vpa nino-vpa--repeat nino-vpa--speed-slow nino-vpa--blur-medium"><div class="nino-alert nino-alert--info">--blur-medium</div></div></div>
		<div class="nino-grid-100 nino-grid-m-33 nino-p-2"><div class="nino-vpa nino-vpa--repeat nino-vpa--speed-slow nino-vpa--blur-hard"><div class="nino-alert nino-alert--info">--blur-hard</div></div></div>
	</div>

	<div class="nino-grid-row">
		<div class="nino-grid-100"><h3 class="nino-section-title">--flip-soft / -medium / -hard</h3></div>
	</div>
	<div class="nino-grid-row">
		<div class="nino-grid-100 nino-grid-m-33 nino-p-2"><div class="nino-vpa nino-vpa--repeat nino-vpa--speed-slow nino-vpa--flip-soft"><div class="nino-alert nino-alert--info">--flip-soft</div></div></div>
		<div class="nino-grid-100 nino-grid-m-33 nino-p-2"><div class="nino-vpa nino-vpa--repeat nino-vpa--speed-slow nino-vpa--flip-medium"><div class="nino-alert nino-alert--info">--flip-medium</div></div></div>
		<div class="nino-grid-100 nino-grid-m-33 nino-p-2"><div class="nino-vpa nino-vpa--repeat nino-vpa--speed-slow nino-vpa--flip-hard"><div class="nino-alert nino-alert--info">--flip-hard</div></div></div>
	</div>
</section>

<!-- ==================== Effekt-Kombinationen ==================== -->

<section class="nino-section nino-section--alt">
	<div class="nino-grid-row">
		<div class="nino-grid-100">
			<h2 class="nino-section-title">Effekt-Kombinationen</h2>
			<p class="nino-section-subtitle">--blur-* setzt nur --vpa-f-hidden/-visible (Filter), nicht --vpa-t-hidden/-visible (Transform) - dadurch lässt es sich mit jedem anderen Effekt kombinieren. Zwei Transform-Effekte gleichzeitig (z.B. --zoom + --flip) überschreiben sich dagegen gegenseitig - hier gewinnt die zuletzt in der Klassenliste... genauer: die im Stylesheet zuletzt definierte Regel.</p>
		</div>
	</div>
	<div class="nino-grid-row">
		<div class="nino-grid-100 nino-grid-m-25 nino-p-2"><div class="nino-vpa nino-vpa--repeat nino-vpa--speed-slow nino-vpa--zoom-medium nino-vpa--blur-soft"><div class="nino-alert nino-alert--info">--zoom-medium + --blur-soft</div></div></div>
		<div class="nino-grid-100 nino-grid-m-25 nino-p-2"><div class="nino-vpa nino-vpa--repeat nino-vpa--speed-slow nino-vpa--zoom-out-hard nino-vpa--blur-hard"><div class="nino-alert nino-alert--info">--zoom-out-hard + --blur-hard</div></div></div>
		<div class="nino-grid-100 nino-grid-m-25 nino-p-2"><div class="nino-vpa nino-vpa--repeat nino-vpa--speed-slow nino-vpa--slide-left-medium nino-vpa--blur-soft"><div class="nino-alert nino-alert--info">--slide-left-medium + --blur-soft</div></div></div>
		<div class="nino-grid-100 nino-grid-m-25 nino-p-2"><div class="nino-vpa nino-vpa--repeat nino-vpa--speed-slow nino-vpa--flip-medium nino-vpa--blur-medium"><div class="nino-alert nino-alert--info">--flip-medium + --blur-medium</div></div></div>
	</div>
</section>

[template /templates/html-footer]
