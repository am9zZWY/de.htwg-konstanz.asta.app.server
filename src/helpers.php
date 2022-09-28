<?php
/**
 * Time in milliseconds since January 1970
 *
 * @return int
 */
function get_time_in_millis(): int
{
    return (int)(microtime(TRUE) * 1000);
}

/**
 * Fetch an HTML from URL and return an DOMXPath Object of it.
 *
 * @param string $url
 *
 * @return DOMXPath|false
 */
function fetch_and_create_domxpath(string $url): false|DOMXPath
{
    $html = file_get_contents($url);
    if ($html === FALSE) {
        return FALSE;
    }
    return create_domxpath($html);
}

/**
 * Fetch an HTML from URL and return an DOMDocument Object of it.
 *
 * @param string $url
 *
 * @return DOMDocument|false
 */
function fetch_and_create_dom(string $url): false|DOMDocument
{
    $html = file_get_contents($url);
    if ($html === FALSE) {
        return FALSE;
    }
    return create_dom($html);
}

/**
 * Return an DOMDocument from a simple HTML.
 *
 * @param string $html
 *
 * @return DOMDocument|false
 */
function create_dom(string $html): false|DOMDocument
{
    $doc = new DOMDocument();
    $ret = $doc->loadHTML($html);
    if ($ret === FALSE) {
        return FALSE;
    }
    return $doc;
}

/**
 * Return an DOMXPath Object from a simple HTML.
 *
 * @param string $html
 *
 * @return DOMXPath|false
 */
function create_domxpath(string|false $html): false|DOMXPath
{
    if ($html === FALSE) {
        return FALSE;
    }
    $dom = create_dom($html);
    if ($dom === FALSE) {
        return FALSE;
    }
    return new DOMXPath($dom);
}

/**
 * @param DOMXPath|false $xpath
 * @param string         $query
 *
 * @return string
 */
function get_node(DOMXPath|false $xpath, string $query): string
{
    if ($xpath === FALSE) {
        return '';
    }

    $result = $xpath->query($query);
    if ($result === FALSE) {
        return '';
    }

    $nodeValue = $result[0]->nodeValue;
    if ($nodeValue === NULL) {
        return '';
    }

    return $nodeValue;
}

/**
 * Escape all html characters.
 *
 * @param string|null $string $string
 *
 * @return string
 */
function clean_string(string|null $string): string
{
    if ($string == NULL) {
        return '';
    }
    return htmlspecialchars($string, ENT_QUOTES);
}

/**
 * Get value from global $_GET variable.
 *
 * @param string $key
 *
 * @return string
 */
function get_value(string $key): string
{
    if ($key != NULL && isset($_GET[$key])) {
        return clean_string($_GET[$key]);
    }
    return '';
}

/**
 * Create array with Cookies.
 *
 * @param string $result
 * @param bool   $as_json
 *
 * @return array<string>|string
 */
function get_cookies(string $result, bool $as_json = FALSE): array|string
{
    preg_match_all('/^Set-Cookie:\s*([^;]*)/mi', $result, $matches); /* Retrieve cookies and save them to an array */
    $cookies = [];
    foreach ($matches[1] as $item) {
        parse_str($item, $cookie);
        $cookies = array_merge($cookies, $cookie);
    }
    if ($as_json === TRUE) {
        try {
            return json_encode($cookies, JSON_THROW_ON_ERROR);
        } catch (JsonException $e) {
            return '';
        }
    }
    return $cookies;
}

/**
 * Return Cookies as String.
 *
 * @param string|false $result
 *
 * @return string
 */
function get_cookies_raw(string|false $result): string
{
    if ($result === FALSE) {
        return '';
    }
    preg_match_all('/^Set-Cookie:\s*([^;]*)/mi', $result, $matches);
    return implode('; ', $matches[1]);
}

/**
 * Creates an array from a key-value-pair which is defined with an equals operator.
 *
 * @param string $str
 *
 * @return array<string>
 */
function cookie_string_to_array(string $str): array
{
    preg_match('/([^=;\s]+)\=([^;\s]+)/mi', $str, $matches);
    return array_slice($matches, 1, 3);
}

/**
 * Add Cookie to Cookiejar.
 *
 * @param string $cookies
 * @param string $cookiejar
 *
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
 *
 * @param mixed $cookie
 * @param bool  $with_cookie_annotation
 *
 * @return string
 */
function create_cookie(mixed $cookie, bool $with_cookie_annotation = TRUE): string
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
 *
 * @param callable          $func
 * @param array<mixed>|null $params
 */
function send_back(callable $func, array|null $params = NULL): void
{
    try {
        if (isset($params)) {
            /* pass all params to func */
            $ret = $func(...$params);
        } else {
            $ret = $func();
        }

        if (is_array($ret)) {
            http_response_code($ret[0]);
            if (code_is_error($ret[0])) {
                echo '';
            } else {
                echo $ret[1];
            }
        } elseif ($ret !== FALSE) {
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
 *
 * @param string      $url
 * @param string      $type
 * @param string|null $post_fields
 * @param mixed|null  $http_header
 * @param bool        $header
 * @param bool        $allow_redirect
 * @param string|null $encoding
 *
 * @return array<mixed>
 */
function send_with_curl(string $url, string $type, string|null $post_fields = NULL, mixed $http_header = NULL, bool $header = TRUE, bool $allow_redirect = FALSE, string|null $encoding = NULL): array
{
    $curl = curl_init($url);
    if ($curl === FALSE) {
        return array(-1);
    }

    if ($type === "POST") {
        curl_setopt($curl, CURLOPT_POST, TRUE);

        /* CURLOPT_POSTFIELDS make only sense when $type is POST */
        if (isset($post_fields)) {
            curl_setopt($curl, CURLOPT_POSTFIELDS, $post_fields);
        }
    }

    if (isset($http_header)) {
        curl_setopt($curl, CURLOPT_HTTPHEADER, $http_header);
    }

    if ($header) {
        curl_setopt($curl, CURLOPT_HEADER, TRUE); /* Enable Cookies */
    }
    curl_setopt($curl, CURLOPT_RETURNTRANSFER, TRUE); /* Don't dump result; only return it */
    curl_setopt($curl, CURLOPT_CONNECTTIMEOUT, 0);
    curl_setopt($curl, CURLOPT_TIMEOUT, 10); /* Set timeout for execution */

    if ($allow_redirect) {
        curl_setopt($curl, CURLOPT_FOLLOWLOCATION, TRUE);
    }

    if ($encoding !== NULL) {
        curl_setopt($curl, CURLOPT_ENCODING, $encoding);
    }

    $result = curl_exec($curl); /* Send request */

    $http_code = curl_getinfo($curl, CURLINFO_HTTP_CODE);

    $curl_errno = curl_errno($curl); /* Get errno */

    curl_close($curl); /* Close connection */

    if ($curl_errno > 0) {
        return array(-1);
    }

    if (is_string($result)) {
        return array($http_code, $result);
    }
    return array(500);
}

/**
 * Checks if status code is error or not
 *
 * @param int $status_code
 *
 * @return bool
 */
function code_is_error(int $status_code): bool
{
    return $status_code !== 200 && $status_code !== 301 && $status_code !== 302;
}

/**
 * Create cached file.
 *
 * @param string $filename
 * @param string $content
 *
 * @return bool
 */
function create_cached_file(string $filename, string $content): bool
{
    $dir = $_SERVER['DOCUMENT_ROOT'] . '/cache/';
    if (!is_dir($dir)) {
        mkdir($dir, 0755, TRUE);
    }

    $f = fopen($dir . $filename, 'w');
    if ($f === FALSE) {
        return FALSE;
    }
    $err = fwrite($f, $content);
    if ($err === FALSE) {
        return FALSE;
    }
    return fclose($f);
}

/**
 * Get a cached file.
 *
 * @param string $filename
 *
 * @return string|false
 */
function get_cached_file(string $filename): string|false
{
    $file = $_SERVER['DOCUMENT_ROOT'] . '/cache/' . $filename;
    if (!file_exists($file)) {
        return FALSE;
    }

    return file_get_contents($file);
}
