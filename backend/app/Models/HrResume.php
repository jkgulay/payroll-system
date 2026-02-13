<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class HrResume extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'hr_resumes';

    protected $fillable = [
        'user_id',
        'first_name',
        'middle_name',
        'last_name',
        'date_of_birth',
        'gender',
        'email',
        'phone',
        'address',
        'position_applied',
        'department_preference',
        'expected_salary',
        'availability_date',
        'notes',
        'original_filename',
        'stored_filename',
        'file_path',
        'file_type',
        'file_size',
        'status',
        'admin_notes',
        'reviewed_by',
        'reviewed_at',
    ];

    /**
     * Get the full name of the applicant
     */
    public function getFullNameAttribute(): string
    {
        if ($this->first_name && $this->last_name) {
            $name = $this->first_name;
            if ($this->middle_name) {
                $name .= ' ' . $this->middle_name;
            }
            $name .= ' ' . $this->last_name;
            return trim($name);
        }
        return '';
    }

    protected $casts = [
        'date_of_birth' => 'date',
        'availability_date' => 'date',
        'reviewed_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    protected $appends = ['file_url', 'full_name'];

    /**
     * Get the user who uploaded the resume
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /**
     * Get the admin who reviewed the resume
     */
    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    /**
     * Get the full URL for the file
     */
    public function getFileUrlAttribute(): string
    {
        return url('storage/' . $this->file_path);
    }

    /**
     * Scope for pending resumes
     */
    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }

    /**
     * Scope for approved resumes
     */
    public function scopeApproved($query)
    {
        return $query->where('status', 'approved');
    }

    /**
     * Scope for rejected resumes
     */
    public function scopeRejected($query)
    {
        return $query->where('status', 'rejected');
    }

    /**
     * Scope for specific user
     */
    public function scopeForUser($query, $userId)
    {
        return $query->where('user_id', $userId);
    }
}
