<?php
require __DIR__ . '/bootstrap.php';

use PHPHtmlParser\Dom;
use Dotenv\Dotenv;

$employees = \Models\Employee::whereFetched(0)->get();
$jar = new \GuzzleHttp\Cookie\CookieJar;
$dotenv = Dotenv::create(__DIR__."/../");
$dotenv = Dotenv::create(__DIR__."/../", '.secrets');
$dotenv->load();

dump(count($employees) ." Employees to be fetched...\r\n");
$i = 1; 
foreach($employees as $employee){
    if(($i % 100) == 0) {
        dump($i/100 . " Employees fetched...");
    }
    dump("Fetching data for ".$employee->name);
    if(strpos($employee->name, "Gast") !== false) {
        continue; 
    }
    $emp_response = null; 
    try{
        $client = new GuzzleHttp\Client();
        $emp_response = $client->request('GET', $employee->url, ['auth' => [env('FW_NIU_USER'), env('FW_NIU_PASS')], 'allow_redirects' => true, 'cookies' => $GLOBALS["jar"]]);
        $emp_response = $client->request('GET', $employee->url, ['auth' => [env('FW_NIU_USER'), env('FW_NIU_PASS')], 'allow_redirects' => true, 'cookies' => $GLOBALS["jar"]]);
        $emp_body = (string) $emp_response->getBody();
        $dom = new Dom;
        $dom->loadStr($emp_body);

        $nameField = strip_tags($dom->find('#ctl00_main__shortEmpl_EmployeeName')->innerHtml);
        $bracketpos = strpos($nameField, "(");
        $name = substr($nameField, 0, $bracketpos-1);
        $dnrs = explode(", ", substr($nameField, $bracketpos+1, -1));
        $employee->name = $name; 
        $employee->dnrs = $dnrs;
        $employee->url = $employee->url;
        $employee->fetched = 1; 
        $employee->save();
    } catch(Exception $ex) {
        //propably HA, thus not fetchable at the moment. 
        dump("Propably HA, can't fetch this user. Skipping...");
    }
}