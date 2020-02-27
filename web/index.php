<?php
require "../boot/bootstrap.php";

use Models\Logs;
use PHPHtmlParser\Dom;

$client = new GuzzleHttp\Client([
    'base_uri' => env("DATASOURCE_URL"),
	"verify" => false,
]);
$jar = new \GuzzleHttp\Cookie\CookieJar;
if (array_key_exists('auth', $_GET)) {
    $auth = $_GET["auth"];
    if (isset($auth)) {
        $auth = explode(":", base64_decode($auth));
        $username = $auth[0];
        $password = $auth[1];

        $log->info("Request for " . $username);

        $base_uri = $client->getConfig("base_uri");
        $header_path = "/Kripo/Header.aspx";
        $niu_today_path = "/Kripo/Today/Today.aspx";
        $statistics_path = "/Kripo/DutyRoster/EmployeeDutyStatistic.aspx?EmployeeNumberID=";
        $course_path = "/Kripo/Kufer/SearchCourse.aspx?EmployeeId=";

        try {
            $auth = $client->request('GET', $base_uri, ['auth' => [$GLOBALS["username"], $GLOBALS["password"]], 'allow_redirects' => true, 'cookies' => $GLOBALS["jar"]]);

            $header_response = $client->request('GET', $header_path, ['auth' => [$GLOBALS["username"], $GLOBALS["password"]], 'allow_redirects' => true, 'cookies' => $GLOBALS["jar"]]);
            $header = (string)$header_response->getBody();

            $dom = new Dom;
            $dom->loadStr($header);

            $userlink = $dom->find('#userName');
            $name = explode(" ", $userlink->innerHtml);
            unset($name[count($name) - 1]);
            $name = implode(" ", $name);

            $userid = explode("=", $userlink->getAttribute('href'))[1];

            // Grabbing Courses
            $courses_response = $client->request('GET', $course_path . $userid, ['auth' => [$GLOBALS["username"], $GLOBALS["password"]], 'allow_redirects' => true, 'cookies' => $GLOBALS["jar"]]);

            $dom = new Dom;
            $dom->loadStr((string)$courses_response->getBody());
            $eventvalidation = $dom->find('#__EVENTVALIDATION')->getAttribute("value");
            $keypostfix = $dom->find('#__KeyPostfix')->getAttribute("value");

            $postData = [
                "__EVENTTARGET" => "ctl00\$main\$m_Search",
                "__EVENTARGUMENT" => "",
                "__KeyPostfix" => $keypostfix,
                "__VIEWSTATE" => "",
                "__EVENTVALIDATION" => $eventvalidation,
                "ctl00\$main\$m_From\$m_Textbox" => date("d.m.Y", strtotime("-2 years", strtotime("first day of january"))),
                "ctl00\$main\$m_Until\$m_Textbox" => date("d.m.Y", strtotime("+2 years", strtotime("last day of december"))),
                "ctl00\$main\$m_CourseName" => "",
                "ctl00\$main\$m_SortOrder" => "Kursdatum",
                "ctl00\$main\$m_Options\$0" => "on",
                "ctl00\$main\$m_Options\$5" => "on",
                "ctl00\$main\$m_Options\$6" => "on"
            ];

            $courses_response = $client->request('POST', $course_path . $userid, ['form_params' => $postData, 'auth' => [$GLOBALS["username"], $GLOBALS["password"]], 'allow_redirects' => true, 'cookies' => $GLOBALS["jar"]]);
            $courses = (string)$courses_response->getBody();
            $dom->loadStr($courses);
            $courseTable = $dom->loadStr($dom->find('#ctl00_main_m_CourseList__CourseTable'));
            $courses = $courseTable->find('tr');
            $courseArray = [];

            foreach ($courses as $k => $course) {
                if (strpos($course, "MessageHeaderCenter") === false && strpos($course, "MessageBodySeperator") === false) {
                    $courseparts = $dom->loadStr($course)->find('td');
                    $c = [
                        "course_id" => strip_tags($courseparts[0]),
                        "title" => strip_tags($courseparts[1]),
                        "start" => strip_tags($courseparts[2]),
                        "end" => strip_tags($courseparts[3]),
                        "location" => strip_tags($courseparts[4]),
                        "state" => strip_tags($courseparts[5]),
                        "participated" => strip_tags($courseparts[6]),
                        "qualification" => strip_tags($courseparts[7]),
                        "days" => []
                    ];
                    // dump($courseparts);
                    // die();
                    // if($courseparts[0])
                    if(is_object($courseparts[9])) {
                        $course_link = $dom->loadStr($courseparts[9]->innerHtml);
                        $a = null;
                        if ($course_link->hasChildren()) {
                            $a = $course_link->find('a');
                        }

                        if (is_object($a))
                        {
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
                                $c["lecturers"] = strip_tags(str_replace("<br />", ",", str_replace(",", "", $lecturers)));
                            } catch (\PHPHtmlParser\Exceptions\EmptyCollectionException $ex) {
                                $c["lecturers"] = "Keine Vortragenden Angegeben oder sie konnten nicht ausgelesen werden.";
                            }
                            //grab infos
                            $detailrows = $course_dom->loadStr(($course_dom->loadStr($html))->find(".MessageTable")[0])->find("tr");

                            $infos = $detailrows[count($detailrows) - 1];
                            $info = $d->loadStr($infos->innerHtml)->find("td")[1];
                            $c["description"] = strip_tags($info->innerHtml);
                            $courseArray[] = $c;
                        }
                    }
                }
            }

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
                "ctl00\$main\$m_From\$m_Textbox" => date("d.m.Y", strtotime("-2 years", strtotime("first day of january"))),
                "ctl00\$main\$m_Until\$m_Textbox" => date("d.m.Y", strtotime("+2 years", strtotime("last day of december"))),
                "ctl00\$main\$m_Employee" => "ALL",
                "ctl00\$main\$m_showProposals" => "on",
                "ctl00\$main\$m_ShowUnfulfilledProposals" => "on",
                "ctl00\$main\$m_JoinBrokenDuties" => "on"
            ];
            $statistics_response = $client->request('POST', $statistics_path . $userid, ['form_params' => $postData, 'auth' => [$GLOBALS["username"], $GLOBALS["password"]], 'allow_redirects' => true, 'cookies' => $GLOBALS["jar"]]);

            $statistics = (string)$statistics_response->getBody();

            $dom = new Dom;
            $dom->loadStr($statistics);
            $rdduty = $dom->find('.MessageTable');
            $ambduty = $dom->find('.AmbulanceTable');

            /**
             * Start of VCALENDAR Stitching
             */
            $vcal = "BEGIN:VCALENDAR\r\nVERSION:2.0\r\nPRODID:-//Daniel Steiner//NONSGML Red Cross Austria, Regional Branch Vienna Dutyschedule v1.0//DE\r\nX-FROM-URL:" . env('SCRIPT_URL') . "/?auth=" . $_GET["auth"] . "\r\nX-WR-RELCALID:WRK_Dutyschedule\r\nX-PUBLISHED-TTL:PT15M\r\nREFRESH-INTERVAL;VALUE=DURATION:P15M\r\nSOURCE;VALUE=URI:" . env('SCRIPT_URL') . "/?auth=" . $_GET["auth"] . "\r\nCOLOR:darkred\r\nNAME:Dienstplan von " . $name . "\r\nDESCRIPTION:In diesem Kalender sind alle Dienste, Ambulanzen und Ausbildung\r\n en von " . $name . " für das Jahr " . date("Y") . "\r\nX-WR-CALNAME:Dienstplan von " . $name . "\r\nX-WR-CALDESC:In diesem Kalender sind alle Dienste, Ambulanzen und Ausbildung\r\n en von " . $name . " für das Jahr " . date("Y") . "\r\nX-WR-TIMEZONE:Europe/Vienna\r\nCALSCALE:GREGORIAN\r\nBEGIN:VTIMEZONE\r\nTZID:Europe/Vienna\r\nX-LIC-LOCATION:Europe/Vienna\r\nBEGIN:DAYLIGHT\r\nTZOFFSETFROM:+0100\r\nTZOFFSETTO:+0200\r\nTZNAME:CEST\r\nDTSTART:19700329T020000\r\nRRULE:FREQ=YEARLY;BYDAY=-1SU;BYMONTH=3\r\nEND:DAYLIGHT\r\nBEGIN:STANDARD\r\nTZOFFSETFROM:+0200\r\nTZOFFSETTO:+0100\r\nTZNAME:CET\r\nDTSTART:19701025T030000\r\nRRULE:FREQ=YEARLY;BYDAY=-1SU;BYMONTH=10\r\nEND:STANDARD\r\nEND:VTIMEZONE\r\n";

            foreach ($rdduty as $dutydom) {
                try {
                    $dom->loadStr($dutydom)->innerHtml;
                    $title = $dutydom->find('.MessageHeader')->innerHtml;
                    $tables = $dutydom->find('.MessageBody table tbody')->innerHtml;
                    $duties = $dom->loadStr($tables)->find('tr');
                    foreach ($duties as $duty) {
                        $vcal .= parseRDDuty($duty, $title);
                    }
                } catch (PhpHtmlParser\Exceptions\EmptyCollectionException $ecex) {
                    //Ignore parse errors;
                }
            }

            foreach ($ambduty as $ambs) {
                try {
                    $dom->loadStr($ambs)->innerHtml;
                    $ambs = $dom->find('tr');
                    unset($ambs[0]);
                    foreach ($ambs as $row) {
                        $vcal .= parseAmb($row);
                    }
                } catch (Exception $ex) {
                }
            }

            foreach ($courseArray as $course) {
                try {
                    $vcal .= parseCourse($course);
                } catch (Exception $ex) {
                }
            }
            $vcal .= "END:VCALENDAR";
            $fixedVcal = "";
            foreach (preg_split("/((\r?\n)|(\r\n?))/", $vcal) as $line) {
                $fixedVcal .= splitLine($line);
            }

            header('Content-Type: text/calendar; charset=utf-8');
            header('Content-Disposition: attachment; filename=dutyschedule.ics');

            echo $fixedVcal;
            die();
        } catch (GuzzleHttp\Exception\TooManyRedirectsException $rex) {
            print_r($rex);
        }
    }
} else {
    header('Location: login.php');
}
