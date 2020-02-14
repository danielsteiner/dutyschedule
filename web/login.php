<?php 
require "../boot/bootstrap.php";

if(array_key_exists('username', $_POST) && array_key_exists('password', $_POST)) {
    $username = $_POST["username"];
    $password = $_POST["password"];
    if(array_key_exists("opt-in", $_POST)){
        $key = bin2hex(random_bytes(16));
        $auth = base64_encode($username.":".$password.":opt-in=".$key);
    } else {
        $auth = base64_encode($username.":".$password);
    }
    $baseurl = env("SCRIPT_URL");
    echo '
    <!doctype html>
    <html lang="en">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
        <meta name="author" content="Daniel Steiner">
        <title>NIU Dienstplan Export | Kalenderurl</title>
        <link href="css/bootstrap.min.css" rel="stylesheet">
        <link href="css/signin.css" rel="stylesheet">
    </head>

    <body class="text-center">
    <a href="https://github.com/danielsteiner/dutyschedule" style="position: fixed;right: 0px;top: 0px;"><img width="149" height="149" src="https://github.blog/wp-content/uploads/2008/12/forkme_right_red_aa0000.png?resize=149%2C149" class="attachment-full size-full" alt="Fork me on GitHub" data-recalc-dims="1"></a>
        <form class="form-signin">
            <label for="calendarurl" class="sr-only">Adresse für den Kalender</label>
            <input type="text" id="calendarurl" name="calendarurl" class="form-control" value="'.$baseurl.'?auth='.$auth.'" required autofocus>
            <button class="btn btn-lg btn-niu btn-block" id="copy" type="submit">Link Kopieren</button>
            <p class="mt-5 mb-3 text-muted">&copy; 2019, Daniel Steiner - <a href="/disclaimer.html">Disclaimer</a></p>
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
        <title>NIU Dienstplan Export | Login</title>
        <link href="css/bootstrap.min.css" rel="stylesheet">
        <link href="css/signin.css" rel="stylesheet">
    </head>
    <body class="text-center">
    <a href="https://github.com/danielsteiner/dutyschedule" style="position: fixed;right: 0px;top: 0px;"><img width="149" height="149" src="https://github.blog/wp-content/uploads/2008/12/forkme_right_red_aa0000.png?resize=149%2C149" class="attachment-full size-full" alt="Fork me on GitHub" data-recalc-dims="1"></a>
        <form class="form-signin" method="post" action="login.php">
            <h1 class="h3 mb-3 font-weight-normal">Bitte melde dich mit deinen NIU Zugangsdaten hier an.</h1>
            <label for="inputEmail" class="sr-only">Benutzername</label>
            <input type="text" id="username" name="username" class="form-control" placeholder="m.muster" required autofocus>
            <label for="inputPassword" class="sr-only">Passwort</label>
            <input type="password" id="password" name="password" class="form-control" placeholder="Passwort" required>
            <button class="btn btn-lg btn-niu btn-block" type="submit">Anmelden</button>
            <p class="mt-5 mb-3 text-muted">&copy; 2019, Daniel Steiner - <a href="/disclaimer.html">Disclaimer</a></p>
        </form>
    </body>
    </html>
    ';
}