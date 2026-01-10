<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MealAllowanceItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'meal_allowance_id',
        'employee_id',
        'employee_name',
        'position_code',
        'no_of_days',
        'amount_per_day',
        'total_amount',
        'sort_order',
    ];

    protected $casts = [
        'no_of_days' => 'integer',
        'amount_per_day' => 'decimal:2',
        'total_amount' => 'decimal:2',
        'sort_order' => 'integer',
    ];

    public function mealAllowance(): BelongsTo
    {
        return $this->belongsTo(MealAllowance::class);
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    protected static function boot()
    {
        parent::boot();

        static::saving(function ($model) {
            // Auto-calculate total_amount
            $model->total_amount = $model->no_of_days * $model->amount_per_day;
        });
    }
}
