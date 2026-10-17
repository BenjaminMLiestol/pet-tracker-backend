<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Vaccination extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = ['name', 'given_at', 'next_due_at', 'notes', 'pet_id', 'user_id'];

    protected $casts = ['given_at' => 'datetime', 'next_due_at' => 'datetime'];

    public function pet()
    {
        return $this->belongsTo(Pet::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
