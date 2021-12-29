<?php
mb_internal_encoding("UTF-8");

require __DIR__ . '/../vendor/autoload.php';
require __DIR__ . '/../models/employee.php';

use PHPHtmlParser\Dom;
use Carbon\Carbon;
use Carbon\CarbonImmutable;
use Models\Employee;
use Models\Location;
use Models\User;
use Monolog\ErrorHandler;
use Monolog\Handler\StreamHandler;
use Monolog\Logger;
use PHPHtmlParser\Exceptions\EmptyCollectionException;
$i = 0;

function parseAmb($row) {
    $elem = $row->find('td');
    if (count($elem) == 10) {
        $day = $elem[5]->innerHtml;
        $date = $elem[5]->innerHtml;
        $duty = [
            'date' => $elem[5]->innerHtml,
            'time' => [
                'start' => Carbon::parse($elem[5]->innerHtml . " " . $elem[6]->innerHtml),
                'end' => Carbon::parse($elem[5]->innerHtml . " " . $elem[7]->innerHtml)
            ]
        ];
        $urldom = $elem[9]->find("a");
        $url = "https://niu.wrk.at" . $urldom->getAttribute("href");
        $parsedTitle = $elem[2]->innerHtml;
        $duty['location'] = "Unbekannt";
        $duty['hash'] = generateHash($elem[2]->innerHtml . date('i'), $elem[6]->innerHtml, $elem[7]->innerHtml);
        $duty['title'] = $parsedTitle;
        $duty['status'] = "CONFIRMED";
        $duty['url'] = $url;
    } else if (count($elem) == 11) {
        $day = $elem[5]->innerHtml;
        $date = $elem[5]->innerHtml;

        $duty = [
            'date' => $elem[5]->innerHtml,
            'time' => [
                'start' => Carbon::parse($elem[5]->innerHtml . " " . $elem[6]->innerHtml),
                'end' => Carbon::parse($elem[5]->innerHtml . " " . $elem[8]->innerHtml)
            ]
        ];

        $urldom = $elem[10]->find("a");
        $url = "https://niu.wrk.at" . $urldom->getAttribute("href");
        $parsedTitle = $elem[2]->innerHtml;
        $duty['location'] = "Unbekannt";
        $duty['hash'] = generateHash($elem[2]->innerHtml . date('i'), $elem[6]->innerHtml, $elem[8]->innerHtml);
        $duty['title'] = $parsedTitle;
        $duty['status'] = "CONFIRMED";
    }
    $details = getAmbDetails($url);        
    $duty['location'] = $details["location"];
    $duty["description"] = $details["description"];
    $duty["team"] = $details["team"];
    return $duty;
}

function parseRDDuty($duty, $title) {
    $fixed = false;

    if (stripos($title, "fixiert") !== false) {
        $fixed = true;
    }
    $dom = new Dom;
    $details = ($dom->loadStr($duty->innerHtml))->find('td');
    $day = $details[0]->innerHtml;
    $date = $details[1]->innerHtml;
    $timestring = $details[2]->innerHtml;
    $location = $details[3]->innerHtml;

    $date = parseDate($details[1]->innerHtml, $details[2]->innerHtml);
    $duty = [
        'day' => $details[0]->innerHtml,
        'date' => $details[1]->innerHtml,
        'time' => [
            'start' => $date['start'],
            'end' => $date['end'],
        ],
        'dutytype' => 'Rettungsdienst',
    ];

    $location = $details[3]->innerHtml;
    $vehicle = null;
    $pos1 = null;
    $pos2 = null;
    $pos3 = null;
    $pos4 = null;
    $pos5 = null;
    $remark = null;

    if (stripos($title, "Referate") !== false) {
        $duty["dutytype"] = "Bezirkstellenreferat";
        if (strlen($details[4]) > 30) {
            $pos1 = trim($details[4]->innerHtml);
        }
        if (strlen($details[5]) > 30) {
            $pos2 = trim($details[5]->innerHtml);
        }
        if (strlen($details[6]) > 30) {
            $pos3 = trim($details[6]->innerHtml);
        }
        if (strlen($details[7]) > 9) {
            $remark = $details[7]->innerHtml;
        }
    } else if (
        stripos($title, "Journal") !== false ||
        stripos($title, "Rufhilfe") !== false
    ) {
        $duty["dutytype"] = "Journal";
        if (strlen($details[4]) > 30) {
            $pos1 = trim($details[4]->innerHtml);
        }
        if (strlen($details[5]) > 30) {
            $pos2 = trim($details[5]->innerHtml);
        }
        if (strlen($details[6]) > 30) {
            $pos3 = trim($details[6]->innerHtml);
        }
        if (strlen($details[7]) > 9) {
            $remark = $details[7]->innerHtml;
        }
    } else if (stripos($title, "Support") !== false) {
        $duty["dutytype"] = "Dienstführung";
        if (strlen($details[4]) > 30) {
            $pos1 = trim($details[4]->innerHtml);
        }
        if (strlen($details[5]) > 30) {
            $pos2 = trim($details[5]->innerHtml);
        }
        if (strlen($details[6]) > 9) {
            $remark = $details[6]->innerHtml;
        }
    } else if (
        stripos($title, "KTW") !== false ||
        stripos($title, "SPEZ") !== false ||
        stripos($title, "SR") !== false ||
        stripos($title, "Ambulanz-Support") !== false ||
        stripos($title, "HIO") !== false
    ) {
        if ($fixed) {
            if (strlen($details[4]) > 9) {
                $vehicle = $details[4]->innerHtml;
            }
            if (strlen($details[5]) > 30) {
                $pos1 = trim($details[5]->innerHtml);
            }
            if (strlen($details[6]) > 30) {
                $pos2 = trim($details[6]->innerHtml);
            }
            if (strlen($details[7]) > 30) {
                $pos3 = trim($details[7]->innerHtml);
            }
            if (strlen($details[8]) > 9) {
                $remark = $details[8]->innerHtml;
            }
        } else {
            if (strlen($details[4]) > 30) {
                $pos1 = trim($details[4]->innerHtml);
            }
            if (strlen($details[5]) > 30) {
                $pos2 = trim($details[5]->innerHtml);
            }
            if (strlen($details[6]) > 30) {
                $pos3 = trim($details[6]->innerHtml);
            }
            if (strlen($details[7]) > 9) {
                $remark = $details[7]->innerHtml;
            }
        }
    } else if (
        stripos($title, "RKP") !== false ||
        stripos($title, "RKS") !== false ||
        stripos($title, "RKL") !== false ||
        stripos($title, "RKIII") !== false
    ) {
        $duty["dutytype"] = "RTW";
        if ($fixed) {
            if (strlen($details[4]) > 9) {
                $vehicle = $details[4]->innerHtml;
            }
            if (strlen($details[5]) > 30) {
                $pos1 = trim($details[5]->innerHtml);
            }
            if (strlen($details[6]) > 30) {
                $pos2 = trim($details[6]->innerHtml);
            }
            if (strlen($details[7]) > 30) {
                $pos3 = trim($details[7]->innerHtml);
            }
            if (strlen($details[8]) > 30) {
                $pos4 = trim($details[8]->innerHtml);
            }
            if (strlen($details[9]) > 30) {
                $pos5 = trim($details[9]->innerHtml);
            }
            if (strlen($details[10]) > 9) {
                $remark = $details[10]->innerHtml;
            }
        } else {
            if (strlen($details[4]) > 30) {
                $pos1 = trim($details[4]->innerHtml);
            }
            if (strlen($details[5]) > 30) {
                $pos2 = trim($details[5]->innerHtml);
            }
            if (strlen($details[6]) > 30) {
                $pos3 = trim($details[6]->innerHtml);
            }
            if (strlen($details[7]) > 9) {
                $remark = $details[7]->innerHtml;
            }
        }
    } else if (stripos($title, "ÄFD-Calltaker") !== false) {
        $duty["dutytype"] = "Journal";
        if (strlen($details[4]) > 30) {
            $pos1 = trim($details[4]->innerHtml);
        }
        if (strlen($details[5]) > 30) {
            $pos2 = trim($details[5]->innerHtml);
        }
        if (strlen($details[6]) > 9) {
            $remark = trim($details[6]->innerHtml);
        }
    }

    if (stripos($title, "KTW") !== false) {
        $duty["dutytype"] = "KTW";
    }
    if (stripos($title, "SPEZ") !== false) {
        $duty["dutytype"] = "Spezialdienst";
    }
    if (stripos($title, "SR") !== false) {
        $duty["dutytype"] = "SR";
    }
    if (stripos($title, "Ambulanz-Support") !== false) {
        $duty["dutytype"] = "Ambulanzsupport";
    }
    if (stripos($title, "HIO") !== false) {
        $duty["dutytype"] = "HIO";
    }

    $parsedTitle = parseTitle($title, $location, $remark);
    $t = [$pos1, $pos2, $pos3, $pos4, $pos5];
    $team = [];
    if (array_key_exists('teamlabels', $parsedTitle)) {
        if (is_array($parsedTitle['teamlabels'])) {
            foreach ($parsedTitle['teamlabels'] as $i => $label) {
                $team[$label] = $t[$i];
            }
        } else {
        }
    }

    $duty['location'] = parseLocation($title, $location);
    $duty['team'] = $team;
    if (!is_null($vehicle) && $vehicle !== 0 && $vehicle !== 00 && $vehicle !== 000) {
        $duty['vehicle'] = str_pad($vehicle, 3, '0', STR_PAD_LEFT);
        if($vehicle !== 0) {
            $duty['url'] = getFZGTagebuchLink($duty['vehicle'])['link'];
        }
    }
    $duty['hash'] = generateHash($title, $date, $timestring);
    $duty['title'] = $parsedTitle['title'];
    $duty['status'] = $parsedTitle['status'];
    $duty['description'] = generateDescription($team, $remark, $vehicle);
    
    // $vevent = makeVEVENT($duty);
   
    return $duty;
}

function parseKufer($event) {
    $events = []; 
    if($event["date"]["start_date"] !== $event["date"]["end_date"]) {
        //multiday;
        $start = $event["date"]["start_date"];
        $end = $event["date"]["end_date"];
        
        $s = explode(".", $start);
        $e = explode(".", $end);
        $start = $s[0].".".$s[1].".20".$s[2];
        $end = $e[0].".".$e[1].".20".$e[2];

        $period = createRange($start, $end, "d.m.Y");
        foreach($period as $day) {
            $duty = [
                'date' => $day,
                'time' => [
                    'start' => CarbonImmutable::parse($day. " ".$event['date']['start_time']),
                    'end' => CarbonImmutable::parse($day. " ".$event['date']['end_time'])
                ],
                'location' => $event['location'],
                'hash' => generateHash($event["title"], $day, $event['date']['start_time'] . "-" . $event['date']['end_time']),
                'title' => replacehex(urldecode($event["title"])),
                'status' => "CONFIRMED",
                'description' => $event["description"],
                'team' => [],
                'url' => $event["url"],
                'dutytype' => 'selfcourse'
            ];
            $events[] = $duty;
        }  
    } else {
        $d = explode(".", $event['date']['start_date']);
        $date = $d[0].".".$d[1].".20".$d[2];
        $duty = [
                'date' => $date,
                'time' => [
                    'start' => CarbonImmutable::parse($date. " ". $event['date']['start_time']),
                    'end' => CarbonImmutable::parse($date. " ". $event['date']['end_time'])
                ],
                'location' => $event['location'],
                'hash' => generateHash($event["title"], $event['date']['start_date'], $event['date']['start_time'] . "-" . $event['date']['end_time']),
                'title' => replacehex(urldecode($event["title"])),
                'status' => "CONFIRMED",
                'description' => $event["description"],
                'team' => [],
                'url' => $event["url"],
                'dutytype' => 'selfcourse'
            ];
            $events[] = $duty;
    }
    return $events;
}

function parseCourse($course) {
    $courses = [];
    if ($course["days"]) {
        foreach ($course["days"] as $day) {
            $duty = [
                'date' => $day["date"],
                'time' => [
                    'start' => Carbon::parse($day["date"] . " " . $day["from"]),
                    'end' => Carbon::parse($day["date"] . " " . $day["to"])
                ]
            ];
            if($course["location"] === "Wiener Linien") {
                $duty['location']['label'] =  "U-Bahn Station U3 Erdberg";
                $duty['location']['address'] = "Erdbergstraße 143, 1030 Wien";
                $duty['location']['lat'] = "48.192050";
                $duty['location']['lon'] = "16.413330";
            } 
            if($day["location"] === "ABZ" || $day["location"] === "SanArena") {
                $duty['location']['label'] =  "Wiener Rotes Kreuz - Ausbildungszentrum";
                if(is_numeric($day["room"])){
                    $duty['location']['address'] = "Safargasse 4, 1030 Wien, ".$day["floor"]. ". Stock, Raum " .$day["room"]." (".$day["description"].")";
                } else {
                    $duty['location']['address'] = "Safargasse 4, 1030 Wien, ".$day["floor"]. ". Stock ".$day["description"];
                }
                $duty['location']['lat'] = "48.189579";
                $duty['location']['lon'] = "16.414110";
            } 
            if($day["location"] === "Nottendorfergasse") {          
                $duty['location']['label'] =  "Wiener Rotes Kreuz Zentrale";
                if($day["floor"] === "Garage"){
                    $duty['location']['address'] = "Nottendorfer Gasse 21, 1030 Wien, Hinteres Gebäude (BT 2) Garage";
                }
                else if($day["description"] === "Nottendorfergasse EG Saal") {
                    $duty['location']['address'] = "Nottendorfer Gasse 21, 1030 Wien, Vorderes Gebäude (BT 1) EG Saal";
                } else {
                    $duty['location']['address'] = "Nottendorfer Gasse 21, 1030 Wien";
                }
                $duty['location']['lat'] = "48.190650";
                $duty['location']['lon'] = "16.411500";
            } 

            if($day["location"] === "Wien West") {          
                $duty['location']['label'] =  "Wiener Rotes Kreuz Bezirkstelle West";
                $duty['location']['address'] = "Spallartgasse 10A, 1140 Wien, ".$day["floor"].", ".$day["description"];
                $duty['location']['lat'] = "48.200580";
                $duty['location']['lon'] = "16.308870";
            } 
            if($day["location"] === "KSS") {          
      
                $duty['location']['label'] =  "Wiener Rotes Kreuz Bezirkstelle Nord";
                $duty['location']['address'] = "Karl-Schäfer-Straße 8, 1210 Wien , ".$day["floor"].", ".$day["room"];
                $duty['location']['lat'] = "48.267200";
                $duty['location']['lon'] = "16.401990";
            } 
            if(!array_key_exists("location", $day)){
                if($course["dutytype"] === "COURSE") {
                    $duty['location']['label'] =  "Wiener Rotes Kreuz - Ausbildungszentrum";
                    $duty['location']['address'] = "Safargasse 4, 1030 Wien";
                    $duty['location']['lat'] = "48.189579";
                    $duty['location']['lon'] = "16.414110";
                }
            } 
            //! MA70, Rettungsakademie, Johanniter Wien, Johanniter-Center-Nord, MedUni Wien / AKH, E-Learning WRK, Moodle Schulung fehlen noch als locations. 

            $duty['hash'] = generateHash($course["title"], $day["date"], $day["from"] . "-" . $day["to"]);
            $duty['title'] = $course["title"];
            $duty['status'] = $course["participated"] === "Storno" ? "CANCELLED" : "CONFIRMED";
            $duty['description'] = $course["description"];
            $duty['team'] = ["mandatory" => $course["lecturers"], "optional" => $course["attendees"]];
            $duty['url'] = $course["url"];
           
            $courses[] = $duty;
        }
    }
    return $courses;
}

function parseTitle($title, $location, $remark) {
    $titleparts = explode(" ", $title);
    $status = ($titleparts[count($titleparts) - 1] === "geplant" ? "TENTATIVE" : "CONFIRMED");
    unset($titleparts[count($titleparts) - 1]);
    $t = implode(" ", $titleparts);
    $fancyresult = getFancyTitle($t, $location, $remark);

    return ["title" => $fancyresult['title'], "status" => $status, 'teamlabels' => $fancyresult['teamlabels']];
}

function getFancyTitle($title, $location, $remark) {
    $fancytitle = "";
    $teamlabels = "";
    switch ($title) {
        case "ÄFD-Calltaker":
            $fancytitle = "Ärztefunkdienst Calltaker";
            $teamlabels = ["Calltaker 1", "Calltaker 2", "Calltaker 3"];
            break;
        case "Journal":
            $fancytitle = "Leitstelle / Journal";
            $teamlabels = ["Journal 1", "Journal 2", "Journal 3"];
            break;
        case "KTW":
            $fancytitle = "KTW";
            $teamlabels = ["Fahrer", "SAN1", "SAN2"];
            break;
        case "RKP":
            $fancytitle = "RTW RKP-1";
            $teamlabels = ["Fahrer", "SAN1", "SAN2", "Arzt", "Azubi"];
            break;
        case "RKS":
            $fancytitle = "RTW RKS-1";
            $teamlabels = ["Fahrer", "SAN1", "SAN2", "Arzt", "Azubi"];
            break;
        case "RKL":
            $fancytitle = "RTW RKL-1";
            $teamlabels = ["Fahrer", "SAN1", "SAN2", "Arzt", "Azubi"];
            break;
        case "RKL2":
            $fancytitle = "RTW RKL-2";
            $teamlabels = ["Fahrer", "SAN1", "SAN2", "Arzt", "Azubi"];
            break;
        case "RKIII":
        case "RKL3":
        case "NAW-RK3":
            $fancytitle = "NAW/ITW RKL-3";
            $teamlabels = ["Fahrer", "SAN1", "SAN2", "Arzt", "Azubi"];
            break;
        case "SPEZ":
            $fancytitle = "Spezialdienste";
            $teamlabels = ["Fahrer", "SAN1", "SAN2", "Azubi 1", "Azubi 2"];
            break;
        case "NFR-Support":
            $fancytitle = "NFR Support";
            $teamlabels = ["Hauptdienst", "Beidienst"];
            break;
        case "KTW-Support":
            $fancytitle = "KTW Support";
            $teamlabels = ["Hauptdienst", "Beidienst"];
            break;
        case "Amb-Support":
            $fancytitle = "Ambulanz Support";
            $teamlabels = ["Hauptdienst", "Beidienst"];
            break;
        case "Referate West":
        case "Referate Nord":
        case "Referate DDL":
        case "Referate VS":
        case "Referate BVS":
            $fancytitle = "Bezirkstellenreferat";
            $teamlabels = ["Referent 1", "Referent 2", "Referent 3"];
            break;
            break;
        case "BT-SAN":
            $fancytitle = "Bereitschaftsdienst BT-SAN";
            $teamlabels = ["Hauptdienst", "Beidienst", "Reserve"];
            break;
        case "RD-Blut": 
            $fancytitle = "Bluttransporte";
            $teamlabels = ["Fahrer"];
            break;  
        default:
            $fancytitle = "undefined";
            $teamlabels = "undefined";
            break;
    }

    if (stripos($remark, "NKTW") !== false) {
        $fancytitle = "NKTW";
        if (strtolower($location) == "west") {
            $fancytitle .= " RKK4";
            $teamlabels = ["Fahrer", "SAN1", "SAN2"];
        }
    }
    if (stripos($remark, "RKK1") !== false) {
        $fancytitle = "NKTW RKK1";
        $teamlabels = ["Fahrer", "SAN1", "SAN2"];
    }
    if (stripos($remark, "RKK2") !== false) {
        $fancytitle = "NKTW RKK2";
        $teamlabels = ["Fahrer", "SAN1", "SAN2"];
    }
    if (stripos($remark, "RKK3") !== false) {
        $fancytitle = "NKTW RKK3";
        $teamlabels = ["Fahrer", "SAN1", "SAN2"];
    }
    if (stripos($remark, "RKK4") !== false) {
        $fancytitle = "NKTW RKK4";
        $teamlabels = ["Fahrer", "SAN1", "SAN2"];
    }
    if (stripos($remark, "RKK5") !== false) {
        $fancytitle = "NKTW RKK5";
        $teamlabels = ["Fahrer", "SAN1", "SAN2"];
    }
    if (stripos($remark, "Notfall-KTW RKK 1") !== false) {
        $fancytitle = "NKTW RKK1";
        $teamlabels = ["Fahrer", "SAN1", "SAN2"];
    }
    if (stripos($remark, "Notfall-KTW RKK 2") !== false) {
        $fancytitle = "NKTW RKK2";
        $teamlabels = ["Fahrer", "SAN1", "SAN2"];
    }
    if (stripos($remark, "Notfall-KTW RKK 3") !== false) {
        $fancytitle = "NKTW RKK3";
        $teamlabels = ["Fahrer", "SAN1", "SAN2"];
    }
    if (stripos($remark, "Notfall-KTW RKK 4") !== false) {
        $fancytitle = "NKTW RKK4";
        $teamlabels = ["Fahrer", "SAN1", "SAN2"];
    }
    if (stripos($remark, "Notfall-KTW RKK 5") !== false) {
        $fancytitle = "NKTW RKK5";
        $teamlabels = ["Fahrer", "SAN1", "SAN2"];
    }
    if (stripos($remark, "ITW") !== false) {
        $fancytitle = "NAW/ITW RKL-3";
        $teamlabels = ["Fahrer", "SAN1", "SAN2", "Arzt", "Azubi"];
    }
    if (stripos($remark, "RTW-RKL2") !== false) {
        $fancytitle = "RTW RKL-2";
        $teamlabels = ["Fahrer", "SAN1", "SAN2", "Arzt", "Azubi"];
    }
    return ["title" => $fancytitle, "teamlabels" => $teamlabels];
}

function generateTeamList($team, $teamlabels) {
    $teamlist = [];
    foreach ($team as $i => $member) {
        if (!is_null($team[$i])) {
            $teamlist[$teamlabels[$i]] = trim($team[$i]);
        }
    }
    return $teamlist;
}

function generateDescription($team, $remark, $fahrzeug = null) {
    $eventDescription = "";

    if (!is_null($fahrzeug)) {
        
        $eventDescription .= "Fahrzeug: " . str_pad($fahrzeug, 3, 0, STR_PAD_LEFT) . "<br>";
        if($fahrzeug !== 0 && $fahrzeug !== 00 && $fahrzeug !== 000 && !is_null($fahrzeug) && !empty($fahrzeug)) {
            
            $vehicle = getFZGTagebuchLink(str_pad($fahrzeug, 3, 0, STR_PAD_LEFT));
            $eventDescription .= "Typ: ". $vehicle["type"]."<br>";
            $eventDescription .= "Funkkennung: " . $vehicle["radioid"]. "<br>";
        }
    }

    $i = 0;
    foreach ($team as $type => $member) {
        if ($member !== "") {
            $member = getUser($member);
            if (!is_null($member)) {
                if ($i != 0) {
                    $eventDescription .= "<br>";
                }
                $eventDescription .= $type . ": " . $member;
                $i++;
            }
        }
    }
    if (!is_null($remark) && !empty($remark)) {
        $eventDescription .= "<br>" . $remark;
    }

    $eventDescription = replaceHex($eventDescription);
    $eventDescripption = splitLine($eventDescription);
    return $eventDescription;
}

function splitLine($text) {
    if (mb_strlen($text) >= 75) {
        $text = wordwrap($text, 75, "\r\n", true);
    } else {
        if (strpos($text, "\n") !== false || strpos($text, "\r\n") !== false) {
            return $text;
        } else {
            return $text . "\r\n";
        }
    }
    return $text . "\r\n";
}

function getUser($member) {
    if ($member != null) {
        if (strpos($member, ">") !== false) {
            $empid = explode('\'', $member)[1];
            $name = explode('>', $member);
            $name = substr($name[1], 1, -3);
            $name = str_replace(" (", ", ", $name);
            $name = str_replace(")", "", $name);

            $empurl = "https://niu.wrk.at/Kripo/Employee/shortemployee.aspx?EmployeeNumberID=";
            $returnstring = '<a href="' . $empurl . $empid . '">' . $name . '</a>';
            return $returnstring;
        } else {
            return null;
        }
    } else {
        return null;
    }
}

function getFullUser($member, $type = "duty") {
    if ($member != null || !empty(trim($member))) {
        $d = new Dom;
        try {
            $d->loadStr($member);
            $link = $d->find('a');
            $jsLink = $link->getAttribute("href");
            $type = null; 
            
            if(strpos($jsLink, "SEmpFNRID")!==false) {
                $type = "SEmpFNRID";
            } else if (strpos($jsLink, "SEmpFID")!==false) {
                $type = "SEmpFID";
            }
            $employeeId = substr($jsLink, 20, -3);
             if (strpos($employeeId, "(") !== false) {
                $employeeId = substr($employeeId, 2);
            }
            $empurl = getUrl($type, $employeeId); 
            $nameString = $link->innerHtml;
            if (strpos($nameString, "(") !== false) {
                $name = substr($nameString, 0, strpos($nameString, " ("));
                $dnrs = explode(", ", substr($nameString, strpos($nameString, " (") + 2, -1));
            } else {
                return null;
            }

           
            $employee = [
                "name" => ucwords(replaceHex(trim($name))),
                "dnrs" => $dnrs,
                "employee_id" => $employeeId,
                "url" => $empurl
            ];
            $e = Employee::whereEmployeeId($employeeId)->first();
            if (is_null($e)) {
                $e = new Employee($employee);
                $e->save();
            } 
            $employee = [
                "name" => $e->name,
                "dnrs" => $e->dnrs,
                "employee_id" => $e->employee_id,
                "url" => $e->url
            ];
            return $employee;
        } catch (EmptyCollectionException $eex) {
            //search member or display as fake user. 
        }
    }
    return null;
}

function getAmbDetails($url) {
    $client = new GuzzleHttp\Client();
    $amb_response = $client->request('GET', $url, ['auth' => [$GLOBALS["username"], $GLOBALS["password"]], 'allow_redirects' => true, 'cookies' => $GLOBALS["jar"]]);
    $amb_response = $client->request('GET', $url, ['auth' => [$GLOBALS["username"], $GLOBALS["password"]], 'allow_redirects' => true, 'cookies' => $GLOBALS["jar"]]);
    $dom = new Dom;
    $dom->loadStr((string) $amb_response->getBody());
    $desc = $dom->find('#ctl00_main_m_AmbulanceDisplay_m_Webinfo')->innerHtml;
    $amb_description = strip_tags($desc);
    $descparts = explode("<p>", $desc);
    $locationResult = [
        "label" => "Wien, Österreich",
        "address" => "Wien, Österreich",
        "lat" => 48.2081743,
        "lon" =>  16.3738189
    ];
    foreach($descparts as $part) {
        if(strpos($part, "Wo:")!==false) {
            $re = '/.*<b>Wo:<\/b>\s*([^\n\r]*)/';
            preg_match_all($re, $part, $matches, PREG_SET_ORDER, 0);
            if(is_countable($matches[0])) {
                if(count($matches[0]) > 1 ) {
                    $location = trim(str_replace("Für Verpflegung ist gesorgt!", "", $matches[0][1]));
                    $geoclient = new GuzzleHttp\Client();
                    if(strlen($location) > 0 ) {
                        $dbloc = \Models\Location::whereLabel($location)->first();
                        if(is_null($dbloc)) {
                            $dbloc = new \Models\Location();
                            if(strpos(strtolower($location), "nodo") !== false) {
                                $locationResult = [
                                    "label" =>  "Wiener Rotes Kreuz Zentrale",
                                    "address" => "Nottendorfer Gasse 21, 1030 Wien",
                                    "lat" => "48.190650",
                                    "lon" => "16.411500"
                                ];
                            } else if (strpos(strtolower($location), "abz") !== false) {
                                $locationResult = [
                                    "label" =>  "Wiener Rotes Kreuz - Ausbildungszentrum",
                                    "address" => "Safargasse 4, 1030 Wien",
                                    "lat" => "48.189579",
                                    "lon" => "16.414110"
                                ];
                            }
                            else {
                                $gmaps = $geoclient->request("GET", "https://maps.googleapis.com/maps/api/geocode/json?address=".rawurlencode($location)."&key=".env("GMAPS_KEY")."&region=at&language=de", ["headers" => [
                                    'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/87.0.4280.66 Safari/537.36',
                                ]]);
                                
                                $loc = json_decode((string) $gmaps->getBody());
                                
                                if(property_exists($loc, "results") && is_countable($loc->results)) {
                                    $locationResult = [
                                        "label" =>  $location,
                                        "address" => $loc->results[0]->formatted_address,
                                        "lat" => $loc->results[0]->geometry->location->lat,
                                        "lon" => $loc->results[0]->geometry->location->lng,
                                    ];
                                    $dbloc->fill($locationResult);
                                    $dbloc->save();
                                }
                            }
                        } else {
                            $locationResult = $dbloc;
                        }
                    }
                } 
            }
        }
    }
    
    $duties = [];
    $teamtables = $dom->find('.DDL, .NORD, .WEST, .BVS, .VS');
    $team = [];
    foreach ($teamtables as $table) {
        $dom->loadStr($table);
        $rows = $dom->find('tr');
        $fields = $dom->loadStr($rows[0])->find("td");
        $team[] = getUserfromAmb($fields);
    }
    return ["description" => $amb_description, "team" => $team, "location" => $locationResult];
}

function debugGetAmbDetails() {
    $url = "https://niu.wrk.at/Kripo/Ambulances/AmbulancesEdit.aspx?AmbulanceID=8180&AmbulanceNr=2022%2f00001&AmbulanceDayID=27406&AmbulanceDayNr=34";
    $client = new GuzzleHttp\Client();
    $amb_response = $client->request('GET', $url, ['auth' => [$GLOBALS["username"], $GLOBALS["password"]], 'allow_redirects' => true, 'cookies' => $GLOBALS["jar"]]);
    $amb_response = $client->request('GET', $url, ['auth' => [$GLOBALS["username"], $GLOBALS["password"]], 'allow_redirects' => true, 'cookies' => $GLOBALS["jar"]]);
    $dom = new Dom;
    $dom->loadStr((string) $amb_response->getBody());
    $desc = $dom->find('#ctl00_main_m_AmbulanceDisplay_m_Webinfo')->innerHtml;
    $amb_description = strip_tags($desc);
    $descparts = explode("<p>", $desc);
    $locationResult = [
        "label" => "Wien, Österreich",
        "address" => "Wien, Österreich",
        "lat" => 48.2081743,
        "lon" =>  16.3738189
    ];
    foreach($descparts as $part) {
        if(strpos($part, "Wo:")!==false) {
            $re = '/.*<b>Wo:<\/b>\s*([^\n\r]*)/';
            preg_match_all($re, $part, $matches, PREG_SET_ORDER, 0);
            if(is_countable($matches[0])) {
                if(count($matches[0]) > 1 ) {
                    $location = trim(str_replace("Für Verpflegung ist gesorgt!", "", $matches[0][1]));
                    $geoclient = new GuzzleHttp\Client();
                    if(strlen($location) > 0 ) {
                        $dbloc = \Models\Location::whereLabel($location)->first();
                        if(is_null($dbloc)) {
                            $dbloc = new \Models\Location();
                            if(strpos(strtolower($location), "nodo") !== false) {
                                $locationResult = [
                                    "label" =>  "Wiener Rotes Kreuz Zentrale",
                                    "address" => "Nottendorfer Gasse 21, 1030 Wien",
                                    "lat" => "48.190650",
                                    "lon" => "16.411500"
                                ];
                            } else if (strpos(strtolower($location), "abz") !== false) {
                                $locationResult = [
                                    "label" =>  "Wiener Rotes Kreuz - Ausbildungszentrum",
                                    "address" => "Safargasse 4, 1030 Wien",
                                    "lat" => "48.189579",
                                    "lon" => "16.414110"
                                ];
                            }
                            else {
                                $gmaps = $geoclient->request("GET", "https://maps.googleapis.com/maps/api/geocode/json?address=".rawurlencode($location)."&key=".env("GMAPS_KEY")."&region=at&language=de", ["headers" => [
                                    'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/87.0.4280.66 Safari/537.36',
                                ]]);
                                
                                $loc = json_decode((string) $gmaps->getBody());
                                
                                if(property_exists($loc, "results") && is_countable($loc->results)) {
                                    $locationResult = [
                                        "label" =>  $location,
                                        "address" => $loc->results[0]->formatted_address,
                                        "lat" => $loc->results[0]->geometry->location->lat,
                                        "lon" => $loc->results[0]->geometry->location->lng,
                                    ];
                                    $dbloc->fill($locationResult);
                                    $dbloc->save();
                                }
                            }
                        } else {
                            $locationResult = $dbloc;
                        }
                    }
                } 
            }
        }
    }
    
    $duties = [];
    $teamtables = $dom->find('.DDL, .NORD, .WEST, .BVS, .VS');
    $team = [];
    foreach ($teamtables as $table) {
        $dom->loadStr($table);
        $rows = $dom->find('tr');
        $fields = $dom->loadStr($rows[0])->find("td");
        $team[] = getUserfromAmb($fields);
    }
    return ["description" => $amb_description, "team" => $team, "location" => $locationResult];
}

function getUserFromAmb($entry) {
    $dom = new Dom;
    $parts = $dom->loadStr($entry[0]->innerHtml)->find('span');
    $user = [];
    $user = $entry[2]->innerHtml;
    return $user;
}

function replaceHex($string) {
    $string = str_replace("&#162;", "¢", $string);
    $string = str_replace("&#163;", "£", $string);
    $string = str_replace("&#164;", "€", $string);
    $string = str_replace("&#165;", "¥", $string);
    $string = str_replace("&#176;", "°", $string);
    $string = str_replace("&#188;", "¼", $string);
    $string = str_replace("&#188;", "Œ", $string);
    $string = str_replace("&#189;", "½", $string);
    $string = str_replace("&#189;", "œ", $string);
    $string = str_replace("&#190;", "¾", $string);
    $string = str_replace("&#190;", "Ÿ", $string);
    $string = str_replace("&#161;", "¡", $string);
    $string = str_replace("&#171;", "«", $string);
    $string = str_replace("&#187;", "»", $string);
    $string = str_replace("&#191;", "¿", $string);
    $string = str_replace("&#192;", "À", $string);
    $string = str_replace("&#193;", "Á", $string);
    $string = str_replace("&#194;", "Â", $string);
    $string = str_replace("&#195;", "Ã", $string);
    $string = str_replace("&#196;", "Ä", $string);
    $string = str_replace("&#197;", "Å", $string);
    $string = str_replace("&#198;", "Æ", $string);
    $string = str_replace("&#199;", "Ç", $string);
    $string = str_replace("&#200;", "È", $string);
    $string = str_replace("&#201;", "É", $string);
    $string = str_replace("&#202;", "Ê", $string);
    $string = str_replace("&#203;", "Ë", $string);
    $string = str_replace("&#204;", "Ì", $string);
    $string = str_replace("&#205;", "Í", $string);
    $string = str_replace("&#206;", "Î", $string);
    $string = str_replace("&#207;", "Ï", $string);
    $string = str_replace("&#208;", "Ð", $string);
    $string = str_replace("&#209;", "Ñ", $string);
    $string = str_replace("&#210;", "Ò", $string);
    $string = str_replace("&#211;", "Ó", $string);
    $string = str_replace("&#212;", "Ô", $string);
    $string = str_replace("&#213;", "Õ", $string);
    $string = str_replace("&#214;", "Ö", $string);
    $string = str_replace("&#216;", "Ø", $string);
    $string = str_replace("&#217;", "Ù", $string);
    $string = str_replace("&#218;", "Ú", $string);
    $string = str_replace("&#219;", "Û", $string);
    $string = str_replace("&#220;", "Ü", $string);
    $string = str_replace("&#221;", "Ý", $string);
    $string = str_replace("&#222;", "Þ", $string);
    $string = str_replace("&#223;", "ß", $string);
    $string = str_replace("&#224;", "à", $string);
    $string = str_replace("&#225;", "á", $string);
    $string = str_replace("&#226;", "â", $string);
    $string = str_replace("&#227;", "ã", $string);
    $string = str_replace("&#228;", "ä", $string);
    $string = str_replace("&#229;", "å", $string);
    $string = str_replace("&#230;", "æ", $string);
    $string = str_replace("&#231;", "ç", $string);
    $string = str_replace("&#232;", "è", $string);
    $string = str_replace("&#233;", "é", $string);
    $string = str_replace("&#234;", "ê", $string);
    $string = str_replace("&#235;", "ë", $string);
    $string = str_replace("&#236;", "ì", $string);
    $string = str_replace("&#237;", "í", $string);
    $string = str_replace("&#238;", "î", $string);
    $string = str_replace("&#239;", "ï", $string);
    $string = str_replace("&#240;", "ð", $string);
    $string = str_replace("&#241;", "ñ", $string);
    $string = str_replace("&#242;", "ò", $string);
    $string = str_replace("&#243;", "ó", $string);
    $string = str_replace("&#244;", "ô", $string);
    $string = str_replace("&#245;", "õ", $string);
    $string = str_replace("&#246;", "ö", $string);
    $string = str_replace("&#248;", "ø", $string);
    $string = str_replace("&#249;", "ù", $string);
    $string = str_replace("&#250;", "ú", $string);
    $string = str_replace("&#251;", "û", $string);
    $string = str_replace("&#252;", "ü", $string);
    $string = str_replace("&#253;", "ý", $string);
    $string = str_replace("&#254;", "þ", $string);
    $string = str_replace("&#255;", "ÿ", $string);
    return $string;
}

function parseLocation($title, $location) {
    $locations = [
        "BVS" => [
            "label" => "Wiener Rotes Kreuz Bezirkstelle Bertha Van Suttner",
            "address" => "Negerlegasse 4/3, 1020 Wien",
            "lat" => "48.214100",
            "lon" => "16.312961"
        ],
        "VS" => [
            "label" => "Wiener Rotes Kreuz Bezirkstelle Van Swieten",
            "address" => "Landgutgasse 8, 1100 Wien",
            "lat" => "48.181430",
            "lon" => "16.377530"
        ],
        "Nord" => [
            "label" => "Wiener Rotes Kreuz Bezirkstelle Nord",
            "address" => "Karl-Schäfer-Straße 8, 1210 Wien",
            "lat" => "48.267200",
            "lon" => "16.401990"
        ],
        "West" => [
            "label" => "Wiener Rotes Kreuz Bezirkstelle West",
            "address" => "Spallartgasse 10A, 1140 Wien, 1. Stock",
            "lat" => "48.200580",
            "lon" => "16.308870"
        ],
        "Nodo" => [
            "label" => "Wiener Rotes Kreuz Zentrale",
            "address" => "Nottendorfer Gasse 21, 1030 Wien, Hinteres Gebäude, 2. Stock",
            "lat" => "48.190650",
            "lon" => "16.411500"
        ],
        "Arsenal" => [
            "label" => "Berufsrettung Wien Rettungsstation Arsenal",
            "address" => "Arsenalstraße 7, 1030 Wien",
            "lat" => "48.179330",
            "lon" => "16.391490"
        ],
        "Penzing" => [
            "label" => "Berufsrettung Wien Rettungsstation Penzing",
            "address" => "Baumgartenstraße 7, 1140 Wien",
            "lat" => "48.193700",
            "lon" => "16.285420"
        ],
        "ABZ" => [
            "label" => "Wiener Rotes Kreuz Ausbildungszentrum",
            "address" => "Safargasse 4, 1030 Wien",
            "lat" => "48.189580",
            "lon" => "16.414110"
        ],
        "Leitstelle WRK" => [
            "label" => "Wiener Rotes Kreuz Leitstelle",
            "address" => "Modecenterstraße 14, 1030 Wien",
            "lat" => "48.185900",
            "lon" => "16.415050"
        ],
    ];
    $loc = "";
    if (stripos($title, "KTW") !== false) {
        //let's assume, this is a KTW duty. This means possible locations are BVS, VS, Nord, West, Nodo.
        if (strtolower($location) == "lv" || strtolower($location) == "rd" || strtolower($location) == "ddl") {
            Location::whereShortlabel("Nodo")->first()->toArray();
        } else {
            Location::whereShortlabel(ucfirst($location))->first()->toArray();
        }
    } else if (stripos($title, "RK") !== false) {
        //let's assume, this is a RTW duty. This means possible locations are Nodo, Arsenal and Penzing.
        if (strtolower($location) == "lv" || strtolower($location) == "rd" || strtolower($location) == "ddl") {
            Location::whereShortlabel("Nodo")->first()->toArray();
        } else if (strtolower($location) == "west") {
            Location::whereShortlabel("Penzing")->first()->toArray();
        } else if (strtolower($location) == "vs") {
            Location::whereShortlabel("Arsenal")->first()->toArray();
        }
    } else if (stripos($title, "Journal") !== false) {
        Location::whereShortlabel("Leitstelle WRK")->first()->toArray();
    } else if (stripos($title, "ÄFD-Calltaker") !== false) {
        $loc = Location::whereShortlabel("Leitstelle ÄFD")->first()->toArray();
    } else {
        $loc = Location::whereShortlabel($title)->first();
        if(!is_null($loc)) {
            dd($title);
            $loc = $loc->toArray();
        }
    }
    return $loc;
}

function parseDate($date, $timestring) {
    $timeparts = explode("-", $timestring);
    $start = trim($timeparts[0]);
    $end = trim($timeparts[1]);


    //check if its day or night shift

    $start_hours = substr($start, 0, 2);
    $end_hours = substr($end, 0, 2);
    $night = false;
    if ($start_hours > $end_hours) {
        $night = true;
    }

    $duration = 0;
    $ds = new CarbonImmutable($date);
    if ($night) {
        $de = $ds->addDays(1);
    } else {
        $de = $ds;
    }

    $starttime = CarbonImmutable::parse($ds->year . "-" . $ds->month . "-" . $ds->day . " " . $start, "Europe/Vienna");
    $endtime = CarbonImmutable::parse($de->year . "-" . $de->month . "-" . $de->day . " " . $end, "Europe/Vienna");




    // $starttime = new CarbonImmutable($date." ".$start, "Europe/Vienna");
    // $endtime = $starttime->addSeconds(abs($etmp - $stmp));
    return ["start" => $starttime, "end" => $endtime];
}

function generateHash($title, $date, $timestring) {
    $GLOBALS["i"]++;
    if (gettype($date) == "array") {
        return substr(md5($GLOBALS["i"] . " " . $title . " " . $date['start']->toDateString() . " " . $timestring), 0, 10);
    } else {
        return substr(md5($GLOBALS["i"] . " " . $title . "" . $date . "" . $timestring), 0, 10);
    }
}

function makeICalendar($events, $name, $dateStart, $dateEnd, $alarms = null) {
    $url = array_key_exists("auth", $_GET) ? env('SCRIPT_URL') . "/?auth=" . $_GET["auth"] : env('SCRIPT_URL');

    $vcal = "BEGIN:VCALENDAR\r\n";
    $vcal .= "VERSION:2.0\r\n";
    $vcal .= "PRODID:-//DS web and app WRK Dutyschedule//NONSGML Dutyschedule Red Cross Austria, Regional Branch Vienna v1.0//DE\r\n";
    $vcal .= "X-FROM-URL:" . $url . "\r\n";
    $vcal .= "X-WR-RELCALID:WRK_Dutyschedule\r\n";
    $vcal .= "X-PUBLISHED-TTL:PT15M\r\n";
    $vcal .= "REFRESH-INTERVAL;VALUE=DURATION:P15M\r\n";
    $vcal .= "SOURCE;VALUE=URI:" . $url . "\r\n";
    $vcal .= "COLOR:darkred\r\n";
    $vcal .= "NAME:Dienstplan von " . $name . "\r\n";
    $vcal .= "X-WR-CALNAME: Dienstplan von " . $name . "\r\n";
    $vcal .= "DESCRIPTION: Dieser Kalender beinhält alle Dienste, Fortbildungen und Ambulanzen aus dem Zeitraum " . $dateStart . " bis " . $dateEnd . "\r\n";
    $vcal .= "X-WR-CALDESC: Dieser Kalender beinhält alle Dienste, Fortbildungen und Ambulanzen aus dem Zeitraum " . $dateStart . " bis " . $dateEnd . "\r\n";
    $vcal .= "X-WR-TIMEZONE:Europe/Vienna\r\n";
    $vcal .= "CALSCALE:GREGORIAN\r\n";
    $vcal .= "BEGIN:VTIMEZONE\r\n";
    $vcal .= "TZID:Europe/Vienna\r\n";
    $vcal .= "X-LIC-LOCATION:Europe/Vienna\r\n";
    $vcal .= "BEGIN:DAYLIGHT\r\n";
    $vcal .= "TZOFFSETFROM:+0100\r\n";
    $vcal .= "TZOFFSETTO:+0200\r\n";
    $vcal .= "TZNAME:CEST\r\n";
    $vcal .= "DTSTART:19700329T020000\r\n";
    $vcal .= "RRULE:FREQ=YEARLY;BYDAY=-1SU;BYMONTH=3\r\n";
    $vcal .= "END:DAYLIGHT\r\n";
    $vcal .= "BEGIN:STANDARD\r\n";
    $vcal .= "TZOFFSETFROM:+0200\r\n";
    $vcal .= "TZOFFSETTO:+0100\r\n";
    $vcal .= "TZNAME:CET\r\n";
    $vcal .= "DTSTART:19701025T030000\r\n";
    $vcal .= "RRULE:FREQ=YEARLY;BYDAY=-1SU;BYMONTH=10\r\n";
    $vcal .= "END:STANDARD\r\n";
    $vcal .= "END:VTIMEZONE\r\n";

    foreach ($events as $event) {
        try {
            $GLOBALS["eventlog"]->info(json_encode($event));
            $vcal .= makeVEVENT($event);
        } catch (Exception $ex) {
            throw $ex;
        }
    }
    
    
    $vcal .= "END:VCALENDAR";
    

    $foldedVCal = "";
    $lines = explode("\r\n", $vcal);
    foreach ($lines as $line) {
        $foldedVCal .= ical_split($line) . "\r\n";
    }
    return $foldedVCal;
}

function makeVEVENT($event) {

    $description = $event["description"];
    $description = replaceHex($description);
    $description = html_entity_decode($description, ENT_HTML5, "UTF-8");
    $description = strip_tags(str_replace(["<br>", "\n", "\r\n", ",", ";"], ["\\n", "\\n", "\\n", "\,", "\;"], $description));

    $category = "RD-DIENST";
    switch ($event["dutytype"]) {
        case "AMB":
            $category = "Ambulanzdienst";
            break;
        case "COURSE":
            $category = "Fortbildung";
            break;
        default:
            $category = $event["dutytype"];
            break;
    }

    $vevent = "BEGIN:VEVENT\r\n";
    $vevent .= "UID:" . $event['hash'] . "@dutyschedule.danielsteiner.net\r\n";
    $vevent .= "DTSTAMP:" . str_replace([":", "-"], "", $event['time']['start']->toDateTimeLocalString()) . "\r\n";
    $vevent .= "CATEGORIES:" . $category . "\r\n";
    $vevent .= "CLASS:PRIVATE\r\n";
    if ($event["time"]["start"]->toDateTimeLocalString() === $event["time"]["end"]->toDateTimeLocalString()) {
        // Bereitschaftsdienst, full day
        $vevent .= "DTSTART;VALUE=DATE:" . str_replace([":", "-"], "", $event['time']['start']->format("Ymd")) . "\r\n";
    } else {
        $vevent .= "DTSTART;TZID=Europe/Vienna:" . str_replace([":", "-"], "", $event['time']['start']->toDateTimeLocalString()) . "\r\n";
        $vevent .= "DTEND;TZID=Europe/Vienna:" . str_replace([":", "-"], "", $event['time']["end"]->toDateTimeLocalString()) . "\r\n";
    }
    $cts = [
        "SAN - Weiterbildung - ",
        "SAN - Fortbildung - ",
        "SAN - Ausbildung - ",
        "Webinar - ",
        "LBA - Ausbildung - ",
        "SEF - Ausbildung - ",
        "SEF - Fortbildung - ",
        "FKW - Ausbildung - ",
        "BAS - Ausbildung - ",
        "FKR - Ausbildung - ",
        "KHD - Ausbildung - ",
        "WRK - Ausbildung - ",
        "FSD - Ausbildung -  ",
        "FSD - Fortbildung -  ",
    ];
    $title = str_replace($cts, "", $event['title']);
    $vevent .= "SUMMARY:" . $title . "\r\n";
    $vevent .= "DESCRIPTION:" . $description . "\r\n";
    $vevent .= "X-ALT-DESC;FMTTYPE=text/html:<html><body>" . $description . "</body></html>\r\n";
    $vevent .= "STATUS:" . $event['status'] . "\r\n";
    $vevent .= "TRANSP:OPAQUE\r\n";
    if(array_key_exists('location', $event)){
        if (is_array($event['location'])) {
            $vevent .= "LOCATION:" . $event['location']['label'] . " " . $event['location']['address'] . "\r\n";
            $vevent .= "GEO:" . $event['location']['lat'] . ";" . $event['location']['lon'] . "\r\n";
        }
    }

    if (array_key_exists("mandatory", $event["team"])) {
        foreach ($event["team"] as $type => $members) {
            $attendeetype = $type === "mandatory" ? "REQ-PARTICIPANT" : "OPT-PARTICIPANT";
            foreach ($members as $member) {
                if (!empty($member)) {
                    $member = getFullUser($member, "course");
                    if(!is_null($member)) {
                        $vevent .= "ATTENDEE;ROLE=" . $attendeetype . ";PARTSTAT=ACCEPTED;CN=" . $member['name'] . ";DIR=" . $member["url"] . ":MAILTO:nirvana@w.roteskreuz.at\r\n";
                    }
                }
            }
        }
    } else {
        foreach ($event["team"] as $member) {
            if (!empty($member)) {
                $member = getFullUser($member);
                if(!is_null($member)) {
                    $vevent .= "ATTENDEE;ROLE=REQ-PARTICIPANT;PARTSTAT=ACCEPTED;CN=" . $member['name'] . ";DIR=" . $member["url"] . ":MAILTO:nirvana@w.roteskreuz.at\r\n";
                }
            }
        }
    }
    if(array_key_exists('url', $event)) {
        $vevent .= "URL:".$event["url"]."\r\n";
    }
    $vevent .= "END:VEVENT\r\n";
    return $vevent;
}

function ical_split($value) {
    $value = trim($value);
    $value = strip_tags($value);
    $value = preg_replace('/\n+/', ' ', $value);
    $value = preg_replace('/\s{2,}/', ' ', $value);

    $lines = array();
    while (strlen($value) > 75) {
        $space = 73;
        $mbcc = $space;
        while ($mbcc) {
            $line = mb_substr($value, 0, $mbcc);
            $oct = strlen($line);
            if ($oct > $space) {
                $mbcc -= $oct - $space;
            } else {
                $lines[] = $line;
                $value = mb_substr($value, $mbcc);
                break;
            }
        }
    }
    if (!empty($value)) {
        $lines[] = $value;
    }

    return implode("\r\n ", $lines);
}

function getUrl($type, $id) {
    if($type === "SEmpFID") {
        return "https://niu.wrk.at/Kripo/Employee/shortemployee.aspx?EmployeeID=".$id;
    } else if ($type === "SEmpFNRID") {
        return "https://niu.wrk.at/Kripo/Employee/shortemployee.aspx?EmployeeNumberID=".$id;
    } else {
        return null;
    }
}

function createRange($start, $end, $format = 'Y-m-d') {
    $start  = new DateTime($start);
    $end    = new DateTime($end);
    $invert = $start > $end;
    $dates = array();
    $dates[] = $start->format($format);
    while ($start != $end) {
        $start->modify(($invert ? '-' : '+') . '1 day');
        $dates[] = $start->format($format);
    }
    return $dates;
}

function getFZGTagebuchLink($vehicle) {
    if($vehicle === "000") {
        return [
            'link' => '',
            'radioid' => '',
            'type' => ''
        ];
    }
    
    $vehicles = [
        '002' => [ 
            'link' => 'https://intranet.wrk.at/confluence/pages/viewpage.action?pageId=130089271', 
            'radioid' => '2-41/002', 
            'type' => 'Mercedes Benz Sprinter, RTW'
        ], 
        '003' => [ 
            'link' => 'https://intranet.wrk.at/confluence/pages/viewpage.action?pageId=130089275', 
            'radioid' => '2-41/003', 
            'type' => 'Mercedes Benz Sprinter, RTW'
        ], 
        '004' => [ 
            'link' => 'https://intranet.wrk.at/confluence/pages/viewpage.action?pageId=130089279', 
            'radioid' => '2-41/004', 
            'type' => 'Mercedes Benz Sprinter, RTW'
        ], 
        '005' => [ 
            'link' => 'https://intranet.wrk.at/confluence/pages/viewpage.action?pageId=130089295', 
            'radioid' => '2-41/005', 
            'type' => 'Mercedes Benz Sprinter, RTW'
        ], 
        
        '008' => [ 
            'link' => 'https://intranet.wrk.at/confluence/pages/viewpage.action?pageId=143680602', 
            'radioid' => '2-41/008', 
            'type' => 'Mercedes Benz Sprinter, RTW'
        ], 
        '009' => [ 
            'link' => 'https://intranet.wrk.at/confluence/pages/viewpage.action?pageId=143680618', 
            'radioid' => '2-41/009', 
            'type' => 'Mercedes Benz Sprinter, RTW'
        ], 
        '011' => [ 
            'link' => 'https://intranet.wrk.at/confluence/pages/viewpage.action?pageId=130089287', 
            'radioid' => '2-41/011', 
            'type' => 'Mercedes Benz Sprinter, RTW'
        ], 
        '016' => [ 
            'link' => 'https://intranet.wrk.at/confluence/pages/viewpage.action?pageId=130089291', 
            'radioid' => '2-41/016', 
            'type' => 'Mercedes Benz Sprinter, RTW'
        ], 
        '300' => [ 
            'link' => 'https://intranet.wrk.at/confluence/pages/viewpage.action?pageId=130089283', 
            'radioid' => '2-41/300', 
            'type' => 'Mercedes Benz Sprinter, RTW'
        ], 
        '301' => [ 
            'link' => 'https://intranet.wrk.at/confluence/pages/viewpage.action?pageId=130089299', 
            'radioid' => '2-41/301', 
            'type' => 'Mercedes Benz Sprinter, RTW'
        ],
        
        '006' => [
            'link' => 'https://intranet.wrk.at/confluence/pages/viewpage.action?pageId=130102334',
            'radioid' => '2-41/006',
            'type' => 'VW T6 Hochdach',
        ], 
        '007' => [
            'link' => 'https://intranet.wrk.at/confluence/pages/viewpage.action?pageId=130102340',
            'radioid' => '2-41/007',
            'type' => 'VW T6 Hochdach',
        ], 
        '025' => [
            'link' => 'https://intranet.wrk.at/confluence/pages/viewpage.action?pageId=130089319',
            'radioid' => '2-41/025',
            'type' => 'VW T6 Hochdach',
        ], 
        '027' => [
            'link' => 'https://intranet.wrk.at/confluence/pages/viewpage.action?pageId=168788438',
            'radioid' => '2-41/027',
            'type' => 'VW T6 Hochdach',
        ], 
        '040' => [
            'link' => 'https://intranet.wrk.at/confluence/pages/viewpage.action?pageId=168788449',
            'radioid' => '2-41/040',
            'type' => 'VW T6 Hochdach',
        ], 

        '010' => [
            'link' => 'https://intranet.wrk.at/confluence/pages/viewpage.action?pageId=130089107',
            'radioid' => '2-41/010',
            'type' => 'VW T5/6 Mittelhochdach',
        ], 
        '012' => [
            'link' => 'https://intranet.wrk.at/confluence/pages/viewpage.action?pageId=130089303',
            'radioid' => '2-41/012',
            'type' => 'VW T5/6 Mittelhochdach',
        ], 
        '017' => [
            'link' => 'https://intranet.wrk.at/confluence/pages/viewpage.action?pageId=176041505',
            'radioid' => '2-41/017',
            'type' => 'Mercedes Benz Sprinter, KTW',
        ], 
        '018' => [
            'link' => 'https://intranet.wrk.at/confluence/pages/viewpage.action?pageId=176041535',
            'radioid' => '2-41/018',
            'type' => 'Mercedes Benz Sprinter, KTW',
        ], 
        '019' => [
            'link' => 'https://intranet.wrk.at/confluence/pages/viewpage.action?pageId=176041661',
            'radioid' => '2-41/019',
            'type' => 'Mercedes Benz Sprinter, KTW',
        ], 
        '020' => [
            'link' => 'https://intranet.wrk.at/confluence/pages/viewpage.action?pageId=176041665',
            'radioid' => '2-41/020',
            'type' => 'Mercedes Benz Sprinter, KTW',
        ], 
        '021' => [
            'link' => 'https://intranet.wrk.at/confluence/pages/viewpage.action?pageId=130089307',
            'radioid' => '2-41/021',
            'type' => 'VW T5/6 Mittelhochdach',
        ], 
        '022' => [
            'link' => 'https://intranet.wrk.at/confluence/pages/viewpage.action?pageId=130089311',
            'radioid' => '2-41/022',
            'type' => 'VW T5/6 Mittelhochdach',
        ], 
        '023' => [
            'link' => 'https://intranet.wrk.at/confluence/pages/viewpage.action?pageId=130089315',
            'radioid' => '2-41/023',
            'type' => 'VW T5/6 Mittelhochdach',
        ], 
        '026' => [
            'link' => 'https://intranet.wrk.at/confluence/pages/viewpage.action?pageId=130089323',
            'radioid' => '2-41/026',
            'type' => 'VW T5/6 Mittelhochdach',
        ], 
        '028' => [
            'link' => 'https://intranet.wrk.at/confluence/pages/viewpage.action?pageId=130089327',
            'radioid' => '2-41/028',
            'type' => 'VW T5/6 Mittelhochdach',
        ], 
        '029' => [
            'link' => 'https://intranet.wrk.at/confluence/pages/viewpage.action?pageId=130089331',
            'radioid' => '2-41/029',
            'type' => 'VW T5/6 Mittelhochdach',
        ], 
        '031' => [
            'link' => 'https://intranet.wrk.at/confluence/pages/viewpage.action?pageId=130089335',
            'radioid' => '2-41/031',
            'type' => 'VW T5/6 Mittelhochdach',
        ], 
        '032' => [
            'link' => 'https://intranet.wrk.at/confluence/pages/viewpage.action?pageId=130089339',
            'radioid' => '2-41/032',
            'type' => 'VW T5/6 Mittelhochdach',
        ], 
        '033' => [
            'link' => 'https://intranet.wrk.at/confluence/pages/viewpage.action?pageId=130089343',
            'radioid' => '2-41/033',
            'type' => 'VW T5/6 Mittelhochdach',
        ], 
        '034' => [
            'link' => 'https://intranet.wrk.at/confluence/pages/viewpage.action?pageId=130089347',
            'radioid' => '2-41/034',
            'type' => 'VW T5/6 Mittelhochdach',
        ], 
        '035' => [
            'link' => 'https://intranet.wrk.at/confluence/pages/viewpage.action?pageId=130089351',
            'radioid' => '2-41/035',
            'type' => 'Mercedes Benz Sprinter, KTW',
        ], 
        '036' => [
            'link' => 'https://intranet.wrk.at/confluence/pages/viewpage.action?pageId=130089355',
            'radioid' => '2-41/036',
            'type' => 'VW T5/6 Mittelhochdach',
        ], 
        '037' => [
            'link' => 'https://intranet.wrk.at/confluence/pages/viewpage.action?pageId=130089359',
            'radioid' => '2-41/037',
            'type' => 'VW T5/6 Mittelhochdach',
        ], 
        '041' => [
            'link' => 'https://intranet.wrk.at/confluence/pages/viewpage.action?pageId=130089363',
            'radioid' => '2-41/041',
            'type' => 'VW T5/6 Mittelhochdach',
        ], 
        '044' => [
            'link' => 'https://intranet.wrk.at/confluence/pages/viewpage.action?pageId=130089367',
            'radioid' => '2-41/044',
            'type' => 'Mercedes Benz Sprinter, KTW',
        ], 
        '045' => [
            'link' => 'https://intranet.wrk.at/confluence/pages/viewpage.action?pageId=130089371',
            'radioid' => '2-41/045',
            'type' => 'VW T5/6 Mittelhochdach',
        ], 
        '046' => [
            'link' => 'https://intranet.wrk.at/confluence/pages/viewpage.action?pageId=130089375',
            'radioid' => '2-41/046',
            'type' => 'VW T5/6 Mittelhochdach',
        ], 
        '047' => [
            'link' => 'https://intranet.wrk.at/confluence/pages/viewpage.action?pageId=130089379',
            'radioid' => '2-41/047',
            'type' => 'VW T5/6 Mittelhochdach',
        ], 
        '050' => [
            'link' => 'https://intranet.wrk.at/confluence/pages/viewpage.action?pageId=130089383',
            'radioid' => '2-41/050',
            'type' => 'VW T5/6 Mittelhochdach',
        ], 
        '053' => [
            'link' => 'https://intranet.wrk.at/confluence/pages/viewpage.action?pageId=130089387',
            'radioid' => '2-41/053',
            'type' => 'VW T5/6 Mittelhochdach',
        ], 
        '054' => [
            'link' => 'https://intranet.wrk.at/confluence/pages/viewpage.action?pageId=150576433',
            'radioid' => '2-41/054',
            'type' => 'Mercedes Benz Sprinter, KTW',
        ], 
        '055' => [
            'link' => 'https://intranet.wrk.at/confluence/pages/viewpage.action?pageId=150569477',
            'radioid' => '2-41/055',
            'type' => 'Mercedes Benz Sprinter, KTW',
        ], 
        '056' => [
            'link' => 'https://intranet.wrk.at/confluence/pages/viewpage.action?pageId=130089391',
            'radioid' => '2-41/056',
            'type' => 'Mercedes Benz Sprinter, KTW',
        ], 
        '057' => [
            'link' => 'https://intranet.wrk.at/confluence/pages/viewpage.action?pageId=130089395',
            'radioid' => '2-41/057',
            'type' => 'VW T5/6 Mittelhochdach',
        ], 
        '060' => [
            'link' => 'https://intranet.wrk.at/confluence/pages/viewpage.action?pageId=150572197',
            'radioid' => '2-41/060',
            'type' => 'Mercedes Benz Sprinter, KTW',
        ], 
        '061' => [
            'link' => 'https://intranet.wrk.at/confluence/pages/viewpage.action?pageId=130089399',
            'radioid' => '2-41/061',
            'type' => 'VW T5/6 Mittelhochdach',
        ], 
        '062' => [
            'link' => 'https://intranet.wrk.at/confluence/pages/viewpage.action?pageId=130089403',
            'radioid' => '2-41/062',
            'type' => 'VW T5/6 Mittelhochdach',
        ], 
        '063' => [
            'link' => 'https://intranet.wrk.at/confluence/pages/viewpage.action?pageId=150572200',
            'radioid' => '2-41/063',
            'type' => 'Mercedes Benz Sprinter, KTW',
        ], 
        '064' => [
            'link' => 'https://intranet.wrk.at/confluence/pages/viewpage.action?pageId=150576435',
            'radioid' => '2-41/064',
            'type' => 'Mercedes Benz Sprinter, KTW',
        ], 
        '065' => [
            'link' => 'https://intranet.wrk.at/confluence/pages/viewpage.action?pageId=130089410',
            'radioid' => '2-41/065',
            'type' => 'VW T5/6 Mittelhochdach',
        ], 
        '066' => [
            'link' => 'https://intranet.wrk.at/confluence/pages/viewpage.action?pageId=150593764',
            'radioid' => '2-41/066',
            'type' => 'Mercedes Benz Sprinter, KTW',
        ], 
        '067' => [
            'link' => 'https://intranet.wrk.at/confluence/pages/viewpage.action?pageId=150593766',
            'radioid' => '2-41/067',
            'type' => 'Mercedes Benz Sprinter, KTW',
        ], 
        '068' => [
            'link' => 'https://intranet.wrk.at/confluence/pages/viewpage.action?pageId=150597817',
            'radioid' => '2-41/068',
            'type' => 'Mercedes Benz Sprinter, KTW',
        ], 
        '069' => [
            'link' => 'https://intranet.wrk.at/confluence/pages/viewpage.action?pageId=150597824',
            'radioid' => '2-41/069',
            'type' => 'Mercedes Benz Sprinter, KTW',
        ], 
        '070' => [
            'link' => 'https://intranet.wrk.at/confluence/pages/viewpage.action?pageId=130089414',
            'radioid' => '2-41/070',
            'type' => 'VW T5/6 Mittelhochdach',
        ], 
        '071' => [
            'link' => 'https://intranet.wrk.at/confluence/pages/viewpage.action?pageId=130089418',
            'radioid' => '2-41/071',
            'type' => 'VW T5/6 Mittelhochdach',
        ], 
        '072' => [
            'link' => 'https://intranet.wrk.at/confluence/pages/viewpage.action?pageId=130089422',
            'radioid' => '2-41/072',
            'type' => 'VW T5/6 Mittelhochdach',
        ], 
        '074' => [
            'link' => 'https://intranet.wrk.at/confluence/pages/viewpage.action?pageId=130089426',
            'radioid' => '2-41/074',
            'type' => 'VW T5/6 Mittelhochdach',
        ], 
        '075' => [
            'link' => 'https://intranet.wrk.at/confluence/pages/viewpage.action?pageId=130089430',
            'radioid' => '2-41/075',
            'type' => 'VW T5/6 Mittelhochdach',
        ], 
        '076' => [
            'link' => 'https://intranet.wrk.at/confluence/pages/viewpage.action?pageId=130089434',
            'radioid' => '2-41/076',
            'type' => 'VW T5/6 Mittelhochdach',
        ], 
        '077' => [
            'link' => 'https://intranet.wrk.at/confluence/pages/viewpage.action?pageId=130089438',
            'radioid' => '2-41/077',
            'type' => 'VW T5/6 Mittelhochdach',
        ], 
        '078' => [
            'link' => 'https://intranet.wrk.at/confluence/pages/viewpage.action?pageId=130089442',
            'radioid' => '2-41/078',
            'type' => 'VW T5/6 Mittelhochdach',
        ], 
        '079' => [
            'link' => 'https://intranet.wrk.at/confluence/pages/viewpage.action?pageId=130089446',
            'radioid' => '2-41/079',
            'type' => 'VW T5/6 Mittelhochdach',
        ], 
        '080' => [
            'link' => 'https://intranet.wrk.at/confluence/pages/viewpage.action?pageId=130089450',
            'radioid' => '2-41/080',
            'type' => 'VW T5/6 Mittelhochdach',
        ], 
        '081' => [
            'link' => 'https://intranet.wrk.at/confluence/pages/viewpage.action?pageId=130089454',
            'radioid' => '2-41/081',
            'type' => 'VW T5/6 Mittelhochdach',
        ], 
        '082' => [
            'link' => 'https://intranet.wrk.at/confluence/pages/viewpage.action?pageId=130089458',
            'radioid' => '2-41/082',
            'type' => 'VW T5/6 Mittelhochdach',
        ], 
        '084' => [
            'link' => 'https://intranet.wrk.at/confluence/pages/viewpage.action?pageId=130089462',
            'radioid' => '2-41/084',
            'type' => 'VW T5/6 Mittelhochdach',
        ], 
        '085' => [
            'link' => 'https://intranet.wrk.at/confluence/pages/viewpage.action?pageId=130089466',
            'radioid' => '2-41/085',
            'type' => 'VW T5/6 Mittelhochdach',
        ], 
        '086' => [
            'link' => 'https://intranet.wrk.at/confluence/pages/viewpage.action?pageId=130089470',
            'radioid' => '2-41/086',
            'type' => 'VW T5/6 Mittelhochdach',
        ], 
        '087' => [
            'link' => 'https://intranet.wrk.at/confluence/pages/viewpage.action?pageId=130089474',
            'radioid' => '2-41/087',
            'type' => 'Mercedes Benz Sprinter, KTW',
        ], 
        '088' => [
            'link' => 'https://intranet.wrk.at/confluence/pages/viewpage.action?pageId=130089478',
            'radioid' => '2-41/088',
            'type' => 'VW T5/6 Mittelhochdach',
        ], 
        '089' => [
            'link' => 'https://intranet.wrk.at/confluence/pages/viewpage.action?pageId=130089482',
            'radioid' => '2-41/089',
            'type' => 'VW T5/6 Mittelhochdach',
        ], 
        '090' => [
            'link' => 'https://intranet.wrk.at/confluence/pages/viewpage.action?pageId=130089486',
            'radioid' => '2-41/090',
            'type' => 'VW T5/6 Mittelhochdach',
        ], 
        '091' => [
            'link' => 'https://intranet.wrk.at/confluence/pages/viewpage.action?pageId=130089490',
            'radioid' => '2-41/091',
            'type' => 'Mercedes Benz Sprinter, KTW',
        ], 
        '092' => [
            'link' => 'https://intranet.wrk.at/confluence/pages/viewpage.action?pageId=130089494',
            'radioid' => '2-41/092',
            'type' => 'VW T5/6 Mittelhochdach',
        ], 
        '093' => [
            'link' => 'https://intranet.wrk.at/confluence/pages/viewpage.action?pageId=130089498',
            'radioid' => '2-41/093',
            'type' => 'VW T5/6 Mittelhochdach',
        ], 
        '094' => [
            'link' => 'https://intranet.wrk.at/confluence/pages/viewpage.action?pageId=130089502',
            'radioid' => '2-41/094',
            'type' => 'VW T5/6 Mittelhochdach',
        ], 
        '095' => [
            'link' => 'https://intranet.wrk.at/confluence/pages/viewpage.action?pageId=130089506',
            'radioid' => '2-41/095',
            'type' => 'VW T5/6 Mittelhochdach',
        ], 
        '096' => [
            'link' => 'https://intranet.wrk.at/confluence/pages/viewpage.action?pageId=130089510',
            'radioid' => '2-41/096',
            'type' => 'VW T5/6 Mittelhochdach',
        ], 
        '098' => [
            'link' => 'https://intranet.wrk.at/confluence/pages/viewpage.action?pageId=150597826',
            'radioid' => '2-41/098',
            'type' => 'Mercedes Benz Sprinter, KTW',
        ], 
        '099' => [
            'link' => 'https://intranet.wrk.at/confluence/pages/viewpage.action?pageId=130089514',
            'radioid' => '2-41/099',
            'type' => 'VW T5/6 Mittelhochdach',
        ], 
        '163' => [
            'link' => 'https://intranet.wrk.at/confluence/pages/viewpage.action?pageId=130089542',
            'radioid' => '2-41/163',
            'type' => 'VW Golf, Rufhilfe',
        ], 
        '311' => [
            'link' => 'https://intranet.wrk.at/confluence/pages/viewpage.action?pageId=130089546',
            'radioid' => '2-41/311',
            'type' => 'VW Caddy',
        ], 
        '325' => [
            'link' => 'https://intranet.wrk.at/confluence/pages/viewpage.action?pageId=154655581',
            'radioid' => '2-41/325',
            'type' => 'Piaggio, REM'
        ], 
        '313' => [
            'link' => 'https://intranet.wrk.at/confluence/pages/viewpage.action?pageId=130089550',
            'radioid' => '2-41/313',
            'type' => 'IM Caddy, Ausgemustert ?',
        ], 
        '322' => [
            'link' => 'https://intranet.wrk.at/confluence/pages/viewpage.action?pageId=130089554',
            'radioid' => '2-41/322',
            'type' => 'VW Caddy, Ausgemustert?',
        ], 
        
        '326' => [
            'link' => 'https://intranet.wrk.at/confluence/pages/viewpage.action?pageId=154640478',
            'radioid' => '2-41/326',
            'type' => 'VW T5 KHD Bus, Ausgemustert?',
        ], 
        '348' => [
            'link' => 'https://intranet.wrk.at/confluence/pages/viewpage.action?pageId=130089538',
            'radioid' => '2-41/348',
            'type' => 'VW Caddy, Rufhilfe',
        ], 
        '353' => [
            'link' => 'https://intranet.wrk.at/confluence/pages/viewpage.action?pageId=130089534',
            'radioid' => '2-41/353',
            'type' => 'VW Caddy',
        ], 
        '361' => [
            'link' => 'https://intranet.wrk.at/confluence/pages/viewpage.action?pageId=130089558',
            'radioid' => '2-41/361',
            'type' => 'Seat Mii KUBE',
        ], 
        '366' => [
            'link' => 'https://intranet.wrk.at/confluence/pages/viewpage.action?pageId=130089518',
            'radioid' => '2-41/366',
            'type' => 'VW Caddy, Rufhilfe',
        ], 
        '367' => [
            'link' => 'https://intranet.wrk.at/confluence/pages/viewpage.action?pageId=130089522',
            'radioid' => '2-41/367',
            'type' => 'VW Caddy',
        ], 
        '368' => [
            'link' => 'https://intranet.wrk.at/confluence/pages/viewpage.action?pageId=130089526',
            'radioid' => '2-41/368',
            'type' => 'VW Caddy',
        ], 
        '369' => [
            'link' => 'https://intranet.wrk.at/confluence/pages/viewpage.action?pageId=130089530',
            'radioid' => '2-41/369',
            'type' => 'VW Caddy',
        ], 
        '441' => [
            'link' => 'https://intranet.wrk.at/confluence/pages/viewpage.action?pageId=184659186',
            'radioid' => '2-41/441',
            'type' => 'VW Caddy',
        ], 
        '467' => [
            'link' => 'https://intranet.wrk.at/confluence/pages/viewpage.action?pageId=184659242',
            'radioid' => '2-41/467',
            'type' => 'Seat Mii, Rufhilfe',
        ], 
    ];
    return $vehicles[$vehicle];
}

function strpos_arr($haystack, $needle) {
    if(!is_array($needle)) $needle = array($needle);
    foreach($needle as $what) {
        if(($pos = strpos($haystack, $what))!==false) return $pos;
    }
    return false;
}

function healthcheck($username) {
    $user = User::whereUsername($username)->first();
    if(is_null($user)) {
        $healthCheckUUID = createCheck($username);
        $user = User::whereUsername($username)->first();
        $user->username = $username; 
        $user->healthcheckuuid = $healthCheckUUID; 
        $user->save();
        $GLOBALS["eventlog"]->info("Created new Healthcheck for ".$username);
    }
    try{
        file_get_contents("https://servicehealth.danielsteiner.net/ping/".trim($user->healthcheckuuid));        
    } catch(Exception $ex) {
        $GLOBALS["eventlog"]->error($ex);
    }
}

function checkCredentials($username, $password) {
    $client = new GuzzleHttp\Client([
        'base_uri' => env("DATASOURCE_URL"),
        "verify" => false,
    ]);
    $jar = new \GuzzleHttp\Cookie\CookieJar;
    try {
        $authRequest = $client->request('GET', '/' , ['auth' => [$username, $password], 'allow_redirects' => true, 'cookies' => $jar]);
        $authResponseBody = (string)$authRequest->getBody();
        $authResponseCode = $authRequest->getStatusCode();
        if($authResponseCode===200) {
            return true; 
        }
    } catch (GuzzleHttp\Exception\ClientException $cex) {
        $GLOBALS["eventlog"] = new Logger('wrk-dutyschedule-events');
        $GLOBALS["eventlog"]->pushHandler(new StreamHandler(__DIR__."/../logs/events_".$username."_".date('y-m-d').".log", Logger::INFO));
        // $authResponseCode = $auth->response->getStatusCode();
        if(strpos($cex->getMessage(), "401 Unauthorized") !== false) {
            $GLOBALS["eventlog"]->info("Request for ".$username." failed due to invalid or missing credentials.");
        } else {
            $GLOBALS["eventlog"]->info("Request for ".$username." failed. ".$cex->getMessage());
        }
        return false;
    }
}

function checkKuferCredentials($username, $password) {
    $client = new GuzzleHttp\Client([
        'base_uri' => env("KUFER_URL"),
        "verify" => false,
    ]);
    $jar = new \GuzzleHttp\Cookie\CookieJar;
    try {
        $authRequest = $client->request('GET', '/' , ['auth' => [$username, $password], 'allow_redirects' => true, 'cookies' => $jar]);
        $authResponseCode = $authRequest->getStatusCode();
        if($authResponseCode===200) {
            return true; 
        }
    } catch (GuzzleHttp\Exception\ClientException $cex) {
        // $authResponseCode = $auth->response->getStatusCode();
        if(strpos($cex->getMessage(), "401 Unauthorized") !== false) {
            $GLOBALS["eventlog"]->info("Request for ".$username." failed due to invalid or missing credentials.");
        } else {
            $GLOBALS["eventlog"]->info("Request for ".$username." failed. ".$cex->getMessage());
        }
        return false;
    }
}

function createCheck($username) {
    $g = new GuzzleHttp\Client();
    $data = [
        'api_key' => env('SERVICEHEALTH_KEY'),
        "name" => "Kalender von ".$username,
        "timeout" => 21600,
        "grace" => 3600,
    ];
    $healthcheckCreateResponse = $g->request('POST', 'https://servicehealth.danielsteiner.net/api/v1/checks/', ['json' => $data]);
    
    $response = json_decode((string)$healthcheckCreateResponse->getBody());
    $parts = explode("/", $response->ping_url);
    $uuid = $parts[count($parts)-1];
    return $uuid;
}