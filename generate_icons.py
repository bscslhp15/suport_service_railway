#!/usr/bin/env python3
"""
PWA Icon Generator
Resizes passlogo.png to required icon sizes (192x192 and 512x512)
"""

from PIL import Image
import os

# Paths
base_path = r"c:\xampp\htdocs\THESIS\SUPPORTSERVICESYSTEM"
source_image = os.path.join(base_path, "IMG ASSETS", "passlogo.png")
output_dir = os.path.join(base_path, "IMG ASSETS")

# Icon sizes
sizes = {
    192: "passlogo-192x192.png",
    512: "passlogo-512x512.png"
}

# Check if source exists
if not os.path.exists(source_image):
    print(f"Error: Source image not found at {source_image}")
    exit(1)

# Open source image
try:
    img = Image.open(source_image)
    print(f"Loaded source image: {img.filename}")
    print(f"Source size: {img.width}x{img.height}")
    print(f"Source mode: {img.mode}\n")
except Exception as e:
    print(f"Error: Could not open source image: {e}")
    exit(1)

# Convert to RGBA if needed (for transparency support)
if img.mode != 'RGBA':
    img = img.convert('RGBA')

success_count = 0

for size, filename in sizes.items():
    try:
        # Create a new image with transparent background
        icon = Image.new('RGBA', (size, size), (0, 0, 0, 0))
        
        # Calculate dimensions maintaining aspect ratio
        ratio = img.width / img.height
        if ratio > 1:
            # Image is wider
            new_width = size
            new_height = int(size / ratio)
        else:
            # Image is taller or square
            new_height = size
            new_width = int(size * ratio)
        
        # Resize the image
        resized = img.resize((new_width, new_height), Image.Resampling.LANCZOS)
        
        # Calculate position to center
        x = (size - new_width) // 2
        y = (size - new_height) // 2
        
        # Paste centered image
        icon.paste(resized, (x, y), resized)
        
        # Save
        output_path = os.path.join(output_dir, filename)
        icon.save(output_path, 'PNG')
        print(f"✓ Created: {filename} ({size}x{size})")
        success_count += 1
        
    except Exception as e:
        print(f"✗ Error creating {filename}: {e}")

print(f"\n{'='*50}")
print(f"Generated {success_count}/{len(sizes)} icon files successfully!")
print(f"Icons are ready for PWA use.")
