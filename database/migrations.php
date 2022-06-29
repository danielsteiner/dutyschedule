<?php
require __DIR__. "/../boot/bootstrap.php";

use Illuminate\Database\Capsule\Manager as Capsule;
try {
    Capsule::schema()->create('logs', function ($table) {
    $table->increments('id');
    $table->string('key');
    $table->longText('html');
    $table->longText('vevent');
    $table->timestamps();
    });
} catch(PDOExcepton $ex) {
    $log->error($ex);
} catch(Exception $e) {
    $log->error($e);
}