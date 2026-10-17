<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class VetVisit extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = ['reason', 'visited_at', 'next_visit_at', 'notes', 'pet_id', 'user_id'];

    protected $casts = ['visited_at' => 'datetime', 'next_visit_at' => 'datetime'];

    public function pet()
    {
        return $this->belongsTo(Pet::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
