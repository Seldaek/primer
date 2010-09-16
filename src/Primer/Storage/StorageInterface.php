<?php

namespace Primer\Storage;

interface StorageInterface
{
    /**
     * checks if an url has been processed already
     *
     * @param string $url the url to verify
     * @return bool
     */
    public function isProcessed($url);

    /**
     * stores the result of a page that has been crawled
     *
     * @param string $url the url parsed
     * @param array $links the links found on it
     * @param string $body the full markup of the page
     */
    public function storeResult($url, $links, $body);

    /**
     * fetches the result of a page that has been crawled
     *
     * @param string $url the url to fetch
     * @return array containing all data as : array('url' => string, 'links' => array, 'body' => string)
     */
    public function fetchResult($url);

    /**
     * returns a traversable object or an array containing all the data collected
     *
     * @return Traversable|Iterator|array
     */
    public function getData();
}