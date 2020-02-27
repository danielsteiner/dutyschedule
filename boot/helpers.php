<?php
require __DIR__ . '/../vendor/autoload.php';

use PHPHtmlParser\Dom;
use Carbon\Carbon;
use Carbon\CarbonImmutable;


$i = 0;
function makeVEVENT($duty)
{
    $vevent = "BEGIN:VEVENT\r\n";
    $vevent .= "UID:" . $duty['hash'] . "@dutyschedule.danielsteiner.net\r\n";
    $vevent .= "DTSTAMP:" . str_replace([":", "-"], "", $duty['time']['start']->toDateTimeLocalString()) . "\r\n";
    if ($duty["time"]["start"]->toDateTimeLocalString() === $duty["time"]["end"]->toDateTimeLocalString()) {
        // Bereitschaftsdienst, full day
        $vevent .= "DTSTART;VALUE=DATE:" . str_replace([":", "-"], "", $duty['time']['start']->format("Ymd")) . "\r\n";
    } else {
        $vevent .= "DTSTART;TZID=Europe/Vienna:" . str_replace([":", "-"], "", $duty['time']['start']->toDateTimeLocalString()) . "\r\n";
        $vevent .= "DTEND;TZID=Europe/Vienna:" . str_replace([":", "-"], "", $duty['time']["end"]->toDateTimeLocalString()) . "\r\n";
    }
    $vevent .= "SUMMARY:" . $duty['title'] . "\r\n";
    $vevent .= "DESCRIPTION:" . strip_tags(str_replace("<br>", "\r\n", $duty['description'])) . "\r\n";
    $vevent .= "X-ALT-DESC;FMTTYPE=text/html:<html><body>" . $duty['description'] . "</body></html>\r\n";
    $vevent .= "STATUS:" . $duty['status'] . "\r\n";
    $vevent .= "TRANSP:OPAQUE\r\n";
    if (is_array($duty['location'])) {
        $vevent .= "LOCATION:" . $duty['location']['label'] . " " . $duty['location']['address'] . "\r\n";
        $vevent .= "GEO:" . $duty['location']['lat'] . ";" . $duty['location']['lon'] . "\r\n";
    }
    $vevent .= "END:VEVENT";
    return $vevent . "\r\n";
}
function parseAmb($row)
{
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
        $duty['description'] = "Weitere Infos: " . $url;
        $dutiesArray[] = $duty;
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
        $duty['description'] = "Details zur Ambulanz : <a href=" . $url . ">siehe NIU</a>";
        $dutiesArray[] = $duty;
    }

    return makeVEVENT($duty);
}
function parseRDDuty($duty, $title)
{
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
        ]
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
    $parsedTitle = parseTitle($title, $location, $remark);
    $t = [$pos1, $pos2, $pos3, $pos4, $pos5];
    $team = [];
    if (array_key_exists('teamlabels', $parsedTitle)) {
        if (is_array($parsedTitle['teamlabels'])) {
            foreach ($parsedTitle['teamlabels'] as $i => $label) {
                $team[$label] = $t[$i];
            }
        } else {
            //dd($parsedTitle);
        }
    }

    $duty['location'] = parseLocation($title, $location);
    $duty['team'] = $team;
    if (!is_null($vehicle)) {
        $duty['vehicle'] = $vehicle;
    }
    $duty['hash'] = generateHash($title, $date, $timestring);
    $duty['title'] = $parsedTitle['title'];
    $duty['status'] = $parsedTitle['status'];
    $duty['description'] = generateDescription($team, $remark, $vehicle);
    $dutiesArray[] = $duty;
    $vevent = makeVEVENT($duty);
    return $vevent;
}
function parseCourse($course)
{
    $courses = "";
    if ($course["days"])
    {
        foreach ($course["days"] as $day) {
            $duty = [
                'date' => $day["date"],
                'time' => [
                    'start' => Carbon::parse($day["date"] . " " . $day["from"]),
                    'end' => Carbon::parse($day["date"] . " " . $day["to"])
                ]
            ];

            $duty['location'] = $day["location"] . ", " . $day["room"] . " " . $day["floor"];
            $duty['hash'] = generateHash($course["title"], $day["date"], $day["from"] . "-" . $day["to"]);
            $duty['title'] = $course["title"];
            $duty['status'] = "CONFIRMED";
            $duty['description'] = str_replace("\n", "<br>", $course["lecturers"] . " " . $day["location"] . ", " . $day["room"] . " " . $day["floor"] . "    " . $course["description"]);
            $courses .= makeVEVENT($duty);
        }
    }
    return $courses;
}
function parseTitle($title, $location, $remark)
{
    $titleparts = explode(" ", $title);
    $status = ($titleparts[count($titleparts) - 1] === "geplant" ? "TENTATIVE" : "CONFIRMED");
    unset($titleparts[count($titleparts) - 1]);
    $t = implode(" ", $titleparts);
    $fancyresult = getFancyTitle($t, $location, $remark);

    return ["title" => $fancyresult['title'], "status" => $status, 'teamlabels' => $fancyresult['teamlabels']];
}
function getFancyTitle($title, $location, $remark)
{
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
function generateTeamList($team, $teamlabels)
{
    $teamlist = [];
    foreach ($team as $i => $member) {
        if (!is_null($team[$i])) {
            $teamlist[$teamlabels[$i]] = trim($team[$i]);
        }
    }
    return $teamlist;
}
function generateDescription($team, $remark, $fahrzeug = null)
{
    $eventDescription = "";

    if (!is_null($fahrzeug)) {
        $eventDescription .= "Fahrzeug: " . str_pad($fahrzeug, 3, 0, STR_PAD_LEFT) . "<br>";
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
function splitLine($text)
{
    if (mb_strlen($text) >= 75) {
        $text = wordwrap($text, 74, "\r\n ", true);
    } else {
        if (strpos($text, "\n") !== false || strpos($text, "\r\n") !== false) {
            return $text;
        } else {
            return $text . "\r\n";
        }
    }
    return $text . "\r\n";
}
function getUser($member)
{
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
function replaceHex($string)
{
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
function parseLocation($title, $location)
{
    $locations = [
        "BVS" => [
            "label" => "Wiener Rotes Kreuz Bezirkstelle Bertha Van Suttner",
            "address" => "Negerlegasse 4/3 1020 Wien Österreich",
            "lat" => "48.214100",
            "lon" => "16.312961"
        ],
        "VS" => [
            "label" => "Wiener Rotes Kreuz Bezirkstelle Van Swieten",
            "address" => "Landgutgasse 8 1100 Wien Österreich",
            "lat" => "48.181430",
            "lon" => "16.377530"
        ],
        "Nord" => [
            "label" => "Wiener Rotes Kreuz Bezirkstelle Nord",
            "address" => "Karl-Schäfer-Straße 8 1210 Wien Österreich",
            "lat" => "48.267200",
            "lon" => "16.401990"
        ],
        "West" => [
            "label" => "Wiener Rotes Kreuz Bezirkstelle West",
            "address" => "Spallartgasse 10A/1. Stock 1140 Wien Österreich",
            "lat" => "48.200580",
            "lon" => "16.308870"
        ],
        "Nodo" => [
            "label" => "Wiener Rotes Kreuz Zentrale",
            "address" => "Nottendorfer Gasse 21/Hinteres Gebäude/2. Stock 1030 Wien, Österreich",
            "lat" => "48.190650",
            "lon" => "16.411500"
        ],
        "Arsenal" => [
            "label" => "Berufsrettung Wien Rettungsstation Arsenal",
            "address" => "Arsenalstraße 7 1030 Wien Österreich",
            "lat" => "48.179330",
            "lon" => "16.391490"
        ],
        "Penzing" => [
            "label" => "Berufsrettung Wien Rettungsstation Penzing",
            "address" => "Baumgartenstraße 7 1140 Wien Österreich",
            "lat" => "48.193700",
            "lon" => "16.285420"
        ],
        "ABZ" => [
            "label" => "Wiener Rotes Kreuz Ausbildungszentrum",
            "address" => "Safargasse 4 1030 Wien Österreich",
            "lat" => "48.189580",
            "lon" => "16.414110"
        ],
        "Leitstelle WRK" => [
            "label" => "Wiener Rotes Kreuz Leitstelle",
            "address" => "Modecenterstraße 14 1030 Wien Österreich",
            "lat" => "48.185900",
            "lon" => "16.415050"
        ],
    ];
    $loc = "";
    if (stripos($title, "KTW") !== false) {
        //let's assume, this is a KTW duty. This means possible locations are BVS, VS, Nord, West, Nodo.
        if (strtolower($location) == "lv" || strtolower($location) == "rd" || strtolower($location) == "ddl") {
            $loc = $locations["Nodo"];
        } else {
            $loc = $locations[ucfirst($location)];
        }
    } else if (stripos($title, "RK") !== false) {
        //let's assume, this is a RTW duty. This means possible locations are Nodo, Arsenal and Penzing.
        if (strtolower($location) == "lv" || strtolower($location) == "rd" || strtolower($location) == "ddl") {
            $loc = $locations["Nodo"];
        } else if (strtolower($location) == "west") {
            $loc = $locations["Penzing"];
        } else if (strtolower($location) == "vs") {
            $loc = $locations["Arsenal"];
        }
    } else if (stripos($title, "Journal") !== false || stripos($title, "ÄFD-Calltaker") !== false) {
        $loc = $locations["Leitstelle WRK"];
    }
    return $loc;
}
function parseDate($date, $timestring)
{
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
function generateHash($title, $date, $timestring)
{
    $GLOBALS["i"]++;
    if (gettype($date) == "array") {
        return substr(md5($GLOBALS["i"] . " " . $title . " " . $date['start']->toDateString() . " " . $timestring), 0, 10);
    } else {
        return substr(md5($GLOBALS["i"] . " " . $title . "" . $date . "" . $timestring), 0, 10);
    }
}
