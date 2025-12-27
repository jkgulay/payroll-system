<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class JobPosting extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'title',
        'position',
        'project_id',
        'location_id',
        'description',
        'requirements',
        'responsibilities',
        'employment_type',
        'salary_min',
        'salary_max',
        'vacancies',
        'posting_date',
        'deadline',
        'status',
        'posted_by',
    ];

    protected $casts = [
        'posting_date' => 'date',
        'deadline' => 'date',
        'salary_min' => 'decimal:2',
        'salary_max' => 'decimal:2',
        'vacancies' => 'integer',
    ];

    public function project()
    {
        return $this->belongsTo(Project::class);
    }

    public function location()
    {
        return $this->belongsTo(Location::class);
    }

    public function postedBy()
    {
        return $this->belongsTo(User::class, 'posted_by');
    }

    public function applicants()
    {
        return $this->hasMany(Applicant::class);
    }
}
