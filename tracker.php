<?php
require("../php/config.php");
require("bencoder.php");
require("peer.php");
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

    public function getPasskeyName() {
        if (!is_null($this->passkey)) {
            return explode(' ', $this->passkey)[0];
        }

        return false;
    }

    protected function doHttpAnnounce($infoHash, $peerIp, $peerPort, $peerId = null, $uploaded = 0, $downloaded = 0, $left = 0) {
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
        curl_setopt($ch, CURLOPT_TIMEOUT, 1);
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
        if (!array_key_exists("peers", $response)) {
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

    protected function doUdpAnnounce($infoHash, $peerIp, $peerPort, $peerId = null, $uploaded = 0, $downloaded = 0, $left = 0) {
        $peers = array();

        $addr = gethostbyname($this->host);
        $port = $this->port;
        $key = 0;

        $sockfd = socket_create(AF_INET, SOCK_DGRAM, SOL_UDP);
        socket_set_option($sockfd, SOL_SOCKET, SO_RCVTIMEO,
            array("sec" => 1, "usec" => 0));

        $transaction_id = rand();
        $req = pack("JNN", 4497486125440, 0, $transaction_id);
        socket_sendto($sockfd, $req, 16, 0, $addr, $port);
        $recv = socket_recvfrom($sockfd, $buf, 16, 0, $addr, $port);
        if ($recv == false || $recv == 0) {
            return false;
        }
        $res = unpack("Naction/Ntransaction_id/Jconnection_id", $buf);
        $connection_id = $res["connection_id"];

        $transaction_id = rand();
        $req = pack("JNN", $connection_id, 1, $transaction_id)
            . hex2bin($infoHash)
            . (is_null($peerId) ? self::ANN_PEER_ID : $peerId)
            . pack("JJJNNNNn", $downloaded, $uploaded, $left,
                0, $peerIp, $key, -1, $peerPort);
        socket_sendto($sockfd, $req, 98, 0, $addr, $port);
        $recv = socket_recvfrom($sockfd, $buf, 65535, 0, $addr, $port);
        if ($recv == false || $recv == 0) {
            return false;
        }
        $res = unpack("Naction/Ntransaction_id/Ninterval/Nleechers/Nseeders", $buf);
        //$count = $res["leechers"] + $res["seeders"];
        $count = ($res - 20) / 6;
        for ($i = 0; $i < $count; $i++) {
            $peer = unpack("Nip/nport", $buf, 20 + $i*6);
            array_push($peers, new Peer($infoHash,
                long2ip($peer["ip"]), $peer["port"]));
        }

        socket_close($sockfd);

        return $peers;
    }

    public function announce($infoHash, $peerIp, $peerPort, $peerId = null, $uploaded = 0, $downloaded = 0, $left = 0) {
        if ($this->proto == "http" || $this->proto == "https") {
            return $this->doHttpAnnounce($infoHash, $peerIp, $peerPort, $peerId, $uploaded, $downloaded, $left);
        } else if ($this->proto == "udp") {
            return $this->doUdpAnnounce($infoHash, $peerIp, $peerPort, $peerId, $uploaded, $downloaded, $left);
        }

        return false;
    }
}

?>