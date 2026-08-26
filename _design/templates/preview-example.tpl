<!--	The page /_design previews against.

	One element per thing a setting changes, and nothing else. The type scale
	is what Headings and Size fan out, the grid row is what Width bounds, the
	grounds are what Depth separates, the card border and the field outline
	are what Depth and Contrast draw, and the alerts are the status surfaces
	the brand knobs are not allowed to reach. Anything that showed the same
	token twice has been taken out - a preview earns its height by showing a
	difference, not by looking like a website.

	The two brand buttons sit side by side because that is the only way
	Harmony is visible at all: it decides where the second brand colour lands
	relative to the first, and one button can only ever show one of them. The
	tinted band is there for the same reason - it is the ground the brand hue
	actually reaches, so the tint and Temperature's Neutral position have
	somewhere to be seen.

	Laid out to be taken in at once. The frame renders at a desktop's layout
	width and is scaled into the panel, so the room here is horizontal rather
	than vertical: the card and the field sit beside the type instead of under
	it, and the four alerts share one short band. Scrolling a preview to find
	the rest of it is scrolling to find out whether a decision was right.

	Design-system classes only: no class here belongs to /_design, and there
	is no literal colour or size anywhere. That is the point of it. What it
	shows is what a project built on these settings gets, which it can only be
	if it is an ordinary page - .nino-article--alt is the framework's own card,
	down to stepping onto a different surface inside a dark section, and
	repeating that here by hand would only prove the copy right.

	Sections rather than <header> and <footer>, deliberately. Nino.css gives a
	bare <header> position:fixed, and a real page's frame stylesheet pays for
	that with a body main { padding-top } this document has no reason to carry
	- without it the first heading renders underneath the bar. Which frame a
	site uses is the Header and Footer dialogs' question anyway; this one is
	about the tokens every frame reads from.	-->

<!--	<main> because Nino.css gives it flex: 1 0 auto inside a body that is a
	column at least a viewport tall - which is what puts the last band at the
	bottom of the frame instead of leaving the rest of it blank. A real page
	behaves this way; the preview had simply never told it to.	-->
<main>

<section class="nino-section">
	<div class="nino-grid-row">
		<div class="nino-grid-100 nino-grid-m-66 nino-p-1">
			<h1 class="nino-section-title nino-text-left">Design that survives the brief</h1>
			<p class="nino-section-subtitle nino-text-left">The muted tier, one step below the ink above it.</p>
			<p>Body copy sits at the one size the scale never moves. Everything above it fans out from here &mdash; and <a href="#">a link like this one</a> is solved against this exact ground, not against the page it was designed on.</p>
			<p>
				<span class="nino-btn nino-btn--primary">Start a project</span>
				<span class="nino-btn nino-btn--brand-alt">The second colour</span>
				<span class="nino-btn nino-btn--outline">See the work</span>
				<span class="nino-btn nino-btn--primary" aria-disabled="true">Unavailable</span>
			</p>
		</div>
		<div class="nino-grid-100 nino-grid-m-33 nino-p-1">
			<div class="nino-article nino-article--alt">
				<h3 class="nino-article-title">A card on the page ground</h3>
				<p>Its border, its corners and its distance from the page are one decision.</p>
			</div>
			<form class="nino-form">
				<label for="preview-email">Work email</label>
				<input type="email" id="preview-email" class="nino-form-input" value="you@studio.com">
			</form>
		</div>
	</div>
</section>

<!--	Tinted rather than the alternate grey: the alternate ground is already
	on screen as the card above, and this is the one surface that carries the
	brand hue rather than a trace of it.	-->
<section class="nino-section nino-section--tint nino-pt-2 nino-pb-2">
	<div class="nino-grid-row">
		<div class="nino-grid-50 nino-grid-m-25 nino-p-1"><p class="nino-alert nino-alert--info">Info takes the brand as ink.</p></div>
		<div class="nino-grid-50 nino-grid-m-25 nino-p-1"><p class="nino-alert nino-alert--success">Success carries its own text.</p></div>
		<div class="nino-grid-50 nino-grid-m-25 nino-p-1"><p class="nino-alert nino-alert--warning">Warning sits between the two.</p></div>
		<div class="nino-grid-50 nino-grid-m-25 nino-p-1"><p class="nino-alert nino-alert--error">And red stays red.</p></div>
	</div>
</section>

<section class="nino-section nino-section--dark nino-pt-3 nino-pb-3">
	<div class="nino-grid-row">
		<div class="nino-grid-100 nino-grid-m-66 nino-p-1">
			<h2 class="nino-section-title nino-text-left">A band that paints its own ground</h2>
			<p>So it owns the ink on it too &mdash; <a href="#">including this link</a>, solved for this surface and no other.</p>
		</div>
		<div class="nino-grid-100 nino-grid-m-33 nino-p-1">
			<div class="nino-article nino-article--alt">
				<h3 class="nino-article-title">...and the card steps down</h3>
				<p>On a dark band the framework moves it to the deepest ground.</p>
			</div>
		</div>
	</div>
</section>

</main>

<section class="nino-section nino-section--black nino-pt-2 nino-pb-2">
	<div class="nino-grid-row">
		<div class="nino-grid-100">
			<p>The deepest ground, which is where most themes put their footer. <a href="#">One link</a> to measure it by.</p>
		</div>
	</div>
</section>
