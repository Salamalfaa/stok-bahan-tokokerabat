<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

#[Fillable(['name', 'email', 'password', 'role', 'no_telepon', 'is_active'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    public const ROLE_CUSTOMER = 'customer';

    public const ROLE_OPERATOR = 'operator';

    public const ROLE_OWNER = 'owner';

    public const ROLE_ADMIN = 'admin';

    protected $attributes = [
        'role' => self::ROLE_CUSTOMER,
        'is_active' => true,
    ];

    /**
     * @return HasOne<Customer, $this>
     */
    public function customer(): HasOne
    {
        return $this->hasOne(Customer::class);
    }

    /**
     * @return HasMany<EmploymentEvent, $this>
     */
    public function employmentEvents(): HasMany
    {
        return $this->hasMany(EmploymentEvent::class);
    }

    /**
     * @return HasMany<PasswordRecoveryRequest, $this>
     */
    public function passwordRecoveryRequests(): HasMany
    {
        return $this->hasMany(PasswordRecoveryRequest::class);
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'is_active' => 'boolean',
        ];
    }
}
