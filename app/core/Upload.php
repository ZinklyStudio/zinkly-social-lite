<?php
/**
 * Zinkly Social Lite - image uploads. Only real images are accepted (checked by content, not by name). Every image is
 * re-drawn with GD, which removes hidden data (EXIF location, scripts in comments), is resized to a sensible size
 * and saved as JPEG / PNG / WebP under uploads/<folder>/<yyyy>/<mm>/<random>.<ext>.
 */
final class Upload
{
    public const TYPES = [IMAGETYPE_JPEG => 'jpg', IMAGETYPE_PNG => 'png', IMAGETYPE_GIF => 'gif', IMAGETYPE_WEBP => 'webp'];

    /** Normalise $_FILES[name] (single or multiple) into a list. */
    public static function files(string $field): array
    {
        $f = $_FILES[$field] ?? null;
        if (!$f) {
            return [];
        }
        if (!is_array($f['name'])) {
            return $f['error'] === UPLOAD_ERR_NO_FILE ? [] : [$f];
        }
        $out = [];
        foreach ($f['name'] as $i => $n) {
            if ($f['error'][$i] === UPLOAD_ERR_NO_FILE) {
                continue;
            }
            $out[] = ['name' => $n, 'type' => $f['type'][$i], 'tmp_name' => $f['tmp_name'][$i], 'error' => $f['error'][$i], 'size' => $f['size'][$i]];
        }
        return $out;
    }

    public static function maxBytes(): int
    {
        return self::maxMb() * 1024 * 1024;
    }

    /** The upload limit in MB: the site limit, or the Pro limit for Pro members when it is higher. */
    public static function maxMb(): int
    {
        $mb = max(1, (int) setting('max_upload_mb', '8'));
        $pro = (int) setting('pro_upload_mb', '0');
        return $pro > $mb && class_exists('Pro') && Pro::enabled() && Pro::isPro(Auth::user()) ? $pro : $mb;
    }

    /**
     * Save one image. Returns ['path' => ..., 'thumb' => ..., 'width' => ..., 'height' => ...] or throws RuntimeException
     * with a message for the member.
     */
    public static function image(array $file, string $folder, int $maxSide = 1600, ?int $thumbSide = 480, bool $square = false): array
    {
        if (!function_exists('imagecreatetruecolor')) {
            throw new RuntimeException(t('upload.no_gd'));
        }
        if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK || !is_uploaded_file($file['tmp_name'])) {
            throw new RuntimeException(t('upload.failed'));
        }
        if ($file['size'] > self::maxBytes()) {
            throw new RuntimeException(t('upload.too_big', ['mb' => self::maxMb()]));
        }
        $info = @getimagesize($file['tmp_name']);
        if (!$info || !isset(self::TYPES[$info[2]])) {
            throw new RuntimeException(t('upload.not_image'));
        }
        [$w, $h, $type] = $info;
        if ($w < 1 || $h < 1 || $w * $h > 40000000) {
            throw new RuntimeException(t('upload.not_image'));
        }
        $src = match ($type) {
            IMAGETYPE_JPEG => @imagecreatefromjpeg($file['tmp_name']),
            IMAGETYPE_PNG => @imagecreatefrompng($file['tmp_name']),
            IMAGETYPE_GIF => @imagecreatefromgif($file['tmp_name']),
            IMAGETYPE_WEBP => function_exists('imagecreatefromwebp') ? @imagecreatefromwebp($file['tmp_name']) : false,
        };
        if (!$src) {
            throw new RuntimeException(t('upload.not_image'));
        }
        if ($type === IMAGETYPE_JPEG && function_exists('exif_read_data')) {
            $src = self::orient($src, $file['tmp_name']);
            $w = imagesx($src);
            $h = imagesy($src);
        }
        $keepAlpha = in_array($type, [IMAGETYPE_PNG, IMAGETYPE_GIF, IMAGETYPE_WEBP], true);
        $ext = $keepAlpha ? (function_exists('imagewebp') ? 'webp' : 'png') : 'jpg';

        $dir = trim($folder, '/') . '/' . date('Y/m');
        $abs = ZL_ROOT . '/uploads/' . $dir;
        if (!is_dir($abs) && !mkdir($abs, 0755, true) && !is_dir($abs)) {
            throw new RuntimeException(t('upload.failed'));
        }
        $name = bin2hex(random_bytes(12));
        $main = $dir . '/' . $name . '.' . $ext;
        [$mw, $mh] = self::save($src, $w, $h, $maxSide, ZL_ROOT . '/uploads/' . $main, $ext, $square);
        $thumb = $main;
        if ($thumbSide) {
            $thumb = $dir . '/' . $name . '_t.' . $ext;
            self::save($src, $w, $h, $thumbSide, ZL_ROOT . '/uploads/' . $thumb, $ext, $square);
        }
        imagedestroy($src);
        return ['path' => $main, 'thumb' => $thumb, 'width' => $mw, 'height' => $mh];
    }

    private static function save($src, int $w, int $h, int $max, string $dest, string $ext, bool $square): array
    {
        if ($square) {
            $side = min($w, $h);
            $sx = intdiv($w - $side, 2);
            $sy = intdiv($h - $side, 2);
            $tw = $th = min($side, $max);
            $sw = $sh = $side;
        } else {
            $scale = min(1, $max / max($w, $h));
            $tw = max(1, (int) round($w * $scale));
            $th = max(1, (int) round($h * $scale));
            $sx = $sy = 0;
            $sw = $w;
            $sh = $h;
        }
        $dst = imagecreatetruecolor($tw, $th);
        if ($ext !== 'jpg') {
            imagealphablending($dst, false);
            imagesavealpha($dst, true);
            imagefill($dst, 0, 0, imagecolorallocatealpha($dst, 0, 0, 0, 127));
        } else {
            imagefill($dst, 0, 0, imagecolorallocate($dst, 255, 255, 255));
        }
        imagecopyresampled($dst, $src, 0, 0, $sx, $sy, $tw, $th, $sw, $sh);
        $ok = match ($ext) {
            'webp' => imagewebp($dst, $dest, 82),
            'png' => imagepng($dst, $dest, 7),
            default => imagejpeg($dst, $dest, 84),
        };
        imagedestroy($dst);
        if (!$ok) {
            throw new RuntimeException(t('upload.failed'));
        }
        @chmod($dest, 0644);
        return [$tw, $th];
    }

    private static function orient($img, string $path)
    {
        $exif = @exif_read_data($path);
        $o = (int) ($exif['Orientation'] ?? 1);
        $angle = [3 => 180, 6 => -90, 8 => 90][$o] ?? 0;
        if ($angle) {
            $r = imagerotate($img, $angle, 0);
            if ($r) {
                imagedestroy($img);
                return $r;
            }
        }
        return $img;
    }

    /** Delete an uploaded file (only inside uploads/). */
    public static function remove(?string $path): void
    {
        if (!$path || str_contains($path, '..')) {
            return;
        }
        $f = ZL_ROOT . '/uploads/' . ltrim($path, '/');
        if (is_file($f)) {
            @unlink($f);
        }
    }
}
