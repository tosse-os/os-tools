<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;


class Scan extends Model
{
    use HasFactory;
    public $incrementing = false;
    protected $keyType = 'string';
    protected $fillable = ['id', 'url', 'status', 'total', 'current', 'started_at', 'finished_at'];

    public function results()
    {
        return $this->hasMany(ScanResult::class);
    }
}
