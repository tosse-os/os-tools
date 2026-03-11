<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CrawlLink extends Model
{
    use HasFactory;

    public $timestamps = false;
    const UPDATED_AT = null;

    protected $fillable = [
        'crawl_id',
        'source_url',
        'target_url',
        'link_type',
        'anchor_text',
        'nofollow',
        'status_code',
        'redirect_target',
        'redirect_chain_length',
        'redirect_chain',
        'created_at',
    ];

    protected $casts = [
        'nofollow' => 'boolean',
        'status_code' => 'integer',
        'redirect_chain_length' => 'integer',
        'redirect_chain' => 'array',
        'created_at' => 'datetime',
    ];

    public function crawl()
    {
        return $this->belongsTo(Crawl::class);
    }
}
