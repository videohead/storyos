<?php
// Minimal autoloader test
define('STORYOS_PLUGIN_DIR', '/app/wordpress/wp-content/plugins/storyos/');

function test_autoloader(string $class): void {
    $prefix = 'StoryOS\\';
    $base_dir = STORYOS_PLUGIN_DIR . 'includes/';

    $len = strlen($prefix);
    if (strncmp($prefix, $class, $len) !== 0) {
        return;
    }

    $relative_class = substr($class, $len);
    
    // Handle special namespace mappings
    $special_mappings = [
        'CPT\\' => 'cpts/',
        'REST\\' => 'rest-api/',
        'Taxonomies\\' => 'taxonomies/',
        'Admin\\' => 'admin/',
        'Utils\\' => 'utils/',
    ];
    foreach ($special_mappings as $ns => $dir) {
        if (strpos($relative_class, $ns) === 0) {
            $relative_class = $dir . substr($relative_class, strlen($ns));
            break;
        }
    }
    
    // Convert camelCase to kebab-case
    $path_parts = explode('/', $relative_class);
    $filename = array_pop($path_parts);
    $filename = strtolower(preg_replace('/(?<!^)[A-Z]/', '-$0', $filename)) . '.php';
    $path_parts[] = $filename;
    $kebab_class = implode('/', $path_parts);
    
    $lower_class = strtolower($relative_class) . '.php';
    
    echo "Class: $class\n";
    echo "Relative: $relative_class\n";
    echo "Kebab: $kebab_class\n";
    echo "Lower: $lower_class\n";
    
    $file = $base_dir . $relative_class . '.php';
    if (!file_exists($file)) {
        $file = $base_dir . $kebab_class;
    }
    if (!file_exists($file)) {
        $file = $base_dir . $lower_class;
    }
    
    echo "File: $file\n";
    echo "Exists: " . (file_exists($file) ? 'YES' : 'NO') . "\n";
}

spl_autoload_register('test_autoloader');

echo "Testing Projects_Controller...\n";
class_exists('StoryOS\REST\Projects_Controller');
