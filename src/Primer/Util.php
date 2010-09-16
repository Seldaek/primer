<?php

namespace Primer;

class Util
{
    public static function normalizeUrl($url)
    {
        $url = preg_replace('{(^http://|^https://|#.*$)}', '', $url);
        $url = rtrim($url, '/?');
        $url = preg_replace('{/+}', '/', $url);
        return $url;
    }
}