<?php
/**
 * Title: World Graph Studio home page
 * Slug: worldgraph-child/page-home
 * Categories: featured, pages
 * Keywords: home, landing, studio, story graph
 * Block Types: core/post-content
 * Post Types: page
 * Viewport Width: 1440
 * Description: A complete World Graph Studio landing page with product positioning, delivered capabilities, workflow, creator-control principles, audiences, and calls to action.
 *
 * @package WorldGraphChild
 */
?>

<!-- wp:group {"align":"full","className":"wg-home","layout":{"type":"default"}} -->
<div class="wp-block-group alignfull wg-home">
	<!-- wp:group {"tagName":"section","align":"full","anchor":"top","backgroundColor":"warm-ivory","textColor":"dark-espresso","className":"wg-section wg-hero","layout":{"type":"constrained"}} -->
	<section class="wp-block-group alignfull wg-section wg-hero has-dark-espresso-color has-warm-ivory-background-color has-text-color has-background" id="top">
		<!-- wp:group {"align":"wide","className":"wg-hero__inner","layout":{"type":"constrained"}} -->
		<div class="wp-block-group alignwide wg-hero__inner">
			<!-- wp:paragraph {"align":"center","className":"wg-eyebrow"} -->
			<p class="has-text-align-center wg-eyebrow"><?php echo esc_html__( 'World Graph Studio', 'worldgraph-child' ); ?></p>
			<!-- /wp:paragraph -->

			<!-- wp:heading {"textAlign":"center","level":1,"className":"wg-hero__title","fontFamily":"headline"} -->
			<h1 class="wp-block-heading has-text-align-center wg-hero__title has-headline-font-family"><?php echo esc_html__( 'Your ideas. Your assets. No credits needed.', 'worldgraph-child' ); ?></h1>
			<!-- /wp:heading -->

			<!-- wp:paragraph {"align":"center","className":"wg-hero__summary"} -->
			<p class="has-text-align-center wg-hero__summary"><?php echo esc_html__( 'The open-source studio for worldbuilding, storytelling, and AI-powered creative production. Build connected worlds without a World Graph Studio credit meter or mandatory model provider.', 'worldgraph-child' ); ?></p>
			<!-- /wp:paragraph -->

			<!-- wp:buttons {"className":"wg-hero__actions","layout":{"type":"flex","justifyContent":"center"}} -->
			<div class="wp-block-buttons wg-hero__actions">
				<!-- wp:button {"className":"wg-button-primary"} -->
				<div class="wp-block-button wg-button-primary"><a class="wp-block-button__link wp-element-button" href="#story-graph"><?php echo esc_html__( 'Explore the Story Graph', 'worldgraph-child' ); ?></a></div>
				<!-- /wp:button -->

				<!-- wp:button {"className":"is-style-outline wg-button-secondary"} -->
				<div class="wp-block-button is-style-outline wg-button-secondary"><a class="wp-block-button__link wp-element-button" href="#capabilities"><?php echo esc_html__( 'See what ships', 'worldgraph-child' ); ?></a></div>
				<!-- /wp:button -->
			</div>
			<!-- /wp:buttons -->

			<!-- wp:group {"align":"wide","className":"wg-grid wg-proof-grid","layout":{"type":"grid","minimumColumnWidth":"12rem"}} -->
			<div class="wp-block-group alignwide wg-grid wg-proof-grid">
				<!-- wp:group {"className":"wg-stat","layout":{"type":"constrained"}} -->
				<div class="wp-block-group wg-stat">
					<!-- wp:paragraph {"align":"center","className":"wg-stat__value"} -->
					<p class="has-text-align-center wg-stat__value"><strong><?php echo esc_html__( 'Free', 'worldgraph-child' ); ?></strong></p>
					<!-- /wp:paragraph -->
					<!-- wp:paragraph {"align":"center","className":"wg-stat__label"} -->
					<p class="has-text-align-center wg-stat__label"><?php echo esc_html__( 'Open-source software', 'worldgraph-child' ); ?></p>
					<!-- /wp:paragraph -->
				</div>
				<!-- /wp:group -->

				<!-- wp:group {"className":"wg-stat","layout":{"type":"constrained"}} -->
				<div class="wp-block-group wg-stat">
					<!-- wp:paragraph {"align":"center","className":"wg-stat__value"} -->
					<p class="has-text-align-center wg-stat__value"><strong><?php echo esc_html__( 'WordPress', 'worldgraph-child' ); ?></strong></p>
					<!-- /wp:paragraph -->
					<!-- wp:paragraph {"align":"center","className":"wg-stat__label"} -->
					<p class="has-text-align-center wg-stat__label"><?php echo esc_html__( 'Self-hosted foundation', 'worldgraph-child' ); ?></p>
					<!-- /wp:paragraph -->
				</div>
				<!-- /wp:group -->

				<!-- wp:group {"className":"wg-stat","layout":{"type":"constrained"}} -->
				<div class="wp-block-group wg-stat">
					<!-- wp:paragraph {"align":"center","className":"wg-stat__value"} -->
					<p class="has-text-align-center wg-stat__value"><strong><?php echo esc_html__( '15 + 9', 'worldgraph-child' ); ?></strong></p>
					<!-- /wp:paragraph -->
					<!-- wp:paragraph {"align":"center","className":"wg-stat__label"} -->
					<p class="has-text-align-center wg-stat__label"><?php echo esc_html__( 'Content types and taxonomies', 'worldgraph-child' ); ?></p>
					<!-- /wp:paragraph -->
				</div>
				<!-- /wp:group -->

				<!-- wp:group {"className":"wg-stat","layout":{"type":"constrained"}} -->
				<div class="wp-block-group wg-stat">
					<!-- wp:paragraph {"align":"center","className":"wg-stat__value"} -->
					<p class="has-text-align-center wg-stat__value"><strong><?php echo esc_html__( '50+', 'worldgraph-child' ); ?></strong></p>
					<!-- /wp:paragraph -->
					<!-- wp:paragraph {"align":"center","className":"wg-stat__label"} -->
					<p class="has-text-align-center wg-stat__label"><?php echo esc_html__( 'Specialist creative advisors', 'worldgraph-child' ); ?></p>
					<!-- /wp:paragraph -->
				</div>
				<!-- /wp:group -->
			</div>
			<!-- /wp:group -->
		</div>
		<!-- /wp:group -->
	</section>
	<!-- /wp:group -->

	<!-- wp:group {"tagName":"section","align":"full","backgroundColor":"charcoal","textColor":"warm-ivory","className":"wg-section wg-problem-solution","layout":{"type":"constrained"}} -->
	<section class="wp-block-group alignfull wg-section wg-problem-solution has-warm-ivory-color has-charcoal-background-color has-text-color has-background">
		<!-- wp:group {"align":"wide","className":"wg-section__header","layout":{"type":"constrained"}} -->
		<div class="wp-block-group alignwide wg-section__header">
			<!-- wp:paragraph {"align":"center","className":"wg-eyebrow"} -->
			<p class="has-text-align-center wg-eyebrow"><?php echo esc_html__( 'From fragments to context', 'worldgraph-child' ); ?></p>
			<!-- /wp:paragraph -->

			<!-- wp:heading {"textAlign":"center","className":"wg-section__title","fontFamily":"headline"} -->
			<h2 class="wp-block-heading has-text-align-center wg-section__title has-headline-font-family"><?php echo esc_html__( 'Keep the story connected from idea to edit.', 'worldgraph-child' ); ?></h2>
			<!-- /wp:heading -->
		</div>
		<!-- /wp:group -->

		<!-- wp:columns {"align":"wide","className":"wg-grid wg-problem-solution__grid"} -->
		<div class="wp-block-columns alignwide wg-grid wg-problem-solution__grid">
			<!-- wp:column -->
			<div class="wp-block-column">
				<!-- wp:group {"backgroundColor":"dark-espresso","textColor":"warm-ivory","className":"wg-card wg-card--problem","layout":{"type":"constrained"}} -->
				<div class="wp-block-group wg-card wg-card--problem has-warm-ivory-color has-dark-espresso-background-color has-text-color has-background">
					<!-- wp:paragraph {"className":"wg-card__label"} -->
					<p class="wg-card__label"><?php echo esc_html__( 'The problem', 'worldgraph-child' ); ?></p>
					<!-- /wp:paragraph -->

					<!-- wp:heading {"level":3,"className":"wg-card__title","fontFamily":"headline"} -->
					<h3 class="wp-block-heading wg-card__title has-headline-font-family"><?php echo esc_html__( 'Storytelling workflows are fragmented.', 'worldgraph-child' ); ?></h3>
					<!-- /wp:heading -->

					<!-- wp:paragraph -->
					<p><?php echo esc_html__( 'Notes, scripts, prompts, assets, and editorial decisions drift into separate tools. The context that gives each choice meaning gets lost between story development and production.', 'worldgraph-child' ); ?></p>
					<!-- /wp:paragraph -->
				</div>
				<!-- /wp:group -->
			</div>
			<!-- /wp:column -->

			<!-- wp:column -->
			<div class="wp-block-column">
				<!-- wp:group {"backgroundColor":"blueprint-blue","textColor":"warm-ivory","className":"wg-card wg-card--solution","layout":{"type":"constrained"}} -->
				<div class="wp-block-group wg-card wg-card--solution has-warm-ivory-color has-blueprint-blue-background-color has-text-color has-background">
					<!-- wp:paragraph {"className":"wg-card__label"} -->
					<p class="wg-card__label"><?php echo esc_html__( 'The solution', 'worldgraph-child' ); ?></p>
					<!-- /wp:paragraph -->

					<!-- wp:heading {"level":3,"className":"wg-card__title","fontFamily":"headline"} -->
					<h3 class="wp-block-heading wg-card__title has-headline-font-family"><?php echo esc_html__( 'One connected creative system.', 'worldgraph-child' ); ?></h3>
					<!-- /wp:heading -->

					<!-- wp:paragraph -->
					<p><?php echo esc_html__( 'World Graph Studio connects storytelling, generation, production, and editorial workflows through the Story Graph, so ideas and assets remain connected, portable, and under creator control.', 'worldgraph-child' ); ?></p>
					<!-- /wp:paragraph -->
				</div>
				<!-- /wp:group -->
			</div>
			<!-- /wp:column -->
		</div>
		<!-- /wp:columns -->
	</section>
	<!-- /wp:group -->

	<!-- wp:group {"tagName":"section","align":"full","anchor":"story-graph","backgroundColor":"warm-ivory","textColor":"dark-espresso","className":"wg-section wg-story-graph","layout":{"type":"constrained"}} -->
	<section class="wp-block-group alignfull wg-section wg-story-graph has-dark-espresso-color has-warm-ivory-background-color has-text-color has-background" id="story-graph">
		<!-- wp:group {"align":"wide","className":"wg-section__header","layout":{"type":"constrained"}} -->
		<div class="wp-block-group alignwide wg-section__header">
			<!-- wp:paragraph {"align":"center","className":"wg-eyebrow"} -->
			<p class="has-text-align-center wg-eyebrow"><?php echo esc_html__( 'Story first', 'worldgraph-child' ); ?></p>
			<!-- /wp:paragraph -->

			<!-- wp:heading {"textAlign":"center","className":"wg-section__title","fontFamily":"headline"} -->
			<h2 class="wp-block-heading has-text-align-center wg-section__title has-headline-font-family"><?php echo esc_html__( 'The Story Graph is the source of truth.', 'worldgraph-child' ); ?></h2>
			<!-- /wp:heading -->

			<!-- wp:paragraph {"align":"center","className":"wg-section__summary"} -->
			<p class="has-text-align-center wg-section__summary"><?php echo esc_html__( 'Instead of treating a story as a pile of documents, World Graph Studio represents narrative, production, asset, and editorial information as structured WordPress records connected by explicit relationships.', 'worldgraph-child' ); ?></p>
			<!-- /wp:paragraph -->
		</div>
		<!-- /wp:group -->

		<!-- wp:columns {"align":"wide","className":"wg-grid wg-story-graph__grid"} -->
		<div class="wp-block-columns alignwide wg-grid wg-story-graph__grid">
			<!-- wp:column -->
			<div class="wp-block-column">
				<!-- wp:group {"className":"wg-node","layout":{"type":"constrained"}} -->
				<div class="wp-block-group wg-node">
					<!-- wp:heading {"level":3,"className":"wg-node__title","fontFamily":"headline"} -->
					<h3 class="wp-block-heading wg-node__title has-headline-font-family"><?php echo esc_html__( 'World', 'worldgraph-child' ); ?></h3>
					<!-- /wp:heading -->
					<!-- wp:paragraph -->
					<p><?php echo esc_html__( 'Projects, story worlds, characters, locations, props, and organizations establish reusable context.', 'worldgraph-child' ); ?></p>
					<!-- /wp:paragraph -->
				</div>
				<!-- /wp:group -->
			</div>
			<!-- /wp:column -->

			<!-- wp:column -->
			<div class="wp-block-column">
				<!-- wp:group {"className":"wg-node","layout":{"type":"constrained"}} -->
				<div class="wp-block-group wg-node">
					<!-- wp:heading {"level":3,"className":"wg-node__title","fontFamily":"headline"} -->
					<h3 class="wp-block-heading wg-node__title has-headline-font-family"><?php echo esc_html__( 'Story', 'worldgraph-child' ); ?></h3>
					<!-- /wp:heading -->
					<!-- wp:paragraph -->
					<p><?php echo esc_html__( 'Episodes, scenes, shots, planned sounds, and storyboard frames carry the narrative into production planning.', 'worldgraph-child' ); ?></p>
					<!-- /wp:paragraph -->
				</div>
				<!-- /wp:group -->
			</div>
			<!-- /wp:column -->

			<!-- wp:column -->
			<div class="wp-block-column">
				<!-- wp:group {"className":"wg-node","layout":{"type":"constrained"}} -->
				<div class="wp-block-group wg-node">
					<!-- wp:heading {"level":3,"className":"wg-node__title","fontFamily":"headline"} -->
					<h3 class="wp-block-heading wg-node__title has-headline-font-family"><?php echo esc_html__( 'Production', 'worldgraph-child' ); ?></h3>
					<!-- /wp:heading -->
					<!-- wp:paragraph -->
					<p><?php echo esc_html__( 'Assets, editorial records, generation templates, and provider connections remain linked to the records that give them meaning.', 'worldgraph-child' ); ?></p>
					<!-- /wp:paragraph -->
				</div>
				<!-- /wp:group -->
			</div>
			<!-- /wp:column -->
		</div>
		<!-- /wp:columns -->

		<!-- wp:group {"align":"wide","backgroundColor":"blueprint-blue","textColor":"warm-ivory","className":"wg-note wg-story-graph__note","layout":{"type":"constrained"}} -->
		<div class="wp-block-group alignwide wg-note wg-story-graph__note has-warm-ivory-color has-blueprint-blue-background-color has-text-color has-background">
			<!-- wp:paragraph {"align":"center"} -->
			<p class="has-text-align-center"><strong><?php echo esc_html__( 'WordPress stores the canonical Story Graph.', 'worldgraph-child' ); ?></strong> <?php echo esc_html__( 'Project records, relationships, permissions, media, and APIs stay in the application you control. Optional services connect around that core; they do not replace it.', 'worldgraph-child' ); ?></p>
			<!-- /wp:paragraph -->
		</div>
		<!-- /wp:group -->
	</section>
	<!-- /wp:group -->

	<!-- wp:group {"tagName":"section","align":"full","anchor":"capabilities","backgroundColor":"charcoal","textColor":"warm-ivory","className":"wg-section wg-capabilities","layout":{"type":"constrained"}} -->
	<section class="wp-block-group alignfull wg-section wg-capabilities has-warm-ivory-color has-charcoal-background-color has-text-color has-background" id="capabilities">
		<!-- wp:group {"align":"wide","className":"wg-section__header","layout":{"type":"constrained"}} -->
		<div class="wp-block-group alignwide wg-section__header">
			<!-- wp:paragraph {"align":"center","className":"wg-eyebrow"} -->
			<p class="has-text-align-center wg-eyebrow"><?php echo esc_html__( 'Delivered today', 'worldgraph-child' ); ?></p>
			<!-- /wp:paragraph -->

			<!-- wp:heading {"textAlign":"center","className":"wg-section__title","fontFamily":"headline"} -->
			<h2 class="wp-block-heading has-text-align-center wg-section__title has-headline-font-family"><?php echo esc_html__( 'A connected creative workspace that ships now.', 'worldgraph-child' ); ?></h2>
			<!-- /wp:heading -->

			<!-- wp:paragraph {"align":"center","className":"wg-section__summary"} -->
			<p class="has-text-align-center wg-section__summary"><?php echo esc_html__( 'Core story and production planning work without an AI or generation connection. Add supported services when they help the project.', 'worldgraph-child' ); ?></p>
			<!-- /wp:paragraph -->
		</div>
		<!-- /wp:group -->

		<!-- wp:group {"align":"wide","className":"wg-grid wg-capability-grid","layout":{"type":"grid","minimumColumnWidth":"18rem"}} -->
		<div class="wp-block-group alignwide wg-grid wg-capability-grid">
			<!-- wp:group {"backgroundColor":"dark-espresso","textColor":"warm-ivory","className":"wg-card wg-capability","layout":{"type":"constrained"}} -->
			<div class="wp-block-group wg-card wg-capability has-warm-ivory-color has-dark-espresso-background-color has-text-color has-background">
				<!-- wp:heading {"level":3,"className":"wg-card__title","fontFamily":"headline"} -->
				<h3 class="wp-block-heading wg-card__title has-headline-font-family"><?php echo esc_html__( 'Worldbuilding and planning', 'worldgraph-child' ); ?></h3>
				<!-- /wp:heading -->
				<!-- wp:paragraph -->
				<p><?php echo esc_html__( 'Develop worlds, characters, locations, props, scenes, shots, sounds, storyboards, assets, and editorial records as connected content.', 'worldgraph-child' ); ?></p>
				<!-- /wp:paragraph -->
			</div>
			<!-- /wp:group -->

			<!-- wp:group {"backgroundColor":"dark-espresso","textColor":"warm-ivory","className":"wg-card wg-capability","layout":{"type":"constrained"}} -->
			<div class="wp-block-group wg-card wg-capability has-warm-ivory-color has-dark-espresso-background-color has-text-color has-background">
				<!-- wp:heading {"level":3,"className":"wg-card__title","fontFamily":"headline"} -->
				<h3 class="wp-block-heading wg-card__title has-headline-font-family"><?php echo esc_html__( 'Story intelligence', 'worldgraph-child' ); ?></h3>
				<!-- /wp:heading -->
				<!-- wp:paragraph -->
				<p><?php echo esc_html__( 'Search Story Graph records, inspect relationship analytics, and run local continuity checks. Configured AI can support broader contextual review.', 'worldgraph-child' ); ?></p>
				<!-- /wp:paragraph -->
			</div>
			<!-- /wp:group -->

			<!-- wp:group {"backgroundColor":"dark-espresso","textColor":"warm-ivory","className":"wg-card wg-capability","layout":{"type":"constrained"}} -->
			<div class="wp-block-group wg-card wg-capability has-warm-ivory-color has-dark-espresso-background-color has-text-color has-background">
				<!-- wp:heading {"level":3,"className":"wg-card__title","fontFamily":"headline"} -->
				<h3 class="wp-block-heading wg-card__title has-headline-font-family"><?php echo esc_html__( 'AI-assisted editing', 'worldgraph-child' ); ?></h3>
				<!-- /wp:heading -->
				<!-- wp:paragraph -->
				<p><?php echo esc_html__( 'Use Story Graph-aware chat, analysis, drafting, and more than 50 specialist creative advisor profiles inside WordPress. Suggestions remain human-directed.', 'worldgraph-child' ); ?></p>
				<!-- /wp:paragraph -->
			</div>
			<!-- /wp:group -->

			<!-- wp:group {"backgroundColor":"dark-espresso","textColor":"warm-ivory","className":"wg-card wg-capability","layout":{"type":"constrained"}} -->
			<div class="wp-block-group wg-card wg-capability has-warm-ivory-color has-dark-espresso-background-color has-text-color has-background">
				<!-- wp:heading {"level":3,"className":"wg-card__title","fontFamily":"headline"} -->
				<h3 class="wp-block-heading wg-card__title has-headline-font-family"><?php echo esc_html__( 'Generation and provenance', 'worldgraph-child' ); ?></h3>
				<!-- /wp:heading -->
				<!-- wp:paragraph -->
				<p><?php echo esc_html__( 'Queue template-backed work through supported connections, track job state, and retain returned media in WordPress with source links and provenance.', 'worldgraph-child' ); ?></p>
				<!-- /wp:paragraph -->
			</div>
			<!-- /wp:group -->

			<!-- wp:group {"backgroundColor":"dark-espresso","textColor":"warm-ivory","className":"wg-card wg-capability","layout":{"type":"constrained"}} -->
			<div class="wp-block-group wg-card wg-capability has-warm-ivory-color has-dark-espresso-background-color has-text-color has-background">
				<!-- wp:heading {"level":3,"className":"wg-card__title","fontFamily":"headline"} -->
				<h3 class="wp-block-heading wg-card__title has-headline-font-family"><?php echo esc_html__( 'Production and editorial', 'worldgraph-child' ); ?></h3>
				<!-- /wp:heading -->
				<!-- wp:paragraph -->
				<p><?php echo esc_html__( 'Create shot lists, storyboard sequences, production views, asset records, and editorial handoffs without separating them from story context.', 'worldgraph-child' ); ?></p>
				<!-- /wp:paragraph -->
			</div>
			<!-- /wp:group -->

			<!-- wp:group {"backgroundColor":"dark-espresso","textColor":"warm-ivory","className":"wg-card wg-capability","layout":{"type":"constrained"}} -->
			<div class="wp-block-group wg-card wg-capability has-warm-ivory-color has-dark-espresso-background-color has-text-color has-background">
				<!-- wp:heading {"level":3,"className":"wg-card__title","fontFamily":"headline"} -->
				<h3 class="wp-block-heading wg-card__title has-headline-font-family"><?php echo esc_html__( 'Practical interchange', 'worldgraph-child' ); ?></h3>
				<!-- /wp:heading -->
				<!-- wp:paragraph -->
				<p><?php echo esc_html__( 'Import World Graph Studio JSON, export Markdown screenplays and storyboards, optionally synchronize supported entities outbound to Celtx, or use EDL format tools.', 'worldgraph-child' ); ?></p>
				<!-- /wp:paragraph -->
			</div>
			<!-- /wp:group -->
		</div>
		<!-- /wp:group -->

		<!-- wp:group {"align":"wide","className":"wg-note wg-capabilities__boundary","layout":{"type":"constrained"}} -->
		<div class="wp-block-group alignwide wg-note wg-capabilities__boundary">
			<!-- wp:paragraph {"align":"center"} -->
			<p class="has-text-align-center"><strong><?php echo esc_html__( 'Optional by design.', 'worldgraph-child' ); ?></strong> <?php echo esc_html__( 'AI and generation features require a configured compatible service. Provider pricing, quotas, licenses, and availability still apply. AI responses are suggestions; you decide what is saved or published.', 'worldgraph-child' ); ?></p>
			<!-- /wp:paragraph -->

			<!-- wp:paragraph {"align":"center"} -->
			<p class="has-text-align-center"><?php echo esc_html__( 'Additional professional script formats are on hold. EDL tools cover parsing, preview, timecode, and format generation—not live timeline interchange.', 'worldgraph-child' ); ?></p>
			<!-- /wp:paragraph -->
		</div>
		<!-- /wp:group -->
	</section>
	<!-- /wp:group -->

	<!-- wp:group {"tagName":"section","align":"full","anchor":"workflow","backgroundColor":"warm-ivory","textColor":"dark-espresso","className":"wg-section wg-workflow","layout":{"type":"constrained"}} -->
	<section class="wp-block-group alignfull wg-section wg-workflow has-dark-espresso-color has-warm-ivory-background-color has-text-color has-background" id="workflow">
		<!-- wp:group {"align":"wide","className":"wg-section__header","layout":{"type":"constrained"}} -->
		<div class="wp-block-group alignwide wg-section__header">
			<!-- wp:paragraph {"align":"center","className":"wg-eyebrow"} -->
			<p class="has-text-align-center wg-eyebrow"><?php echo esc_html__( 'A durable workflow', 'worldgraph-child' ); ?></p>
			<!-- /wp:paragraph -->

			<!-- wp:heading {"textAlign":"center","className":"wg-section__title","fontFamily":"headline"} -->
			<h2 class="wp-block-heading has-text-align-center wg-section__title has-headline-font-family"><?php echo esc_html__( 'Move from an idea to connected production.', 'worldgraph-child' ); ?></h2>
			<!-- /wp:heading -->
		</div>
		<!-- /wp:group -->

		<!-- wp:group {"align":"wide","className":"wg-grid wg-workflow__steps","layout":{"type":"grid","minimumColumnWidth":"14rem"}} -->
		<div class="wp-block-group alignwide wg-grid wg-workflow__steps">
			<!-- wp:group {"className":"wg-step","layout":{"type":"constrained"}} -->
			<div class="wp-block-group wg-step">
				<!-- wp:paragraph {"className":"wg-step__number"} -->
				<p class="wg-step__number"><?php echo esc_html__( '01', 'worldgraph-child' ); ?></p>
				<!-- /wp:paragraph -->
				<!-- wp:heading {"level":3,"className":"wg-step__title","fontFamily":"headline"} -->
				<h3 class="wp-block-heading wg-step__title has-headline-font-family"><?php echo esc_html__( 'Build the world', 'worldgraph-child' ); ?></h3>
				<!-- /wp:heading -->
				<!-- wp:paragraph -->
				<p><?php echo esc_html__( 'Create the project, story world, people, places, scenes, shots, and relationships that define the work.', 'worldgraph-child' ); ?></p>
				<!-- /wp:paragraph -->
			</div>
			<!-- /wp:group -->

			<!-- wp:group {"className":"wg-step","layout":{"type":"constrained"}} -->
			<div class="wp-block-group wg-step">
				<!-- wp:paragraph {"className":"wg-step__number"} -->
				<p class="wg-step__number"><?php echo esc_html__( '02', 'worldgraph-child' ); ?></p>
				<!-- /wp:paragraph -->
				<!-- wp:heading {"level":3,"className":"wg-step__title","fontFamily":"headline"} -->
				<h3 class="wp-block-heading wg-step__title has-headline-font-family"><?php echo esc_html__( 'Review the context', 'worldgraph-child' ); ?></h3>
				<!-- /wp:heading -->
				<!-- wp:paragraph -->
				<p><?php echo esc_html__( 'Search the graph, inspect relationships, check continuity, and invite a configured specialist advisor to offer labeled suggestions.', 'worldgraph-child' ); ?></p>
				<!-- /wp:paragraph -->
			</div>
			<!-- /wp:group -->

			<!-- wp:group {"className":"wg-step","layout":{"type":"constrained"}} -->
			<div class="wp-block-group wg-step">
				<!-- wp:paragraph {"className":"wg-step__number"} -->
				<p class="wg-step__number"><?php echo esc_html__( '03', 'worldgraph-child' ); ?></p>
				<!-- /wp:paragraph -->
				<!-- wp:heading {"level":3,"className":"wg-step__title","fontFamily":"headline"} -->
				<h3 class="wp-block-heading wg-step__title has-headline-font-family"><?php echo esc_html__( 'Make and organize', 'worldgraph-child' ); ?></h3>
				<!-- /wp:heading -->
				<!-- wp:paragraph -->
				<p><?php echo esc_html__( 'Plan production, connect a supported generator when needed, and keep returned media beside its source and provenance.', 'worldgraph-child' ); ?></p>
				<!-- /wp:paragraph -->
			</div>
			<!-- /wp:group -->

			<!-- wp:group {"className":"wg-step","layout":{"type":"constrained"}} -->
			<div class="wp-block-group wg-step">
				<!-- wp:paragraph {"className":"wg-step__number"} -->
				<p class="wg-step__number"><?php echo esc_html__( '04', 'worldgraph-child' ); ?></p>
				<!-- /wp:paragraph -->
				<!-- wp:heading {"level":3,"className":"wg-step__title","fontFamily":"headline"} -->
				<h3 class="wp-block-heading wg-step__title has-headline-font-family"><?php echo esc_html__( 'Exchange the work', 'worldgraph-child' ); ?></h3>
				<!-- /wp:heading -->
				<!-- wp:paragraph -->
				<p><?php echo esc_html__( 'Use documented JSON, Markdown, outbound Celtx synchronization, or EDL format helpers while WordPress remains canonical.', 'worldgraph-child' ); ?></p>
				<!-- /wp:paragraph -->
			</div>
			<!-- /wp:group -->
		</div>
		<!-- /wp:group -->
	</section>
	<!-- /wp:group -->

	<!-- wp:group {"tagName":"section","align":"full","anchor":"creative-control","backgroundColor":"dark-espresso","textColor":"warm-ivory","className":"wg-section wg-creative-control","layout":{"type":"constrained"}} -->
	<section class="wp-block-group alignfull wg-section wg-creative-control has-warm-ivory-color has-dark-espresso-background-color has-text-color has-background" id="creative-control">
		<!-- wp:group {"align":"wide","className":"wg-section__header","layout":{"type":"constrained"}} -->
		<div class="wp-block-group alignwide wg-section__header">
			<!-- wp:paragraph {"align":"center","className":"wg-eyebrow"} -->
			<p class="has-text-align-center wg-eyebrow"><?php echo esc_html__( 'Creator owned', 'worldgraph-child' ); ?></p>
			<!-- /wp:paragraph -->

			<!-- wp:heading {"textAlign":"center","className":"wg-section__title","fontFamily":"headline"} -->
			<h2 class="wp-block-heading has-text-align-center wg-section__title has-headline-font-family"><?php echo esc_html__( 'Creative control without a platform meter.', 'worldgraph-child' ); ?></h2>
			<!-- /wp:heading -->

			<!-- wp:paragraph {"align":"center","className":"wg-section__summary"} -->
			<p class="has-text-align-center wg-section__summary"><?php echo esc_html__( 'World Graph Studio does not sell usage credits, require a World Graph Studio cloud, or make one model provider the owner of your project.', 'worldgraph-child' ); ?></p>
			<!-- /wp:paragraph -->
		</div>
		<!-- /wp:group -->

		<!-- wp:columns {"align":"wide","className":"wg-grid wg-control-grid"} -->
		<div class="wp-block-columns alignwide wg-grid wg-control-grid">
			<!-- wp:column -->
			<div class="wp-block-column">
				<!-- wp:group {"className":"wg-card wg-control-card","layout":{"type":"constrained"}} -->
				<div class="wp-block-group wg-card wg-control-card">
					<!-- wp:heading {"level":3,"className":"wg-card__title","fontFamily":"headline"} -->
					<h3 class="wp-block-heading wg-card__title has-headline-font-family"><?php echo esc_html__( 'Choose the home', 'worldgraph-child' ); ?></h3>
					<!-- /wp:heading -->
					<!-- wp:paragraph -->
					<p><?php echo esc_html__( 'Run WordPress in an environment you control, keep it private through your own configuration, or publish when you choose.', 'worldgraph-child' ); ?></p>
					<!-- /wp:paragraph -->
				</div>
				<!-- /wp:group -->
			</div>
			<!-- /wp:column -->

			<!-- wp:column -->
			<div class="wp-block-column">
				<!-- wp:group {"className":"wg-card wg-control-card","layout":{"type":"constrained"}} -->
				<div class="wp-block-group wg-card wg-control-card">
					<!-- wp:heading {"level":3,"className":"wg-card__title","fontFamily":"headline"} -->
					<h3 class="wp-block-heading wg-card__title has-headline-font-family"><?php echo esc_html__( 'Choose the connections', 'worldgraph-child' ); ?></h3>
					<!-- /wp:heading -->
					<!-- wp:paragraph -->
					<p><?php echo esc_html__( 'Use supported local or hosted services and change providers without rebuilding the Story Graph or moving canonical records out of WordPress.', 'worldgraph-child' ); ?></p>
					<!-- /wp:paragraph -->
				</div>
				<!-- /wp:group -->
			</div>
			<!-- /wp:column -->

			<!-- wp:column -->
			<div class="wp-block-column">
				<!-- wp:group {"className":"wg-card wg-control-card","layout":{"type":"constrained"}} -->
				<div class="wp-block-group wg-card wg-control-card">
					<!-- wp:heading {"level":3,"className":"wg-card__title","fontFamily":"headline"} -->
					<h3 class="wp-block-heading wg-card__title has-headline-font-family"><?php echo esc_html__( 'Choose what becomes canon', 'worldgraph-child' ); ?></h3>
					<!-- /wp:heading -->
					<!-- wp:paragraph -->
					<p><?php echo esc_html__( 'Advisors propose, analyze, and draft. Creators explicitly accept, revise, discard, save, generate, or publish.', 'worldgraph-child' ); ?></p>
					<!-- /wp:paragraph -->
				</div>
				<!-- /wp:group -->
			</div>
			<!-- /wp:column -->
		</div>
		<!-- /wp:columns -->

		<!-- wp:group {"align":"wide","backgroundColor":"sepia","textColor":"dark-espresso","className":"wg-note wg-provider-caveat","layout":{"type":"constrained"}} -->
		<div class="wp-block-group alignwide wg-note wg-provider-caveat has-dark-espresso-color has-sepia-background-color has-text-color has-background">
			<!-- wp:paragraph -->
			<p><strong><?php echo esc_html__( 'A clear boundary:', 'worldgraph-child' ); ?></strong> <?php echo esc_html__( 'optional hosted providers can apply their own prices, credits, quotas, licenses, moderation rules, availability, data practices, and terms. Self-hosting controls deployment and data location; site privacy still depends on WordPress and hosting configuration.', 'worldgraph-child' ); ?></p>
			<!-- /wp:paragraph -->
		</div>
		<!-- /wp:group -->
	</section>
	<!-- /wp:group -->

	<!-- wp:group {"tagName":"section","align":"full","anchor":"audiences","backgroundColor":"warm-ivory","textColor":"dark-espresso","className":"wg-section wg-audiences","layout":{"type":"constrained"}} -->
	<section class="wp-block-group alignfull wg-section wg-audiences has-dark-espresso-color has-warm-ivory-background-color has-text-color has-background" id="audiences">
		<!-- wp:group {"align":"wide","className":"wg-section__header","layout":{"type":"constrained"}} -->
		<div class="wp-block-group alignwide wg-section__header">
			<!-- wp:paragraph {"align":"center","className":"wg-eyebrow"} -->
			<p class="has-text-align-center wg-eyebrow"><?php echo esc_html__( 'One studio, many disciplines', 'worldgraph-child' ); ?></p>
			<!-- /wp:paragraph -->

			<!-- wp:heading {"textAlign":"center","className":"wg-section__title","fontFamily":"headline"} -->
			<h2 class="wp-block-heading has-text-align-center wg-section__title has-headline-font-family"><?php echo esc_html__( 'For people building connected stories.', 'worldgraph-child' ); ?></h2>
			<!-- /wp:heading -->
		</div>
		<!-- /wp:group -->

		<!-- wp:group {"align":"wide","className":"wg-grid wg-audience-grid","layout":{"type":"grid","minimumColumnWidth":"12rem"}} -->
		<div class="wp-block-group alignwide wg-grid wg-audience-grid">
			<!-- wp:group {"className":"wg-card wg-audience-card","layout":{"type":"constrained"}} -->
			<div class="wp-block-group wg-card wg-audience-card">
				<!-- wp:heading {"level":3,"className":"wg-card__title","fontFamily":"headline"} -->
				<h3 class="wp-block-heading wg-card__title has-headline-font-family"><?php echo esc_html__( 'Filmmakers', 'worldgraph-child' ); ?></h3>
				<!-- /wp:heading -->
				<!-- wp:paragraph -->
				<p><?php echo esc_html__( 'Develop scripts, coverage, storyboards, shots, assets, and editorial handoffs.', 'worldgraph-child' ); ?></p>
				<!-- /wp:paragraph -->
			</div>
			<!-- /wp:group -->

			<!-- wp:group {"className":"wg-card wg-audience-card","layout":{"type":"constrained"}} -->
			<div class="wp-block-group wg-card wg-audience-card">
				<!-- wp:heading {"level":3,"className":"wg-card__title","fontFamily":"headline"} -->
				<h3 class="wp-block-heading wg-card__title has-headline-font-family"><?php echo esc_html__( 'Game creators', 'worldgraph-child' ); ?></h3>
				<!-- /wp:heading -->
				<!-- wp:paragraph -->
				<p><?php echo esc_html__( 'Design worlds, characters, locations, props, and narrative relationships.', 'worldgraph-child' ); ?></p>
				<!-- /wp:paragraph -->
			</div>
			<!-- /wp:group -->

			<!-- wp:group {"className":"wg-card wg-audience-card","layout":{"type":"constrained"}} -->
			<div class="wp-block-group wg-card wg-audience-card">
				<!-- wp:heading {"level":3,"className":"wg-card__title","fontFamily":"headline"} -->
				<h3 class="wp-block-heading wg-card__title has-headline-font-family"><?php echo esc_html__( 'Scriptwriters', 'worldgraph-child' ); ?></h3>
				<!-- /wp:heading -->
				<!-- wp:paragraph -->
				<p><?php echo esc_html__( 'Keep structured story context close while writing, reviewing, and revising.', 'worldgraph-child' ); ?></p>
				<!-- /wp:paragraph -->
			</div>
			<!-- /wp:group -->

			<!-- wp:group {"className":"wg-card wg-audience-card","layout":{"type":"constrained"}} -->
			<div class="wp-block-group wg-card wg-audience-card">
				<!-- wp:heading {"level":3,"className":"wg-card__title","fontFamily":"headline"} -->
				<h3 class="wp-block-heading wg-card__title has-headline-font-family"><?php echo esc_html__( 'Video producers', 'worldgraph-child' ); ?></h3>
				<!-- /wp:heading -->
				<!-- wp:paragraph -->
				<p><?php echo esc_html__( 'Organize scenes, sequences, sounds, media, and production metadata.', 'worldgraph-child' ); ?></p>
				<!-- /wp:paragraph -->
			</div>
			<!-- /wp:group -->

			<!-- wp:group {"className":"wg-card wg-audience-card","layout":{"type":"constrained"}} -->
			<div class="wp-block-group wg-card wg-audience-card">
				<!-- wp:heading {"level":3,"className":"wg-card__title","fontFamily":"headline"} -->
				<h3 class="wp-block-heading wg-card__title has-headline-font-family"><?php echo esc_html__( 'Technical creators', 'worldgraph-child' ); ?></h3>
				<!-- /wp:heading -->
				<!-- wp:paragraph -->
				<p><?php echo esc_html__( 'Extend a self-hosted WordPress application through documented data and API surfaces.', 'worldgraph-child' ); ?></p>
				<!-- /wp:paragraph -->
			</div>
			<!-- /wp:group -->
		</div>
		<!-- /wp:group -->
	</section>
	<!-- /wp:group -->

	<!-- wp:group {"tagName":"section","align":"full","backgroundColor":"charcoal","textColor":"warm-ivory","className":"wg-section wg-cta","layout":{"type":"constrained"}} -->
	<section class="wp-block-group alignfull wg-section wg-cta has-warm-ivory-color has-charcoal-background-color has-text-color has-background">
		<!-- wp:group {"align":"wide","className":"wg-cta__inner","layout":{"type":"constrained"}} -->
		<div class="wp-block-group alignwide wg-cta__inner">
			<!-- wp:heading {"textAlign":"center","className":"wg-cta__title","fontFamily":"headline"} -->
			<h2 class="wp-block-heading has-text-align-center wg-cta__title has-headline-font-family"><?php echo esc_html__( 'Build worlds. Connect ideas. Generate anything. No credits needed.', 'worldgraph-child' ); ?></h2>
			<!-- /wp:heading -->

			<!-- wp:paragraph {"align":"center","className":"wg-cta__summary"} -->
			<p class="has-text-align-center wg-cta__summary"><?php echo esc_html__( 'Start with a Story Graph you control. Add optional AI and generation connections only when they serve the work.', 'worldgraph-child' ); ?></p>
			<!-- /wp:paragraph -->

			<!-- wp:buttons {"className":"wg-cta__actions","layout":{"type":"flex","justifyContent":"center"}} -->
			<div class="wp-block-buttons wg-cta__actions">
				<!-- wp:button {"className":"wg-button-primary"} -->
				<div class="wp-block-button wg-button-primary"><a class="wp-block-button__link wp-element-button" href="<?php echo esc_url( admin_url( 'admin.php?page=worldgraph' ) ); ?>"><?php echo esc_html__( 'Open Studio', 'worldgraph-child' ); ?></a></div>
				<!-- /wp:button -->

				<!-- wp:button {"className":"is-style-outline wg-button-secondary"} -->
				<div class="wp-block-button is-style-outline wg-button-secondary"><a class="wp-block-button__link wp-element-button" href="#top"><?php echo esc_html__( 'Back to top', 'worldgraph-child' ); ?></a></div>
				<!-- /wp:button -->
			</div>
			<!-- /wp:buttons -->
		</div>
		<!-- /wp:group -->
	</section>
	<!-- /wp:group -->
</div>
<!-- /wp:group -->
