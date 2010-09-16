<?php

namespace Primer\Storage;

use Primer\Util;

class RuntimeStorage implements StorageInterface
{
    protected $data = array();

    public function isProcessed($url)
    {
        return isset($this->data[Util::normalizeUrl($url)]);
    }

    public function storeResult($url, $links, $body)
    {
        $this->data[Util::normalizeUrl($url)] = array(
            'url' => $url,
            'links' => $links,
            'body' => $body,
            'hits' => 1,
        );
    }

    public function fetchResult($url)
    {
        $url = Util::normalizeUrl($url);
        $this->data[$url]['hits']++;
        return $this->data[$url];
    }

    public function getData()
    {
        return $this->data;
    }
}