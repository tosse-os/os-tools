<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ReportResult extends Model
{
  protected $fillable = [
    'report_id',
    'module',
    'url',
    'position',
    'score',
    'key',
    'value',
    'payload',
  ];

  protected $casts = [
    'payload' => 'array',
  ];

  public function report()
  {
    return $this->belongsTo(Report::class);
  }
}
