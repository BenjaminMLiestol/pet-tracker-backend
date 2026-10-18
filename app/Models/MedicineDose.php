<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MedicineDose extends Model
{
    protected $fillable = ['medicine_id', 'user_id', 'given_for', 'slot', 'given_at'];

    protected $casts = ['given_for' => 'date:Y-m-d', 'given_at' => 'datetime'];

    public function medicine()
    {
        return $this->belongsTo(Medicine::class);
    }
}
