<?php
/**
 * Title: World Graph Studio not found
 * Slug: worldgraph-child/not-found
 * Inserter: no
 *
 * @package WorldGraphChild
 */
?>
<!-- wp:group {"tagName":"section","align":"full","backgroundColor":"warm-ivory","textColor":"dark-espresso","className":"wg-section wg-not-found","layout":{"type":"constrained"}} -->
<section class="wp-block-group alignfull wg-section wg-not-found has-dark-espresso-color has-warm-ivory-background-color has-text-color has-background">
	<!-- wp:group {"align":"wide","layout":{"type":"constrained"}} -->
	<div class="wp-block-group alignwide">
		<!-- wp:paragraph {"className":"wg-eyebrow"} -->
		<p class="wg-eyebrow"><?php echo esc_html__( 'Error 404', 'worldgraph-child' ); ?></p>
		<!-- /wp:paragraph -->

		<!-- wp:heading {"level":1,"className":"wg-section__title","fontFamily":"headline"} -->
		<h1 class="wp-block-heading wg-section__title has-headline-font-family"><?php echo esc_html__( 'That page left the graph.', 'worldgraph-child' ); ?></h1>
		<!-- /wp:heading -->

		<!-- wp:paragraph {"className":"wg-section__summary"} -->
		<p class="wg-section__summary"><?php echo esc_html__( 'The address may have changed, or the page may no longer exist. Search the site or return to the studio home.', 'worldgraph-child' ); ?></p>
		<!-- /wp:paragraph -->

		<!-- wp:search {"label":"<?php echo esc_attr__( 'Search', 'worldgraph-child' ); ?>","showLabel":false,"placeholder":"<?php echo esc_attr__( 'Search World Graph Studio', 'worldgraph-child' ); ?>","buttonText":"<?php echo esc_attr__( 'Search', 'worldgraph-child' ); ?>","buttonUseIcon":true} /-->

		<!-- wp:buttons -->
		<div class="wp-block-buttons">
			<!-- wp:button -->
			<div class="wp-block-button"><a class="wp-block-button__link wp-element-button" href="<?php echo esc_url( home_url( '/' ) ); ?>"><?php echo esc_html__( 'Return home', 'worldgraph-child' ); ?></a></div>
			<!-- /wp:button -->
		</div>
		<!-- /wp:buttons -->
	</div>
	<!-- /wp:group -->
</section>
<!-- /wp:group -->
