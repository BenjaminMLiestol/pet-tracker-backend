<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Walk extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = ['walk_check', 'walked_at', 'pet_id', 'user_id'];

    protected $casts = ['walk_check' => 'boolean', 'walked_at' => 'datetime'];

    public function pet()
    {
        return $this->belongsTo(Pet::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
