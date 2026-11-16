<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class KelompokRapatDetail extends Model
{
    protected $table = 'kelompok_rapat_detail';
    protected $primaryKey = 'id_detail';
    public $timestamps = false;
    protected $guarded = [];
}