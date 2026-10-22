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
        'parent_id',
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
        'jenis_gangguan',
        'is_potential_massal',
        'penyelesaian_masalah',
        'impact',
        'jumlah_agent_terdampak',
        'analisa',
        'nomor_surat',
        'kode',
        'id_task_sip',
    ];

    protected function casts(): array
    {
        return [
            'start_time'           => 'datetime',
            'end_time'             => 'datetime',
            'read_at'              => 'datetime',
            'resolved_at'          => 'datetime',
            'is_potential_massal'  => 'boolean',
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

    public function parent(): BelongsTo
    {
        return $this->belongsTo(Gangguan::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(Gangguan::class, 'parent_id');
    }

    public function affectedAgents(): HasMany
    {
        return $this->hasMany(GangguanAffectedAgent::class, 'gangguan_id');
    }

    public function evidences(): HasMany
    {
        return $this->hasMany(Evidence::class);
    }
}
