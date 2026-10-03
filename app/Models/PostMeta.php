<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PostMeta extends Model
{
    protected $table = 'post_meta';

    public $timestamps = false;

    protected $fillable = ['post_id', 'meta_key', 'meta_value'];
}
