<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class DriverProfile extends Model
{
    protected $fillable = [
        'user_id',
        'vehicle_type',
        'license_number',
        'approval_status',
        'is_online',
    ];

    protected $casts = [
        'is_online' => 'boolean',
    ];

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return HasMany<DriverLocation, $this>
     */
    public function locations(): HasMany
    {
        return $this->hasMany(DriverLocation::class);
    }
}
