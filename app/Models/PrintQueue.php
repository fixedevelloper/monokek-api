<?php


namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PrintQueue extends Model
{

    protected $fillable = [
        'printer_id',
        'job_type',
        'content',
        'attempts',
        'status',
        'error_message',
        'priority'
    ];

    // Cast automatique du JSON en array PHP
    protected $casts = [
        'content' => 'array',
        'printed_at' => 'datetime',
    ];

    /**
     * Relation avec l'imprimante
     */
    public function printer(): BelongsTo
    {
        return $this->belongsTo(Printer::class);
    }

    /**
     * Scope pour récupérer les tâches à traiter
     */
    public function scopePending($query)
    {
        return $query->whereIn('status', ['pending', 'failed'])
            ->where('attempts', '<', 3)
            ->orderBy('priority', 'desc')
            ->orderBy('created_at', 'asc');
    }
}
