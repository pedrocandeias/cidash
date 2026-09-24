<?php

namespace App\Assets;

use App\Models\Asset;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

/**
 * Stores an uploaded file as an asset: kind from the extension, image size and a
 * small thumbnail for the gallery (when the server has GD).
 */
class AssetFiles
{
    public const IMAGES = ['jpg', 'jpeg', 'png', 'gif', 'webp', 'heic', 'tif', 'tiff'];

    public const VIDEOS = ['mp4', 'mov', 'webm', 'm4v'];

    public const GRAPHICS = ['svg', 'eps', 'ai', 'pdf'];

    /** Kilobytes; the server's upload_max_filesize must allow it too. */
    public const MAX_SIZE = 102400;

    public static function kind(string $extension): string
    {
        $extension = strtolower($extension);

        return match (true) {
            in_array($extension, self::VIDEOS, true) => 'video',
            in_array($extension, self::GRAPHICS, true) => 'graphic',
            default => 'image',
        };
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function store(UploadedFile $file, int $workspaceId, array $attributes = []): Asset
    {
        $extension = strtolower($file->getClientOriginalExtension());
        $path = $file->store("assets/{$workspaceId}", 'local');
        [$width, $height] = $this->dimensions($file->getRealPath(), $extension);

        $asset = new Asset([
            'title' => $attributes['title'] ?? (pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME) ?: $file->getClientOriginalName()),
            ...array_intersect_key($attributes, array_flip(['caption', 'credit', 'category', 'taken_on'])),
        ]);
        $asset->forceFill([
            'kind' => self::kind($extension),
            'path' => $path,
            'thumbnail_path' => $this->thumbnail($file->getRealPath(), $extension, $workspaceId),
            'original_name' => mb_strimwidth($file->getClientOriginalName(), 0, 255),
            'mime_type' => $file->getMimeType(),
            'size' => $file->getSize(),
            'width' => $width,
            'height' => $height,
        ])->save();

        return $asset;
    }

    /**
     * @return array{0: int|null, 1: int|null}
     */
    private function dimensions(string $path, string $extension): array
    {
        if (! in_array($extension, self::IMAGES, true)) {
            return [null, null];
        }

        $size = @getimagesize($path);

        return $size === false ? [null, null] : [$size[0], $size[1]];
    }

    /**
     * A 640px JPEG for the gallery grid, so it does not load full-size photos.
     */
    private function thumbnail(string $path, string $extension, int $workspaceId): ?string
    {
        if (! in_array($extension, ['jpg', 'jpeg', 'png', 'gif', 'webp'], true) || ! function_exists('imagecreatefromstring')) {
            return null;
        }

        $contents = @file_get_contents($path);
        $source = $contents === false ? false : @imagecreatefromstring($contents);
        if ($source === false) {
            return null;
        }

        $width = imagesx($source);
        $height = imagesy($source);
        $scale = min(1, 640 / max($width, 1));
        $thumb = imagecreatetruecolor(max(1, (int) round($width * $scale)), max(1, (int) round($height * $scale)));
        imagefill($thumb, 0, 0, (int) imagecolorallocate($thumb, 255, 255, 255));
        imagecopyresampled($thumb, $source, 0, 0, 0, 0, imagesx($thumb), imagesy($thumb), $width, $height);

        ob_start();
        imagejpeg($thumb, null, 82);
        $jpeg = (string) ob_get_clean();

        $thumbPath = "assets/{$workspaceId}/thumbs/".pathinfo($path, PATHINFO_FILENAME).'-'.bin2hex(random_bytes(4)).'.jpg';
        Storage::disk('local')->put($thumbPath, $jpeg);

        return $thumbPath;
    }
}
