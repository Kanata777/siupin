<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TempatRapat extends Model
{
    protected $table = 'tempat_rapat';
    protected $primaryKey = 'id_tempat';
    public $timestamps = false;
    protected $guarded = [];
}