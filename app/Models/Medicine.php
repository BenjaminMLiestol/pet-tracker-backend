<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Medicine extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = ['name', 'dose', 'times', 'interval_days', 'starts_on', 'ends_on', 'pet_id', 'user_id'];

    protected $casts = [
        'times' => 'array',
        'interval_days' => 'integer',
        'starts_on' => 'date:Y-m-d',
        'ends_on' => 'date:Y-m-d',
    ];

    public function pet()
    {
        return $this->belongsTo(Pet::class);
    }

    public function doses()
    {
        return $this->hasMany(MedicineDose::class);
    }

    /** Whether the medicine is to be given on this calendar day ("YYYY-MM-DD"). */
    public function isDueOn(string $date): bool
    {
        $day = CarbonImmutable::parse($date, 'UTC')->startOfDay();
        $start = CarbonImmutable::parse($this->starts_on->toDateString(), 'UTC')->startOfDay();
        if ($day->lt($start)) {
            return false;
        }
        if ($this->ends_on && $day->gt(CarbonImmutable::parse($this->ends_on->toDateString(), 'UTC')->startOfDay())) {
            return false;
        }

        return ((int) $start->diffInDays($day)) % max(1, $this->interval_days) === 0;
    }
}
