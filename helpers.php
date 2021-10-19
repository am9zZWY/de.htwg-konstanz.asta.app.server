<?php
/**
 * Fetch an HTML from URL and return an DOMXPath Object of it.
 * @param $url
 * @return DOMXPath
 */
function fetch_and_create_dom($url): DOMXPath
{
    $html = file_get_contents($url);
    return create_domxpath($html);
}

/**
 * Return an DOMXPath Object from a simple HTML.
 * @param $html
 * @return DOMXPath
 */
function create_domxpath($html): DOMXPath
{
    $doc = new DOMDocument();
    $doc->loadHTML($html);
    return new DOMXPath($doc);
}

/**
 * Escape all html characters.
 * @param $string
 * @return string
 */
function clean_string($string): string
{
    return htmlspecialchars($string, ENT_QUOTES);
}

/**
 * Create array with Cookies.
 * @param $result
 * @param bool $as_json
 * @return array|false
 */
function get_cookies($result, bool $as_json=false): bool|array
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
 * @param $result
 * @return mixed
 */
function get_cookies_raw($result): mixed
{
    preg_match('/^Set-Cookie:\s*([^;]*)/mi', $result, $matches);
    return $matches[1];
}

/**
 * Wraps Cookie for request.
 * @param $cookie
 * @return string
 */
function create_cookie($cookie): string
{
    $cookie_as_string = $cookie;
    if (!is_string($cookie)) {
        try {
            $cookie_as_string = json_encode($cookie, JSON_THROW_ON_ERROR);
        } catch (JsonException $e) {
            $cookie_as_string = '';
        }
    }
    return 'Cookie: ' . $cookie_as_string;
}

/**
 * Calls func which returns either a string or false.
 * Catches any problems to JSON.
 * @param $func
 * @param null $params
 */
function send($func, $params = null): void
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
            http_response_code(404);
            echo 'Not found';
        }
    } catch (JsonException) {
        http_response_code(500);
        echo 'Error';
    }
}