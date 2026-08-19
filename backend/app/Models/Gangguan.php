<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Gangguan extends Model
{
    use HasFactory;

    protected $fillable = [
        'ticket_number',
        'judul',
        'deskripsi',
        'status',
        'kategori',
        'priority',
        'created_by',
        'assigned_to',
        'start_time',
        'end_time',
        'durasi',
        'read_at',
        'read_by',
        'resolved_at',
        'resolved_by',
        'penyebab_permasalahan',
        'penyelesaian_masalah',
        'impact',
        'analisa',
    ];

    protected function casts(): array
    {
        return [
            'start_time'  => 'datetime',
            'end_time'    => 'datetime',
            'read_at'     => 'datetime',
            'resolved_at' => 'datetime',
        ];
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    public function reader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'read_by');
    }

    public function resolver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'resolved_by');
    }

    public function evidences(): HasMany
    {
        return $this->hasMany(Evidence::class);
    }
}
