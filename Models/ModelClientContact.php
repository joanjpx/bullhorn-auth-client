<?php

namespace Models;

use Illuminate\Database\Eloquent\Model;

class ModelClientContact extends Model
{
    protected $table = "Contact";

    public $timestamps = false;

    protected $connection = "mssql";
}