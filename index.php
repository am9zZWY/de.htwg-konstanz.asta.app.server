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
$dotenv = Dotenv\Dotenv::createImmutable(__DIR__);
$dotenv->safeLoad();

/* Disable warnings from DOMDocument */
libxml_use_internal_errors(true);

/**
 * Decrypts message via the 4096 bit long key.
 *
 * @param string $encrypted_message
 * @return string | null
 */
function decrypt_message(string $encrypted_message): null|string
{
    $private_key = $_ENV['PRIV_KEY'];
    openssl_private_decrypt(
        base64_decode($encrypted_message),
        $decrypted_data,
        $private_key
    );
    return $decrypted_data;
}

/* Set return-headers to enable CORS policy */
header("Access-Control-Allow-Origin: *");
header('Access-Control-Allow-Credentials: true');
header('Access-Control-Max-Age: 86400');

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
    echo '
        <html>
            <body>
                <h2>HTWG App Backend</h2>
                <form action="index.php" method="post">
                    <label for="username">Benutzername</label>
                    <input id="username" name="username" /><br/>
                    <label for="password">Passwort</label>
                    <input id="password" name="password" type="password"/>
                    <br />
                    <select name="reqtype">
                        <option value="drucker" selected>Druckerkonto</option>
                        <option value="noten">Noten</option>
                        <option value="immatrikulations_bescheinigung">Immatrikulationsbescheinigung</option>
                        <option value="stundenplan">Stundenplan</option>
                    </select>
                    <br />
                    <button type="submit">Anfragen</button>
                </form>
            </body>
        </html>
    ';
}
