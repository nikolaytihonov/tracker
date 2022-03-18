<?php
require("../php/config.php");
require("bencoder.php");
require("peer.php");
$ANN_USERAGENT = "Transmission/3.00";
$ANN_PEER_ID = "01234567890123456789";
$DB_NAME = "torrent";

class Tracker {
    const ANN_USERAGENT = "Transmission/3.00";
    const ANN_PEER_ID = "01234567890123456789";

    protected $proto;
    protected $host;
    protected $port;
    protected $path;
    protected $passkey;
    protected $proxy;

    public function __toString() {
        return $this->proto . "://" . $this->host
            . ":" . $this->port . $this->path;
    }

    public function getHttpUrl() {
        return $this->proto . "://" . $this->host . $this->path;
    }

    public function doHttpAnnounce($infoHash, $peerIp, $peerPort, $peerId = null, $uploaded = 0, $downloaded = 0, $left = 0) {
        $peers = array();

        $passkey = is_null($this->passkey) ? "" : $this->passkey . "&";
        $params = http_build_query(array(
            "info_hash" => hash_encode($infoHash),
            "peer_id" => is_null($peerId) ? self::ANN_PEER_ID : $peerId,
            "ip" => $peerIp,
            "port" => $peerPort,
            "uploaded" => $uploaded,
            "downloaded" => $downloaded,
            "left" => $left,
        ));

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $this->getHttpUrl() . "?" . $passkey . $params);
        curl_setopt($ch, CURLOPT_PORT, intval($this->port));
        curl_setopt($ch, CURLOPT_USERAGENT, self::ANN_USERAGENT);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        if (!is_null($this->proxy)) {
            curl_setopt($ch, CURLOPT_PROXY, $this->proxy);
        }

        $result = curl_exec($ch);
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        if ($code != 200 || $result == false) {
            return false;
        }

        $response = bdecode($result);
        if (array_key_exists($response, "failure reason")) {
            return false;
        }

        $peersEnc = $response["peers"];
        for ($i = 0; $i < strlen($peersEnc); $i+=6) {
            $remoteIp = long2ip(unpack("N", $peersEnc, $i)[1]);
            $remotePort = unpack("n", $peersEnc, $i + 4)[1];
            array_push($peers, new Peer($infoHash, $remoteIp, $remotePort));
        }

        return $peers;
    }

    public function announce($infoHash, $peerIp, $peerPort, $peerId = null, $uploaded = 0, $downloaded = 0, $left = 0) {
        if ($this->proto == "http" || $this->proto == "https") {
            return $this->doHttpAnnounce($infoHash, $peerIp, $peerPort, $peerId, $uploaded, $downloaded, $left);
        }

        return false;
    }
}

?>