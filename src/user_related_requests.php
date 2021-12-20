<?php

require_once 'helpers.php';

/**
 * Get information about the Printer Account from HTWG.
 * @param string $username
 * @param string $password
 * @return string|false
 */
function get_druckerkonto(string $username, string $password): string|false
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
    $result_login = send_with_curl('https://login.rz.htwg-konstanz.de/index.spy', type: "POST", post_fields: http_build_query($fields));
    if ($result_login === false) {
        return false;
    }

    $cookies = get_cookies($result_login);
    if ($cookies === false) {
        return false;
    }

    /**
     * Get prepared page from server.
     * Cookies are needed to authenticate.
     */
    $result_druckerkonto = send_with_curl('https://login.rz.htwg-konstanz.de/userprintacc.spy?activeMenu=Druckerkonto', type: "GET", http_header: array(create_cookie($cookies)));
    if ($result_druckerkonto === false) {
        return false;
    }

    /* Get first digits */
    $matches = array();
    $result_match = preg_match('(\d+,\d+)', $result_druckerkonto, $matches);
    if ($result_match === false || !isset($matches[0])) {
        return false;
    }

    header(CONTENT_TEXT);
    return $matches[0];
}

/**
 * Get grades from QIS.
 * @param string $username
 * @param string $password
 * @return string|false
 * @throws JsonException
 */
function get_noten(string $username, string $password): string|false
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
    $result_post_prepare = send_with_curl('https://qisserver.htwg-konstanz.de/qisserver/rds?state=user&type=1&category=auth.login&startpage=portal.vm', type: "POST", post_fields: http_build_query($fields), http_header: $header_login);
    if ($result_post_prepare === false) {
        return false;
    }
    $cookies = get_cookies_raw($result_post_prepare); /* Need raw cookies */

    $header_noten = array(
        'Host: qisserver.htwg-konstanz.de',
        'Connection: keep-alive',
        create_cookie($cookies)
    );

    /* Login. */
    send_with_curl('https://qisserver.htwg-konstanz.de/qisserver/rds?state=user&type=0&category=menu.browse&breadCrumbSource=&startpage=portal.vm&chco=y', type: "GET", http_header: $header_noten);

    /* Prüfungsverwaltung. */
    $result_get_pruefungsverwaltung = send_with_curl('https://qisserver.htwg-konstanz.de/qisserver/rds?state=change&type=1&moduleParameter=studyPOSMenu&nextdir=change&next=menu.vm&subdir=applications&xml=menu&purge=y&navigationPosition=functions%2CstudyPOSMenu&breadcrumb=studyPOSMenu&topitem=loggedin&subitem=studyPOSMenu', type: "GET", http_header: $header_noten);
    if ($result_get_pruefungsverwaltung === false) {
        return false;
    }

    /* Path for Notenspiegel über alle bestandenen Prüfungsleistungen. */
    $xpath = create_domxpath($result_get_pruefungsverwaltung);
    $notenspiegel_path = $xpath->query('.//a[contains(text(), "Notenspiegel über alle bestandenen Leistungen")]/@href');
    if ($notenspiegel_path === false || !isset($notenspiegel_path[0])) {
        return false;
    }

    /* Notenspiegel über alle bestandenen Prüfungsleistungen. */
    $result_get_notenspiegel = send_with_curl($notenspiegel_path[0]->nodeValue, type: "GET", http_header: $header_noten);
    if ($result_get_notenspiegel === false) {
        return false;
    }


    /* Parse Notenspiegel. */
    $xpath = create_domxpath($result_get_notenspiegel);

    /* Get all grades */
    $grades = $xpath->query('.//span[text()="Prüfungsnummer"]/ancestor::tr/following-sibling::tr');
    if ($grades !== false) {
        $grades_obj = [];

        foreach ($grades as $grade) {
            $grade_tds = $xpath->query('.//td[@class="tabelle1"]', $grade);
            if ($grade_tds === false) {
                continue;
            }

            /* Skip the pseudo-grade 'Vorläuf. Notendurschnitt' if it exists. */
            if ($grade_tds[0]->nodeValue === '80000') {
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
    return false;
}

/**
 * Get timetable from LSF.
 * @param string $username
 * @param string $password
 * @param string $week
 * @param string $year
 * @param string $type
 * @return string|false
 */
function get_stundenplan(string $username, string $password, string $week, string $year = 'all', string $type = 'table'): string|false
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
    $result_post_prepare = send_with_curl('https://lsf.htwg-konstanz.de/qisserver/rds?state=user&type=1&category=auth.login&startpage=portal.vm&breadCrumbSource=portal', type: "POST", post_fields: http_build_query($fields), http_header: $header_login);
    if ($result_post_prepare === false) {
        return false;
    }
    $cookies = get_cookies_raw($result_post_prepare); /* Need raw cookies */

    $header_stundenplan = array(
        'Host: lsf.htwg-konstanz.de',
        'Connection: keep-alive',
        create_cookie($cookies)
    );

    /* Login. */
    send_with_curl('https://lsf.htwg-konstanz.de/qisserver/rds?state=user&type=0&category=menu.browse&breadCrumbSource=portal&startpage=portal.vm&chco=y', type: "GET", http_header: $header_stundenplan);


    /* year = all */
    $timetable = send_with_curl('https://lsf.htwg-konstanz.de/qisserver/rds?state=wplan&week=-1&act=show&pool=&show=plan&P.vx=kurz&P.Print=', type: "GET", http_header: $header_stundenplan, header: false);

    if ($year != null && $year !== 'all' && $week != null) {
        /* Get timetable by week and year */
        $timetable = send_with_curl('https://lsf.htwg-konstanz.de/qisserver/rds?state=wplan&week=' . $week . '_' . $year . '&act=show&pool=&show=plan&P.vx=kurz&P.Print=', type: "GET", http_header: $header_stundenplan, header: false);

        if ($timetable === false) {
            return false;
        }
    }

    if ($type === 'table' || $type === '') {
        return $timetable;
    }

    return '';
}