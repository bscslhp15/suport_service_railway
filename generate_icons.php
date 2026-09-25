<?php
/**
 * PWA Icon Generator
 * Resizes the main logo to required icon sizes for PWA
 * 
 * Usage: php generate_icons.php
 */

// Source logo file
$sourceImage = __DIR__ . '/IMG ASSETS/passlogo.png';
$outputDir = __DIR__ . '/IMG ASSETS/';

// Icon sizes to generate
$sizes = [
    192 => 'passlogo-192x192.png',
    512 => 'passlogo-512x512.png'
];

if (!file_exists($sourceImage)) {
    die("Error: Source image not found at $sourceImage\n");
}

if (!extension_loaded('gd')) {
    die("Error: GD library is not installed. Please install php-gd extension.\n");
}

// Load the source image
$source = imagecreatefrompng($sourceImage);
if (!$source) {
    die("Error: Could not load source image. Make sure it's a valid PNG file.\n");
}

$sourceWidth = imagesx($source);
$sourceHeight = imagesy($source);

echo "Generating PWA icons from: $sourceImage\n";
echo "Source image size: {$sourceWidth}x{$sourceHeight}\n\n";

$successCount = 0;
foreach ($sizes as $size => $filename) {
    // Create a new image with the target size
    $resized = imagecreatetruecolor($size, $size);
    
    // Enable transparency
    imagealphablending($resized, false);
    imagesavealpha($resized, true);
    
    // Create transparent background
    $transparent = imagecolorallocatealpha($resized, 0, 0, 0, 127);
    imagefill($resized, 0, 0, $transparent);
    
    // Calculate the dimensions to maintain aspect ratio
    $ratio = $sourceWidth / $sourceHeight;
    if ($ratio > 1) {
        // Image is wider than tall
        $newWidth = $size;
        $newHeight = (int)($size / $ratio);
    } else {
        // Image is taller than wide or square
        $newHeight = $size;
        $newWidth = (int)($size * $ratio);
    }
    
    // Calculate position to center the image
    $x = (int)(($size - $newWidth) / 2);
    $y = (int)(($size - $newHeight) / 2);
    
    // Resize and copy the source image
    imagecopyresampled(
        $resized,
        $source,
        $x, $y,        // Destination position
        0, 0,          // Source position
        $newWidth,     // Destination width
        $newHeight,    // Destination height
        $sourceWidth,  // Source width
        $sourceHeight  // Source height
    );
    
    // Save the resized image
    $outputPath = $outputDir . $filename;
    if (imagepng($resized, $outputPath)) {
        echo "✓ Created: $filename ({$size}x{$size})\n";
        $successCount++;
    } else {
        echo "✗ Failed to create: $filename\n";
    }
    
    imagedestroy($resized);
}

imagedestroy($source);

echo "\n" . str_repeat('=', 50) . "\n";
echo "Generated $successCount/" . count($sizes) . " icon files successfully!\n";
echo "Icons are ready for PWA use.\n";
?>
