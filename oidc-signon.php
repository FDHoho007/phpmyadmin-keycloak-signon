<?php

ini_set("display_errors", "off");
require "/etc/phpmyadmin/config.inc.php";
ini_set("display_errors", "on");

/*
 * HTTP Library Functions
 */
function http_get($url, $headers = []) {
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    $json = json_decode(curl_exec($ch), true);
    curl_close($ch);
    return $json;
}

function http_post($url, $body, $headers = []) {
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

// Load environment
$oidc_url = getenv("OIDC_URL");
$oidc_client_id = getenv("OIDC_CLIENT_ID");
$oidc_client_secret = getenv("OIDC_CLIENT_SECRET");
$redirect_uri = urlencode("https://" . $_SERVER["SERVER_NAME"] . $_SERVER["PHP_SELF"]);
$config = http_get($oidc_url . "/.well-known/openid-configuration");

// Initialize session and unset any previous session data
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

// Fetch all existing resources and their attributes (hostname)
$pat = http_post($config["token_endpoint"], "grant_type=client_credentials&client_id=$oidc_client_id&client_secret=$oidc_client_secret", ["Content-Type: application/x-www-form-urlencoded"])["access_token"];

$resources = isset($_SESSION["keycloak_resources"]) ? $_SESSION["keycloak_resources"] : [];
foreach(http_get($oidc_url . "/authz/protection/resource_set", ["Authorization: Bearer $pat"]) as $rsid) 
    if(!array_key_exists($rsid, $resources)) {
        $res = http_get($oidc_url . "/authz/protection/resource_set/$rsid", ["Authorization: Bearer $pat"]);
        if(isset($res["attributes"]["hostname"]) && isset($res["attributes"]["username"]))
            $resources[$rsid] = $res["attributes"]["username"][0] . "@" . $res["attributes"]["hostname"][0];
    }
$_SESSION["keycloak_resources"] = $resources;

// Add missing resources
$hostnames = array_values($resources);
foreach ($cfg["Servers"] as $server)
    if(!in_array($server["user"] . "@" . $server["host"], $hostnames)){
        $res = [
            "name" => $server["verbose"],
            "type" => "urn:phpmyadmin:resources:server",
            "attributes" => [
                "hostname" => [$server["host"]],
                "username" => [$server["user"]]
            ]
        ];
        $rsid = http_post($oidc_url . "/authz/protection/resource_set", json_encode($res), ["Authorization: Bearer $pat", "Content-Type: application/json"]);
        $rsid = $rsid["_id"];
        $resources[$rsid] = $res["attributes"]["hostname"][0];
    }

// Database parameter will be set to DB Hostname when called through phpMyAdmin
if(isset($_GET["database"])) {
    $auth_uri = $config["authorization_endpoint"] . "?response_type=code&client_id=$oidc_client_id&redirect_uri=$redirect_uri&state=" . $_GET["database"];
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
    $rpt = http_post($config["token_endpoint"], "grant_type=urn:ietf:params:oauth:grant-type:uma-ticket&audience=$oidc_client_id&response_mode=permissions", ["Authorization: Bearer " . $token["access_token"], "Content-Type: application/x-www-form-urlencoded"]);
    if(!isset($rpt["error"])) {
        $rpt = array_map(function($e) { return $e["rsid"]; }, $rpt);
        foreach(array_diff($rpt, array_keys($resources)) as $unused_resource)
                unset($rpt[array_search($unused_resource, $rpt)]);
    }
    if(isset($rpt["error"]) || empty($rpt)) {
        header("HTTP/1.1 403 Forbidden");
        return;
    }
    // Try to serve requested server but fallback to first allowed if has no access to requested one
    $requested_rsid = array_search($database, $resources);
    if(!in_array($requested_rsid, $rpt))
        $requested_rsid = $rpt[0];
    foreach($cfg["Servers"] as $server)
        if($server["user"] . "@" . $server["host"] == $resources[$requested_rsid]){
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