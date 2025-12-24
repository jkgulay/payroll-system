<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class EmployeeGovernmentInfo extends Model
{
    protected $table = 'employee_government_info';

    protected $fillable = [
        'employee_id',
        'sss_number',
        'sss_registration_date',
        'philhealth_number',
        'philhealth_registration_date',
        'pagibig_number',
        'pagibig_registration_date',
        'tin_number',
        'rdo_code',
        'tin_registration_date',
        'tax_status',
        'is_minimum_wage_earner',
        'is_govt_contribution_exempt',
    ];

    protected $casts = [
        'sss_registration_date' => 'date',
        'philhealth_registration_date' => 'date',
        'pagibig_registration_date' => 'date',
        'tin_registration_date' => 'date',
        'is_minimum_wage_earner' => 'boolean',
        'is_govt_contribution_exempt' => 'boolean',
    ];

    public function employee()
    {
        return $this->belongsTo(Employee::class);
    }
}
