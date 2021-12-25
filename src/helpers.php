<?php
/**
 * Time in milliseconds since January 1970
 * @return int
 */
function get_time_in_millis(): int
{
    return intval(microtime(true) * 1000);
}

/**
 * Fetch an HTML from URL and return an DOMXPath Object of it.
 * @param string $url
 * @return DOMXPath|false
 */
function fetch_and_create_domxpath(string $url): false|DOMXPath
{
    $html = file_get_contents($url);
    if ($html === false) {
        return false;
    }
    return create_domxpath($html);
}

/**
 * Fetch an HTML from URL and return an DOMDocument Object of it.
 * @param string $url
 * @return DOMDocument|false
 */
function fetch_and_create_dom(string $url): false|DOMDocument
{
    $html = file_get_contents($url);
    if ($html === false) {
        return false;
    }
    return create_dom($html);
}

/**
 * Return an DOMDocument from a simple HTML.
 * @param string $html
 * @return DOMDocument|false
 */
function create_dom(string $html): false|DOMDocument
{
    $doc = new DOMDocument();
    $ret = $doc->loadHTML($html);
    if ($ret === false) {
        return false;
    }
    return $doc;
}

/**
 * Return an DOMXPath Object from a simple HTML.
 * @param string $html
 * @return DOMXPath|false
 */
function create_domxpath(string|false $html): false|DOMXPath
{
    if ($html === false) {
        return false;
    }
    $dom = create_dom($html);
    if ($dom === false) {
        return false;
    }
    return new DOMXPath($dom);
}

/**
 * @param DOMXPath $xpath
 * @param string $query
 * @return string
 */
function get_node(DOMXPath|false $xpath, string $query): string
{
    if ($xpath === false) {
        return '';
    }

    $result = $xpath->query($query);
    if ($result === false) {
        return '';
    }

    return $result[0]->nodeValue;
}

/**
 * Escape all html characters.
 * @param string|null $string $string
 * @return string
 */
function clean_string(string|null $string): string
{
    if ($string == null) {
        return '';
    }
    return htmlspecialchars($string, ENT_QUOTES);
}

/**
 * Get value from global $_GET variable.
 * @param string $key
 * @return string
 */
function get_value(string $key): string
{
    if ($key != null && isset($_GET[$key])) {
        return clean_string($_GET[$key]);
    }
    return '';
}

/**
 * Create array with Cookies.
 * @param string $result
 * @param bool $as_json
 * @return array<string>|string|false
 */
function get_cookies(string $result, bool $as_json = false): false|array|string
{
    preg_match_all('/^Set-Cookie:\s*([^;]*)/mi', $result, $matches); /* Retrieve cookies and save them to an array */
    $cookies = [];
    foreach ($matches[1] as $item) {
        parse_str($item, $cookie);
        $cookies = array_merge($cookies, $cookie);
    }
    if ($as_json === true) {
        try {
            return json_encode($cookies, JSON_THROW_ON_ERROR);
        } catch (JsonException $e) {
            return false;
        }
    }
    return $cookies;
}

/**
 * Return Cookies as String.
 * @param string|false $result
 * @return string
 */
function get_cookies_raw(string|false $result): string
{
    if ($result === false) {
        return '';
    }
    preg_match_all('/^Set-Cookie:\s*([^;]*)/mi', $result, $matches);
    return implode('; ', $matches[1]);
}

/**
 * Creates an array from a key-value-pair which is defined with an equals operator.
 * @param string $str
 * @return array<string>
 */
function cookie_string_to_array(string $str): array
{
    preg_match('/([^=;\s]+)\=([^;\s]+)/mi', $str, $matches);
    return array_slice($matches, 1, 3);
}

/**
 * Add Cookie to Cookiejar.
 * @param string $cookies
 * @param string $cookiejar
 * @return string
 */
function add_cookies(string $cookies, string $cookiejar): string
{

    /* Convert cookiejar from string to object */
    $local_cookies = explode(';', $cookiejar);
    $local_cookiejar = new stdClass();
    foreach ($local_cookies as $local_cookie) {
        $local_cookie_obj = cookie_string_to_array($local_cookie);
        $k = $local_cookie_obj[0];
        $v = $local_cookie_obj[1];
        $local_cookiejar->{$k} = $v;
    }

    /* Get new cookies and add them to cookiejar */
    $local_cookies = explode(';', $cookies);
    foreach ($local_cookies as $local_cookie) {
        $local_cookie_obj = cookie_string_to_array($local_cookie);
        $k = $local_cookie_obj[0];
        $v = $local_cookie_obj[1];
        $local_cookiejar->{$k} = $v;
    }

    /* Convert object back to string */
    $cookies_as_entries = get_object_vars($local_cookiejar);
    $cookies_as_string = [];
    foreach (array_keys($cookies_as_entries) as $cookie_key) {
        $cookie_as_string = $cookie_key . '=' . $cookies_as_entries[$cookie_key];
        $cookies_as_string[] = $cookie_as_string;
    }

    return implode('; ', $cookies_as_string);
}

/**
 * Wraps Cookie for request.
 * @param mixed $cookie
 * @param bool $with_cookie_annotation
 * @return string
 */
function create_cookie(mixed $cookie, bool $with_cookie_annotation = true): string
{
    $cookie_as_string = $cookie;
    if (!is_string($cookie)) {
        try {
            $cookie_as_string = json_encode($cookie, JSON_THROW_ON_ERROR);
        } catch (JsonException $e) {
            $cookie_as_string = '';
        }
    }
    if ($with_cookie_annotation) {
        return 'Cookie: ' . $cookie_as_string;
    }
    return $cookie_as_string;
}

/**
 * Calls func which returns either a string or false.
 * Catches any problems to JSON.
 * @param callable $func
 * @param array<mixed>|null $params
 */
function send_back(callable $func, array|null $params = null): void
{
    try {
        if (isset($params)) {
            /* pass all params to func */
            $ret = $func(...$params);
        } else {
            $ret = $func();
        }

        if ($ret !== false) {
            http_response_code(200);
            echo $ret;
        } else {
            http_response_code(403);
            echo 'Falscher Benutzername oder Passwort.';
        }
    } catch (JsonException) {
        http_response_code(500);
        echo 'Error';
    }
}

/**
 * Send POST | GET request via curl.
 * @param string $url
 * @param string $type
 * @param string|null $post_fields
 * @param mixed|null $http_header
 * @param bool $header
 * @param bool $allow_redirect
 * @param string|null $encoding
 * @return string|false
 */
function send_with_curl(string $url, string $type, string|null $post_fields = null, mixed $http_header = null, bool $header = true, bool $allow_redirect = false, string|null $encoding = null): string|false
{
    $curl = curl_init($url);
    if ($curl === false) {
        return false;
    }

    if ($type === "POST") {
        curl_setopt($curl, CURLOPT_POST, true);

        /* CURLOPT_POSTFIELDS make only sense when $type is POST */
        if (isset($post_fields)) {
            curl_setopt($curl, CURLOPT_POSTFIELDS, $post_fields);
        }
    }

    if (isset($http_header)) {
        curl_setopt($curl, CURLOPT_HTTPHEADER, $http_header);
    }

    if ($header) {
        curl_setopt($curl, CURLOPT_HEADER, true); /* Enable Cookies */
    }
    curl_setopt($curl, CURLOPT_RETURNTRANSFER, true); /* Don't dump result; only return it */
    curl_setopt($curl, CURLOPT_CONNECTTIMEOUT, 0);
    curl_setopt($curl, CURLOPT_TIMEOUT, 10); /* Set timeout for execution */

    if ($allow_redirect) {
        curl_setopt($curl, CURLOPT_FOLLOWLOCATION, true);
    }

    if ($encoding !== null) {
        curl_setopt($curl, CURLOPT_ENCODING, $encoding);
    }

    $result = curl_exec($curl); /* Send request */

    $curl_errno = curl_errno($curl); /* Get errno */

    curl_close($curl); /* Close connection */

    if ($curl_errno > 0) {
        return false;
    }

    if (is_string($result)) {
        return $result;
    }
    return false;
}

/**
 * Create cached file.
 * @param string $filename
 * @param string $content
 * @return bool
 */
function create_cached_file(string $filename, string $content): bool
{
    $dir = $_SERVER['DOCUMENT_ROOT'] . '/cache/';
    if (!is_dir($dir)) {
        mkdir($dir, 0755, true);
    }

    $f = fopen($dir . $filename, 'w');
    if ($f === false) {
        return false;
    }
    $err = fwrite($f, $content);
    if ($err === false) {
        return false;
    }
    return fclose($f);
}

/**
 * Get a cached file.
 * @param string $filename
 * @return string|false
 */
function get_cached_file(string $filename): string|false
{
    $file = $_SERVER['DOCUMENT_ROOT'] . '/cache/' . $filename;
    if (!file_exists($file)) {
        return false;
    }

    return file_get_contents($file);
}