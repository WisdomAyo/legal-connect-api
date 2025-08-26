<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Specialization extends Model
{
    use HasFactory;

    protected $fillable = ['name'];

    public function lawyers()
    {
        return $this->belongsToMany(LawyerProfile::class, 'lawyer_specialization');
    }
}
