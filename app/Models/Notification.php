<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\NotificationType;
use App\Models\Concerns\ReleasesNameOnDelete;
use DateTimeInterface;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable(['lead_days', 'name', 'send_time', 'target', 'type'])]
final class Notification extends Model
{
    use ReleasesNameOnDelete;
    use SoftDeletes;

    public House $house {
        get => $this->getRelationValue('house');
    }

    public string $houseId {
        get => $this->getAttribute('house_id');
    }

    public ?int $leadDays {
        get => $this->getAttribute('lead_days');
    }

    public string $name {
        get => $this->getAttribute('name');
    }

    public ?string $sendTime {
        get => $this->getAttribute('send_time');
    }

    public string $target {
        get => $this->getAttribute('target');
    }

    public NotificationType $type {
        get {
            /** @var string $type */
            $type = $this->getAttribute('type');

            return NotificationType::from($type);
        }
    }

    public ?DateTimeInterface $updatedAt {
        get => $this->getAttribute('updated_at');
    }

    /** Only present on notifications loaded by NotificationRepository::getDueNotifications(). */
    public string $userExternalId {
        get => $this->getAttribute('user_external_id');
    }

    /** @return BelongsTo<House, $this> */
    public function house(): BelongsTo
    {
        return $this->belongsTo(House::class);
    }

    /** @return HasMany<NotificationLog, $this> */
    public function logs(): HasMany
    {
        return $this->hasMany(NotificationLog::class);
    }
}
