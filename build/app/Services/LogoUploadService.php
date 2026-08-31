<?php

namespace App\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Party logos, for the screens and for the paper.
 *
 * These end up on vouchers, receipts and every report PDF, which DomPDF
 * renders -- and DomPDF reads PNG, JPEG and GIF but not WebP. So whatever
 * is uploaded is re-encoded to PNG here rather than stored as it arrived:
 * a WebP logo that looked right in the browser would otherwise come out of
 * a voucher as a blank square.
 *
 * The upload cap is on the file, but the file size is the wrong thing to
 * care about on its own -- a 200 KB PNG can be four thousand pixels wide
 * and render as mush once a PDF scales it into a corner. Everything is
 * brought down to a predictable edge on the way in, so what the layout
 * gets is the same shape every time.
 */
class LogoUploadService
{
    /** Matches the `max:` on the validation rules, which are in kilobytes. */
    public const MAX_KILOBYTES = 300;

    /** Long edge in pixels. A logo is never rendered larger than this. */
    public const MAX_EDGE = 600;

    public const ACCEPTED = ['png', 'jpg', 'jpeg', 'webp'];

    /**
     * Stores the upload and returns the public URL to put in logo_url.
     *
     * $replacing is the record's current logo_url; when it points at a file
     * this service wrote, that file is removed. An external URL someone
     * typed in before is left alone -- it is not ours to delete.
     */
    public function store(UploadedFile $file, string $directory, ?string $replacing = null): string
    {
        $image = @imagecreatefromstring((string) file_get_contents($file->getRealPath()));

        if ($image === false) {
            throw ValidationException::withMessages([
                'logo' => 'That file could not be read as an image. Upload a PNG, JPG or WebP.',
            ]);
        }

        $resized = $this->scaleToMaxEdge($image);

        // Logos are usually cut out against nothing. Without these two the
        // transparent parts come back black on the voucher.
        imagealphablending($resized, false);
        imagesavealpha($resized, true);

        ob_start();
        imagepng($resized, null, 6);
        $png = (string) ob_get_clean();

        imagedestroy($resized);
        if ($resized !== $image) {
            imagedestroy($image);
        }

        $path = trim($directory, '/').'/'.Str::uuid().'.png';
        Storage::disk('public')->put($path, $png);

        $this->deleteIfOurs($replacing);

        return Storage::url($path);
    }

    public function deleteIfOurs(?string $url): void
    {
        if (! $url || ! str_starts_with($url, '/storage/')) {
            return;
        }

        Storage::disk('public')->delete(str_replace('/storage/', '', $url));
    }

    /**
     * @param  \GdImage  $image
     * @return \GdImage the original when it already fits, so callers must
     *                  compare before destroying both
     */
    private function scaleToMaxEdge($image)
    {
        $width = imagesx($image);
        $height = imagesy($image);
        $longest = max($width, $height);

        if ($longest <= self::MAX_EDGE) {
            return $image;
        }

        $scale = self::MAX_EDGE / $longest;
        $target = imagescale($image, (int) round($width * $scale), (int) round($height * $scale));

        return $target === false ? $image : $target;
    }
}
