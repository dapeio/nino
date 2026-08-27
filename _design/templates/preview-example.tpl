<!--	The page /_design previews against.

	A whole page rather than a strip of specimens: a design decision cannot be
	judged one token at a time, and what an operator is deciding is how a site
	feels, not what any single value is. So this is an ordinary Nino page,
	written the way a real one is, and every class in it is one the framework
	defines. Nothing here belongs to /_design.

	The markup is the live markup, lifted from the section presets the Template
	Builder inserts (_install/library/pages/.demo-catalogue): the same nesting,
	the same utilities, the same spacing classes. That is the whole point of it
	- a mock with margins of its own would preview its own margins.

	What this file does *not* carry is the bar and the footer. Those are the
	project's own header and footer units, prepared by /_install and pasted
	around this markup by Preview::document(): a bar drawn here out of framework
	classes is a bar no site has, and on a look built around a vertical rail
	rather than a top bar it was not a simplification but the wrong picture.
	Everything between them is this file.

	Four bands, each there for something the settings move:

		the cover        the display end of the scale over a photograph, and
		                 all three button roles at once
		three cards      the alternate surface, borders, corners, images
		the split band   a second ground, body copy, and the four status
		                 surfaces the brand knobs are not allowed to reach
		the closing band the second brand colour as a surface of its own

	Sized to be taken in at once. The frame renders at a desktop's layout width
	and is scaled into the panel, so the room is horizontal rather than
	vertical, and the page is one screen of a site rather than all of one -
	scrolling a preview to find the rest of it is scrolling to find out whether
	a decision was right.

	The pictures are named the way a page names them, a path under the public
	directory, and Preview::images() carries them into the document - a srcdoc
	frame has an opaque origin and fetches nothing. They are the same demo
	images the catalogue page uses, so the preview shows the project's own
	pictures rather than a grey rectangle standing in for one.	-->
<main>

<section style="min-height:50vh" class="nino-atf nino-pt-1 nino-pb-2 nino-mt-0 nino-mb-0 nino-cover nino-cover--dim">
	<img src="[[/nino/public]]/images/.demo/demo-00.svg" alt="">
	<div class="nino-cover-content">
		<div class="nino-grid-row">
			<div class="nino-grid-100 nino-grid-m-66 nino-pt-6 nino-text-left">
				<h2 class="nino-atf-title">Design that survives the brief</h2>
				<p class="nino-atf-subtitle">Furniture made to last &mdash; designed and built in Regensburg.</p>
				<p>
					<a href="#" class="nino-btn nino-btn--primary">Start a project</a>
					<a href="#" class="nino-btn nino-btn--brand-alt">The second colour</a>
					<a href="#" class="nino-btn nino-btn--outline">See the work</a>
				</p>
			</div>
		</div>
	</div>
</section>

<section class="nino-section nino-section--alt nino-mt-0 nino-mb-0" aria-labelledby="karten-borderless-title">
	<div class="nino-grid-row nino-pb-3">
		<div class="nino-grid-100">
			<h4 class="nino-section-title">What we do</h4>
			<p class="nino-section-subtitle">Everything for your success</p>
		</div>
	</div>
	<div class="nino-grid-row">
		<div class="nino-grid-25">
			<article class="nino-article nino-article--alt nino-mb-3 nino-article--borderless">
				<img src="[[/nino/public]]/images/.demo/demo-00.svg" alt="Consulting" class="nino-article-img">
				<div class="nino-article-content">
					<h3 class="nino-article-title">Consulting</h3>
					<div class="nino-article-descr">In a conversation, we determine what the project really needs.</div>
					<a class="nino-btn" href="#">Learn more</a>
				</div>
			</article>
		</div>
		<div class="nino-grid-25">
			<article class="nino-article nino-article--alt nino-mb-3 nino-article--borderless">
				<img src="[[/nino/public]]/images/.demo/demo-00.svg" alt="Consulting" class="nino-article-img">
				<div class="nino-article-content">
					<h3 class="nino-article-title">Design</h3>
					<div class="nino-article-descr">The requirements become a design that works always in practice.</div>
					<a class="nino-btn" href="#">Learn more</a>
				</div>
			</article>
		</div>
		<div class="nino-grid-25">
			<article class="nino-article nino-article--alt nino-mb-3 nino-article--borderless">
				<img src="[[/nino/public]]/images/.demo/demo-00.svg" alt="Consulting" class="nino-article-img">
				<div class="nino-article-content">
					<h3 class="nino-article-title">Implementation</h3>
					<div class="nino-article-descr">Clean markup, clear structure and perfect maintainable for years.</div>
					<a class="nino-btn" href="#">Learn more</a>
				</div>
			</article>
		</div>
		<div class="nino-grid-25">
			<article class="nino-article nino-article--alt nino-mb-3 nino-article--borderless">
				<img src="[[/nino/public]]/images/.demo/demo-00.svg" alt="Consulting" class="nino-article-img">
				<div class="nino-article-content">
					<h3 class="nino-article-title">Support</h3>
					<div class="nino-article-descr">Updates, small changes and one contact who knows your project.</div>
					<a class="nino-btn" href="#">Learn more</a>
				</div>
			</article>
		</div>
		<div class="nino-grid-100 nino-text-center nino-mt-3">
			<a href="#" class="nino-btn nino-btn--primary nino-btn--big">Read more</a>
		</div>
	</div>
</section>
<section class="nino-section nino-section--primary nino-p-6">
	<div class="nino-grid-row">
		<div class="nino-grid-100 nino-text-center"><h2 class="nino-section-title" id="cta-centered-title">Have a piece of furniture in mind?</h2><p class="nino-section-subtitle">We take a look at the space and provide a no-obligation quote.</p></div>
		<div class="nino-grid-100 nino-mt-3 nino-text-center"><a href="#v-contact-form-split" class="nino-btn nino-btn--light">Book an appointment</a><a href="#katalog-presets" class="nino-btn nino-btn--outline">Browse first</a></div>
	</div>
</section>
<section class="nino-section nino-section--alt" aria-labelledby="preview-split-title">
	<div class="nino-grid-row nino-grid-middle">
		<div class="nino-grid-50"><div class="nino-img-cover"><img src="[[/nino/public]]/images/.demo/demo-01.svg" style="max-height:30vh" alt=""></div></div>
		<div class="nino-grid-50">
			<div class="nino-article">
				<div class="nino-article-content">
					<h3 class="nino-article-title" id="preview-split-title">Always included</h3>
					<p class="nino-article-subtitle">At no extra cost, in every package.</p>
						<ul class="nino-list nino-list--check">

						<li>Concept, copy and design from one source</li>

						<li>One appointment per week while the project is running</li>

						<li>Handover with backend training</li>

						</ul>
				</div>
			</div>
		</div>
	</div>
</section>
<section id="ablauf-timeline" class="nino-section nino-mt-0 nino-mb-0" aria-labelledby="ablauf-timeline-title">
	<div class="nino-grid-row">
		<div class="nino-grid-100 nino-mb-3 nino-text-center"><h2 class="nino-section-title" id="ablauf-timeline-title">From enquiry to handover</h2><p class="nino-section-subtitle">Four steps every project goes through.</p></div>
		<div class="nino-grid-100">
			<ol class="nino-timeline nino-timeline--counted">
				<li class="nino-timeline-step"><h4>Getting to know each other</h4><p>A conversation about goals, scope and timeframe.</p></li><li class="nino-timeline-step"><h4>Concept</h4><p>Structure, content and design are defined.</p></li><li class="nino-timeline-step"><h4>Implementation</h4><p>The site takes shape, and you see every stage along the way.</p></li><li class="nino-timeline-step"><h4>Handover</h4><p>Backend handover and training, with continued support afterwards.</p></li>
			</ol>
		</div>
	</div>
</section>
<section class="nino-section nino-section--tint" aria-labelledby="preview-split-title">
	<div class="nino-grid-row nino-grid-middle">
		<div class="nino-grid-50">
			<div class="nino-article">
				<div class="nino-article-content">
					<h3 class="nino-article-title" id="preview-split-title">Always included</h3>
					<p class="nino-article-subtitle">At no extra cost, in every package.</p>
					<p class="nino-article-descr">
						Body copy sits at the one size the scale never moves, and <a href="#">a link like this one</a> is solved against this exact ground rather than against the page it was designed on.
					</p>
				</div>
			</div>
		</div>
		<div class="nino-grid-50"><div class="nino-img-cover"><img src="[[/nino/public]]/images/.demo/demo-01.svg" style="max-height:30vh" alt=""></div></div>
	</div>
</section>

<section class="nino-section nino-section--dark nino-mt-0 nino-mb-0 nino-section" aria-labelledby="newsletter-centered-title">
	<div class="nino-grid-row nino-pt-4 nino-pb-4">
		<div class="nino-grid-100 nino-mb-3 nino-text-center"><h2 class="nino-section-title" id="newsletter-centered-title">Workshop newsletter</h2><p class="nino-section-subtitle">Four times a year, a look at what is currently being made.</p></div>
		<div class="nino-grid-100 nino-grid-m-66 nino-mx-auto">
			<form class="nino-form nino-newsletter-form nino-form--inline">
				<input type="text" name="location" value="" tabindex="-1" autocomplete="off" aria-hidden="true" class="nino-form-trap">
				<label for="newsletter-centered-email" class="nino-sr-only">Email address</label>
				<input style="width:50%" type="email" name="email" class="nino-form-input" placeholder="Email address" required="">
				<button type="submit" class="nino-btn nino-btn--primary nino-form-submit">Subscribe</button>
				<p class="nino-form-message nino-grid-100"></p>
			</form>
		</div>
	</div>
</section>
</main>

