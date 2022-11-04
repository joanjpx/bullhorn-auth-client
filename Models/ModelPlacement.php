<?php

namespace Models;

use Illuminate\Database\Eloquent\Model;

class ModelPlacement extends Model
{
    protected $table = "Placement";
    public $timestamps = false;
    protected $connection = "mssql";
}