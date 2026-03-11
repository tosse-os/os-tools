<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CrawlLink extends Model
{
    use HasFactory;

    protected $fillable = [
        'crawl_id',
        'source_url',
        'target_url',
        'type',
        'status_code',
        'redirect_chain_length',
    ];
}
