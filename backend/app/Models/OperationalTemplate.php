<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OperationalTemplate extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'title',
        'category',
        'content',
        'original_filename',
        'file_path',
        'is_shared',
    ];

    protected $casts = [
        'is_shared' => 'boolean',
    ];

    protected $appends = [
        'file_url',
    ];

    public function getFileUrlAttribute(): ?string
    {
        if (!$this->file_path) {
            return null;
        }
        return url("/api/operational-templates/{$this->id}/file");
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
