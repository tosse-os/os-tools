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
        'domain',
        'root_url',
        'start_url',
        'status',
        'pages_discovered',
        'pages_scanned',
        'pages_failed',
        'pages_total',
        'started_at',
        'finished_at',
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
}
