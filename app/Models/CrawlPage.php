<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CrawlPage extends Model
{
    use HasFactory;

    public $timestamps = false;
    const UPDATED_AT = null;

    protected $fillable = [
        'crawl_id',
        'url',
        'canonical_url',
        'canonical',
        'status',
        'status_code',
        'title',
        'alt_count',
        'meta_description',
        'heading_count',
        'h1_count',
        'error',
        'image_count',
        'alt_missing_count',
        'internal_links',
        'external_links',
        'content_hash',
        'text_hash',
        'internal_links_in',
        'internal_links_out',
        'depth',
        'created_at',
    ];

    protected $casts = [
        'created_at' => 'datetime',
        'status_code' => 'integer',
        'h1_count' => 'integer',
        'heading_count' => 'integer',
        'image_count' => 'integer',
        'alt_missing_count' => 'integer',
        'internal_links' => 'integer',
        'external_links' => 'integer',
        'internal_links_in' => 'integer',
        'internal_links_out' => 'integer',
        'depth' => 'integer',
    ];

    public function crawl()
    {
        return $this->belongsTo(Crawl::class);
    }
}
