<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property-read Collection<int, Room> $rooms
 * @property-read int|null $rooms_count
 *
 * @mixin \Eloquent
 */
class Warkspace extends Model
{
    protected $fillable = [
        'name',
        'timezone',
        'address',
    ];

    /**
     * @return HasMany<Room, $this>
     */
    public function rooms(): HasMany
    {
        return $this->hasMany(Room::class);
    }
}
