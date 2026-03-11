<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CrawlQueue extends Model
{
    use HasFactory;

    public $timestamps = false;

    protected $table = 'crawl_queue';

    protected $fillable = [
        'crawl_id',
        'url',
        'depth',
        'status',
        'created_at',
    ];
}
