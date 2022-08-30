<?php

namespace Models;

use Illuminate\Database\Eloquent\Model;

class ModelCandidate extends Model
{
    protected $table = "Candidate";

    public $timestamps = false;

    protected $connection = "mssql";
}