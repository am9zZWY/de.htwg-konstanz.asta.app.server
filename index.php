<?php

/**
 * Fetch a html from url and return an DOMXPath Object of it.
 * @param $url
 * @return DOMXPath
 */
function fetch_and_create_dom($url)
{
    $html = file_get_contents($url);
    return create_domxpath($html);
}

/**
 * Return an DOMXPath Object from a simple html.
 * @param $html
 * @return DOMXPath
 */
function create_domxpath($html)
{
    $doc = new DOMDocument();
    $doc->loadHTML($html);
    return new DOMXPath($doc);
}

/**
 * Get information about the Druckerkonto of the HTWG Konstanz.
 * @param $username
 * @param $password
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

    preg_match_all('/^Set-Cookie:\s*([^;]*)/mi', $result_login, $matches); /* Retrieve cookies and save them to an array */
    $cookies = array();
    foreach ($matches[1] as $item) {
        parse_str($item, $cookie);
        $cookies = array_merge($cookies, $cookie);
    }

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

    echo $matches[0];
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
    echo json_encode($speiseplan);
}

/* Set return-headers to enable CORS policy */
header("Access-Control-Allow-Origin: *");
header('Access-Control-Allow-Credentials: true');
header('Access-Control-Max-Age: 86400');


/* Handle GET requests */
if (isset($_GET['drucker'])) {
    echo get_druckerkonto($_GET['username'], $_GET['password']);
} else if (isset($_GET['mensa']) || isset($_GET['speiseplan'])) {
    echo get_speiseplan();
}
