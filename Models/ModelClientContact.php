<?php

namespace Models;

use Illuminate\Database\Eloquent\Model;

class ModelClientContact extends Model
{
    protected $table = "centro_costos";

    public $timestamps = false;

    protected $connection = "mssql";
}