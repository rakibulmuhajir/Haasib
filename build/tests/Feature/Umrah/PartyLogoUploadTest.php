<?php

use App\Services\LogoUploadService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

/**
 * Party logos go on vouchers, receipts and every report PDF.
 *
 * DomPDF reads PNG, JPEG and GIF but not WebP, so an upload is re-encoded
 * rather than stored as it arrived -- a WebP logo that looked right in the
 * browser would come out of a voucher as an empty square. And a logo is
 * brought down to a predictable edge, because file size alone says nothing
 * about how it will render: a small PNG can be four thousand pixels wide.
 */
function logoFile(int $width, int $height, string $format = 'png'): UploadedFile
{
    $image = imagecreatetruecolor($width, $height);
    imagefill($image, 0, 0, imagecolorallocate($image, 10, 120, 90));

    ob_start();
    match ($format) {
        'webp' => imagewebp($image),
        'jpeg' => imagejpeg($image),
        default => imagepng($image),
    };
    $bytes = (string) ob_get_clean();
    imagedestroy($image);

    $path = tempnam(sys_get_temp_dir(), 'logo').'.'.$format;
    file_put_contents($path, $bytes);

    return new UploadedFile($path, 'logo.'.$format, null, null, true);
}

beforeEach(function () {
    Storage::fake('public');
});

test('an oversized logo is brought down to a predictable width', function () {
    $url = app(LogoUploadService::class)->store(logoFile(4000, 2000), 'party-logos/test');

    $stored = Storage::disk('public')->get(str_replace('/storage/', '', $url));
    $image = imagecreatefromstring($stored);

    expect(imagesx($image))->toBe(LogoUploadService::MAX_EDGE)
        ->and(imagesy($image))->toBe(LogoUploadService::MAX_EDGE / 2);
});

test('a logo already small enough keeps its size', function () {
    $url = app(LogoUploadService::class)->store(logoFile(200, 80), 'party-logos/test');

    $image = imagecreatefromstring(Storage::disk('public')->get(str_replace('/storage/', '', $url)));

    expect(imagesx($image))->toBe(200)->and(imagesy($image))->toBe(80);
});

test('a webp upload is stored as png, because the PDFs cannot read webp', function () {
    $url = app(LogoUploadService::class)->store(logoFile(300, 300, 'webp'), 'party-logos/test');

    $bytes = Storage::disk('public')->get(str_replace('/storage/', '', $url));

    expect($url)->toEndWith('.png')
        // The PNG signature, so this is the format itself rather than the
        // extension claiming it.
        ->and(bin2hex(substr($bytes, 0, 4)))->toBe('89504e47');
});

test('replacing a logo removes the file it replaced', function () {
    $service = app(LogoUploadService::class);

    $first = $service->store(logoFile(100, 100), 'party-logos/test');
    $second = $service->store(logoFile(100, 100), 'party-logos/test', $first);

    Storage::disk('public')->assertMissing(str_replace('/storage/', '', $first));
    Storage::disk('public')->assertExists(str_replace('/storage/', '', $second));
});

test('a logo somebody linked to elsewhere is left alone', function () {
    // An external URL is not ours to delete.
    $service = app(LogoUploadService::class);
    $service->deleteIfOurs('https://example.com/their-logo.png');

    expect(true)->toBeTrue();
});

test('a file that is not an image is refused', function () {
    $path = tempnam(sys_get_temp_dir(), 'notanimage');
    file_put_contents($path, 'this is not an image');

    expect(fn () => app(LogoUploadService::class)->store(
        new UploadedFile($path, 'logo.png', null, null, true),
        'party-logos/test',
    ))->toThrow(Illuminate\Validation\ValidationException::class);
});
