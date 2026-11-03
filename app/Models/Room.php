<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $workspace_id
 * @property string $name
 * @property string|null $description
 * @property int $capacity
 * @property int $price_per_hour_minor
 * @property string $currency_code
 * @property bool $is_active
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Collection<int, Booking> $bookings
 * @property-read int|null $bookings_count
 * @property-read Warkspace $workspace
 *
 * @mixin \Eloquent
 */
class Room extends Model
{
    protected $fillable = [
        'workspace_id',
        'name',
        'description',
        'capacity',
        'price_per_hour_minor',
        'currency_code',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'capacity' => 'integer',
            'price_per_hour_minor' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    /**
     * @return BelongsTo<Warkspace, $this>
     */
    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Warkspace::class);
    }

    /**
     * @return HasMany<Booking, $this>
     */
    public function bookings(): HasMany
    {
        return $this->hasMany(Booking::class);
    }
}
