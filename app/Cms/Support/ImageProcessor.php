<?php

namespace App\Cms\Support;

use Illuminate\Support\Facades\Storage;
use Throwable;

/**
 * Generates intermediate image sizes with GD (thumbnail, medium, large and
 * any size added with add_image_size()).
 */
class ImageProcessor
{
    protected array $extraSizes = [];

    public function addSize(string $name, int $width, int $height, bool $crop = false): void
    {
        $this->extraSizes[$name] = compact('width', 'height', 'crop');
    }

    public function sizes(): array
    {
        $sizes = [
            'thumbnail' => [
                'width' => (int) get_option('thumbnail_size_w', 150),
                'height' => (int) get_option('thumbnail_size_h', 150),
                'crop' => (bool) get_option('thumbnail_crop', true),
            ],
            'medium' => ['width' => (int) get_option('medium_size_w', 300), 'height' => (int) get_option('medium_size_h', 300), 'crop' => false],
            'large' => ['width' => (int) get_option('large_size_w', 1024), 'height' => (int) get_option('large_size_h', 1024), 'crop' => false],
        ];

        return apply_filters('intermediate_image_sizes', array_merge($sizes, $this->extraSizes));
    }

    /**
     * @return array{width: int|null, height: int|null, sizes: array}
     */
    public function process(string $disk, string $path, string $mime): array
    {
        $result = ['width' => null, 'height' => null, 'sizes' => []];

        if (! in_array($mime, ['image/jpeg', 'image/png', 'image/gif', 'image/webp'], true) || ! function_exists('imagecreatefromstring')) {
            return $result;
        }

        try {
            $contents = Storage::disk($disk)->get($path);
            $src = @imagecreatefromstring($contents);
            if (! $src) {
                return $result;
            }

            $w = imagesx($src);
            $h = imagesy($src);
            $result['width'] = $w;
            $result['height'] = $h;

            $info = pathinfo($path);
            foreach ($this->sizes() as $name => $size) {
                $tw = max(0, (int) $size['width']);
                $th = max(0, (int) $size['height']);
                if (($tw === 0 && $th === 0) || ($w <= $tw && $h <= $th)) {
                    continue;
                }

                [$dst, $dw, $dh] = $size['crop'] && $tw && $th
                    ? $this->crop($src, $w, $h, $tw, $th)
                    : $this->fit($src, $w, $h, $tw ?: PHP_INT_MAX, $th ?: PHP_INT_MAX);

                $file = $info['dirname'].'/'.$info['filename'].'-'.$dw.'x'.$dh.'.'.$info['extension'];

                ob_start();
                match ($mime) {
                    'image/png' => imagepng($dst, null, 8),
                    'image/gif' => imagegif($dst),
                    'image/webp' => imagewebp($dst, null, 82),
                    default => imagejpeg($dst, null, 82),
                };
                Storage::disk($disk)->put($file, (string) ob_get_clean());
                imagedestroy($dst);

                $result['sizes'][$name] = ['path' => $file, 'width' => $dw, 'height' => $dh];
            }

            imagedestroy($src);
        } catch (Throwable $e) {
            report($e);
        }

        return $result;
    }

    protected function fit($src, int $w, int $h, int $maxW, int $maxH): array
    {
        $ratio = min($maxW / $w, $maxH / $h, 1);
        $dw = max(1, (int) round($w * $ratio));
        $dh = max(1, (int) round($h * $ratio));
        $dst = $this->canvas($dw, $dh);
        imagecopyresampled($dst, $src, 0, 0, 0, 0, $dw, $dh, $w, $h);

        return [$dst, $dw, $dh];
    }

    protected function crop($src, int $w, int $h, int $tw, int $th): array
    {
        $scale = max($tw / $w, $th / $h);
        $cropW = (int) round($tw / $scale);
        $cropH = (int) round($th / $scale);
        $x = (int) round(($w - $cropW) / 2);
        $y = (int) round(($h - $cropH) / 2);
        $dst = $this->canvas($tw, $th);
        imagecopyresampled($dst, $src, 0, 0, $x, $y, $tw, $th, $cropW, $cropH);

        return [$dst, $tw, $th];
    }

    protected function canvas(int $w, int $h)
    {
        $img = imagecreatetruecolor($w, $h);
        imagealphablending($img, false);
        imagesavealpha($img, true);
        imagefill($img, 0, 0, imagecolorallocatealpha($img, 0, 0, 0, 127));

        return $img;
    }
}
