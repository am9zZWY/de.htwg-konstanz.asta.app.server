<?php

require __DIR__ . '/vendor/autoload.php';
require_once __DIR__ . '/src/helpers.php';
require_once __DIR__ . '/src/common_requests.php';
require_once __DIR__ . '/src/user_related_requests.php';

/* CONSTANTS */
const CONTENT_JSON = 'Content-type:application/json;charset=utf-8';
const CONTENT_TEXT = 'Content-type:text/plain;charset=UTF-8';
const CONTENT_HTML = 'Content-type:text/html;charset=UTF-8';
const CONTENT_ICAL = 'Content-type:text/calendar;charset=utf-8';
const CONTENT_PDF = 'Content-Type:application/pdf;charset=utf-8';
const CONTENT_DOWNLOAD = 'Content-Description:File Transfer';


/* Used to load private key from .env file */
$dotenv = Dotenv\Dotenv::createImmutable(__DIR__, ['.env', 'pub.env'], false);
$dotenv->safeLoad();

/* Disable warnings from DOMDocument */
libxml_use_internal_errors(true);

$is_local = $_SERVER['SERVER_NAME'] === '127.0.0.1';

/**
 * Decrypts message via the 4096 bit long key.
 *
 * @param string $encrypted_message
 * @return string | null
 */
function decrypt_message(string $encrypted_message): null|string
{
    global $is_local;
    if (isset($_ENV['PRIV_KEY']) && !$is_local) {
        $private_key = $_ENV['PRIV_KEY'];
    } else {
        $private_key = $_ENV['PRIV_KEY_DEV'];
    }

    openssl_private_decrypt(
        base64_decode($encrypted_message),
        $decrypted_data,
        $private_key
    );
    return $decrypted_data;
}


/* Set return-headers to enable CORS policy */
if ($is_local) {
    header("Access-Control-Allow-Origin: *");
} else {
    header("Access-Control-Allow-Origin: https://htwg-app.github.io");
}

header('Access-Control-Allow-Methods: POST, GET');
header('Access-Control-Allow-Credentials: true');
header('Access-Control-Max-Age: 86400');
header("Strict-Transport-Security: max-age=600; includeSubDomains");

/* Get POST body */
$post_body = file_get_contents('php://input');
try {
    if ($post_body !== false) {
        $json = json_decode($post_body, true, 512, JSON_THROW_ON_ERROR);
    }
} catch (JsonException $e) {
    $json = null;
}

/**
 * @param string|null $username
 * @param string|null $password
 * @param string|null $reqtype
 * @return void
 */
function handle_post(string|null $username, string|null $password, string|null $reqtype)
{
    $cleaned_username = clean_string($username);
    $cleaned_password = clean_string($password);
    $cleaned_reqtype = clean_string($reqtype);
    if ($cleaned_username && $cleaned_password) {
        if ($cleaned_reqtype === 'drucker') {
            send_back('get_druckerkonto', [$cleaned_username, $cleaned_password]);
        } elseif ($cleaned_reqtype === 'noten') {
            send_back('get_noten', [$cleaned_username, $cleaned_password]);
        } elseif ($cleaned_reqtype === 'immatrikulations_bescheinigung') {
            send_back('get_immatrikulations_bescheinigung', [$cleaned_username, $cleaned_password]);
        } elseif ($cleaned_reqtype === 'stundenplan') {
            send_back('get_stundenplan', [$cleaned_username, $cleaned_password, get_value('week'), get_value('year'), get_value('type')]);
        }
    } else {
        http_response_code(403);
        echo 'Password oder Benutzername können nicht entschlüsselt werden.';
    }
}

/* Handle requests */
if (isset($json)) {
    /* POST */
    if (isset($json['reqtype'])) {
        $username = decrypt_message($json['username']);
        $password = decrypt_message($json['password']);
        handle_post($username, $password, $json['reqtype']);
    }
} else if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    handle_post($_POST['username'], $_POST['password'], $_POST['reqtype']);
    /* GET */
} else if (isset($_GET['mensa'])) {
    send_back('get_speiseplan');
} else if (isset($_GET['termine'])) {
    send_back('get_termine');
} else if (isset($_GET['endlicht'], $_GET['reqtype']) && (get_value('reqtype') === 'preise' || get_value('reqtype') === 'zeiten')) {
    send_back('get_endlicht', [get_value('reqtype')]);
} else {
    ?>
    <html lang="de">
    <head>
        <style>
            body {
                font: 100%/1.5 "swis721", "Helvetica", "Arial", sans-serif;
            }

            * {
                margin-top: 10px;
            }

            input, select, button {
                width: 20em;
                padding: 12px 20px;
                font-size: 1em;
            }

            input {
                border: 0;
                outline: none;
                background-color: #d9e5ec;
                border-radius: 0;
            }

            button {
                display: inline-block;
                background: #009b91;
                color: white;
                line-height: 1;
                border: 0;
                border-radius: 6px;
                text-align: center;
                font-weight: bold;
            }
        </style>
        <title>HTWG App Backend</title>
    </head>
    <body>
    <h1>HTWG App Backend</h1>
    <form action="index.php" method="post">
        <input id="username" name="username" placeholder="Benutzername"/><br/>
        <input id="password" name="password" type="password" placeholder="Password"/>
        <br/>
        <select name="reqtype">
            <option value="drucker" selected>Druckerkonto</option>
            <option value="noten">Noten</option>
            <option value="immatrikulations_bescheinigung">Immatrikulationsbescheinigung</option>
            <option value="stundenplan">Stundenplan</option>
        </select>
        <br/>
        <button type="submit">Anfragen</button>
    </form>
    </body>
    </html>
    <?php
}
