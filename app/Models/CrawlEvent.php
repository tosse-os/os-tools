<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CrawlEvent extends Model
{
    use HasFactory;

    public $timestamps = false;
    const UPDATED_AT = null;

    protected $fillable = [
        'crawl_id',
        'type',
        'url',
        'status',
        'alt_count',
        'heading_count',
        'error',
        'created_at',
    ];

    protected $casts = [
        'created_at' => 'datetime',
    ];
}
