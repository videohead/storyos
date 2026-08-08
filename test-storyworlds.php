<?php
// Test StoryWorlds_Controller autoloader path
define('STORYOS_PLUGIN_DIR', '/app/wordpress/wp-content/plugins/storyos/');

$class = 'StoryOS\REST\StoryWorlds_Controller';
$prefix = 'StoryOS\\';
$base_dir = STORYOS_PLUGIN_DIR . 'includes/';

$len = strlen($prefix);
$relative_class = substr($class, $len);

// Handle special namespace mappings
$special_mappings = [
    'REST\\' => 'rest-api/',
];
foreach ($special_mappings as $ns => $dir) {
    if (strpos($relative_class, $ns) === 0) {
        $relative_class = $dir . substr($relative_class, strlen($ns));
        break;
    }
}

// Convert camelCase and underscores to kebab-case
$path_parts = explode('/', $relative_class);
$filename = array_pop($path_parts);
echo "Original filename: $filename\n";

$filename = str_replace('_', '-', $filename);
echo "After underscore replace: $filename\n";

$filename = strtolower(preg_replace('/(?<!^)[A-Z]/', '-$0', $filename));
echo "After camelCase convert: $filename\n";

$filename = preg_replace('/-+/', '-', $filename);
echo "After multiple hyphens cleanup: $filename\n";

$filename .= '.php';
echo "Final: $filename\n";

$path_parts[] = $filename;
$kebab_class = implode('/', $path_parts);
echo "Full path: $kebab_class\n";

$file = $base_dir . $kebab_class;
echo "Full file: $file\n";
echo "Exists: " . (file_exists($file) ? 'YES' : 'NO') . "\n";

// Check what files actually exist
echo "\nActual files in rest-api/:\n";
$files = scandir($base_dir . 'rest-api/');
foreach ($files as $f) {
    if (strpos($f, 'storyworld') !== false || strpos($f, 'story-world') !== false) {
        echo "  - $f\n";
    }
}
