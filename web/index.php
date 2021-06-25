<?php
require "../boot/bootstrap.php";

use Models\Logs;
use PHPHtmlParser\Dom;
use Monolog\ErrorHandler;
use Monolog\Handler\StreamHandler;
use Monolog\Logger;
$client = new GuzzleHttp\Client([
    'base_uri' => env("DATASOURCE_URL"),
    "verify" => false,
]);
$jar = new \GuzzleHttp\Cookie\CookieJar;
$is_lba = false; 


$dateStart = date("d.m.Y", strtotime("-1 years", strtotime("first day of january")));
$dateEnd = date("d.m.Y", strtotime("+1 years", strtotime("last day of december")));
if(array_key_exists("start", $_GET)){
    $dateStart = $_GET["start"];
}
if(array_key_exists("end", $_GET)){
    $dateEnd = $_GET["end"];
}

if (!isset($_SERVER['PHP_AUTH_USER'])) {
    if (array_key_exists("auth", $_GET)) {
        $cipher = "aes-128-ctr";
        
        if (in_array($cipher, openssl_get_cipher_methods())) {
            $ivlen = openssl_cipher_iv_length($cipher);
            $iv = openssl_random_pseudo_bytes($ivlen);
            $decode = base64_decode($_GET["auth"]);
            
            if(strlen($decode) < 50) {
                $old_auth = true; 
                $a = explode(":", $decode);
                $auth = [
                    "username" => $a[0],
                    "password" => $a[1]
                ];
            } else {

                $iv = substr($decode, 0, openssl_cipher_iv_length($cipher));
                $ciphertext = substr($decode, openssl_cipher_iv_length($cipher));
                $auth = openssl_decrypt(
                    $ciphertext,
                    $cipher, 
                    base64_decode(env('APP_KEY')), 
                    0, 
                    $iv
                );
                $auth = json_decode($auth, true);
            }
            if(array_key_exists("is_lba", $auth)){
                $kufer_username = $auth["kufer_username"];
                $kufer_password = $auth["kufer_password"];
                $is_lba = true;
            } 
            $username = $auth["username"];
            $password = $auth["password"];
        }
    } else {
        header('WWW-Authenticate: Basic realm="WRK Dienstplanexport"');
        header('HTTP/1.0 401 Unauthorized');
        header('Location: login.php');
        exit;
    }
} else {
    $username = $_SERVER['PHP_AUTH_USER'];
    $password = $_SERVER['PHP_AUTH_PW'];
}

if (!isset($username) && !isset($password)) {
    header('Location: login.php');
    die();
}
$GLOBALS["requestingUser"] = $username;
$GLOBALS["eventlog"] = new Logger('wrk-dutyschedule-events');
$GLOBALS["eventlog"]->pushHandler(new StreamHandler(__DIR__."/../logs/events_".$GLOBALS["requestingUser"]."_".date('y-m-d').".log", Logger::INFO));

if(!checkCredentials($username, $password)) {
    header('Location: login.php');
    die();
}


$debug = env("APP_DEBUG");

$log->info("Request for " . $username." started");

$base_uri = $client->getConfig("base_uri");
$header_path = "/Kripo/Header.aspx";
$niu_today_path = "/Kripo/Today/Today.aspx";
$statistics_path = "/Kripo/DutyRoster/EmployeeDutyStatistic.aspx?EmployeeNumberID=";
$course_path = "/Kripo/Kufer/SearchCourse.aspx";

try {
    $auth = $client->request('GET', $base_uri, ['auth' => [$GLOBALS["username"], $GLOBALS["password"]], 'allow_redirects' => true, 'cookies' => $GLOBALS["jar"]]);
    $header_response = $client->request('GET', $header_path, ['auth' => [$GLOBALS["username"], $GLOBALS["password"]], 'allow_redirects' => true, 'cookies' => $GLOBALS["jar"]]);
    $header = (string)$header_response->getBody();

    $dom = new Dom;
    $dom->loadStr($header);

    $userlink = $dom->find('#userName');
    //$name = $userlink->innerHtml;
    $dnrs = explode(", ", substr($userlink->innerHtml, strpos($userlink->innerHtml, "(") + 1,-1));
    $name = substr($userlink->innerHtml, 0, strpos($userlink->innerHtml, "("));

    $dienstnummern = ["primary" => "", "secondary" => []];
    foreach ($dnrs as $key => $dnr) {
        $dnr = trim($dnr);
        $isPrimary = false;
        switch (strlen($dnr)) {
            case 1:
                //LV Gast
            case 2:
                //LV HA / FW
                if ($dnr > 1 && $dnr <= 19) {
                    //LV FW Spezial
                    $isPrimary = true;
                } else if ($dnr === 20) {
                    //HA GL
                    $isPrimary = true;
                } else if ($dnr >= 21 && $dnr <= 29) {
                    //LV FW Spezial
                    $isPrimary = true;
                } else if ($dnr === 30) {
                    //HA GL
                    $isPrimary = true;
                } else if ($dnr >= 31 && $dnr <= 51) {
                    //LV FW Spezial
                    $isPrimary = true;
                } else if ($dnr >= 52 && $dnr <= 799) {
                    //HA Administrativ
                    $isPrimary = true;
                } else if ($dnr >= 800 && $dnr <= 899) {
                    //Malteser / Praktikanten
                    $isPrimary = true;
                } else if ($dnr >= 900 && $dnr <= 999) {
                    //Ärtze
                    $isPrimary = true;
                }
                break;
            case 3:
                //HA Admin, Ärzte
                if ($dnr >= 52 && $dnr <= 799) {
                    //HA Administrativ
                    $isPrimary = true;
                } else if ($dnr >= 800 && $dnr <= 899) {
                    //Malteser / Praktikanten
                    $isPrimary = true;
                } else if ($dnr >= 900 && $dnr <= 999) {
                    //Ärtze
                    $isPrimary = true;
                }
                break;
            case 4:
                //BS, HA RD, JRK, GSD
                switch (substr($dnr, 0, 1)) {
                    case 1:
                        //BS West
                        $isPrimary = true;
                        break;
                    case 2:
                        //BS VS
                        $isPrimary = true;
                        break;
                    case 3:
                        //BS BVS
                        $isPrimary = true;
                        break;
                    case 4:
                        //JRK (Alt?)
                        $isPrimary = true;
                        break;
                    case 5:
                        //GSD
                        $isPrimary = true;
                        break;
                    case 6:
                        //RD HA
                        $isPrimary = true;
                        break;
                    case 7:
                        //BS DDL
                        $isPrimary = true;
                        break;
                    case 8:
                        //BS Nord
                        $isPrimary = true;
                        break;
                }
                break;
            case 5:
                switch (substr($dnr, 0, 1)) {
                    case 1:
                        //Jugendgruppen
                        $isPrimary = true;
                        break;
                    case 2:
                        //AN!
                        $isPrimary = true;
                        break;
                    case 3:
                        //ABZ LBA
                        $isPrimary = false;
                        break;
                    case 6:
                        // RD?
                        $isPrimary = true;
                        break;
                    case 7:
                        //70000-71999 GKW
                        //78000-78999 FSJ
                        $isPrimary = true;
                        break;
                    case 8:
                        //84000-84999 STA MA
                        $isPrimary = true;
                        break;
                    case 9:
                        //ZDL
                        $isPrimary = true;
                        break;
                }
                break;
            case 6:
                $isPrimary = true;
                break;
            case 7:
                $isPrimary = true;
                break;
        }
        if ($isPrimary) {
            if (empty($dienstnummern["primary"])) {
                $dienstnummern["primary"] = $dnr;
            } else {
                $dienstnummern["secondary"][] = $dnr;
            }
        } else {
            $dienstnummern["secondary"][] = $dnr;
        }
    }
    $dnrs = $dienstnummern;
    // dd($dnrs);
    $userid = explode("=", $userlink->getAttribute('href'))[1];

    // Grabbing Courses
    $courses_response = $client->request('GET', $course_path, ['auth' => [$GLOBALS["username"], $GLOBALS["password"]], 'allow_redirects' => true, 'cookies' => $GLOBALS["jar"]]);
    $courses_response = $client->request('GET', $course_path, ['auth' => [$GLOBALS["username"], $GLOBALS["password"]], 'allow_redirects' => true, 'cookies' => $GLOBALS["jar"]]);

    $dom = new Dom;
    $dom->loadStr((string)$courses_response->getBody());
    $eventvalidation = $dom->find('#__EVENTVALIDATION')->getAttribute("value");
    $keypostfix = $dom->find('#__KeyPostfix')->getAttribute("value");
    $postData = [
        "__EVENTTARGET" => "ctl00\$main\$m_Search",
        "__EVENTARGUMENT" => "",
        "__KeyPostfix" => "",
        "__VIEWSTATE" => "",
        "__EVENTVALIDATION" => "",
        "ctl00\$main\$m_From\$m_Textbox" => $dateStart,
        "ctl00\$main\$m_To\$m_Textbox" => $dateEnd,
        "ctl00\$main\$m_CourseID" => "",
        "ctl00\$main\$m_Courses" => "20720106",
        "ctl00\$main\$m_CourseYear" => date('Y'),
        "ctl00\$main\$m_Employee" => "AAAAAAAAAAAAAAAAAAAAAA==",
        "ctl00\$main\$m_EmployeeNumber" => $dnrs["primary"],
        "ctl00\$main\$m_CourseName" => "",
        "ctl00\$main\$m_ELearningCourse" => "",
        "ctl00\$main\$m_SortOrder" => "Kursdatum",
        "ctl00\$main\$m_Options\$5" => "on",
        "ctl00\$main\$m_Options\$6" => "on",
        "ctl00\$main\$m_Options\$7" => "on",
        "ctl00\$main\$m_Options\$3" => "on",
        "ctl00\$main\$m_Options\$8" => "on",
    ];
    $postData['__KeyPostfix'] = $keypostfix;
    $postData['__EVENTVALIDATION'] = $eventvalidation;
    
    $courses_response = $client->request('POST', $course_path, ['form_params' => $postData, 'auth' => [$GLOBALS["username"], $GLOBALS["password"]], 'allow_redirects' => true, 'cookies' => $GLOBALS["jar"]]);
    $courses = (string)$courses_response->getBody();
    if(strpos($courses, "Anmeldestatus") === false) {
        $cc_response = $client->request('GET', 'https://niu.wrk.at/Kripo/external/ControlCenterHead.aspx?strip=true',  ['auth' => [$GLOBALS["username"], $GLOBALS["password"]], 'allow_redirects' => true, 'cookies' => $GLOBALS["jar"]]); 
        $cc_response = $client->request('GET', 'https://niu.wrk.at/Kripo/external/ControlCenterHead.aspx?strip=true',  ['auth' => [$GLOBALS["username"], $GLOBALS["password"]], 'allow_redirects' => true, 'cookies' => $GLOBALS["jar"]]); 
        $control_center = (string)$cc_response->getBody();
        $ccenterdom = $dom->loadStr($control_center);
        $lvstat_link = $ccenterdom->find('#m_lbtLVStatistik')[0]->getAttribute('href');
        $employee_id = explode("=", explode('?', $lvstat_link)[1])[1];
        $courses_response = $client->request('GET', 'https://niu.wrk.at/Kripo/Kufer/SearchCourse.aspx?EmployeeId='.$employee_id, ['auth' => [$GLOBALS["username"], $GLOBALS["password"]], 'allow_redirects' => true, 'cookies' => $GLOBALS["jar"]]);
        $dom = new Dom;
        $dom->loadStr((string)$courses_response->getBody());
        $eventvalidation = $dom->find('#__EVENTVALIDATION')->getAttribute("value");
        $keypostfix = $dom->find('#__KeyPostfix')->getAttribute("value");

        $postData = [
            "__EVENTTARGET" => "ctl00\$main\$m_Search",
            "__EVENTARGUMENT" => "",
            "__KeyPostfix" => "",
            "__VIEWSTATE" => "",
            "__EVENTVALIDATION" => "",
            "ctl00\$main\$m_From\$m_Textbox" => $dateStart,
            "ctl00\$main\$m_To\$m_Textbox" => $dateEnd,
            "ctl00\$main\$m_CourseName" => "",
            "ctl00\$main\$m_ELearningCourse" => "",
            "ctl00\$main\$m_SortOrder" => "Kursdatum",
            "ctl00\$main\$m_Options\$5" => "on",
            "ctl00\$main\$m_Options\$6" => "on",
            "ctl00\$main\$m_Options\$7" => "on",
            "ctl00\$main\$m_Options\$3" => "on",
            "ctl00\$main\$m_Options\$8" => "on",
        ];

        $postData['__KeyPostfix'] = $keypostfix;
        $postData['__EVENTVALIDATION'] = $eventvalidation;

        $courses_response = $client->request('POST', 'https://niu.wrk.at/Kripo/Kufer/SearchCourse.aspx?EmployeeId='.$employee_id,  ['form_params' => $postData, 'auth' => [$GLOBALS["username"], $GLOBALS["password"]], 'allow_redirects' => true, 'cookies' => $GLOBALS["jar"]]); 
        $courses_response = $client->request('POST', 'https://niu.wrk.at/Kripo/Kufer/SearchCourse.aspx?EmployeeId='.$employee_id,  ['form_params' => $postData, 'auth' => [$GLOBALS["username"], $GLOBALS["password"]], 'allow_redirects' => true, 'cookies' => $GLOBALS["jar"]]); 
        $courses = (string)$courses_response->getBody();
        // echo $courses; 
        // die();
    } 
    $dom->loadStr($courses);
    $courseTable = $dom->loadStr($dom->find('#ctl00_main_m_CourseList__CourseTable'));

    $courses = $courseTable->find('tr');
    
    $allCourses = [];
    foreach ($courses as $k => $course) {
        if (strpos($course, "MessageHeaderCenter") === false && strpos($course, "MessageBodySeperator") === false) {
            $courseparts = $dom->loadStr($course)->find('td');
            $cts = [
                "SAN - Weiterbildung - ",
                "SAN - Fortbildung - ",
                "Webinar - ",
                "LBA - Ausbildung - ",
                "SEF - Ausbildung - ",
                "FKW - Ausbildung - ",
                "BAS - Ausbildung - ",
                "FKR - Ausbildung - ",
                "KHD - Ausbildung - ",
                "WRK - Ausbildung - ",
                "SEF - Fortbildung - ",
                "FSD - Ausbildung -  ",
                "FSD - Fortbildung -  ",
            ];
            $replaceval = ["","","","","","","","","","","","",""];
            $title = str_replace($cts, $replaceval, strip_tags($courseparts[1]));
            
            $c = [
                "course_id" => strip_tags($courseparts[0]),
                "title" => $title,
                "start" => strip_tags($courseparts[2]),
                "end" => strip_tags($courseparts[3]),
                "location" => strip_tags($courseparts[4]),
                "state" => strip_tags($courseparts[5]),
                "participated" => strip_tags($courseparts[6]),
                "qualification" => strip_tags($courseparts[7]),
                "days" => []
            ];
            $courseArray = array();
        
            
            if(count($courseparts) === 9) {
                $course_link = $dom->loadStr($courseparts[8]->innerHtml);
            } else {
                $course_link = $dom->loadStr($courseparts[9]->innerHtml);
            }

            $a = $course_link->find('a');
            $courselink = $a->getAttribute('href');
            $details_html = (string)$client->request('GET', "/Kripo/Kufer/" . $courselink, ['auth' => [$GLOBALS["username"], $GLOBALS["password"]], 'allow_redirects' => true, 'cookies' => $GLOBALS["jar"]])->getBody();

            $course_dom = new Dom;
            $course_dom->loadStr($details_html);
            $html = $course_dom->innerHtml;
            $d = new Dom;
            $daysRow = $course_dom->find('#ctl00_main_m_DaysRow');
            $days = $d->loadStr($daysRow)->find(".MessageTable tr");
            unset($days[0]);

            foreach ($days as $day) {
                $d = $course_dom->loadStr($day->innerHtml)->find('td');
                $darray = [
                    "date" => $d[0]->innerHtml,
                    "from" => explode(" - ", $d[1]->innerHtml)[0],
                    "to" => explode(" - ", $d[1]->innerHtml)[1],
                    "location" => $d[2]->innerHtml,
                    "floor" => $d[3]->innerHtml,
                    "room" => $d[4]->innerHtml,
                    "description" => $d[5]->innerHtml,
                ];
                $c["days"][] = $darray;
            }
            $d = new Dom;

            $lecturerRow = $course_dom->loadStr($html)->find('#ctl00_main_m_LecturerRow');
            try {
                $tmp = $d->loadStr($lecturerRow->innerHtml)->find("td");
                $lecturers = $tmp[1]->innerHtml;
                $c["lecturers"] = explode("<br /> ", $lecturers);
                foreach ($c["lecturers"] as $key => $lecturer) {
                    if (empty(trim($lecturer))) {
                        unset($c["lecturers"][$key]);
                    }
                    if ($lecturer === "noch nicht bekannt" || $lecturer === " noch nicht bekannt" || $lecturer === "Trainer" || $lecturer === "Übungsraum") {
                        unset($c["lecturers"][$key]);
                    }
                }
            } catch (\PHPHtmlParser\Exceptions\EmptyCollectionException $ex) {
                $c["lecturers"] = "Keine Vortragenden Angegeben oder sie konnten nicht ausgelesen werden.";
            }
            //grab infos
            $detailrows = $course_dom->loadStr(($course_dom->loadStr($html))->find(".MessageTable")[0])->find("tr");

            $infos = $detailrows[count($detailrows) - 1];
            $info = $d->loadStr($infos->innerHtml)->find("td")[1];
            $c["description"] = strip_tags($info->innerHtml);

            //grabbing attendees
            $mts = $course_dom->loadStr($html)->find(".MessageTable");


            $attendees = $course_dom->loadStr($mts[count($mts) - 1])->find("tr");
            unset($attendees[0]);
            foreach ($attendees as $attendee) {
                $tmp = $d->loadStr($attendee->innerHtml)->find("td");
                if ($tmp[3]->innerHtml !== "Storno") {
                    $c["attendees"][] = replaceHex($tmp[1]->innerHtml);
                }
            }
            $c["url"] = "https://niu.wrk.at/Kripo/Kufer/" . $courselink;
            $allCourses[] = $c;
        }
    }
    

    $statistics_response = $client->request('GET', $statistics_path . $userid, ['auth' => [$GLOBALS["username"], $GLOBALS["password"]], 'allow_redirects' => true, 'cookies' => $GLOBALS["jar"]]);
    $statistics_response = $client->request('GET', $statistics_path . $userid, ['auth' => [$GLOBALS["username"], $GLOBALS["password"]], 'allow_redirects' => true, 'cookies' => $GLOBALS["jar"]]);

    $dom = new Dom;
    $dom->loadStr((string)$statistics_response->getBody());
    $eventvalidation = $dom->find('#__EVENTVALIDATION')->getAttribute("value");
    $keypostfix = $dom->find('#__KeyPostfix')->getAttribute("value");
    $postData = [
        "__EVENTTARGET" => "ctl00\$main\$m_Submit",
        "__EVENTARGUMENT" => "",
        "__KeyPostfix" => $keypostfix,
        "__VIEWSTATE" => "",
        "__EVENTVALIDATION" => $eventvalidation,
        "ctl00\$main\$m_From\$m_Textbox" => $dateStart,
        "ctl00\$main\$m_Until\$m_Textbox" => $dateEnd,
        "ctl00\$main\$m_Employee" => "ALL",
        // "ctl00\$main\$m_showProposals" => "on", //! This currently breaks NIU!
        "ctl00\$main\$m_ShowUnfulfilledProposals" => "on",
        "ctl00\$main\$m_JoinBrokenDuties" => "on"
    ];

    $statistics_response = $client->request('POST', $statistics_path . $userid, ['form_params' => $postData, 'auth' => [$GLOBALS["username"], $GLOBALS["password"]], 'allow_redirects' => true, 'cookies' => $GLOBALS["jar"]]);
    $statistics_response = $client->request('POST', $statistics_path . $userid, ['form_params' => $postData, 'auth' => [$GLOBALS["username"], $GLOBALS["password"]], 'allow_redirects' => true, 'cookies' => $GLOBALS["jar"]]);

    $statistics = (string)$statistics_response->getBody();

    $dom = new Dom;
    $dom->loadStr($statistics);
    $rdduty = $dom->find('.MessageTable');
    $ambduty = $dom->find('.AmbulanceTable');

    $events = [];     

    foreach ($rdduty as $dutydom) {
        try {
            $dom->loadStr($dutydom)->innerHtml;
            $title = $dutydom->find('.MessageHeader')->innerHtml;
            $tables = $dutydom->find('.MessageBody table tbody')->innerHtml;
            $duties = $dom->loadStr($tables)->find('tr');
            foreach ($duties as $duty) {
                $events[] = parseRDDuty($duty, $title);
            }
        } catch (PhpHtmlParser\Exceptions\EmptyCollectionException $ecex) {
        }
    }
    
    foreach ($ambduty as $ambs) {
        try {
            $dom->loadStr($ambs)->innerHtml;
            $ambs = $dom->find('tr');
            unset($ambs[0]);
            foreach ($ambs as $row) {
                $parsedDuty = parseAmb($row);
                $parsedDuty["dutytype"] = "AMB";
                $events[] = $parsedDuty; 
            }
        } catch (Exception $ex) {
            $log->error($ex);
            // throw $ex;
        }
    }
    
    foreach ($allCourses as $course) {
        try {
            $days = parseCourse($course); 
            foreach($days as $key => $day) {
                $days[$key]["dutytype"] = "COURSE"; 
            }
            $events = array_merge($events, $days);
        } catch (Exception $ex) {
            throw $ex;
        }
    }
    if($is_lba) {
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
                if($titel === "Nachverrechnung") {
                    continue; 
                }
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
                    $location['lat'] = "48.189579";
                    $location['lon'] = "16.414110";
                } else {
                    $tmp = explode(";", $ort);
                    $location = [
                        "label" => $tmp[0],
                        "address" => $tmp[1],
                        "lat" => "0",
                        "lon" => "0",
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
                    "status" => "CONFIRMED",
                    "description" => "", 
                    "location" => $location,
                    "url" => "https://niu.wrk.at/Kripo/Kufer/CourseDetail.aspx?CourseID=".$id,
                    "dutytype" => "COURSE",
                    "date" => [
                        "start_date" => $startdate,
                        "start_time" => $starttime,
                        "end_date" => $enddate,
                        "end_time" => $endtime
                    ]
                ];
                $event = parseKufer($event);
                
                $events = array_merge($events, $event);
            }


        } catch (GuzzleHttp\Exception\TooManyRedirectsException $rex) {
            print_r($rex);
        }
    }
    $log->info("Request for " . $username." has ".count($events)." Events");
    healthcheck($username);
    if (!$GLOBALS["debug"]) {
        header('Content-Type: text/calendar; charset=utf-8');
        header('Content-Disposition: attachment; filename=dienstplan_'.str_replace(".", "", $GLOBALS["username"]).'.ics');
    }
    echo makeICalendar($events, $name, $dateStart, $dateEnd);
    die();
} catch (GuzzleHttp\Exception\TooManyRedirectsException $rex) {
    print_r($rex);
}
