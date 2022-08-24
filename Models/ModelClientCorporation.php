<?php

namespace Models;

use Illuminate\Database\Eloquent\Model;

class ModelClientCorporation extends Model
{
    protected $table = "Company";

    public $timestamps = false;

    protected $connection = "mssql";
}