<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Event extends Model
{
    //
    protected $fillable = ['title', 'day', 'event_date', 'end_date', 'description', 'location'];

    // Add this block
    protected $casts = [
        'event_date' => 'date',
        'end_date' => 'date',
    ];

    public function attendances()
    {
        return $this->hasMany(Attendance::class);
    }

    public function users()
    {
        return $this->belongsToMany(User::class);
    }
    public function getStatusAttribute()
{
    $today = now()->startOfDay();
    $start = \Carbon\Carbon::parse($this->event_date)->startOfDay();
    
    // If no end_date is set, we treat it as a 1-day event
    $end = $this->end_date 
           ? \Carbon\Carbon::parse($this->end_date)->startOfDay() 
           : $start;

    if ($today->lt($start)) {
        return 'upcoming';
    }

    // If today is between start and end (inclusive), it is active
    if ($today->lte($end)) {
        return 'active';
    }

    return 'closed';
}
}