<?php

ini_set("display_errors", "off");
require "/etc/phpmyadmin/config.inc.php";
ini_set("display_errors", "on");

/*
 * HTTP Library Functions
 */
function http_get($url) {
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    $json = json_decode(curl_exec($ch), true);
    curl_close($ch);
    return $json;
}

function http_post($url, $body, $headers) {
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);
    curl_setopt($ch, CURLOPT_POST, 1);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    $json = json_decode(curl_exec($ch), true);
    curl_close($ch);
    return $json;
}

$oidc_url = getenv("OIDC_URL");
$oidc_client_id = getenv("OIDC_CLIENT_ID");
$oidc_client_secret = getenv("OIDC_CLIENT_SECRET");
$redirect_uri = urlencode("https://" . $_SERVER["SERVER_NAME"] . $_SERVER["PHP_SELF"]);
$config = http_get($oidc_url . "/.well-known/openid-configuration");

ini_set('session.use_cookies', 'true');
session_set_cookie_params(0, '/', '', true, true);
session_name("SignonSession");
@session_start();
unset($_SESSION['PMA_single_signon_host']);
unset($_SESSION['PMA_single_signon_user']);
unset($_SESSION['PMA_single_signon_password']);

if(!$oidc_url || !$oidc_client_id || !$oidc_client_secret) {
    echo("Not all required environment variables are set. Required environment variables: OIDC_URL, OIDC_CLIENT_ID, OIDC_CLIENT_SECRET");
    return;
}
if(isset($_GET["database"])) {
    $auth_uri = $config["authorization_endpoint"] . "?response_type=code&client_id=$oidc_client_id&redirect_uri=$redirect_uri&state=" . urlencode(base64_encode($_GET["database"]));
    header("Location: $auth_uri");
}
else if(isset($_GET["code"]) && isset($_GET["state"])) {
    $database = base64_decode(urldecode($_GET["state"]));
    $token = http_post($config["token_endpoint"], "grant_type=authorization_code&audience=phpmyadmin&code=" . $_GET["code"] . "&redirect_uri=$redirect_uri", ["Authorization: Basic " . base64_encode($oidc_client_id . ":" . $oidc_client_secret), "Content-Type: application/x-www-form-urlencoded"]);
    if(isset($token["error"])) {
        if($token["error_description"] == "Code not valid")
            header("Location: /");
        echo($token["error_description"]);
        return;
    }
    $rpt = http_post($config["token_endpoint"], "grant_type=urn:ietf:params:oauth:grant-type:uma-ticket&audience=$oidc_client_id&response_include_resource_name=true&response_mode=permissions", ["Authorization: Bearer " . $token["access_token"], "Content-Type: application/x-www-form-urlencoded"]);
    $res = [];
    if(!isset($rpt["error"]))
        $res = array_filter($rpt, function($r) use ($database) { return $r["rsname"] == $database; });
    if(empty($res)) {
        header("HTTP/1.1 403 Forbidden");
        return;
    }
    foreach($cfg["Servers"] as $server)
        if($server["host"] == $database){
            $_SESSION['PMA_single_signon_host'] = $server["host"];
            $_SESSION['PMA_single_signon_user'] = $server["user"];
            $_SESSION['PMA_single_signon_password'] = $server["password"];
            $_SESSION['PMA_single_signon_HMAC_secret'] = hash('sha1', uniqid(strval(random_int(0, mt_getrandmax())), true));
        }
    session_write_close();
    header("Location: /");
}
else
    header("Location: /");