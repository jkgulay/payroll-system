<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'employee_id',
        'username',
        'name',
        'email',
        'password',
        'role',
        'is_active',
        'must_change_password',
        'avatar',
        'last_login_at',
        'two_factor_secret',
        'two_factor_enabled',
        'two_factor_confirmed_at',
        'two_factor_recovery_codes',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var array<int, string>
     */
    protected $hidden = [
        'password',
        'remember_token',
        'two_factor_secret',
        'two_factor_recovery_codes',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'email_verified_at' => 'datetime',
        'password' => 'hashed',
        'is_active' => 'boolean',
        'must_change_password' => 'boolean',
        'last_login_at' => 'datetime',
        'two_factor_enabled' => 'boolean',
        'two_factor_confirmed_at' => 'datetime',
    ];

    protected $appends = [
        'full_name',
    ];

    public function getFullNameAttribute(): string
    {
        if ($this->relationLoaded('employee') && $this->employee) {
            return $this->employee->full_name;
        }

        return $this->name ?? 'System';
    }

    /**
     * Get the avatar URL attribute
     */
    protected function getAvatarUrlAttribute(): ?string
    {
        if (!$this->avatar) {
            return null;
        }

        // If avatar is already a full URL, return it
        if (str_starts_with($this->avatar, 'http')) {
            return $this->avatar;
        }

        // Return the storage URL
        return url('storage/' . $this->avatar);
    }

    /**
     * Get the employee that owns the user account
     */
    public function employee()
    {
        return $this->belongsTo(Employee::class, 'employee_id');
    }
}
