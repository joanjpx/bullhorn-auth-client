<?php

namespace Models;

use Illuminate\Database\Eloquent\Model;

class ModelSubmission extends Model
{
    protected $table = "JobApplication";
    public $timestamps = false;
    protected $connection = "mssql";
}