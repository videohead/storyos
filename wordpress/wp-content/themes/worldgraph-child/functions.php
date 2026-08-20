<?php
/**
 * Enqueue styles for the World Graph Studio child theme.
 *
 * Frost enqueues its stylesheet using the `frost` handle, so the child only
 * needs to enqueue its own stylesheet after that dependency.
 *
 * @package WorldGraphChild
 */

function worldgraph_child_enqueue_styles() {
    $theme = wp_get_theme();

    wp_enqueue_style(
        'worldgraph-child',
        get_stylesheet_uri(),
        array( 'frost' ),
        $theme->get( 'Version' )
    );
}
add_action( 'wp_enqueue_scripts', 'worldgraph_child_enqueue_styles' );