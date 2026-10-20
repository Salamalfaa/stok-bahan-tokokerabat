<?php

namespace App\Models;

use Database\Factories\DisciplinaryActionFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['user_id', 'actor_id', 'kind', 'note', 'warning_subject', 'email_sent_at'])]
class DisciplinaryAction extends Model
{
    /** @use HasFactory<DisciplinaryActionFactory> */
    use HasFactory;

    public const KIND_VERBAL_WARNING = 'teguran_lisan';

    public const KIND_WARNING_LETTER = 'surat_peringatan';

    public const KIND_OTHER = 'sanksi_lainnya';

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_id');
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_sent_at' => 'datetime',
        ];
    }
}
