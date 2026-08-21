<?php
/**
 * Title: Studio notes posts index
 * Slug: worldgraph-child/posts-index
 * Inserter: no
 *
 * @package WorldGraphChild
 */
?>
<!-- wp:group {"tagName":"section","align":"full","backgroundColor":"warm-ivory","textColor":"dark-espresso","className":"wg-section wg-posts-index","layout":{"type":"constrained"}} -->
<section class="wp-block-group alignfull wg-section wg-posts-index has-dark-espresso-color has-warm-ivory-background-color has-text-color has-background">
	<!-- wp:group {"align":"wide","className":"wg-section__header","layout":{"type":"constrained"}} -->
	<div class="wp-block-group alignwide wg-section__header">
		<!-- wp:paragraph {"align":"center","className":"wg-eyebrow"} -->
		<p class="has-text-align-center wg-eyebrow"><?php echo esc_html__( 'From the studio', 'worldgraph-child' ); ?></p>
		<!-- /wp:paragraph -->

		<!-- wp:heading {"textAlign":"center","level":1,"className":"wg-section__title","fontFamily":"headline"} -->
		<h1 class="wp-block-heading has-text-align-center wg-section__title has-headline-font-family"><?php echo esc_html__( 'Studio notes', 'worldgraph-child' ); ?></h1>
		<!-- /wp:heading -->
	</div>
	<!-- /wp:group -->

	<!-- wp:group {"align":"wide","layout":{"type":"constrained"}} -->
	<div class="wp-block-group alignwide">
		<!-- wp:pattern {"slug":"worldgraph-child/posts"} /-->
	</div>
	<!-- /wp:group -->
</section>
<!-- /wp:group -->
