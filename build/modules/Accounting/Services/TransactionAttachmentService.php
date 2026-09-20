<?php

namespace App\Modules\Accounting\Services;

use App\Modules\Accounting\Models\Transaction;
use App\Modules\Accounting\Models\TransactionAttachment;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

/**
 * Stores the document behind a transaction and hands back the record.
 *
 * The private disk, not the public one. A supplier invoice carries account numbers,
 * pricing and sometimes a phone number; putting it under /storage would make it readable
 * by anyone who can guess a filename, which is not a trade a receipt is worth.
 */
class TransactionAttachmentService
{
    /** Matches the `max:` on the validation rules, which are in kilobytes. */
    public const MAX_KILOBYTES = 10240;

    public const ACCEPTED = ['pdf', 'png', 'jpg', 'jpeg', 'webp', 'heic'];

    public function store(Transaction $transaction, UploadedFile $file, ?string $userId = null): TransactionAttachment
    {
        $extension = strtolower($file->getClientOriginalExtension());
        if (! in_array($extension, self::ACCEPTED, true)) {
            throw ValidationException::withMessages([
                'attachment' => 'Attach a PDF or a photo (' . implode(', ', self::ACCEPTED) . ').',
            ]);
        }

        // Namespaced by company so one tenant's documents never share a directory with
        // another's, which keeps a mistaken disk-level read from crossing tenants.
        $path = $file->store("attachments/{$transaction->company_id}/transactions/{$transaction->id}", 'local');
        if (! $path) {
            throw ValidationException::withMessages(['attachment' => 'That file could not be stored. Try again.']);
        }

        return TransactionAttachment::create([
            'company_id' => $transaction->company_id,
            'transaction_id' => $transaction->id,
            'disk' => 'local',
            'path' => $path,
            'original_name' => $file->getClientOriginalName(),
            'mime_type' => $file->getClientMimeType(),
            'size_bytes' => $file->getSize(),
            'uploaded_by_user_id' => $userId,
        ]);
    }

    public function delete(TransactionAttachment $attachment): void
    {
        Storage::disk($attachment->disk)->delete($attachment->path);
        $attachment->delete();
    }
}
