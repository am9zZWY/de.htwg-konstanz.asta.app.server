<?php

// Allow from any origin
if (isset($_SERVER['HTTP_ORIGIN'])) {
    header("Access-Control-Allow-Origin: {$_SERVER['HTTP_ORIGIN']}");
    header('Access-Control-Allow-Credentials: true');
    header('Access-Control-Max-Age: 86400');
}

function fetch_and_create_dom($url) {
    $doc = new DOMDocument();
    $html = file_get_contents($url);
    $doc->loadHTML($html);
    return new DOMXPath($doc);
}

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
get_speiseplan();
?>
