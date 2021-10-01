<?php

function get_speiseplan()
{
    $doc = new DOMDocument();
    $html = file_get_contents('https://seezeit.com/essen/speiseplaene/mensa-htwg/');
    $doc->loadHTML($html);
    $xpath = new DOMXPath($doc);

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
