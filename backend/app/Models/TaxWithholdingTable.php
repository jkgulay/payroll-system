<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TaxWithholdingTable extends Model
{
    use HasFactory;

    protected $table = 'tax_withholding_table';

    protected $fillable = [
        'period_type',
        'min_income',
        'max_income',
        'tax_rate',
        'base_tax',
        'excess_rate',
        'effective_year',
    ];

    protected $casts = [
        'min_income' => 'decimal:2',
        'max_income' => 'decimal:2',
        'tax_rate' => 'decimal:4',
        'base_tax' => 'decimal:2',
        'excess_rate' => 'decimal:4',
        'effective_year' => 'integer',
    ];
}
