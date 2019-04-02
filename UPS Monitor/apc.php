<?php
// Configuration
$apcupsd_host = 'localhost';
$apcupsd_port = 3551;
$st_hub = '192.168.7.250';


$action = isset($_REQUEST['a']) ? $_REQUEST['a'] : 'status';
$pull = isset($_REQUEST['p']) ? true : false;

// Pull UPS Data
if ($action == 'status')
{
        $data = query($apcupsd_host, $apcupsd_port);
        if ($data)
        {
                if ($pull)
                {
                        header('Referer: apcupsd');
                        header('Content-type: text/json');
                        echo json_encode(array('data' => array('device' => $data)));
                }
                else
                        stDataPush(array('device' => $data));
        }
}


// Event notification
else if ($action == 'notify')
{
        $status = isset($_REQUEST['status']) ? $_REQUEST['status'] : '';
        stDataPush(array('event' => $status));
        die('OK');
}


// Event notification
else if ($action == 'ping')
{
        header("Referer: apcupsd\r\n");
        die('OK');
}


function htons($i)
{
    return pack('n', $i);
}


function nstoh($d)
{
    $length = unpack('n', $d);
    return $length[1];
}


function query($ip, $port)
 {
        $fp = fsockopen($ip, $port, $errno, $errstr);
        if (!$fp)
        {
            return;
        }

        stream_set_blocking($fp, 1);
        stream_set_timeout($fp, 5);

        fwrite($fp, htons(6), 2);
        fwrite($fp, 'status', 6);

        $status = '';
        while (!feof($fp))
        {
                $length = nstoh(fread($fp, 2));
                if ($length == 0)
                {
                        break;
                }
                $status .= fread($fp, $length);
        }
        fclose($fp);

        $lines = explode("\n", $status);
        foreach ($lines AS $line)
        {
                $rec = preg_split("/:/", $line, 2);
                if (empty($rec[0])) break;
                $response[strtolower(trim($rec[0]))] = trim($rec[1]);
        }

        return $response;
}


function stDataPush($data)
{
        global $st_hub;

        $url = "http://$st_hub/notify";

        $data = array(
                'data'          => $data
        );

        $data = json_encode($data);

        // request via cURL
        $c = curl_init($url);   curl_setopt($c, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($c, CURLOPT_PORT, 39501);
        curl_setopt($c, CURLOPT_POST, 1);

        curl_setopt($c, CURLOPT_POSTFIELDS,     $data );
        curl_setopt($c, CURLOPT_HTTPHEADER,     array('Content-Type: text/html', 'Content-Length: ' . strlen($data), 'Referer: apcupsd'));

        $response = curl_exec($c);
}
?>
