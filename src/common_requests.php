<?php

require_once 'helpers.php';

/**
 * Get the newest meals of the HTWG Canteen.
 * @return string|false
 * @throws JsonException
 */
function get_speiseplan(): string|false
{
    $speiseplan_json = get_cached_file('./cache/speiseplan.json');
    if ($speiseplan_json !== false) {
        return $speiseplan_json;
    }

    $speiseplan_xml = file_get_contents('https://www.max-manager.de/daten-extern/seezeit/xml/mensa_htwg/speiseplan.xml');
    if ($speiseplan_xml === false) {
        return false;
    }
    $xml = simplexml_load_string($speiseplan_xml);
    if ($xml === false) {
        return false;
    }

    $speiseplan = new stdClass();
    foreach ($xml->tag as $tag) {
        $day = new stdClass();
        $_timestamp = $tag->attributes();
        if ($_timestamp === null) {
            continue;
        }

        $timestamp = date('d.m.Y', (int) round(((int)$_timestamp->timestamp) / (86400)) * 86400);

        $items = $tag->item;
        $cleaned_items = [];
        foreach ($items as $item) {
            $food = new stdClass();
            $food->category = (string)$item->category;
            $food->title = (string)$item->title;
            $food->price = [(string)$item->preis1, (string)$item->preis2, (string)$item->preis3, (string)$item->preis4];
            $cleaned_items[] = $food;
            $food->kind = (string)$item->icons;
        }
        $day->items = $cleaned_items;

        $speiseplan->$timestamp = $day;
    }

    $encoded_speiseplan = json_encode($speiseplan, JSON_THROW_ON_ERROR);
    if ($encoded_speiseplan === false) {
        return false;
    }

    create_cached_file('speiseplan.json', $encoded_speiseplan);

    header(CONTENT_JSON);
    return $encoded_speiseplan;
}

/**
 * Get HTML of "Termine und Fristen" from HTWG.
 * @return string|false
 */
function get_termine(): string|false
{
    $xpath = fetch_and_create_domxpath('https://www.htwg-konstanz.de/studium/pruefungsangelegenheiten/terminefristen/');
    if ($xpath === false) {
        return false;
    }

    $termine = $xpath->query('.//h2');
    if ($termine === false) {
        return false;
    }

    header(CONTENT_HTML);
    return $termine[0]->parentNode->ownerDocument->saveHTML($termine[0]->parentNode);
}

/**
 * Get prices and opening times of Café Endlicht.
 * @param string $param
 * @return string|false
 * @throws JsonException
 */
function get_endlicht(string $param): string|false
{
    $xpath = fetch_and_create_domxpath('https://www.htwg-konstanz.de/%20/hochschule/einrichtungen/asta/cafe-endlicht/');
    if ($xpath === false) {
        return false;
    }

    if ($param === 'zeiten') {
        $endlicht_zeiten = $xpath->query('.//*[contains(text(), "Öffnungszeiten")]');
        if ($endlicht_zeiten === false) {
            return false;
        }

        header(CONTENT_HTML);
        return $endlicht_zeiten[0]->ownerDocument->saveHTML($endlicht_zeiten[0]);
    }

    if ($param === 'preise') {
        $endlicht_preise = $xpath->query('.//*[contains(text(), "€")]/parent::ul/li/text()');
        if ($endlicht_preise === false) {
            return false;
        }

        $preise = [];
        foreach ($endlicht_preise as $endlicht_preis) {
            $item = new stdClass();
            $parsed_string = explode(':', $endlicht_preis->textContent);
            $item->name = trim($parsed_string[0]);
            $item->price = trim($parsed_string[1]);
            $preise[] = $item;
        }
        header(CONTENT_JSON);
        return json_encode($preise, JSON_THROW_ON_ERROR);
    }

    return false;
}