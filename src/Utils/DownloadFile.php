<?php

namespace Ozerich\FileStorage\Utils;

use Ozerich\FileStorage\Exceptions\DownloadFileException;

class DownloadFile
{
    public static function download($url, $filepath)
    {
        set_time_limit(0);
        $fp = fopen($filepath, 'w+');
        $ch = curl_init(str_replace(" ", "%20", $url));
        curl_setopt($ch, CURLOPT_TIMEOUT, 50);
        curl_setopt($ch, CURLOPT_FILE, $fp);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_exec($ch);
        $info = curl_getinfo($ch);
        curl_close($ch);
        fclose($fp);

        if ($info['http_code'] !== 200) {
            @unlink($filepath);
            throw new DownloadFileException('Can not download ' . $url . ' - Response code: ' . $info['http_code']);
        }
    }
}
