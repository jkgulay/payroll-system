<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CompanyInfo extends Model
{
    use HasFactory;

    protected $table = 'company_info';

    protected $fillable = [
        'company_name',
        'business_type',
        'tin',
        'registration_number',
        'address',
        'city',
        'province',
        'postal_code',
        'country',
        'phone',
        'mobile',
        'email',
        'website',
        'logo_path',
        'report_header',
        'report_footer',
        'prepared_by_title',
        'approved_by_title',
    ];
}
