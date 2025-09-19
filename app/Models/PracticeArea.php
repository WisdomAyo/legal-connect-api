<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PracticeArea extends Model
{
    use HasFactory;

    protected $fillable = ['name'];

    public function lawyers()
    {
        return $this->belongsToMany(LawyerProfile::class, 'lawyer_practice_area');
    }
}
