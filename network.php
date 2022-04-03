<?php

function bad_request($msg)
{
    http_response_code(400);
    echo($msg);
    exit;
}

function do_error($msg)
{
    die($msg);
}

function ip4_loopback()
{
    return inet_pton("127.0.0.1");
}

function ip6_loopback()
{
    return inet_pton("::1");
}

function ip_address($ip)
{
    $ipAddr = inet_pton($ip);
    if ($ipAddr == false) {
        throw new Exception("invalid ip address");
    }

    return $ipAddr;
}

function is_loopback($ip)
{
    $ipAddr = ip_address($ip);
    return $ipAddr == ip4_loopback() || $ipAddr == ip6_loopback();
}

function get_external_ip()
{
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, "http://ident.me");
    curl_setopt($ch, CURLOPT_PORT, 80);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    $result = curl_exec($ch);
    curl_close($ch);

    if ($result == false) {
        throw new Exception("request to ident.me failed");
    } else if (inet_pton($result) == false) {
        throw new Exception("ident.me respond invalid ip");
    }

    return $result;
}

?>