<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Evidence extends Model
{
    use HasFactory;

    protected $table = 'evidences';

    protected $fillable = [
        'gangguan_id',
        'filename',
        'filepath',
    ];

    public function gangguan(): BelongsTo
    {
        return $this->belongsTo(Gangguan::class);
    }
}
