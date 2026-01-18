<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Applicant extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'applicant_number',
        'job_posting_id',
        'first_name',
        'middle_name',
        'last_name',
        'suffix',
        'date_of_birth',
        'gender',
        'civil_status',
        'nationality',
        'email',
        'mobile_number',
        'phone_number',
        'address_line1',
        'address_line2',
        'city',
        'province',
        'postal_code',
        'position_applied',
        'application_date',
        'expected_salary',
        'available_date',
        'status',
        'remarks',
        'reviewed_by',
        'reviewed_at',
        'approved_by',
        'approved_at',
        'hired_as_employee',
    ];

    protected $casts = [
        'date_of_birth' => 'date',
        'application_date' => 'date',
        'available_date' => 'date',
        'expected_salary' => 'decimal:2',
        'reviewed_at' => 'datetime',
        'approved_at' => 'datetime',
    ];

    public function jobPosting()
    {
        return $this->belongsTo(JobPosting::class);
    }

    public function reviewedBy()
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    public function approvedBy()
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function employee()
    {
        return $this->belongsTo(Employee::class, 'hired_as_employee');
    }

    public function getFullNameAttribute()
    {
        return trim("{$this->first_name} {$this->middle_name} {$this->last_name} {$this->suffix}");
    }
}
