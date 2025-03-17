<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Message extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'message',
        'you',
    ];

    protected $casts = [
        'created_at' => 'datetime:d.m.Y H:i',
    ];
}