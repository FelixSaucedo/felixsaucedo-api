<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\ContactFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Contact extends Model
{
    /** @use HasFactory<ContactFactory> */
    use HasFactory;

    protected $fillable = ['name', 'email', 'subject', 'message', 'ip_address', 'user_agent'];

    protected $attributes = ['status' => 'unread'];

    protected $hidden = ['ip_address', 'user_agent'];
}
