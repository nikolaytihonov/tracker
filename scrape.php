<?php
require("../php/config.php");
require("network.php");
require("tracker.php");
$DB_NAME = "torrent";

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

$conn = new PDO("mysql:host=$DB_HOST;dbname=$DB_NAME", $DB_USER, $DB_PASS);
$conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

$stmt = $conn->prepare("SELECT count(*) FROM `peers` WHERE info_hash = :info_hash");
$stmt->bindValue(":info_hash", $hash, PDO::PARAM_STR);
$stmt->execute();

$name = "torrent-" . $hash;
$count = intval($stmt->fetchColumn());

$response = array(
    "files" => array(
        hash_encode($hash) => array(
            "complete" => $count,
            "downloaded" => $count,
            "incomplete" => 0,
            "name" => $name,
        ),
    ),
);
echo(bencode($response));

?>