<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;


class ScanResult extends Model
{
    use HasFactory;
    protected $fillable = ['scan_id', 'position', 'url', 'result', 'payload'];

    public function scan()
    {
        return $this->belongsTo(Scan::class);
    }

    protected $casts = [
        'result' => 'array',
        'payload' => 'array',
    ];
}
