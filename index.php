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

/* Handle requests */
if (isset($json)) {
    /* POST */
    if (isset($json['reqtype'])) {
        $username = decrypt_message($json['username']);
        $password = decrypt_message($json['password']);

        if ($username && $password) {
            if (clean_string($json['reqtype']) === 'drucker') {
                send_back('get_druckerkonto', [$username, $password]);
            } elseif (clean_string($json['reqtype']) === 'noten') {
                send_back('get_noten', [$username, $password]);
            } elseif (clean_string($json['reqtype']) === 'stundenplan') {
                send_back('get_stundenplan', [$username, $password, get_value('week'), get_value('year'), get_value('type')]);
            }
        } else {
            http_response_code(403);
            echo 'Password oder Benutzername können nicht entschlüsselt werden.';
        }
    }
    /* GET */
} else if (isset($_GET['mensa'])) {
    send_back('get_speiseplan');
} else if (isset($_GET['termine'])) {
    send_back('get_termine');
} else if (isset($_GET['veranstaltungen'])) {
    send_back('get_veranstaltungs_kalender');
} else if (isset($_GET['endlicht'], $_GET['reqtype']) && (get_value('reqtype') === 'preise' || get_value('reqtype')=== 'zeiten')) {
    send_back('get_endlicht', [get_value('reqtype')]);
} else {
    http_response_code(400);
    echo 'Computer sagt nein.';
}
