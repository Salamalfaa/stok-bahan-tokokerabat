<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['user_id', 'nama', 'email', 'no_telepon', 'alamat'])]
class Customer extends Model
{
    /** @use HasFactory<CustomerFactory> */
    use HasFactory;

    protected $table = 'customers';

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return HasMany<Reservasi, $this>
     */
    public function reservasis(): HasMany
    {
        return $this->hasMany(Reservasi::class);
    }

    /**
     * @return HasMany<Pesanan, $this>
     */
    public function pesanans(): HasMany
    {
        return $this->hasMany(Pesanan::class);
    }
}
