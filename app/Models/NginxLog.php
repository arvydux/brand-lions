<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class NginxLog extends Model
{
    use HasFactory;

    protected $fillable = [
        'domain', 'request_time'
    ];
}
