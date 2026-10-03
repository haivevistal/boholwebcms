<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class UserMeta extends Model
{
    protected $table = 'user_meta';

    public $timestamps = false;

    protected $fillable = ['user_id', 'meta_key', 'meta_value'];
}
