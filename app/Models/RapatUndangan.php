<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class RapatUndangan extends Model
{
    public const PENDING  = 'pending';
    public const TERKIRIM = 'terkirim'; 
    public const GAGAL    = 'gagal';

    protected $table = 'rapat_undangan';
    protected $guarded = [];
    protected $casts = ['terkirim_pada' => 'datetime'];
}