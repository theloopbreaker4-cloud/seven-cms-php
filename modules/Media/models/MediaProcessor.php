<?php
/** SevenCMS — github.com/theloopbreaker4-cloud/seven-cms-php */

defined('_SEVEN') or die('No direct script access allowed');

/**
 * MediaProcessor — generates resized/WebP variants of an uploaded image.
 *
 * Uses the GD extension (ships with PHP). Skips silently if GD is missing
 * or the image format is not supported.
 *
 * Variants produced:
 *   - thumb (320 wide)
 *   - medium (768 wide)
 *   - large (1600 wide)  — only if original is wider
 *   - webp (full-size WebP) — for jpeg/png inputs
 */
class MediaProcessor
{
    public const SIZES = [
        'thumb'  => 320,
        'medium' => 768,
        'large'  => 1600,
    ];

    /**
     * @param string $absFile Absolute path to the original image.
     * @param string $relPath Public-relative path of the original (e.g. /uploads/2026/04/abc.jpg).
     * @param string $mime    Mime type of the original.
     * @return array<string,string> Map of variant label → public-relative path.
     */
    public static function generate(string $absFile, string $relPath, string $mime): array
    {
        if (!extension_loaded('gd'))                                 return [];
        if (!in_array($mime, ['image/jpeg', 'image/png'], true))     return [];

        $src = self::loadImage($absFile, $mime);
        if (!$src) return [];

        $srcW = imagesx($src);
        $srcH = imagesy($src);
        $base = pathinfo($absFile, PATHINFO_FILENAME);
        $dir  = dirname($absFile) . '/variants';
        if (!is_dir($dir)) mkdir($dir, 0755, true);

        $relDir   = dirname($relPath) . '/variants';
        $variants = [];

        foreach (self::SIZES as $label => $maxW) {
            if ($srcW <= $maxW && $label !== 'thumb') continue;
            $resized = self::resizeToWidth($src, $srcW, $srcH, min($maxW, $srcW));
            if (!$resized) continue;

            $outAbs = "$dir/{$base}-{$label}.webp";
            $outRel = "{$relDir}/{$base}-{$label}.webp";

            if (function_exists('imagewebp') && @imagewebp($resized, $outAbs, 82)) {
                $variants[$label] = $outRel;
            } else {
                // Fallback: same format as source
                $jpgAbs = "$dir/{$base}-{$label}.jpg";
                $jpgRel = "{$relDir}/{$base}-{$label}.jpg";
                if (imagejpeg($resized, $jpgAbs, 85)) $variants[$label] = $jpgRel;
            }
            imagedestroy($resized);
        }

        // Full-size WebP for jpeg/png originals.
        if (function_exists('imagewebp')) {
            $webpAbs = "$dir/{$base}-full.webp";
            $webpRel = "{$relDir}/{$base}-full.webp";
            if (@imagewebp($src, $webpAbs, 85)) $variants['webp'] = $webpRel;
        }

        imagedestroy($src);
        return $variants;
    }

    /** @return \GdImage|null */
    private static function loadImage(string $path, string $mime)
    {
        return match ($mime) {
            'image/jpeg' => @imagecreatefromjpeg($path) ?: null,
            'image/png'  => @imagecreatefrompng($path)  ?: null,
            'image/webp' => function_exists('imagecreatefromwebp') ? (@imagecreatefromwebp($path) ?: null) : null,
            'image/gif'  => @imagecreatefromgif($path)  ?: null,
            default      => null,
        };
    }

    /** @return \GdImage|null */
    private static function resizeToWidth($src, int $srcW, int $srcH, int $newW)
    {
        $newH = (int)round($srcH * ($newW / $srcW));
        $dst  = imagecreatetruecolor($newW, $newH);
        // Preserve transparency for PNG/WEBP.
        imagealphablending($dst, false);
        imagesavealpha($dst, true);
        $transparent = imagecolorallocatealpha($dst, 0, 0, 0, 127);
        imagefilledrectangle($dst, 0, 0, $newW, $newH, $transparent);
        if (!imagecopyresampled($dst, $src, 0, 0, 0, 0, $newW, $newH, $srcW, $srcH)) {
            imagedestroy($dst);
            return null;
        }
        return $dst;
    }
}
