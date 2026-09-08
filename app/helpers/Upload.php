<?php
// app/helpers/Upload.php
class Upload {
    public static function imageError(array $file): ?string {
        $error = (int)($file['error'] ?? UPLOAD_ERR_NO_FILE);
        if ($error === UPLOAD_ERR_NO_FILE) {
            return 'No image uploaded.';
        }
        if ($error !== UPLOAD_ERR_OK) {
            if ($error === UPLOAD_ERR_INI_SIZE || $error === UPLOAD_ERR_FORM_SIZE) {
                return 'Image file too large. Max 5MB allowed.';
            }
            if ($error === UPLOAD_ERR_PARTIAL) {
                return 'The image upload was incomplete. Please try again.';
            }

            return 'Image upload failed. Please try again.';
        }

        if ((int)($file['size'] ?? 0) > MAX_IMAGE_SIZE) {
            return 'Image file too large. Max 5MB allowed.';
        }

        $tmpName = (string)($file['tmp_name'] ?? '');
        if ($tmpName === '' || !is_file($tmpName)) {
            return 'The uploaded image could not be read.';
        }

        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mime  = finfo_file($finfo, $tmpName);
        finfo_close($finfo);

        if (!in_array($mime, ALLOWED_IMAGE_TYPES, true)) {
            return 'Only JPG, PNG, WebP, or GIF images are allowed.';
        }

        return null;
    }

    public static function image(array $file, string $dir = 'thumbnails') {
        if (self::imageError($file) !== null) return false;

        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mime  = finfo_file($finfo, $file['tmp_name']);
        finfo_close($finfo);

        $ext = 'jpg';
        if ($mime === 'image/png') {
            $ext = 'png';
        } elseif ($mime === 'image/webp') {
            $ext = 'webp';
        } elseif ($mime === 'image/gif') {
            $ext = 'gif';
        }
        $filename = uniqid('fn_', true) . '.' . $ext;
        $targetDir = UPLOAD_PATH . '/' . $dir;
        if (!is_dir($targetDir) && !mkdir($targetDir, 0777, true) && !is_dir($targetDir)) {
            return false;
        }
        $dest     = $targetDir . '/' . $filename;

        if (!move_uploaded_file($file['tmp_name'], $dest)) return false;

        // Optionally resize (requires GD)
        if (function_exists('imagecreatefromjpeg')) {
            self::resize($dest, $mime, 1200, 630);
        }
        return $filename;
    }

    public static function video(array $file, string $dir = 'shorts') {
        $error = (int)($file['error'] ?? UPLOAD_ERR_NO_FILE);
        if ($error !== UPLOAD_ERR_OK) {
            return false;
        }
        if ((int)($file['size'] ?? 0) > MAX_VIDEO_SIZE) {
            return false;
        }

        $tmpName = (string)($file['tmp_name'] ?? '');
        if ($tmpName === '' || !is_uploaded_file($tmpName)) {
            return false;
        }

        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mime  = (string)finfo_file($finfo, $tmpName);
        finfo_close($finfo);

        $map = [
            'video/mp4'        => 'mp4',
            'video/webm'       => 'webm',
            'video/ogg'        => 'ogv',
            'video/quicktime'  => 'mov',
            'video/x-m4v'      => 'm4v',
        ];
        if (!isset($map[$mime])) {
            return false;
        }

        $filename  = uniqid('fv_', true) . '.' . $map[$mime];
        $targetDir = UPLOAD_PATH . '/' . $dir;
        if (!is_dir($targetDir) && !mkdir($targetDir, 0755, true) && !is_dir($targetDir)) {
            return false;
        }

        return move_uploaded_file($tmpName, $targetDir . '/' . $filename) ? $filename : false;
    }

    private static function resize(string $path, string $mime, int $maxW, int $maxH): void {
        [$w, $h] = getimagesize($path);
        if ($w <= $maxW && $h <= $maxH) return;
        $ratio  = min($maxW / $w, $maxH / $h);
        $nW     = (int)($w * $ratio);
        $nH     = (int)($h * $ratio);
        $src = null;
        if ($mime === 'image/jpeg') {
            $src = imagecreatefromjpeg($path);
        } elseif ($mime === 'image/png') {
            $src = imagecreatefrompng($path);
        } elseif ($mime === 'image/webp') {
            $src = imagecreatefromwebp($path);
        }
        if (!$src) return;
        $dst = imagecreatetruecolor($nW, $nH);
        imagecopyresampled($dst, $src, 0, 0, 0, 0, $nW, $nH, $w, $h);
        if ($mime === 'image/jpeg') {
            imagejpeg($dst, $path, 85);
        } elseif ($mime === 'image/png') {
            imagepng($dst, $path, 8);
        } elseif ($mime === 'image/webp') {
            imagewebp($dst, $path, 85);
        }
        imagedestroy($src);
        imagedestroy($dst);
    }
}
