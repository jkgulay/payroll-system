<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Project extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'code',
        'name',
        'description',
        'time_in',
        'time_out',
        'grace_period_minutes',
        'head_employee_id',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'grace_period_minutes' => 'integer',
    ];

    public function employees(): HasMany
    {
        return $this->hasMany(Employee::class);
    }

    public function headEmployee()
    {
        return $this->belongsTo(Employee::class, 'head_employee_id');
    }
}
