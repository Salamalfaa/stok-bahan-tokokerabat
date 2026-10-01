<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['kode_pesanan', 'customer_id', 'reservasi_id', 'karyawan_id', 'total_harga', 'status', 'catatan'])]
class Pesanan extends Model
{
    /** @use HasFactory<PesananFactory> */
    use HasFactory;

    protected $table = 'pesanans';

    /**
     * @return BelongsTo<Customer, $this>
     */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    /**
     * @return BelongsTo<Reservasi, $this>
     */
    public function reservasi(): BelongsTo
    {
        return $this->belongsTo(Reservasi::class);
    }

    /**
     * @return BelongsTo<Karyawan, $this>
     */
    public function karyawan(): BelongsTo
    {
        return $this->belongsTo(Karyawan::class);
    }

    /**
     * @return HasMany<DetailPesanan, $this>
     */
    public function detailPesanans(): HasMany
    {
        return $this->hasMany(DetailPesanan::class);
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'total_harga' => 'decimal:2',
        ];
    }
}
