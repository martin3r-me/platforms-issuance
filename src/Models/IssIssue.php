<?php

namespace Platform\Issuance\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Symfony\Component\Uid\UuidV7;

class IssIssue extends Model
{
    protected $table = 'iss_issues';

    protected $fillable = [
        'uuid',
        'team_id',
        'created_by_user_id',
        'recipient_type',
        'recipient_id',
        'issue_type_id',
        'title',
        'description',
        'identifier',
        'status',
        'issued_at',
        'returned_at',
        'metadata',
        'notes',
        'signature_data',
        'signed_at',
    ];

    protected $casts = [
        'issued_at' => 'date',
        'returned_at' => 'date',
        'metadata' => 'array',
        'signed_at' => 'datetime',
    ];

    public function recipient(): MorphTo
    {
        return $this->morphTo();
    }

    public function type(): BelongsTo
    {
        return $this->belongsTo(IssIssueType::class, 'issue_type_id');
    }

    /**
     * Hilfsmethode: Empfängername je nach Typ auflösen.
     */
    public function getRecipientName(): string
    {
        $recipient = $this->recipient;

        if (!$recipient) {
            return '—';
        }

        // HcmEmployee
        if ($recipient instanceof \Platform\Hcm\Models\HcmEmployee) {
            return $recipient->getContact()?->full_name ?? $recipient->employee_number;
        }

        // Fallback: display_name oder name
        return $recipient->display_name ?? $recipient->name ?? '—';
    }

    /**
     * Hilfsmethode: Empfänger-Untertitel (z.B. Personalnummer).
     */
    public function getRecipientSubtitle(): ?string
    {
        $recipient = $this->recipient;

        if ($recipient instanceof \Platform\Hcm\Models\HcmEmployee) {
            return $recipient->employee_number;
        }

        return null;
    }

    protected static function booted(): void
    {
        static::creating(function (self $model) {
            if (empty($model->uuid)) {
                do {
                    $uuid = UuidV7::generate();
                } while (self::where('uuid', $uuid)->exists());
                $model->uuid = $uuid;
            }
        });
    }
}
