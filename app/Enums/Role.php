<?php

namespace App\Enums;


enum Role: string
{
    case Lawyer = 'lawyer';
    case Client = 'client';
    case Admin = 'admin';
}
