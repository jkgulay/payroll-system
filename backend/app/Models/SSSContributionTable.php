<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SSSContributionTable extends Model
{
    use HasFactory;

    protected $table = 'sss_contribution_table';

    protected $fillable = [
        'min_salary',
        'max_salary',
        'employee_share',
        'employer_share',
        'total_contribution',
        'ec_contribution',
        'effective_year',
    ];

    protected $casts = [
        'min_salary' => 'decimal:2',
        'max_salary' => 'decimal:2',
        'employee_share' => 'decimal:2',
        'employer_share' => 'decimal:2',
        'total_contribution' => 'decimal:2',
        'ec_contribution' => 'decimal:2',
        'effective_year' => 'integer',
    ];
}
