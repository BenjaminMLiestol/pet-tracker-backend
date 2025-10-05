<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Feeding extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = ['feed_check', 'fed_at', 'pet_id', 'user_id'];

    protected $casts = ['feed_check' => 'boolean', 'fed_at' => 'datetime'];

    public function pet()
    {
        return $this->belongsTo(Pet::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
