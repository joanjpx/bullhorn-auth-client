<?php

use Illuminate\Database\Capsule\Manager as Capsule;

$capsule = new Capsule;

$capsule->addConnection([
    'driver'    => 'sqlsrv',
    'host'      => 'DESKTOP-RA0E45Q',
    'database'  => 'JobAdderBackup3677',
    'username'  => '',
    'password'  => '',
],'mssql');

// Make this Capsule instance available globally via static methods... (optional)
$capsule->setAsGlobal();

// Setup the Eloquent ORM... (optional; unless you've used setEventDispatcher())
$capsule->bootEloquent();