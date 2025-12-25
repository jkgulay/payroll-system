<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class InterviewSchedule extends Model
{
    use HasFactory;

    protected $fillable = [
        'applicant_id',
        'scheduled_date',
        'interview_type',
        'interviewer_name',
        'interviewer_id',
        'location',
        'meeting_link',
        'status',
        'notes',
        'rating',
        'feedback',
    ];

    protected $casts = [
        'scheduled_date' => 'datetime',
        'rating' => 'integer',
    ];

    public function applicant()
    {
        return $this->belongsTo(Applicant::class);
    }

    public function interviewer()
    {
        return $this->belongsTo(User::class, 'interviewer_id');
    }
}
