<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Schema;

class PositionRate extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'position_name',
        'code',
        'daily_rate',
        'category',
        'description',
        'is_active',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'daily_rate' => 'decimal:2',
        'is_active' => 'boolean',
    ];

    // Relationships
    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    public function employees(): HasMany
    {
        return $this->hasMany(Employee::class, 'position_id');
    }

    // Scopes
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function scopeByCategory(Builder $query, string $category): Builder
    {
        return $query->where('category', $category);
    }

    // Helper methods
    public function getEmployeeCount(): int
    {
        return $this->employeeCountQuery()->count();
    }

    public function employeeCountQuery()
    {
        return Employee::where(function ($query) {
            $query->where('position_id', $this->id);

            if (Schema::hasColumn('employees', 'position')) {
                $query->orWhere(function ($legacyQuery) {
                    $legacyQuery
                        ->whereNull('position_id')
                        ->whereRaw('LOWER(position) = ?', [strtolower($this->position_name)]);
                });
            }
        });
    }
}
