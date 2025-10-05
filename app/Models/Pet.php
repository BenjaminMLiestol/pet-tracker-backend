<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Pet extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = ['name', 'age', 'breed', 'date_of_birth'];

    public function users()
    {
        return $this->belongsToMany(User::class)->withTimestamps()->withTrashed();
    }

    public function feedings()
    {
        return $this->hasMany(Feeding::class);
    }

    public function walks()
    {
        return $this->hasMany(Walk::class);
    }

    public function baths()
    {
        return $this->hasMany(Bath::class);
    }

    public function weights()
    {
        return $this->hasMany(Weight::class);
    }
}
