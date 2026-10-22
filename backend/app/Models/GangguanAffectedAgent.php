<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class GangguanAffectedAgent extends Model
{
    use HasFactory;

    protected $fillable = [
        'gangguan_id',
        'user_id',
        'agent_name',
        'cubicle_name',
        'notes',
    ];

    public function gangguan(): BelongsTo
    {
        return $this->belongsTo(Gangguan::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
