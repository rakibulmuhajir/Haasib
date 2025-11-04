<?php

namespace Modules\Accounting\Domain\Customers\Models;

use App\Models\Customer as BaseCustomer;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class CustomerCommunication extends Model
{
    protected $table = 'acct.customer_communications';

    protected $fillable = [
        'customer_id',
        'company_id',
        'contact_id',
        'channel',
        'direction',
        'subject',
        'body',
        'logged_by_user_id',
        'occurred_at',
        'attachments',
    ];

    protected $casts = [
        'attachments' => 'array',
        'occurred_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    protected $dates = [
        'occurred_at',
        'created_at',
        'updated_at',
    ];

    /**
     * The channels that are mass assignable.
     */
    public const CHANNELS = ['email', 'phone', 'meeting', 'note'];

    /**
     * The directions that are mass assignable.
     */
    public const DIRECTIONS = ['inbound', 'outbound', 'internal'];

    /**
     * Get the customer that owns the communication.
     */
    public function customer()
    {
        return $this->belongsTo(BaseCustomer::class, 'customer_id');
    }

    /**
     * Get the company that owns the communication.
     */
    public function company()
    {
        return $this->belongsTo(\App\Models\Company::class, 'company_id');
    }

    /**
     * Get the contact associated with the communication.
     */
    public function contact()
    {
        return $this->belongsTo(CustomerContact::class, 'contact_id');
    }

    /**
     * Get the user who logged the communication.
     */
    public function loggedBy()
    {
        return $this->belongsTo(User::class, 'logged_by_user_id');
    }

    /**
     * Scope to get communications by channel.
     */
    public function scopeByChannel(Builder $query, string $channel): Builder
    {
        return $query->where('channel', $channel);
    }

    /**
     * Scope to get communications by direction.
     */
    public function scopeByDirection(Builder $query, string $direction): Builder
    {
        return $query->where('direction', $direction);
    }

    /**
     * Scope to get communications within a date range.
     */
    public function scopeBetweenDates(Builder $query, \DateTime $startDate, \DateTime $endDate): Builder
    {
        return $query->whereBetween('occurred_at', [$startDate, $endDate]);
    }

    /**
     * Scope to get recent communications.
     */
    public function scopeRecent(Builder $query, int $days = 30): Builder
    {
        return $query->where('occurred_at', '>=', now()->subDays($days));
    }

    /**
     * Scope to search communications by subject or body.
     */
    public function scopeSearch(Builder $query, string $search): Builder
    {
        return $query->where(function (Builder $q) use ($search) {
            $q->where('subject', 'ILIKE', "%{$search}%")
                ->orWhere('body', 'ILIKE', "%{$search}%");
        });
    }

    /**
     * Scope to get communications with attachments.
     */
    public function scopeWithAttachments(Builder $query): Builder
    {
        return $query->whereNotNull('attachments')
            ->where('attachments', '!=', '[]');
    }

    /**
     * Get the timeline data for display.
     */
    public function getTimelineDataAttribute(): array
    {
        return [
            'id' => $this->id,
            'channel' => $this->channel,
            'direction' => $this->direction,
            'subject' => $this->subject,
            'body' => $this->getTruncatedBody(),
            'occurred_at' => $this->occurred_at->toISOString(),
            'logged_by' => $this->loggedBy?->name,
            'contact' => $this->contact?->full_name,
            'has_attachments' => $this->has_attachments,
            'attachment_count' => $this->attachment_count,
        ];
    }

    /**
     * Get truncated body for preview.
     */
    public function getTruncatedBodyAttribute(): string
    {
        $length = 150;
        if (strlen($this->body) <= $length) {
            return $this->body;
        }

        return substr($this->body, 0, $length).'...';
    }

    /**
     * Check if communication has attachments.
     */
    public function getHasAttachmentsAttribute(): bool
    {
        return ! empty($this->attachments) && is_array($this->attachments);
    }

    /**
     * Get the attachment count.
     */
    public function getAttachmentCountAttribute(): int
    {
        return count($this->attachments ?? []);
    }

    /**
     * Get formatted channel with icon.
     */
    public function getFormattedChannelAttribute(): string
    {
        $icons = [
            'email' => '📧',
            'phone' => '📞',
            'meeting' => '👥',
            'note' => '📝',
        ];

        return ($icons[$this->channel] ?? '📋').' '.ucfirst($this->channel);
    }

    /**
     * Get formatted direction with icon.
     */
    public function getFormattedDirectionAttribute(): string
    {
        $icons = [
            'inbound' => '📥',
            'outbound' => '📤',
            'internal' => '🔄',
        ];

        return ($icons[$this->direction] ?? '📋').' '.ucfirst($this->direction);
    }

    /**
     * Add an attachment to the communication.
     */
    public function addAttachment(array $attachment): void
    {
        $attachments = $this->attachments ?? [];
        $attachments[] = $attachment;
        $this->update(['attachments' => $attachments]);
    }

    /**
     * Remove an attachment by index.
     */
    public function removeAttachment(int $index): void
    {
        $attachments = $this->attachments ?? [];
        if (isset($attachments[$index])) {
            array_splice($attachments, $index, 1);
            $this->update(['attachments' => $attachments]);
        }
    }

    /**
     * Get communication statistics for a customer.
     */
    public static function getCustomerStats(BaseCustomer $customer, ?\DateTime $startDate = null, ?\DateTime $endDate = null): array
    {
        $query = static::where('customer_id', $customer->id);

        if ($startDate) {
            $query->where('occurred_at', '>=', $startDate);
        }

        if ($endDate) {
            $query->where('occurred_at', '<=', $endDate);
        }

        $total = $query->count();

        $byChannel = $query->select('channel', \DB::raw('count(*) as count'))
            ->groupBy('channel')
            ->pluck('count', 'channel')
            ->toArray();

        $byDirection = $query->select('direction', \DB::raw('count(*) as count'))
            ->groupBy('direction')
            ->pluck('count', 'direction')
            ->toArray();

        return [
            'total' => $total,
            'by_channel' => $byChannel,
            'by_direction' => $byDirection,
        ];
    }

    /**
     * Get communication timeline using the database function.
     */
    public static function getTimeline(BaseCustomer $customer, int $limit = 50, int $offset = 0): array
    {
        return \DB::select(
            'SELECT * FROM invoicing.get_customer_communication_timeline(?, ?, ?, ?)',
            [$customer->id, $customer->company_id, $limit, $offset]
        );
    }

    /**
     * Boot the model.
     */
    protected static function booted()
    {
        static::creating(function ($communication) {
            // Set company context from customer if not provided
            if (! $communication->company_id && $communication->customer_id) {
                $communication->company_id = BaseCustomer::find($communication->customer_id)?->company_id;
            }

            // Set current user as logged by if not provided
            if (! $communication->logged_by_user_id) {
                $communication->logged_by_user_id = auth()->id();
            }

            // Set occurred_at to now if not provided
            if (! $communication->occurred_at) {
                $communication->occurred_at = now();
            }
        });

        static::saving(function ($communication) {
            // Validate channel
            if (! in_array($communication->channel, static::CHANNELS)) {
                throw new \InvalidArgumentException("Invalid channel: {$communication->channel}");
            }

            // Validate direction
            if (! in_array($communication->direction, static::DIRECTIONS)) {
                throw new \InvalidArgumentException("Invalid direction: {$communication->direction}");
            }

            // Body is required for internal notes
            if ($communication->direction === 'internal' && empty($communication->body)) {
                throw new \InvalidArgumentException('Body is required for internal notes');
            }
        });
    }
}
