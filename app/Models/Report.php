<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Report extends Model
{
    use HasFactory;

    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'id',
        'user_id',
        'type',
        'url',
        'keyword',
        'city',
        'status',
        'total_urls',
        'processed_urls',
        'score',
        'started_at',
        'finished_at'
    ];

    protected $casts = [
        'started_at' => 'datetime',
        'finished_at' => 'datetime',
    ];

    public function results()
    {
        return $this->hasMany(\App\Models\ReportResult::class, 'report_id');
    }
    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
