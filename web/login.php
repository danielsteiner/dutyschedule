<?php 
require "../boot/bootstrap.php";

if(array_key_exists('username', $_POST) && array_key_exists('password', $_POST)) {
    $username = $_POST["username"];
    $password = $_POST["password"];
    if(!checkCredentials($username, $password)) {
        header('Location: login.php');
        die();
    }

    if(array_key_exists("is_lba", $_POST)){
        $auth = [
            "username" => $username,
            "password" => $password,
            "is_lba" => true,
            "kufer_username" => $_POST["kufer_username"],
            "kufer_password" => $_POST["kufer_password"]
        ];
        if(!checkKuferCredentials($username, $password)) {
            header('Location: login.php');
            die();
        }
    } else {
        $auth = [
            "username" => $username,
            "password" => $password,
        ];
    }
    $hash = "";
    $auth = json_encode($auth);
    $cipher = "aes-128-ctr";
    $ivlen = openssl_cipher_iv_length($cipher);
    $iv = openssl_random_pseudo_bytes($ivlen);
    if (in_array($cipher, openssl_get_cipher_methods()))
    {
        $ciphertext = openssl_encrypt(
            $auth, 
            $cipher, 
            base64_decode(env('APP_KEY')), 
            0, 
            $iv
        );
        $hash = base64_encode($iv.$ciphertext);
    }

    $baseurl = env("SCRIPT_URL");
    echo '
    <!doctype html>
    <html lang="en">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
        <meta name="author" content="Daniel Steiner">
        <title>NIU Dienstplan Sync | Kalenderurl</title>
        <link href="css/bootstrap.min.css" rel="stylesheet">
        <link href="css/signin.css" rel="stylesheet">
    </head>

    <body class="text-center">
    <a href="https://github.com/danielsteiner/dutyschedule" style="position: fixed;right: 0px;top: 0px;"><img width="149" height="149" src="https://github.blog/wp-content/uploads/2008/12/forkme_right_red_aa0000.png?resize=149%2C149" class="attachment-full size-full" alt="Fork me on GitHub" data-recalc-dims="1"></a>
        <form class="form-signin">
            <label for="calendarurl" class="sr-only">Adresse für den Kalender</label>
            <input type="text" id="calendarurl" name="calendarurl" class="form-control" value="'.$baseurl.'/'.$hash.'.ics" required autofocus>
            <button class="btn btn-lg btn-niu btn-block" id="copy" type="submit">Link Kopieren</button>
            <p class="mt-5 mb-3 text-muted">&copy; 2019 - '.date("Y").', Daniel Steiner, Arash Dalir - <a href="/disclaimer.html">Disclaimer</a></p>
            <div class="tutorials">
                <a href="https://www.buero-kaizen.de/mit-outlook-kalender-abonnieren/">Kalenderabonnement in Outlook einrichten</a><br>
                <a href="http://www.ff-altenschwand.de/seiten/service/ical/android.html">Kalender auf Android über Gmail Konto abonnieren</a><br>
                <a href="http://www.ff-altenschwand.de/seiten/service/ical/iphone.html">Kalender auf iOS abonnieren</a><br>
            </div>  
        </form>
        
        <script>
            document.querySelector("#copy").addEventListener("click", function(ev) {
                document.querySelector("#calendarurl").select();
                document.execCommand("Copy");
                alert("Link wurde in die Zwischenablage kopiert");
                event.preventDefault();
            });
        </script>
    </body>
    </html>
    ';
   // header('Location: index.php?auth='.$auth);
} else {
    echo '
    <!doctype html>
    <html lang="en">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
        <meta name="author" content="Daniel Steiner">
        <title>NIU Dienstplan Sync | Login</title>
        <link href="css/bootstrap.min.css" rel="stylesheet">
        <link href="css/signin.css" rel="stylesheet">
    </head>
    <body class="text-center">
    <a href="https://github.com/danielsteiner/dutyschedule" style="position: fixed;right: 0px;top: 0px;"><img width="149" height="149" src="https://github.blog/wp-content/uploads/2008/12/forkme_right_red_aa0000.png?resize=149%2C149" class="attachment-full size-full" alt="Fork me on GitHub" data-recalc-dims="1"></a>
        <form class="form-signin" method="post" action="login.php">
            <h1 class="h3 mb-3 font-weight-normal">Bitte melde dich mit deinen NIU Zugangsdaten hier an.</h1>
            <label for="username" class="sr-only">Benutzername</label>
            <input type="text" id="username" name="username" class="form-control" placeholder="m.muster" required autofocus>
            <label for="inputPassword" class="sr-only">Passwort</label>
            <input type="password" id="password" name="password" class="form-control" placeholder="Passwort" required>
            <div class="form-check">
                <input class="form-check-input" type="checkbox" name="is_lba" id="is_lba">
                <label class="form-check-label" for="is_lba">
                    Ich bin Lehrsanitäter / Lehrbeauftragter
                </label>
            </div>
            <div class="kuferlogin">
                <label for="kufer_username" class="sr-only">Kufer Benutzername</label>
                <input type="text" id="kufer_username" name="kufer_username" class="form-control" placeholder="m.muster" autofocus>
                <label for="kufer_password" class="sr-only">Kufer Passwort</label>
                <input type="password" id="kufer_password" name="kufer_password" class="form-control" placeholder="Passwort">
            </div>
            <button class="btn btn-lg btn-niu btn-block" type="submit">Anmelden</button>
            <p class="mt-5 mb-3 text-muted">&copy; 2019 - '.date("Y").', Daniel Steiner, Arash Dalir - <a href="/disclaimer.html">Disclaimer</a></p>
        </form>
        <script>
        document.querySelector("#is_lba").addEventListener("change",function(){
            if(document.querySelector("#is_lba").checked === true) {
                show(document.querySelector(".kuferlogin"));
            } else {
                hide(document.querySelector(".kuferlogin"));
            }
        });


        // Show an element
        var show = function (elem) {
            elem.style.display = "block";
        };

        // Hide an element
        var hide = function (elem) {
            elem.style.display = "none";
        };

        // Toggle element visibility
        var toggle = function (elem) {

            // If the element is visible, hide it
            if (window.getComputedStyle(elem).display === "block") {
                hide(elem);
                return;
            }

            // Otherwise, show it
            show(elem);

        };
        </script>
    </body>
    </html>
    ';
}