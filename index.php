<?php
include 'simple_html_dom.php';
header('Content-Type: text/html; charset=utf-8');
?>

<?php

$lines = readeFile();
foreach ($lines as $site){
    $i = 1;// Counter for debug purpose
    $errorToMessage = array();
    $arrayEmptyUrl = array();
    $arrayUnreadUrl = array();
    $toCheckURL = rtrim($site);
    $baseUrl = getBaseUrl($toCheckURL);
    $siteName = parse_url($toCheckURL);
    $result = init($toCheckURL);
    toLog($result['message']);
    toEmptyUrl($arrayEmptyUrl);
    toErrorUrl($arrayUnreadUrl);
    sendMail($result['message'], $arrayEmptyUrl);
    echo "-------------next---------- \n";
}

function getBaseUrl($url){
    $parsedUrl = parse_url($url);
    $result = $parsedUrl["scheme"] . "://" . $parsedUrl["host"];
    return $result;
}

function init($url) {

    static $arrayChecked  = array(), $arrayUncheck = array(), $arrayError = array();
    global $errorToMessage;
    $res = array();

    $res['code'] = checkCodeUrl($url);
    if($res['code']==200){
        $arrayChecked[] = $url;
        if (in_array($url, $arrayUncheck)){
            $key = array_search($url,$arrayUncheck);
            unset($arrayUncheck[$key]);
        }
        $links = getUrlArray($url);
        if(!empty($links)){
            foreach($links as $link){
                if (!in_array($link, $arrayChecked)&&!in_array($link, $arrayUncheck)&&!in_array($link, $arrayError)){
                    $arrayUncheck[] = $link;
                }
            }
        }
    }
    else {
        $arrayError[] = $url;
        $errorToMessage[$url] = $res['code'];
        if (in_array($url, $arrayUncheck)){
            $key = array_search($url,$arrayUncheck);
            unset($arrayUncheck[$key]);
        }
    }

    foreach ($arrayUncheck as $unchecked){
        init($unchecked);
    }

    $resulettest['checked'] = $arrayChecked;
    $resulettest['error'] = $arrayError;
    $resulettest['unchecked'] = $arrayUncheck;
    $resulettest['message'] = $errorToMessage;
return $resulettest;
}

function checkCodeUrl($url) {
    $codes = array(0=>'Domain Not Found',
               100=>'Continue',
               101=>'Switching Protocols',
               200=>'OK',
               201=>'Created',
               202=>'Accepted',
               203=>'Non-Authoritative Information',
               204=>'No Content',
               205=>'Reset Content',
               206=>'Partial Content',
               300=>'Multiple Choices',
               301=>'Moved Permanently',
               302=>'Found',
               303=>'See Other',
               304=>'Not Modified',
               305=>'Use Proxy',
               307=>'Temporary Redirect',
               400=>'Bad Request',
               401=>'Unauthorized',
               402=>'Payment Required',
               403=>'Forbidden',
               404=>'Not Found',
               405=>'Method Not Allowed',
               406=>'Not Acceptable',
               407=>'Proxy Authentication Required',
               408=>'Request Timeout',
               409=>'Conflict',
               410=>'Gone',
               411=>'Length Required',
               412=>'Precondition Failed',
               413=>'Request Entity Too Large',
               414=>'Request-URI Too Long',
               415=>'Unsupported Media Type',
               416=>'Requested Range Not Satisfiable',
               417=>'Expectation Failed',
               500=>'Internal Server Error',
               501=>'Not Implemented',
               502=>'Bad Gateway',
               503=>'Service Unavailable',
               504=>'Gateway Timeout',
               505=>'HTTP Version Not Supported');

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_HEADER, true);
    curl_setopt($ch, CURLOPT_NOBODY, true);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    curl_setopt($ch, CURLOPT_MAXREDIRS, 10);
    $data = curl_exec($ch);
    $http_code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    return $http_code;
}

function getUrlArray($url) {
    global $baseUrl, $i, $arrayEmptyUrl;
    global $arrayUnreadUrl;
    $links = array();
    echo '№'. $i++ . ': '; // Counter for debug purpose
    echo $url. "\n";
    if($url=='')
        return;
    $html = file_get_html($url);
    if (!method_exists($html,"find")){
        $arrayUnreadUrl[]=$url;
        return;
    }
    foreach($html->find('a') as $element)
    {
        $value=$element->href;
         if((strlen(trim($value))<2)||(strstr($value, "?"))||(strstr($value, "http"))||(strstr($value, "mailto:"))||(strstr($value, "tel"))){
            if ($value == '#'){
                $name = $element->plaintext;
                $value = $baseUrl . '/' . $value;
                 $arrayEmptyUrl[$name] = $value;
            }
            continue;
        }
        if (substr($value, 0, 1) !== '/'){
             $value = '/' . $value;
        }
        $value=str_replace(' ','%20',$value);
        $val = $baseUrl . $value;
        $links[]=$val;
    }
    $html->clear();
    unset($html);
    return $links;
}
function sendMail($errors, $empty) {
    global $siteName;
    $message = '';
    If (empty($errors) && empty($empty)) {
        return;
    }
    if (!empty($errors)) {
        $message .= "Ошибки на сайте: \n";
        foreach($errors as $key => $value){
            $message .= 'Ссылка: ' . $key . ' Код ошибки: ' . $value ."\n";
        }
    }
    if(!empty($empty)) {
        $emptyCount = count($empty);
        $message .= "\n Количество пустых ссылок на сайте: " . $emptyCount . "\n";
    }
    mail('newax90@gmail.com', 'Ошибки на сайте ' . $siteName['host'], $message);
}

function toLog($arrayError) {
    global $siteName;
    $message = '';
    if (!file_exists('logs/' . $siteName['host'])){
        mkdir('logs/' . $siteName['host'], 0777, TRUE);
    }
    $file = 'logs/' . $siteName['host'] . '/'.date("Y-m-d H:i:s").'.txt';
    if (empty($arrayError)) {
        $message .= 'Ошибки не найдены, возрадуйся';
    }
    else{
        foreach($arrayError as $key => $value){
            $message .= 'Ссылка: ' . $key . ' Код ошибки: ' . $value ."\n";
        }
    }

    file_put_contents($file, $message);
}

function toEmptyUrl($emptyUrl) {
    global $siteName;
    $message = '';
    $file = 'emptyUrl/'. $siteName['host'] .'.txt';
    if (empty($emptyUrl)) {
        $message .= 'Ересь не обнаружена. За ИМПЕРАТОРА!';
    }
    else{
        foreach($emptyUrl as $key => $value){
            $message .= 'Название: ' . $key . ' Ссылка: ' . $value ."\n";
        }
    }
    file_put_contents($file, $message);
}

function toErrorUrl($unreadUrl) {
        $message = '';
        $file = 'errorUrl.txt';
        foreach($unreadUrl as $key => $value){
             $message .= 'Ссылка: ' . $value ."\n";
         }
        file_put_contents($file, $message);
}

function readeFile() {
    $lines = file('list.txt');
    return $lines;
}

 ?>