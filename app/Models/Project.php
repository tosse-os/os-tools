<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Project extends Model
{
    use HasFactory;

    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'id',
        'user_id',
        'name',
        'domain',
    ];

    public function analyses()
    {
        return $this->hasMany(Analysis::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
