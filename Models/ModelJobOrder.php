<?php

namespace Models;

use Illuminate\Database\Eloquent\Model;

class ModelJobOrder extends Model
{
    protected $table = "JobOrder";
    public $timestamps = false;
    protected $connection = "mssql";
}