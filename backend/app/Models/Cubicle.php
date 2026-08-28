<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Cubicle extends Model
{
    use HasFactory;

    protected $fillable = [
        'nama',
        'ext',
        'ip',
    ];
}
