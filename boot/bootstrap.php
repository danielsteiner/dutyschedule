<?php

require __DIR__ . '/helpers.php';

use Monolog\ErrorHandler;
use Monolog\Handler\StreamHandler;
use Illuminate\Database\Capsule\Manager as Capsule;
use Dotenv\Dotenv;
use Monolog\Logger;


$log = new Logger('wrk-dutyschedule');
$log->pushHandler(new StreamHandler(__DIR__."/../logs/calendar_".date('y-m-d').".log", Logger::INFO));

$dotenv = Dotenv::create(__DIR__."/../");
$dotenv->load();


$whoops = new \Whoops\Run;
$whoops->prependHandler(new \Whoops\Handler\PrettyPageHandler);
$whoops->register();

$capsule = new Capsule;
 
$capsule->addConnection([
    "driver" => "mysql",
    "host" => env('DB_HOST'),
    "database" => env('DB_DATABASE'),
    "username" => env('DB_USER'),
    "password" => env('DB_PASS'),
]);

try{
    $capsule->setAsGlobal();
    $capsule->bootEloquent();
    
} catch(PDOException $ex) {
    $log->error($ex);
}


function dd($var){
    dump($var);
    die();
}