<?php
require "../boot/bootstrap.php";

use Models\Logs;
use PHPHtmlParser\Dom;

$client = new GuzzleHttp\Client([
    'base_uri' => env("KUFER_URL"),
    "verify" => false,
]);
$jar = new \GuzzleHttp\Cookie\CookieJar;

$dateStart = date("d.m.Y", strtotime("-1 years", strtotime("first day of january")));
$dateEnd = date("d.m.Y", strtotime("+1 years", strtotime("last day of december")));
if(array_key_exists("start", $_GET)){
    $dateStart = $_GET["start"];
}
if(array_key_exists("end", $_GET)){
    $dateEnd = $_GET["end"];
}

// if (!isset($_SERVER['PHP_AUTH_USER'])) {
//     if (!array_key_exists("auth", $_GET)) {
//         header('WWW-Authenticate: Basic realm="My Realm"');
//         header('HTTP/1.0 401 Unauthorized');
//         echo "Username oder Passwort sind nicht angegeben, bitte melden Sie sich erst an";
//         exit;
//     } else {
//         $auth = explode(":", base64_decode($_GET["auth"]));
//         $username = $auth[0];
//         $password = $auth[1];
//     }
// } else {
//     $username = $_SERVER['PHP_AUTH_USER'];
//     $password = $_SERVER['PHP_AUTH_PW'];
// }
// if (!isset($username) && !isset($password)) {
//     header('Location: login.php');
// }
$kufer_username = "NDISSAUERBA";
$kufer_password = "Mar1aJac0b!";

$debug = array_key_exists("debug", $_GET) ? $_GET["debug"] : false;

$log->info("Request for " . $kufer_username." started");

$base_uri = $client->getConfig("base_uri");
try {
    $auth_request = $client->request('GET', env("KUFER_URL"), ['allow_redirects' => true, 'cookies' => $GLOBALS["jar"]]);

    $auth_body = (string)$auth_request->getBody();
    $dom = new Dom;
    $dom->loadStr($auth_body);
    $action = $dom->find('#login')->getAttribute("action");
    $tmp = explode("?", $action); 
    $xsrf = explode("=", $tmp[1]);
    

    $postData = [
      "Kennwort" =>  $kufer_username,
      "Passwort" => $kufer_password,
      "anmelden.x" => rand(0,120),
      "anmelden.y" => rand(0,30),
      "loginFormularVersendet" => 1,
      "postId" => 1
    ];
    $loginURL = "https://kursbuchung.wrk.at/fileadmin/kuferweb/webtools/usertools.php?xsrf=".$xsrf[1]; 
    $login_request = $client->request('POST', $loginURL, ['form_params' => $postData, 'allow_redirects' => true, 'cookies' => $GLOBALS["jar"]]);
    
    $my_course_url = "https://kursbuchung.wrk.at/fileadmin/kuferweb/webtools/usertools.php?tool_id=1&toolsection_id=1&einstieg=1&markerAction=loescheMarkierungen";
    $course_request = $client->request('GET', $my_course_url, ['allow_redirects' => true, 'cookies' => $GLOBALS["jar"]]);
    $course_response = (string)$course_request->getBody();
    $dom->loadStr($course_response);
    $course_table = $dom->find('#main_content');
    $dom->loadStr($course_table);
    $courses = $dom->find(".content_zeile");
    foreach($courses as $course) {
      $dom->loadStr($course);
      $titel = $dom->find('.kurs_kurzbez_titel')->innerHtml;
      $id = $dom->find('.kurs_id')->innerHtml;
      $ort = $dom->find('.kurs_ort')[1]->innerHtml;
      if(strpos($ort, "ABZ")!==false) {
        $room = explode(";", $ort)[1]; 
        $location = [
          "label" => "Wiener Rotes Kreuz - Ausbildungszentrum"
        ];
        if(is_numeric($room)) {
          $floor = substr($room, 0,1);
          $location['address'] = "Safargasse 4, 1030 Wien, ".$floor. ". Stock, Raum " .$room;
        } else {
          $location['address'] = "Safargasse 4, 1030 Wien, Raum " .$room;
        }
      } else {
        $tmp = explode(";", $ort);
        $location = [
          "label" => $tmp[0],
          "address" => $tmp[1]
        ];
      }
      $datum = $dom->find('.kurs_beginn_ende_komplett')->innerHtml;
      $dateparts = explode(",", $datum); 
      
      $date = trim($dateparts[1]);
      $times = trim($dateparts[2]);
      
      if(count($dateparts) > 3) {
        $startdate = $date;
        $enddate = trim($dateparts[3]);
        $starttime = trim($dateparts[2]);
        $starttime = substr($starttime, 0,5);
        $endtime = str_replace(" Uhr", "", trim($dateparts[4]));
      } else {
        $times = explode(" bis ", $times); 
        $startdate = $date;
        $enddate = $date;
        $starttime = str_replace(" Uhr", "", $times[0]);
        $endtime = str_replace(" Uhr", "", $times[1]);
      }
      $event = [
        "title" => $titel, 
        "location" => $location,
        "id" => $id,
        "date" => [
          "start_date" => $startdate,
          "start_time" => $starttime,
          "end_date" => $enddate,
          "end_time" => $endtime
        ]
      ];
      dump($event);
    }


} catch (GuzzleHttp\Exception\TooManyRedirectsException $rex) {
    print_r($rex);
}
