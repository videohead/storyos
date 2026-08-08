<?php
// Test autoloader path generation
define('STORYOS_PLUGIN_DIR', '/app/wordpress/wp-content/plugins/storyos/');

function test_path(string $class): void {
    $prefix = 'StoryOS\\';
    $base_dir = STORYOS_PLUGIN_DIR . 'includes/';

    $len = strlen($prefix);
    if (strncmp($prefix, $class, $len) !== 0) {
        return;
    }

    $relative_class = substr($class, $len);
    
    $special_mappings = [
        'REST\\' => 'rest-api/',
    ];
    foreach ($special_mappings as $ns => $dir) {
        if (strpos($relative_class, $ns) === 0) {
            $relative_class = $dir . substr($relative_class, strlen($ns));
            break;
        }
    }
    
    $path_parts = explode('/', $relative_class);
    $filename = array_pop($path_parts);
    echo "Original: $filename\n";
    
    $filename = str_replace('_', '-', $filename);
    echo "After underscore replace: $filename\n";
    
    $filename = strtolower(preg_replace('/(?<!^)[A-Z]/', '-$0', $filename));
    echo "After camelCase: $filename\n";
    
    $filename = preg_replace('/-+/', '-', $filename);
    echo "After cleanup: $filename\n";
    
    $filename .= '.php';
    $path_parts[] = $filename;
    $kebab_class = implode('/', $path_parts);
    
    $lower_class = strtolower($relative_class) . '.php';
    
    $file = $base_dir . $relative_class . '.php';
    if (!file_exists($file)) {
        $file = $base_dir . $kebab_class;
    }
    if (!file_exists($file)) {
        $file = $base_dir . $lower_class;
    }
    
    echo "Final path: $file\n";
    echo "Exists: " . (file_exists($file) ? 'YES' : 'NO') . "\n\n";
}

echo "Testing Projects_Controller:\n";
test_path('StoryOS\REST\Projects_Controller');

echo "Testing StoryWorlds_Controller:\n";
test_path('StoryOS\REST\StoryWorlds_Controller');
