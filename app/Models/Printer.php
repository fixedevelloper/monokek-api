<?php


namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Printer extends Model
{
    protected $fillable = [
        'branch_id', 'name', 'type', 'connection', 'ip', 'port', 'char_per_line', 'is_active',
        'location','use_beep','paper_width'
    ];

    // Relation avec la branche
    public function branch() {
        return $this->belongsTo(Branch::class);
    }
}
