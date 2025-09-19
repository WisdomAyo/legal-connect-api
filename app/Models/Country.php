<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Country extends Model
{
    protected $fillable = ['name', 'country_code', 'flag', 'dialing_code', 'is_active'];

}
