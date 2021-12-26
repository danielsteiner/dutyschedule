<?php
set_time_limit(600);
ini_set('max_execution_time', '600');
ini_set('memory_limit', '1G');
require __DIR__ . '/helpers.php';

use Monolog\ErrorHandler;
use Monolog\Handler\StreamHandler;
use Monolog\Logger;
use Illuminate\Database\Capsule\Manager as Capsule;
use Dotenv\Dotenv;

$requestingUser = "";

$log = new Logger('wrk-dutyschedule');
$error = new Logger("wrk-dutyschedule.error");

$log->pushHandler(new StreamHandler(__DIR__."/../logs/calendar_".date('y-m-d').".log", Logger::INFO));
$error->pushHandler(new StreamHandler(__DIR__."/../logs/errors_".date('y-m-d').".log", Logger::ERROR));
ErrorHandler::register($error);

$dotenv = Dotenv::create(__DIR__."/../");
$dotenv->load();


if(env("APP_DEBUG")) {
    $whoops = new \Whoops\Run;
    $whoops->prependHandler(new \Whoops\Handler\PrettyPageHandler);
    $whoops->register(); 
} else {
    set_error_handler('productionErrorHandler');     
}

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
    $error->error($ex);
}
if(!function_exists("dd")) {
    function dd($var){
        dump($var);
        die();
    }
}

function productionErrorHandler(int $errNo, string $errMsg, string $file, int $line) {
    $GLOBALS["error"]->error($errMsg." in file ".$file." on line ".$line);
    $messages = [
        "Ups... Sieht so aus, als ob jemand die Blaulichtflüssigkeit nicht nachgefüllt hätte. Wir arbeiten daran - bitte versuch es später erneut!",
        "Ups... Sieht so aus, als ob der Praktikant die Rettungskette vergessen hätte. Wir holen sie schnell, versuch es bitte etwas später erneut!"
    ];
    echo '<!DOCTYPE html>
    <html>
      <head>
        <title>Ups... Es ist ein Fehler passiert</title>
        <meta http-equiv="content-type" content="text/html; charset=utf-8" >
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <style type="text/css">
          body {
            background-color: #eee;
          }
          body, h1, p {
            font-family: "Helvetica Neue", "Segoe UI", Segoe, Helvetica, Arial, "Lucida Grande", sans-serif;
            font-weight: normal;
            margin: 0;
            padding: 0;
            text-align: center;
          }
          .container {
            margin-left:  auto;
            margin-right:  auto;
            margin-top: 7px;
            max-width: 1170px;
            padding-right: 15px;
            padding-left: 15px;
          }
          .row:before, .row:after {
            display: table;
            content: " ";
          }
          .col-md-6 {
            width: 50%;
          }
          .col-md-push-3 {
            margin-left: 25%;
          }
          h1 {
            font-size: 48px;
            font-weight: 300;
            margin: 0 0 20px 0;
          }
          .lead {
            font-size: 21px;
            font-weight: 200;
            margin-bottom: 20px;
          }
          p {
            margin: 0 0 10px;
          }
          a {
            color: #3282e6;
            text-decoration: none;
          }
          /* Copyright (c) 2010, Yahoo! Inc. All rights reserved. Code licensed under the BSD License: http://developer.yahoo.com/yui/license.html */
          html{color:#000;background:#FFF;}body,div,dl,dt,dd,ul,ol,li,h1,h2,h3,h4,h5,h6,pre,code,form,fieldset,legend,input,textarea,p,blockquote,th,td{margin:0;padding:0;}table{border-collapse:collapse;border-spacing:0;}fieldset,img{border:0;}address,caption,cite,code,dfn,em,strong,th,var{font-style:normal;font-weight:normal;}li{list-style:none;}caption,th{text-align:left;}h1,h2,h3,h4,h5,h6{font-size:100%;font-weight:normal;}q:before,q:after{content:\'\';}abbr,acronym{border:0;font-variant:normal;}sup{vertical-align:text-top;}sub{vertical-align:text-bottom;}input,textarea,select{font-family:inherit;font-size:inherit;font-weight:inherit;}input,textarea,select{*font-size:100%;}legend{color:#000;}
          html { background: #eee; padding: 10px }
          img { border: 0; }
          #sf-resetcontent { width:970px; margin:0 auto; }
          .sf-reset { font: 11px Verdana, Arial, sans-serif; color: #333 }
          .sf-reset .clear { clear:both; height:0; font-size:0; line-height:0; }
          .sf-reset .clear_fix:after { display:block; height:0; clear:both; visibility:hidden; }
          .sf-reset .clear_fix { display:inline-block; }
          .sf-reset * html .clear_fix { height:1%; }
          .sf-reset .clear_fix { display:block; }
          .sf-reset, .sf-reset .block { margin: auto }
          .sf-reset abbr { border-bottom: 1px dotted #000; cursor: help; }
          .sf-reset p { font-size:14px; line-height:20px; color:#868686; padding-bottom:20px }
          .sf-reset strong { font-weight:bold; }
          .sf-reset a { color:#6c6159; cursor: default; }
          .sf-reset a img { border:none; }
          .sf-reset a:hover { text-decoration:underline; }
          .sf-reset em { font-style:italic; }
          .sf-reset h1, .sf-reset h2 { font: 20px Georgia, "Times New Roman", Times, serif }
          .sf-reset .exception_counter { background-color: #fff; color: #333; padding: 6px; float: left; margin-right: 10px; float: left; display: block; }
          .sf-reset .exception_title { margin-left: 3em; margin-bottom: 0.7em; display: block; }
          .sf-reset .exception_message { margin-left: 3em; display: block; }
          .sf-reset .traces li { font-size:12px; padding: 2px 4px; list-style-type:decimal; margin-left:20px; }
          .sf-reset .block { background-color:#FFFFFF; padding:10px 28px; margin-bottom:20px;
                             -webkit-border-bottom-right-radius: 16px;
                             -webkit-border-bottom-left-radius: 16px;
                             -moz-border-radius-bottomright: 16px;
                             -moz-border-radius-bottomleft: 16px;
                             border-bottom-right-radius: 16px;
                             border-bottom-left-radius: 16px;
                             border-bottom:1px solid #ccc;
                             border-right:1px solid #ccc;
                             border-left:1px solid #ccc;
          }
          .sf-reset .block_exception { background-color:#ddd; color: #333; padding:20px;
                                       -webkit-border-top-left-radius: 16px;
                                       -webkit-border-top-right-radius: 16px;
                                       -moz-border-radius-topleft: 16px;
                                       -moz-border-radius-topright: 16px;
                                       border-top-left-radius: 16px;
                                       border-top-right-radius: 16px;
                                       border-top:1px solid #ccc;
                                       border-right:1px solid #ccc;
                                       border-left:1px solid #ccc;
                                       overflow: hidden;
                                       word-wrap: break-word;
          }
          .sf-reset a { background:none; color:#868686; text-decoration:none; }
          .sf-reset a:hover { background:none; color:#313131; text-decoration:underline; }
          .sf-reset ol { padding: 10px 0; }
          .sf-reset h1 { background-color:#FFFFFF; padding: 15px 28px; margin-bottom: 20px;
                         -webkit-border-radius: 10px;
                         -moz-border-radius: 10px;
                         border-radius: 10px;
                         border: 1px solid #ccc;
          }
        </style>
      </head>
    
      <body>
        <div class="container text-center" id="error">
          <svg height="100" width="100">
          <polygon points="50,25 17,80 82,80" stroke-linejoin="round" style="fill:none;stroke:#ff8a00;stroke-width:8" />
          <text x="42" y="74" fill="#ff8a00" font-family="sans-serif" font-weight="900" font-size="42px">!</text>
          </svg>
          <div class="row">
            <div class="col-md-6 col-md-push-3">
              <p class="lead">'.$messages[rand(0, count($messages)-1)].'</p>
            </div>
          </div>
        </div>
      </body>
    </html>';
    die();
}