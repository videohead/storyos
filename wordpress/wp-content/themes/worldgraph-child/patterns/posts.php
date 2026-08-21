<?php
/**
 * Title: World Graph Studio posts
 * Slug: worldgraph-child/posts
 * Block Types: core/query
 * Inserter: no
 *
 * @package WorldGraphChild
 */
?>
<!-- wp:query {"queryId":0,"query":{"pages":0,"offset":0,"postType":"post","order":"desc","orderBy":"date","author":"","search":"","exclude":[],"sticky":"","inherit":true},"className":"wg-posts-query","layout":{"type":"constrained"}} -->
<div class="wp-block-query wg-posts-query">
	<!-- wp:post-template -->
		<!-- wp:group {"tagName":"article","layout":{"type":"default"}} -->
		<article class="wp-block-group">
			<!-- wp:group {"tagName":"header","style":{"spacing":{"blockGap":"10px"}},"className":"entry-header"} -->
			<header class="wp-block-group entry-header">
				<!-- wp:post-title {"isLink":true} /-->
				<!-- wp:group {"style":{"spacing":{"blockGap":"8px","margin":{"bottom":"24px"}},"typography":{"fontSize":"0.9rem"}},"className":"post-meta","layout":{"type":"flex","flexWrap":"wrap"}} -->
				<div class="wp-block-group post-meta" style="margin-bottom:24px;font-size:0.9rem">
					<!-- wp:post-date /-->
					<!-- wp:paragraph -->
					<p>·</p>
					<!-- /wp:paragraph -->
					<!-- wp:post-author-name {"isLink":true} /-->
				</div>
				<!-- /wp:group -->
			</header>
			<!-- /wp:group -->
			<!-- wp:post-excerpt {"moreText":"<?php echo esc_attr__( 'Read more', 'worldgraph-child' ); ?>"} /-->
		</article>
		<!-- /wp:group -->
	<!-- /wp:post-template -->

	<!-- wp:query-pagination {"layout":{"type":"flex","justifyContent":"space-between"}} -->
		<!-- wp:query-pagination-previous /-->
		<!-- wp:query-pagination-numbers /-->
		<!-- wp:query-pagination-next /-->
	<!-- /wp:query-pagination -->

	<!-- wp:query-no-results -->
		<!-- wp:group {"className":"wg-query-empty","layout":{"type":"constrained"}} -->
		<div class="wp-block-group wg-query-empty">
			<!-- wp:heading {"level":2,"fontFamily":"headline"} -->
			<h2 class="wp-block-heading has-headline-font-family"><?php echo esc_html__( 'Nothing is connected here yet.', 'worldgraph-child' ); ?></h2>
			<!-- /wp:heading -->
			<!-- wp:paragraph -->
			<p><?php echo esc_html__( 'Try another search, or return later for new notes from the studio.', 'worldgraph-child' ); ?></p>
			<!-- /wp:paragraph -->
			<!-- wp:search {"label":"<?php echo esc_attr__( 'Search', 'worldgraph-child' ); ?>","showLabel":false,"placeholder":"<?php echo esc_attr__( 'Search studio notes', 'worldgraph-child' ); ?>","buttonText":"<?php echo esc_attr__( 'Search', 'worldgraph-child' ); ?>","buttonUseIcon":true} /-->
		</div>
		<!-- /wp:group -->
	<!-- /wp:query-no-results -->
</div>
<!-- /wp:query -->
