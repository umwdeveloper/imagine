<?php

// Define directory path containing images
$directory = 'images/';

// Function to resize image
function resizeImage($imagePath, $destPath, $maxWidth, $maxHeight, $crop = true) {
    list($width, $height, $type) = getimagesize($imagePath);

    // $newWidth = $maxWidth;
    // $newHeight = $maxHeight;

    $r = $width / $height;
    if ($crop) {
        if ($width > $height) {
            $width = ceil($width - ($width * abs($r - $maxWidth / $maxHeight)));
        } else {
            $height = ceil($height - ($height * abs($r - $maxWidth / $maxHeight)));
        }
        $newWidth = $maxWidth;
        $newHeight = $maxHeight;
    } else {
        if ($maxWidth / $maxHeight > $r) {
            $newWidth = $maxHeight * $r;
            $newHeight = $maxHeight;
        } else {
            $newHeight = $maxWidth / $r;
            $newWidth = $maxWidth;
        }
    }

    $newImage = imagecreatetruecolor($newWidth, $newHeight);

    switch ($type) {
        case IMAGETYPE_JPEG:
            $sourceImage = imagecreatefromjpeg($imagePath);
            imagecopyresampled($newImage, $sourceImage, 0, 0, 0, 0, $newWidth, $newHeight, $width, $height);
            // imagecopyresized($newImage, $sourceImage, 0, 0, 0, 0, $newWidth, $newHeight, $width, $height);
            imagejpeg($newImage, $destPath, 80);
            break;
        case IMAGETYPE_PNG:
            $sourceImage = imagecreatefrompng($imagePath);
            imagealphablending($newImage, false);
            imagesavealpha($newImage, true);
            imagecopyresampled($newImage, $sourceImage, 0, 0, 0, 0, $newWidth, $newHeight, $width, $height);
            // imagecopyresized($newImage, $sourceImage, 0, 0, 0, 0, $newWidth, $newHeight, $width, $height);
            imagepng($newImage, $destPath);
            break;
        case IMAGETYPE_GIF:
            $sourceImage = imagecreatefromgif($imagePath);
            $transparentIndex = imagecolortransparent($sourceImage);
            if ($transparentIndex !== false) {
                $transparentColor = imagecolorsforindex($sourceImage, $transparentIndex);
                $transparentColor = imagecolorallocate($newImage, $transparentColor['red'], $transparentColor['green'], $transparentColor['blue']);
                imagefill($newImage, 0, 0, $transparentColor);
                imagecolortransparent($newImage, $transparentColor);
            }
            imagecopyresampled($newImage, $sourceImage, 0, 0, 0, 0, $newWidth, $newHeight, $width, $height);
            // imagecopyresized($newImage, $sourceImage, 0, 0, 0, 0, $newWidth, $newHeight, $width, $height);
            imagegif($newImage, $destPath);
            break;
        default:
            return false;
    }

    imagedestroy($newImage);
    imagedestroy($sourceImage);
    return true;
}

// Open directory
if ($handle = opendir($directory)) {
    while (false !== ($file = readdir($handle))) {
        if ($file != "." && $file != "..") {
            $imagePath = $directory . '/' . $file;
            // $fileInfo = pathinfo($imagePath);
            if (!file_exists("images/resized")) {
                mkdir("images/resized");
            }
            $destPath = $directory . 'resized/resized_' . $file;

            if (resizeImage($imagePath, $destPath, 1280, 720)) {
                echo "Resized: " . $file . "\n";
            } else {
                echo "Skipped: " . $file . " (not landscape)\n";
            }
        }
    }
    closedir($handle);
} else {
    echo "Error: Could not open directory";
}
