<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class InputStock extends Model
{
    public $timestamps = false;

    protected $table = 'input_stock';

    protected $primaryKey = 'input_stock_id';

    protected $guarded = [];
}