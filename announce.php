<?php
require("../php/config.php");
require("network.php");
require("tracker.php");
$DB_NAME = "torrent";
$ANN_MIN_INTERVAL = 15;
$ANN_INTERVAL = 60;

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


if (!isset($_GET["info_hash"])) {
    bad_request("No info_hash");
}

try {
    $hash = hash_decode($_GET["info_hash"]);
    $hashLen = strlen($hash);
    if ($hashLen < 40 || $hashLen > 40) {
        bad_request("Invalid info_hash");
    }
} catch (Exception $e) {
    bad_request("Invalid info_hash");
}

try {
    if (isset($_GET["ip"])) {
        $ip = urldecode($_GET["ip"]);
    } else if (isset($_SERVER["HTTP_X_FORWARDED_FOR"])) {
        $ip = $_SERVER["HTTP_X_FORWARDED_FOR"];
    } else {
        $ip = $_SERVER["REMOTE_ADDR"];
    }
} catch (Exception $e) {
    bad_request("Invalid ip");
}

if (!$ipAddr = inet_pton($ip)) {
    bad_request("Invalid ip");
}

if (isset($_GET["port"])) {
    $port = intval(urldecode($_GET["port"]));
} else {
	bad_request("No port");
}

$externalIp = get_external_ip();
$clientPeer = new Peer($hash, is_loopback($ip) ? $externalIp : $ip, $port);

$conn = new PDO("mysql:host=$DB_HOST;dbname=$DB_NAME", $DB_USER, $DB_PASS);
$conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

$peers = array();
array_push($peers, $clientPeer);

$stmt = $conn->prepare("SELECT * FROM `trackers`");
$stmt->execute();
$trackers = $stmt->fetchAll(PDO::FETCH_CLASS, Tracker::class);
foreach($trackers as $tracker) {
    $remotePeers = $tracker->announce($hash,
        $clientPeer->getIp(), $clientPeer->getPort(),
        isset($_GET["peer_id"]) ? $_GET["peer_id"] : null,
        isset($_GET["uploaded"]) ? $_GET["uploaded"] : 0,
        isset($_GET["downloaded"]) ? $_GET["downloaded"] : 0,
        isset($_GET["left"]) ? $_GET["left"] : 0
    );

    if ($remotePeers != false) {
        $peers = array_merge($peers, $remotePeers);
    }
}

foreach($peers as $peer) {
    $stmt = $conn->prepare("REPLACE INTO `peers` (info_hash, ip, port, update_time) VALUES (:info_hash, :ip, :port, :update_time)");
    $peer->bind($stmt);
    $stmt->execute();
}

$peers = '';

$stmt = $conn->prepare("SELECT ip, port FROM `peers` WHERE info_hash = :info_hash");
$stmt->bindValue(":info_hash", $hash, PDO::PARAM_STR);
$stmt->execute();
$stmt->bindColumn(1, $peerIp);
$stmt->bindColumn(2, $peerPort);
while ($stmt->fetch(PDO::FETCH_BOUND)) {
	if (is_loopback($peerIp) && !is_loopback($ip)) {
		$peerIp = $externalIp;
	} else if ($peerIp == $externalIp && is_loopback($ip)) {
		$peerIp = ip4_loopback();
	}
    $peers .= pack("Nn", inet_pton($peerIp), $peerPort);
}

$response = array(
    "min interval" => $ANN_MIN_INTERVAL,
    "interval" => $ANN_INTERVAL,
    "peers" => $peers,
);
echo(bencode($response));

?>