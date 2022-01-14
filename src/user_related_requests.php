<?php

require_once 'helpers.php';

/**
 * Get information about the Printer Account from HTWG.
 * @param string $username
 * @param string $password
 * @return array<mixed>
 */
function get_druckerkonto(string $username, string $password): array
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
    if (code_is_error($result_login[0])) {
        return $result_login;
    }

    $cookies = get_cookies($result_login[1]);


    /**
     * Get prepared page from server.
     * Cookies are needed to authenticate.
     */
    $result_druckerkonto = send_with_curl('https://login.rz.htwg-konstanz.de/userprintacc.spy?activeMenu=Druckerkonto', type: "GET", http_header: array(create_cookie($cookies)));
    if (code_is_error($result_druckerkonto[0])) {
        return $result_druckerkonto;
    }

    /* Get first digits */
    $matches = array();
    $result_match = preg_match('(\d+,\d+)', $result_druckerkonto[1], $matches);
    if ($result_match === false || !isset($matches[0])) {
        return array(500);
    }

    header(CONTENT_TEXT);
    return array(200, $matches[0]);
}

/**
 * Get grades from QIS.
 * @param string $username
 * @param string $password
 * @return array<mixed>
 * @throws JsonException
 */
function get_noten(string $username, string $password): array
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
    if (code_is_error($result_post_prepare[0])) {
        return $result_post_prepare;
    }
    $cookies = get_cookies_raw($result_post_prepare[1]); /* Need raw cookies */

    $header_noten = array(
        'Host: qisserver.htwg-konstanz.de',
        'Connection: keep-alive',
        create_cookie($cookies)
    );

    /* Login. */
    send_with_curl('https://qisserver.htwg-konstanz.de/qisserver/rds?state=user&type=0&category=menu.browse&breadCrumbSource=&startpage=portal.vm&chco=y', type: "GET", http_header: $header_noten);

    /* Prüfungsverwaltung. */
    $result_get_pruefungsverwaltung = send_with_curl('https://qisserver.htwg-konstanz.de/qisserver/rds?state=change&type=1&moduleParameter=studyPOSMenu&nextdir=change&next=menu.vm&subdir=applications&xml=menu&purge=y&navigationPosition=functions%2CstudyPOSMenu&breadcrumb=studyPOSMenu&topitem=loggedin&subitem=studyPOSMenu', type: "GET", http_header: $header_noten);
    if (code_is_error($result_get_pruefungsverwaltung[0])) {
        return $result_get_pruefungsverwaltung;
    }

    /* Path for Notenspiegel über alle bestandenen Prüfungsleistungen. */
    $xpath = create_domxpath($result_get_pruefungsverwaltung[1]);
    if ($xpath === false) {
        return array(500);
    }
    $notenspiegel_path = $xpath->query('.//a[contains(text(), "Notenspiegel über alle bestandenen Leistungen")]/@href');
    if ($notenspiegel_path === false || !isset($notenspiegel_path[0])) {
        return array(500);
    }

    /* Notenspiegel über alle bestandenen Prüfungsleistungen. */
    $result_get_notenspiegel = send_with_curl($notenspiegel_path[0]->nodeValue, type: "GET", http_header: $header_noten);
    if (code_is_error($result_get_notenspiegel[0])) {
        return $result_get_notenspiegel;
    }


    /* Parse Notenspiegel. */
    $xpath = create_domxpath($result_get_notenspiegel[1]);
    if ($xpath === false) {
        return array(500);
    }

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
        return array(200, json_encode($grades_obj, JSON_THROW_ON_ERROR));
    }
    return array(500);
}

/**
 * Get timetable from LSF.
 * @param string $username
 * @param string $password
 * @param string $week
 * @param string $year
 * @param string $type
 * @return array<mixed>
 */
function get_stundenplan(string $username, string $password, string $week, string $year = 'all', string $type = 'table'): array
{
    /* Fields for POST request */
    $fields = [
        'asdf' => $username,
        'fdsa' => $password,
        'submit' => 'Anmelden'
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
    if (code_is_error($result_post_prepare[0])) {
        return $result_post_prepare;
    }
    $cookies = get_cookies_raw($result_post_prepare[1]); /* Need raw cookies */

    $header_stundenplan = array(
        'Host: lsf.htwg-konstanz.de',
        'Connection: keep-alive',
        create_cookie($cookies)
    );

    /* Login. */
    send_with_curl('https://lsf.htwg-konstanz.de/qisserver/rds?state=user&type=0&category=menu.browse&breadCrumbSource=portal&startpage=portal.vm&chco=y', type: "GET", http_header: $header_stundenplan);


    /* year = all */
    $timetable = send_with_curl('https://lsf.htwg-konstanz.de/qisserver/rds?state=wplan&week=500&act=show&pool=&show=plan&P.vx=kurz&P.Print=', type: "GET", http_header: $header_stundenplan, header: false);

    if ($year != null && $year !== 'all' && $week != null) {
        /* Get timetable by week and year */
        $timetable = send_with_curl('https://lsf.htwg-konstanz.de/qisserver/rds?state=wplan&week=' . $week . '_' . $year . '&act=show&pool=&show=plan&P.vx=kurz&P.Print=', type: "GET", http_header: $header_stundenplan, header: false);

        if (code_is_error($timetable[0])) {
            return $timetable;
        }
    }

    if ($type === 'table' || $type === '') {
        header(CONTENT_HTML);
        return $timetable;
    }

    return array(500);
}

/**
 * @param string $username
 * @param string $password
 * @return array<mixed>
 */
function get_immatrikulations_bescheinigung(string $username, string $password): array
{
    $user_agent = 'User-Agent: Mozilla/5.0 (Macintosh; Intel Mac OS X 10.15; rv:95.0) Gecko/20100101 Firefox/95.0';
    $host = 'Host: hisinone.htwg-konstanz.de';
    $origin = 'Origin: https://hisinone.htwg-konstanz.de';

    $header = array(
        'Content-Type: application/x-www-form-urlencoded',
        $user_agent,
        $host,
        $origin,
        'Connection: keep-alive'
    );

    /* GET: Startpage (not logged in) */
    $result_startpage = send_with_curl('https://hisinone.htwg-konstanz.de/qisserver/pages/cs/sys/portal/hisinoneStartPage.faces', type: "GET", http_header: $header, allow_redirect: true);
    if (code_is_error($result_startpage[0])) {
        return $result_startpage;
    }

    /* Get new cookies and variables */
    $cookies = get_cookies_raw($result_startpage[1]);
    $cookies = add_cookies('sessionRefresh=0', $cookies);
    $xpath = create_domxpath($result_startpage[1]);
    $ajax_token = get_node($xpath, './/*[@id="ajaxToken"]/@value');

    /******************************************************************************************************************/

    /* Fields for POST request */
    $login_fields = [
        'userInfo' => '',
        'ajax-token' => $ajax_token,
        'asdf' => $username,
        'fdsa' => $password,
        'submit' => ''
    ];

    /* Header: Login */
    $cookies = add_cookies('lastRefresh=' . get_time_in_millis(), $cookies);
    $header_login = array(
        $user_agent,
        $host,
        $origin,
        create_cookie($cookies),
        'Content-Type: application/x-www-form-urlencoded; charset=utf-8',
        'Connection: keep-alive'
    );

    /* POST: Login 302 */
    $result_login = send_with_curl('https://hisinone.htwg-konstanz.de/qisserver/rds?state=user&type=1&category=auth.login', type: "POST", post_fields: http_build_query($login_fields), http_header: $header_login);
    if (code_is_error($result_login[0])) {
        return $result_login;
    }
    $result_login_cookies = get_cookies_raw($result_login[1]);
    $cookies = add_cookies($result_login_cookies, $cookies);

    /* Header: After Login */
    $header = array(
        $user_agent,
        $host,
        $origin,
        create_cookie($cookies),
        'Connection: keep-alive'
    );

    /* GET: Login 302 */
    send_with_curl('https://hisinone.htwg-konstanz.de/qisserver/rds?state=user&type=0&category=menu.browse&breadCrumbSource=&startpage=portal.vm&chco=y', type: "GET", http_header: $header);

    /* GET: Login 200 */
    $result_login_redirect = send_with_curl('https://hisinone.htwg-konstanz.de/qisserver/pages/cs/sys/portal/hisinoneStartPage.faces', type: "GET", http_header: $header);
    if (code_is_error($result_login_redirect[0])) {
        return $result_login_redirect;
    }

    /* Get new cookies and variables */
    $result_login_redirect_cookies = get_cookies_raw($result_login_redirect[1]);
    $cookies = add_cookies($result_login_redirect_cookies, $cookies);
    $xpath = create_domxpath($result_login_redirect[1]);
    $authenticity_token = get_node($xpath, './/input[@name="authenticity_token"]/@value');
    $javax_faces_ViewState = get_node($xpath, './/input[@name="javax.faces.ViewState"]/@value');

    /******************************************************************************************************************/


    /* Fields for POST for Start Page */
    $startpage_fields = array(
        'activePageElementId' => '',
        'refreshButtonClickedId' => '',
        'navigationPosition' => 'link_homepage',
        'authenticity_token' => $authenticity_token,
        'autoScroll' => '',
        'startPage:portletInstanceId_20581:portletInstanceId_20581CollapsedState' => 'true',
        'startPage:portletInstanceId_20583:portletInstanceId_20583CollapsedState' => 'false',
        'startPage:portletInstanceId_20584:portletInstanceId_20584CollapsedState' => 'false',
        'startPage_SUBMIT' => '1',
        'javax.faces.ViewState' => $javax_faces_ViewState,
        'javax.faces.behavior.event' => 'action',
        'javax.faces.partial.event' => 'click',
        'javax.faces.source' => 'startPage:portletInstanceId_20584:hisinoneFunction:load',
        'javax.faces.partial.ajax' => 'true',
        'javax.faces.partial.execute' => 'startPage:workaroundForForceIdAjaxRequest startPage:portletInstanceId_20584:hisinoneFunction:load',
        'javax.faces.partial.render' => 'startPage:portletInstanceId_20584:hisinoneFunction:onready startPage:workaroundForForceIdAjaxRequest',
        'startPage' => 'startPage',
    );

    $cookies = add_cookies('lastRefresh=' . get_time_in_millis(), $cookies);
    $header = array(
        $user_agent,
        $host,
        $origin,
        create_cookie($cookies),
        'Content-Type: application/x-www-form-urlencoded; charset=utf-8',
        'Connection: keep-alive'
    );

    /* POST: Start Page (logged in) */
    $result_startpage = send_with_curl('https://hisinone.htwg-konstanz.de/qisserver/pages/cs/sys/portal/hisinoneStartPage.faces', type: "POST", post_fields: http_build_query($startpage_fields), http_header: $header);
    if (code_is_error($result_startpage[0])) {
        return $result_startpage;
    }

    $result_startpage_cookies = get_cookies_raw($result_startpage[1]);
    $cookies = add_cookies($result_startpage_cookies, $cookies);

    /******************************************************************************************************************/

    $cookies = add_cookies('lastRefresh=' . get_time_in_millis(), $cookies);
    $header = array(
        $user_agent,
        $host,
        $origin,
        create_cookie($cookies),
        'Connection: keep-alive'
    );

    /* GET: Studienservice 302 */
    send_with_curl('https://hisinone.htwg-konstanz.de/qisserver/pages/cm/exa/enrollment/info/start.xhtml?_flowId=studyservice-flow&navigationPosition=hisinoneMeinStudium%2ChisinoneStudyservice&recordRequest=true', type: "GET", http_header: $header, allow_redirect: true);


    /******************************************************************************************************************/


    /* Fields: POST Bescheide/Bescheinigungen */
    $studienservice_bescheinigungen_fields = array(
        'activePageElementId' => "studyserviceForm:bescheinigung_TabBtn",
        'refreshButtonClickedId' => "",
        'navigationPosition' => "hisinoneMeinStudium,hisinoneStudyservice",
        'authenticity_token' => $authenticity_token,
        'autoScroll' => "",
        'studyserviceForm:fieldsetInforStatusStudent:collapsiblePanelCollapsedState' => "true",
        'studyserviceForm:fieldsetPersoenlicheData:collapsiblePanelCollapsedState' => "true",
        'studyserviceForm:fieldsetForAktionStudystatus:collapsiblePanelCollapsedState' => "false",
        'studyserviceForm:content.6' => "",
        'studyserviceForm:studienstatus:collapsibleFieldsetCourseOfStudies:collapsiblePanelCollapsedState' => "false",
        'studyserviceForm_SUBMIT' => "1",
        'javax.faces.ViewState' => "e1s1",
    );

    $cookies = add_cookies('lastRefresh=' . get_time_in_millis(), $cookies);


    $header = array(
        $user_agent,
        $host,
        $origin,
        create_cookie($cookies),
        'Content-Type: application/x-www-form-urlencoded; charset=utf-8',
        'Connection: keep-alive'
    );

    /* POST: Bescheide/Bescheinigungen 302 */
    $result_bescheide = send_with_curl('https://hisinone.htwg-konstanz.de/qisserver/pages/cm/exa/enrollment/info/start.xhtml?_flowId=studyservice-flow&_flowExecutionKey=e1s1', type: "POST", post_fields: http_build_query($studienservice_bescheinigungen_fields), http_header: $header, allow_redirect: true);
    if (code_is_error($result_bescheide[0])) {
        return $result_bescheide;
    }

    $xpath = create_domxpath($result_bescheide[1]);
    $javax_faces_ViewState = get_node($xpath, './/input[@name="javax.faces.ViewState"]/@value');

    /******************************************************************************************************************/

    /* POST: Immatrikulationsbescheinigung */
    $imm_besch_fields = array(
        'activePageElementId' => '',
        'refreshButtonClickedId' => '',
        'navigationPosition' => 'hisinoneMeinStudium,hisinoneStudyservice',
        'authenticity_token' => $authenticity_token,
        'autoScroll' => '',
        'studyserviceForm:fieldsetInforReport:collapsiblePanelCollapsedState' => 'true',
        'studyserviceForm:fieldsetForAktionReports:collapsiblePanelCollapsedState' => 'false',
        'studyserviceForm:bescheinigung:reports:collapsiblePanelCollapsedState' => 'false',
        'studyserviceForm_SUBMIT' => '1',
        'javax.faces.ViewState' => $javax_faces_ViewState,
        'javax.faces.behavior.event' => 'action',
        'javax.faces.partial.event' => 'click',
        'javax.faces.source' => 'studyserviceForm:bescheinigung:reports:reportButtons:jobConfigurationButtons:0:jobConfigurationButtons:2:job2',
        'javax.faces.partial.ajax' => 'true',
        'javax.faces.partial.execute' => 'studyserviceForm:bescheinigung:reports:reportButtons:jobConfigurationButtons:0:jobConfigurationButtons:2:job2',
        'javax.faces.partial.render' => 'studyserviceForm:bescheinigung:reports:reportButtons:jobConfigurationButtonsOverlay studyserviceForm:bescheinigung:reports:reportButtons:jobDownload studyserviceForm:messages-infobox',
        'studyserviceForm' => 'studyserviceForm',
    );

    $cookies = add_cookies('lastRefresh=' . get_time_in_millis(), $cookies);
    $header = array(
        $user_agent,
        $host,
        $origin,
        create_cookie($cookies),
        'Content-Type: application/x-www-form-urlencoded; charset=utf-8',
        'Connection: keep-alive'
    );

    $result_download_page = send_with_curl('https://hisinone.htwg-konstanz.de/qisserver/pages/cm/exa/enrollment/info/start.xhtml?_flowId=studyservice-flow&_flowExecutionKey=' . $javax_faces_ViewState, type: "POST", post_fields: http_build_query($imm_besch_fields), http_header: $header, allow_redirect: true);
    if (code_is_error($result_download_page[0])) {
        return $result_download_page;
    }

    $xpath = create_domxpath($result_download_page[1]);
    $link_to_download = get_node($xpath, './/a[@class="downloadFile unsichtbar"]/@href');
    $result_download = send_with_curl('https://hisinone.htwg-konstanz.de' . $link_to_download, type: "GET", http_header: $header, header: false, allow_redirect: true);

    header(CONTENT_DOWNLOAD);
    header(CONTENT_PDF);

    return $result_download;
}

