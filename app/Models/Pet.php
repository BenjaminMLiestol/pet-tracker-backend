<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Pet extends Model
{
    use HasFactory, SoftDeletes;

    /** Relations whose records are soft-deleted together with the pet. */
    private const CASCADE = ['feedings', 'walks', 'baths', 'weights', 'vetVisits', 'vaccinations', 'parasiteTreatments'];

    protected static function booted(): void
    {
        static::deleting(function (Pet $pet) {
            if ($pet->isForceDeleting()) {
                return; // DB foreign keys cascade hard deletes
            }
            foreach (self::CASCADE as $relation) {
                $pet->{$relation}()->delete();
            }
        });

        static::restoring(function (Pet $pet) {
            foreach (self::CASCADE as $relation) {
                $pet->{$relation}()->onlyTrashed()->restore();
            }
        });
    }

    protected $appends = ['age'];

    protected $casts = ['date_of_birth' => 'date'];

    protected $fillable = ['name', 'breed', 'date_of_birth'];

    /** Age in whole years, derived from date_of_birth. */
    protected function age(): Attribute
    {
        return Attribute::get(fn () => $this->date_of_birth?->age);
    }

    public function scopeOwnedBy($query, User $user)
    {
        return $query->whereHas('users', fn ($q) => $q->where('users.id', $user->id));
    }

    public function users()
    {
        return $this->belongsToMany(User::class)->withTimestamps();
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

    public function vetVisits()
    {
        return $this->hasMany(VetVisit::class);
    }

    public function vaccinations()
    {
        return $this->hasMany(Vaccination::class);
    }

    public function parasiteTreatments()
    {
        return $this->hasMany(ParasiteTreatment::class);
    }
}
