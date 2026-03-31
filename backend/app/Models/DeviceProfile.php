<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class DeviceProfile extends Model
{
    use HasFactory;

    protected $fillable = [
        'device_name',
        'designation',
        'location',
    ];
}
