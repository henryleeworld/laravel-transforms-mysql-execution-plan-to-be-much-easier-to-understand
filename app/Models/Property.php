<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Property extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'city_id',
        'address_street',
        'address_postcode',
        'lat',
        'long',
        'bookings_avg_rating',
    ];

    public function apartments()
    {
        return $this->hasMany(Apartment::class);
    }

    public function address(): Attribute
    {
        return new Attribute(
            get: fn () => $this->address_street .', ' . $this->address_postcode . ', ' . $this->city->name
        );
    }

    public function bookings()
    {
        return $this->hasManyThrough(Booking::class, Apartment::class);
    }

    public function city()
    {
        return $this->belongsTo(City::class);
    }
}
