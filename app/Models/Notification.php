<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\NotificationType;
use App\Enums\PhoneNumberStatus;
use App\Models\Concerns\ReleasesNameOnDelete;
use DateTimeInterface;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable(['lead_days', 'name', 'phone_number_id', 'send_time', 'type'])]
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

    public PhoneNumber $phoneNumber {
        get => $this->getRelationValue('phoneNumber');
    }

    public string $phoneNumberId {
        get => $this->getAttribute('phone_number_id');
    }

    public ?string $sendTime {
        get => $this->getAttribute('send_time');
    }

    /** Where the message goes: the number of the phone number this notification uses. */
    public string $target {
        get => $this->phoneNumber->number;
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

    /** Whether the destination has been proved to belong to the user. Until it has, nothing is sent. */
    public bool $verified {
        get => $this->phoneNumber->status === PhoneNumberStatus::VERIFIED;
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

    /** @return BelongsTo<PhoneNumber, $this> */
    public function phoneNumber(): BelongsTo
    {
        return $this->belongsTo(PhoneNumber::class);
    }
}
