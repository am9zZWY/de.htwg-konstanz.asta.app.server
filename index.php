<?php
require __DIR__ . '/vendor/autoload.php';
include 'helpers.php';

// CONSTANTS
const CONTENT_JSON = 'Content-type:application/json;charset=utf-8';

// used to load private key from .env file
$dotenv = Dotenv\Dotenv::createImmutable(__DIR__);
$dotenv->safeLoad();

/**
 * Decrypts message via the 4096 bit long key.
 *
 * @param $encrypted_message
 * @return string | null
 */
function decrypt_message($encrypted_message): null|string
{
    $private_key = $_ENV['PRIV_KEY'];
    openssl_private_decrypt(
        base64_decode($encrypted_message),
        $decrypted_data,
        $private_key
    );
    return $decrypted_data;
}


/**
 * Get information about the Printer Account from HTWG.
 * @param $username
 * @param $password
 * @return string
 * @throws JsonException
 */
function get_druckerkonto($username, $password): string
{
    /* Fields for POST request */
    $fields = [
        'username' => $username,
        'password' => $password,
        'login' => 'Anmelden'
    ];

    /**
     * Initial POST request to log in.
     * The server then sends back identification cookies which should be used to get the page.
     */
    $curl_post_login = curl_init('https://login.rz.htwg-konstanz.de/index.spy');
    curl_setopt($curl_post_login, CURLOPT_POST, true);
    curl_setopt($curl_post_login, CURLOPT_POSTFIELDS, http_build_query($fields));
    curl_setopt($curl_post_login, CURLOPT_HEADER, 1); /* Enable Cookies */
    curl_setopt($curl_post_login, CURLOPT_RETURNTRANSFER, true); /* Don't dump result; only return it */
    $result_login = curl_exec($curl_post_login);

    $cookies = get_cookies($result_login);

    /**
     * Get prepared page from server.
     * Cookies are needed to authenticate.
     */
    $curl_get_druckerkonto = curl_init('https://login.rz.htwg-konstanz.de/userprintacc.spy?activeMenu=Druckerkonto');
    curl_setopt($curl_get_druckerkonto, CURLOPT_HTTPHEADER, array(create_cookie($cookies)));
    curl_setopt($curl_get_druckerkonto, CURLOPT_RETURNTRANSFER, true);
    $result_druckerkonto = curl_exec($curl_get_druckerkonto);

    /* Get first digits */
    $matches = array();
    preg_match('(\d+,\d+)', $result_druckerkonto, $matches);

    return $matches[0];
}

/**
 * Get the newest meals of the HTWG Canteen.
 * @throws JsonException
 */
function get_speiseplan(): bool|string
{
    $speiseplan_xml = file_get_contents('https://www.max-manager.de/daten-extern/seezeit/xml/mensa_htwg/speiseplan.xml');
    $xml = simplexml_load_string($speiseplan_xml);

    $speiseplan = new stdClass();
    foreach ($xml->tag as $tag) {
        $day = new stdClass();
        $timestamp = (string)$tag->attributes()->timestamp;

        $items = $tag->item;
        $cleaned_items = [];
        foreach ($items as $item) {
            $food = new stdClass();
            $food->category = (string)$item->category;
            $food->title = (string)$item->title;
            $food->price = [(string)$item->preis1, (string)$item->preis2, (string)$item->preis3, (string)$item->preis4];
            $cleaned_items[] = $food;
        }
        $day->items = $cleaned_items;

        $speiseplan->$timestamp = $day;
    }

    header(CONTENT_JSON);
    return json_encode($speiseplan, JSON_THROW_ON_ERROR);
}

/**
 * Get grades from QIS.
 * @param $username
 * @param $password
 * @return string
 * @throws JsonException
 */
function get_noten($username, $password): string
{
    /* Fields for POST request */
    $fields = [
        'username' => $username,
        'password' => $password,
        'submit' => 'Anmeldung'
    ];

    $header_login = array(
        'Content-Type: application/x-www-form-urlencoded',
        'Host: qisserver.htwg-konstanz.de'
    );

    /**
     * Initial POST request to prepare for log in.
     * The server then sends back identification cookies which should be used to get the page.
     */
    $curl_post_prepare = curl_init('https://qisserver.htwg-konstanz.de/qisserver/rds?state=user&type=1&category=auth.login&startpage=portal.vm');
    curl_setopt($curl_post_prepare, CURLOPT_POST, true);
    curl_setopt($curl_post_prepare, CURLOPT_POSTFIELDS, http_build_query($fields));
    curl_setopt($curl_post_prepare, CURLOPT_HEADER, true); /* Enable Cookies */
    curl_setopt($curl_post_prepare, CURLOPT_RETURNTRANSFER, true); /* Don't dump result; only return it */
    curl_setopt($curl_post_prepare, CURLOPT_HTTPHEADER, $header_login);

    $result_post_prepare = curl_exec($curl_post_prepare);
    $cookies = get_cookies_raw($result_post_prepare); /* Need raw cookies */


    $header_noten = array(
        'Host: qisserver.htwg-konstanz.de',
        'Connection: keep-alive',
        create_cookie($cookies)
    );

    /**
     * Login.
     */
    $curl_get_login = curl_init('https://qisserver.htwg-konstanz.de/qisserver/rds?state=user&type=0&category=menu.browse&breadCrumbSource=&startpage=portal.vm&chco=y');
    curl_setopt($curl_get_login, CURLOPT_HEADER, true);
    curl_setopt($curl_get_login, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($curl_get_login, CURLOPT_HTTPHEADER, $header_noten);
    curl_exec($curl_get_login);


    /**
     * Prüfungsverwaltung.
     */
    $curl_get_pruefungsverwaltung = curl_init('https://qisserver.htwg-konstanz.de/qisserver/rds?state=change&type=1&moduleParameter=studyPOSMenu&nextdir=change&next=menu.vm&subdir=applications&xml=menu&purge=y&navigationPosition=functions%2CstudyPOSMenu&breadcrumb=studyPOSMenu&topitem=loggedin&subitem=studyPOSMenu');
    curl_setopt($curl_get_pruefungsverwaltung, CURLOPT_HEADER, true);
    curl_setopt($curl_get_pruefungsverwaltung, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($curl_get_pruefungsverwaltung, CURLOPT_HTTPHEADER, $header_noten);
    $result_get_pruefungsverwaltung = curl_exec($curl_get_pruefungsverwaltung);

    /**
     * Path for Notenspiegel über alle bestandenen Prüfungsleistungen
     */
    $xpath = create_domxpath($result_get_pruefungsverwaltung);
    $notenspiegel_path = $xpath->query('.//a[contains(text(), "Notenspiegel über alle bestandenen Leistungen")]/@href')[0];

    /**
     * Notenspiegel über alle bestandenen Prüfungsleistungen.
     */
    $curl_get_notenspiegel = curl_init($notenspiegel_path->nodeValue);
    curl_setopt($curl_get_notenspiegel, CURLOPT_HEADER, true);
    curl_setopt($curl_get_notenspiegel, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($curl_get_notenspiegel, CURLOPT_HTTPHEADER, $header_noten);
    $result_get_notenspiegel = curl_exec($curl_get_notenspiegel);

    /**
     * Parse Notenspiegel.
     */
    $xpath = create_domxpath($result_get_notenspiegel);

    /* Get all grades */
    $grades = $xpath->query('.//span[text()="Prüfungsnummer"]/ancestor::tr/following-sibling::tr');

    $grades_obj = [];

    foreach ($grades as $grade) {
        $grade_tds = $xpath->query('.//td[@class="tabelle1"]', $grade);

        /* Skip the pseudo-grade 'Vorläuf. Notendurschnitt' if it exists. */
        if ($grade_tds[0]->nodeValue == '80000') {
            continue;
        }

        $grade_obj = new stdClass();
        $grade_obj->number = $grade_tds[0]->nodeValue;
        $grade_obj->name = $grade_tds[1]->nodeValue;
        $grade_obj->semester = $grade_tds[2]->nodeValue;
        $grade_obj->grade = $grade_tds[3]->nodeValue;
        $grade_obj->ects = $grade_tds[4]->nodeValue;
        $grade_obj->status = $grade_tds[5]->nodeValue;
        $grades_obj[] = $grade_obj;
    }

    header(CONTENT_JSON);
    return json_encode($grades_obj, JSON_THROW_ON_ERROR);
}

/**
 * Get timetable from LSF.
 * @param $username
 * @param $password
 * @return string
 */
function get_stundenplan($username, $password): string
{
    /* Fields for POST request */
    $fields = [
        'username' => $username,
        'password' => $password,
        'submit' => 'Anmeldung'
    ];

    $header_login = array(
        'Content-Type: application/x-www-form-urlencoded',
        'Host: lsf.htwg-konstanz.de'
    );

    /**
     * Initial POST request to prepare for log in.
     * The server then sends back identification cookies which should be used to get the page.
     */
    $curl_post_prepare = curl_init('https://lsf.htwg-konstanz.de/qisserver/rds?state=user&type=1&category=auth.login&startpage=portal.vm&breadCrumbSource=portal');
    curl_setopt($curl_post_prepare, CURLOPT_POST, true);
    curl_setopt($curl_post_prepare, CURLOPT_POSTFIELDS, http_build_query($fields));
    curl_setopt($curl_post_prepare, CURLOPT_HEADER, true); /* Enable Cookies */
    curl_setopt($curl_post_prepare, CURLOPT_RETURNTRANSFER, true); /* Don't dump result; only return it */
    curl_setopt($curl_post_prepare, CURLOPT_HTTPHEADER, $header_login);

    $result_post_prepare = curl_exec($curl_post_prepare);
    $cookies = get_cookies_raw($result_post_prepare); /* Need raw cookies */

    $header_noten = array(
        'Host: lsf.htwg-konstanz.de',
        'Connection: keep-alive',
        create_cookie($cookies),
    );

    /**
     * Login.
     */
    $curl_get_login = curl_init('https://lsf.htwg-konstanz.de/qisserver/rds?state=user&type=0&category=menu.browse&breadCrumbSource=portal&startpage=portal.vm&chco=y');
    curl_setopt($curl_get_login, CURLOPT_HEADER, true);
    curl_setopt($curl_get_login, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($curl_get_login, CURLOPT_HTTPHEADER, $header_noten);
    $result_login = curl_exec($curl_get_login);

    header(CONTENT_JSON);
    return $result_login;
}

/**
 * Get HTML of "Termine und Fristen" from HTWG.
 * @return string
 */
function get_termine(): string
{
    $xpath = fetch_and_create_dom('https://www.htwg-konstanz.de/studium/pruefungsangelegenheiten/terminefristen/');
    $termine = $xpath->query('.//h2')[0]->parentNode;
    return $termine->ownerDocument->saveHTML($termine);
}

function get_veranstaltungen(): string
{
    return '';
}

/**
 * Get prices and opening times of Café Endlicht.
 * @param $param
 * @return string|bool
 * @throws JsonException
 */
function get_endlicht($param): string|bool
{
    $xpath = fetch_and_create_dom('https://www.htwg-konstanz.de/%20/hochschule/einrichtungen/asta/cafe-endlicht/');
    if ($param === 'zeiten') {
        $endlicht_zeiten = $xpath->query('.//*[contains(text(), "Öffnungszeiten")]')[0];
        return $endlicht_zeiten->ownerDocument->saveHTML($endlicht_zeiten);
    }

    if ($param === 'preise') {
        $endlicht_preise = $xpath->query('.//*[contains(text(), "€")]/parent::ul/li/text()');
        $preise = [];
        foreach ($endlicht_preise as $endlicht_preis) {
            $item = new stdClass();
            $parsed_string = explode(':', $endlicht_preis->textContent);
            $item->name = trim($parsed_string[0]);
            $item->price = trim($parsed_string[1]);
            $preise[] = $item;
        }
        header('Content-type:application/json;charset=utf-8');
        return json_encode($preise, JSON_THROW_ON_ERROR);
    }

    return false;
}


/* Set return-headers to enable CORS policy */
header("Access-Control-Allow-Origin: *");
header('Access-Control-Allow-Credentials: true');
header('Access-Control-Max-Age: 86400');

/* Get POST body */
$post_body = file_get_contents('php://input');
try {
    $json = json_decode($post_body, true, 512, JSON_THROW_ON_ERROR);
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
                send('get_druckerkonto', [$username, $password]);
            } elseif (clean_string($json['reqtype']) === 'noten') {
                send('get_noten', [$username, $password]);
            } elseif (clean_string($json['reqtype']) === 'stundenplan') {
                send('get_stundenplan', [$username, $password]);
            }
        } else {
            http_response_code(403);
            echo 'Password oder Benutzername können nicht entschlüsselt werden.';
        }
    }
    /* GET */
} else if (isset($_GET['mensa'])) {
    send('get_speiseplan');
} else if (isset($_GET['termine'])) {
    send('get_termine');
} else if (isset($_GET['veranstaltungen'])) {
    send('get_veranstaltungen');
} else if (isset($_GET['endlicht'], $_GET['reqtype']) && ($_GET['reqtype'] === 'preise' || $_GET['reqtype'] === 'zeiten')) {
    send('get_endlicht', [clean_string($_GET['reqtype'])]);
} else {
    http_response_code(400);
    echo 'Bad Request';
}
