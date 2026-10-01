<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['customer_id', 'tanggal_reservasi', 'waktu_reservasi', 'jumlah_orang', 'status', 'catatan'])]
class Reservasi extends Model
{
    /** @use HasFactory<ReservasiFactory> */
    use HasFactory;

    protected $table = 'reservasis';

    /**
     * @return BelongsTo<Customer, $this>
     */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    /**
     * @return HasMany<Pesanan, $this>
     */
    public function pesanans(): HasMany
    {
        return $this->hasMany(Pesanan::class);
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'tanggal_reservasi' => 'date',
            'jumlah_orang' => 'integer',
        ];
    }
}
