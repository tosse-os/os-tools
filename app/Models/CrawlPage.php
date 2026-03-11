<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CrawlPage extends Model
{
    use HasFactory;

    protected $fillable = [
        'crawl_id',
        'url',
        'status_code',
        'depth',
        'title',
        'meta_description',
        'h1_count',
        'alt_missing_count',
        'internal_links_count',
        'external_links_count',
        'word_count',
        'content_hash',
        'text_hash',
        'response_time',
        'internal_links_in',
        'created_at',
        'updated_at',
    ];

    protected $casts = [
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public function crawl()
    {
        return $this->belongsTo(Crawl::class);
    }
}
