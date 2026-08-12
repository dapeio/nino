<!-- nino:template-name Home -->
<!-- nino:template-vpa on -->
<!-- nino:template-slot header -->
[template /templates/html-header]
<section id="welcome" class="ui-atf ui-section--fullwidth js-cover js-cover--dim js-cover-center" data-cover-height="100">
	<!-- nino:section {"preset":"hero-full","version":1,"pageId":"home","id":"welcome","shell":"hero","surface":"default","background":"image-cover","header":"title-subtitle","align":"center","content":"none","contentStyle":"auto","action":"none","motion":"page","pageMotion":"on","padding":"default","margin":"none","border":"none","layout":"auto","limit":3,"elementType":""} -->
	<img src="[[/nino/dir]]/images/demo.jpg">
	<div class="js-cover-content">
		<div class="ui-grid-row js-vpa">
			<div class="ui-grid-100 ui-text-center">
				<h2 class="ui-atf-title">[[/page-home/welcome/title]]</h2>
				<p class="ui-atf-subtitle">[[/page-home/welcome/subtitle]]</p>
			</div>
		</div>
	</div>
</section>
<!-- nino:template-slot footer -->
[template /templates/html-footer]
