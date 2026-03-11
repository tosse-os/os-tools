<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Crawl extends Model
{
    use HasFactory;

    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'id',
        'root_url',
        'start_url',
        'domain',
        'status',
        'pages_discovered',
        'pages_scanned',
        'pages_failed',
        'pages_total',
        'started_at',
        'finished_at',
        'created_at',
        'updated_at',
    ];

    protected $casts = [
        'started_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'finished_at' => 'datetime',
    ];

    public function pages()
    {
        return $this->hasMany(CrawlPage::class);
    }

    public function links()
    {
        return $this->hasMany(CrawlLink::class);
    }

    public function getEntryUrlAttribute(): string
    {
        return $this->root_url ?: $this->start_url;
    }
}
