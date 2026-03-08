<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Analysis extends Model
{
    use HasFactory;

    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'id',
        'project_id',
        'keyword',
        'city',
        'url',
    ];

    public function project()
    {
        return $this->belongsTo(Project::class);
    }

    public function reports()
    {
        return $this->hasMany(Report::class);
    }
}
