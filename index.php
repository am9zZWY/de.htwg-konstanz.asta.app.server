<?php
require __DIR__ . '/vendor/autoload.php';

// used to load private key from .env file
$dotenv = Dotenv\Dotenv::createImmutable(__DIR__);
$dotenv->safeLoad();

/**
 * Decrypts message via the 4096 bit long key.
 *
 * @param $encrypted_message
 * @return string | bool
 */
function decrypt_message($encrypted_message)
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
 * Fetch an HTML from URL and return an DOMXPath Object of it.
 * @param $url
 * @return DOMXPath
 */
function fetch_and_create_dom($url)
{
    $html = file_get_contents($url);
    return create_domxpath($html);
}

/**
 * Return an DOMXPath Object from a simple HTML.
 * @param $html
 * @return DOMXPath
 */
function create_domxpath($html)
{
    $doc = new DOMDocument();
    $doc->loadHTML($html);
    return new DOMXPath($doc);
}

function clean_string($string)
{
    $cleaned_string = htmlspecialchars($string, ENT_QUOTES);
    return $cleaned_string;
}


function get_cookies($result)
{
    preg_match_all('/^Set-Cookie:\s*([^;]*)/mi', $result, $matches); /* Retrieve cookies and save them to an array */
    $cookies = array();
    foreach ($matches[1] as $item) {
        parse_str($item, $cookie);
        $cookies = array_merge($cookies, $cookie);
    }
    return $cookies;
}

function get_cookies_raw($result)
{
    preg_match('/^Set-Cookie:\s*([^;]*)/mi', $result, $matches);
    return $matches[1];
}

/**
 * Get information about the Druckerkonto from HTWG.
 * @param $username
 * @param $password
 * @return string
 */
function get_druckerkonto($username, $password)
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
    curl_setopt($curl_get_druckerkonto, CURLOPT_HTTPHEADER, array('Cookie: ' . json_encode($cookies)));
    curl_setopt($curl_get_druckerkonto, CURLOPT_RETURNTRANSFER, true);
    $result_druckerkonto = curl_exec($curl_get_druckerkonto);

    /* Get first digits */
    $matches = array();
    preg_match('(\d+,\d+)', $result_druckerkonto, $matches);

    return $matches[0];
}

/**
 * Get the newest meals of the HTWG Mensa.
 */
function get_speiseplan()
{
    $xpath = fetch_and_create_dom('https://seezeit.com/essen/speiseplaene/mensa-htwg/');

    $activeday_el = $xpath->query('//div[contains(@class, "contents_aktiv")]')[0];

    $speiseplan = [];

    if (!is_null($activeday_el)) {
        $node = $activeday_el->firstChild;
        while ($node !== null) {

            $category = $xpath->query('.//div[@class="category"]', $node)[0];
            $title = $xpath->query('.//div[@class="title"]', $node)[0];
            $price = $xpath->query('.//div[@class="preise"]', $node)[0];

            if ($category !== null && $title !== null && $price !== null) {
                $food = new stdClass();
                $food->category = $category->nodeValue;
                $food->title = $title->nodeValue;
                $food->price = $price->nodeValue;
                array_push($speiseplan, $food);
            }
            $node = $node->nextSibling;
        }
    }

    header('Content-type:application/json;charset=utf-8');
    return json_encode($speiseplan);
}

/**
 * Get grades from HTWG.
 * @param $username
 * @param $password
 * @return string
 */
function get_noten($username, $password)
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
        'Cookie: ' . $cookies,
    );

    /**
     * Login.
     */
    $curl_get_login = curl_init('https://qisserver.htwg-konstanz.de/qisserver/rds?state=user&type=0&category=menu.browse&breadCrumbSource=&startpage=portal.vm&chco=y');
    curl_setopt($curl_get_login, CURLOPT_HEADER, true);
    curl_setopt($curl_get_login, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($curl_get_login, CURLOPT_HTTPHEADER, $header_noten);
    $result_login = curl_exec($curl_get_login);


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
        $grade_obj = new stdClass();
        $grade_obj->number = $grade_tds[0]->nodeValue;
        $grade_obj->name = $grade_tds[1]->nodeValue;
        $grade_obj->semester = $grade_tds[2]->nodeValue;
        $grade_obj->grade = $grade_tds[3]->nodeValue;
        $grade_obj->ects = $grade_tds[4]->nodeValue;
        $grade_obj->status = $grade_tds[5]->nodeValue;
        array_push($grades_obj, $grade_obj);
    }

    header('Content-type:application/json;charset=utf-8');
    return json_encode($grades_obj);
}

/**
 * Get stundenplan from HTWG.
 * @param $username
 * @param $password
 * @return string
 */
function get_stundenplan($username, $password)
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
        'Cookie: ' . $cookies,
    );

    /**
     * Login.
     */
    $curl_get_login = curl_init('https://lsf.htwg-konstanz.de/qisserver/rds?state=user&type=0&category=menu.browse&breadCrumbSource=portal&startpage=portal.vm&chco=y');
    curl_setopt($curl_get_login, CURLOPT_HEADER, true);
    curl_setopt($curl_get_login, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($curl_get_login, CURLOPT_HTTPHEADER, $header_noten);
    $result_login = curl_exec($curl_get_login);

    return $result_login;
}

/**
 * Get HTML of Termine und Fristen from HTWG.
 * @return string
 */
function get_termine()
{
    $xpath = fetch_and_create_dom('https://www.htwg-konstanz.de/studium/pruefungsangelegenheiten/terminefristen/');
    $termine = $xpath->query('.//h2')[0]->parentNode;
    return $termine->ownerDocument->saveHTML($termine);
}


/* Set return-headers to enable CORS policy */
header("Access-Control-Allow-Origin: *");
header('Access-Control-Allow-Credentials: true');
header('Access-Control-Max-Age: 86400');

/* Get POST body */
$post_body = file_get_contents('php://input');
$json = json_decode($post_body, true);

/* Handle GET requests */
if (isset($json)) {
    if (isset($json['reqtype'])) {
        $username = decrypt_message($json['username']);
        $password = decrypt_message($json['password']);

        if ($username && $password) {
            http_response_code(200);
            if (clean_string($json['reqtype']) == 'drucker') {
                echo get_druckerkonto($username, $password);
            } elseif (clean_string($json['reqtype']) == 'noten') {
                echo get_noten($username, $password);
            } elseif (clean_string($json['reqtype']) == 'stundenplan') {
                echo get_stundenplan($username, $password);
            }
        } else {
            http_response_code(403);
            echo 'Password oder Benutzername können nicht entschlüsselt werden.';
        }
    }
} else if (isset($_GET['mensa']) || isset($_GET['speiseplan'])) {
    http_response_code(200);
    echo get_speiseplan();
} else if (isset($_GET['termine']) || isset($_GET['fristen'])) {
    http_response_code(200);
    echo get_termine();
} else {
    http_response_code(204);
    echo 'Moin.';
}
