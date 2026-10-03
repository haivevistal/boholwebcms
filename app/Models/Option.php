<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Option extends Model
{
    public $timestamps = false;

    protected $fillable = ['name', 'value', 'autoload'];

    protected function casts(): array
    {
        return ['autoload' => 'boolean'];
    }
}
