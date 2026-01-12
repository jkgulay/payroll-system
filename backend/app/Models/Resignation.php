<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Resignation extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'employee_id',
        'resignation_date',
        'last_working_day',
        'effective_date',
        'reason',
        'attachments',
        'status',
        'remarks',
        'processed_by',
        'processed_at',
        'thirteenth_month_amount',
        'final_pay_amount',
        'final_pay_released',
        'final_pay_release_date',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'resignation_date' => 'date',
        'last_working_day' => 'date',
        'effective_date' => 'date',
        'processed_at' => 'datetime',
        'attachments' => 'array',
        'thirteenth_month_amount' => 'decimal:2',
        'final_pay_amount' => 'decimal:2',
        'final_pay_released' => 'boolean',
        'final_pay_release_date' => 'date',
    ];

    protected $appends = ['status_label', 'days_remaining'];

    // Relationships
    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function processedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'processed_by');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    // Accessors
    public function getStatusLabelAttribute(): string
    {
        return match ($this->status) {
            'pending' => 'Pending Approval',
            'approved' => 'Approved',
            'rejected' => 'Rejected',
            'completed' => 'Completed',
            default => 'Unknown',
        };
    }

    public function getDaysRemainingAttribute(): ?int
    {
        if (!$this->effective_date) {
            return null;
        }
        
        $today = \Carbon\Carbon::now()->startOfDay();
        $effectiveDate = \Carbon\Carbon::parse($this->effective_date)->startOfDay();
        
        if ($effectiveDate->isPast()) {
            return 0;
        }
        
        return $today->diffInDays($effectiveDate, false);
    }

    // Scopes
    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }

    public function scopeApproved($query)
    {
        return $query->where('status', 'approved');
    }

    public function scopeCompleted($query)
    {
        return $query->where('status', 'completed');
    }

    public function scopeWithRelations($query)
    {
        return $query->with(['employee', 'processedBy', 'createdBy']);
    }
}
