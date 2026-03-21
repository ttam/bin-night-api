<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class NotificationLog extends Model
{
    public function notification(): BelongsTo
    {
        return $this->belongsTo(Notification::class);
    }
}
