<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Backup extends Model
{
    use HasFactory;

    protected $fillable = [
        'filename',
        'path',
        'backup_date',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'backup_date' => 'datetime',
        ];
    }
}
