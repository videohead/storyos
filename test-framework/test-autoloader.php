<?php
// Test autoloader for REST controllers
require_once '/app/wordpress/wp-load.php';

echo "Testing autoloader...\n";

// Check if Base_Controller exists
if (class_exists('StoryOS\REST\Base_Controller')) {
    echo "✓ Base_Controller loaded\n";
} else {
    echo "✗ Base_Controller NOT loaded\n";
}

// Check if Projects_Controller exists
if (class_exists('StoryOS\REST\Projects_Controller')) {
    echo "✓ Projects_Controller loaded\n";
} else {
    echo "✗ Projects_Controller NOT loaded\n";
}

echo "Done.\n";
