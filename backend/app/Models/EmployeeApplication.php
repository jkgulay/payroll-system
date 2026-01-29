<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EmployeeApplication extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'first_name',
        'middle_name',
        'last_name',
        'date_of_birth',
        'gender',
        'email',
        'mobile_number',
        'worker_address',
        'project_id',
        'position',
        'date_hired',
        'contract_type',
        'activity_status',
        'employment_type',
        'salary_type',
        'basic_salary',
        'has_water_allowance',
        'water_allowance',
        'has_cola',
        'cola',
        'has_incentives',
        'incentives',
        'has_ppe',
        'ppe',
        'resume_path',
        'id_path',
        'contract_path',
        'certificates_path',
        'application_status',
        'created_by',
        'reviewed_by',
        'rejection_reason',
        'submitted_at',
        'reviewed_at',
        'employee_id',
    ];

    protected $casts = [
        'date_of_birth' => 'date',
        'date_hired' => 'date',
        'basic_salary' => 'decimal:2',
        'water_allowance' => 'decimal:2',
        'cola' => 'decimal:2',
        'incentives' => 'decimal:2',
        'ppe' => 'decimal:2',
        'has_water_allowance' => 'boolean',
        'has_cola' => 'boolean',
        'has_incentives' => 'boolean',
        'has_ppe' => 'boolean',
        'submitted_at' => 'datetime',
        'reviewed_at' => 'datetime',
    ];

    /**
     * Get the project that the application belongs to.
     */
    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    /**
     * Get the user who created the application (HR).
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Get the admin who reviewed the application.
     */
    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    /**
     * Get the employee created from this application.
     */
    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    /**
     * Scope to get pending applications.
     */
    public function scopePending($query)
    {
        return $query->where('application_status', 'pending');
    }

    /**
     * Scope to get approved applications.
     */
    public function scopeApproved($query)
    {
        return $query->where('application_status', 'approved');
    }

    /**
     * Scope to get rejected applications.
     */
    public function scopeRejected($query)
    {
        return $query->where('application_status', 'rejected');
    }
}
