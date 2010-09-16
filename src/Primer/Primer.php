<?php

namespace Primer;

use Symfony\Component\DomCrawler\Crawler;
use Primer\Storage\StorageInterface;
use Primer\Storage\RuntimeStorage;

/**
 * Primer crawls sites recursively based on simple route definitions
 *
 * @author Jordi Boggiano <j.boggiano@seld.be>
 */
class Primer
{
    /**
     * @var array
     */
    protected $config;

    /**
     * @var array
     */
    protected $routes;

    /**
     * @var Primer\Storage\StorageInterface
     */
    protected $storage;

    /**
     * default route configuration
     *
     * options:
     *   - depth: maximum link depth a route will go down into
     *   - domain: domain policy, one of:
     *       - strict: only links with the exact same hostname are followed
     *       - same-sld: (default) links with the same sld.tld (e.g. *.example.com) are followed
     *       - same-tld: links with the same tld (e.g. *.com) are followed
     *       - any: all links are followed
     *   - whitelist: an array of regexes that a link must match to be followed
     *   - blacklist: an array of regexes that a link must not match to be followed
     *   - timeout: duration (in seconds) after which the url fetching of any link in this route will be aborted
     *   - http.auth: "login:password" couple for sites restricted by http authentication
     *
     * @var array
     */
    protected $routeDefaults = array(
        'depth' => 0,
        'domain' => 'same-sld',
        'whitelist' => null,
        'blacklist' => null,
        'timeout' => 10,
        'http.auth' => null,
    );

    /**
     * constructor
     *
     * @param array $config configuration
     * @param Primer\Storage\StorageInterface $storage optional storage backend
     */
    public function __construct(array $config, StorageInterface $storage = null)
    {
        if (!$storage) {
            $storage = new RuntimeStorage();
        }
        $this->storage = $storage;
        $this->config = $config['options'];
        $this->routes = $config['routes'];
        $this->routeDefaults = array_merge(
            $this->routeDefaults,
            $config['routes']['defaults']
        );
        unset($config, $this->routes['defaults']);
    }

    /**
     * crawls all the configured routes
     */
    public function run()
    {
        $this->log('crawling '.count($this->routes).' routes...');
        $this->log('----');
        while ($route = $this->getRoute()) {
            $this->crawl($route['url'], $route);
            $this->log('----');
        }
        $this->log('done');
    }

    /**
     * returns the storage backend
     *
     * @return Primer\Storage\StorageInterface
     */
    public function getStorage()
    {
        return $this->storage;
    }


    /**
     * crawls a page and its children recursively
     *
     * @param string $url url to fetch
     * @param array $routeOptions current route options
     * @param int $depth depth of the page being processed, used by recursive calls
     */
    protected function crawl($url, array $routeOptions, $depth = 0)
    {
        $prefix = str_repeat('  ', $depth);

        // skip URL if needed
        if ($depth > 0
            && (!$this->domainPolicyAllows($url, $routeOptions) || !$this->routeAllows($url, $routeOptions))
        ) {
            return;
        }

        // processed urls are fetched from the cache
        if ($this->storage->isProcessed($url)) {
            $data = $this->storage->fetchResult($url);
            $links = $data['links'];
            $msg = 'fetched '.$url.' from cache';
        } else {
            // otherwise we fetch from the website itself
            $markup = $this->getMarkup($url, $routeOptions);
            $crawler = new Crawler($markup, $url);

            $links = $crawler->filterXPath('//a')->links();
            foreach ($links as $k => $link) {
                $links[$k] = $link->getUri();
            }
            $this->storage->storeResult($url, $links, $markup);

            $msg = 'parsed '.$url;
            if ($this->config['sleep'] > 0) {
                usleep($this->config['sleep'] * 1000);
            }
        }

        if ($depth >= $this->getOption('depth', $routeOptions)) {
            $this->log($prefix . $msg . ', reached depth ' . $depth);
            return;
        }
        $this->log($prefix . $msg . ', found '.count($links).' links');

        foreach ($links as $link) {
            $this->crawl($link, $routeOptions, $depth+1);
        }
    }

    /**
     * retrieves the markup of the given url
     *
     * @param string $url url to fetch
     * @param array $routeOptions current route options
     * @return string|false
     */
    protected function getMarkup($url, array $routeOptions)
    {
        $curl = curl_init($url);
        curl_setopt_array($curl, array(
            CURLOPT_USERAGENT => 'Primer',
            CURLOPT_CONNECTTIMEOUT => $this->getOption('timeout', $routeOptions),
            CURLOPT_TIMEOUT => $this->getOption('timeout', $routeOptions),
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_MAXREDIRS => 50,
            CURLOPT_AUTOREFERER => true,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_SSL_VERIFYHOST => 0,
        ));
        if ($httpAuth = $this->getOption('http.auth', $routeOptions)) {
            curl_setopt($curl, CURLOPT_USERPWD, $httpAuth);
        }
        $markup = curl_exec($curl);
        if (!$markup) {
            $this->error($url.' has no content');
        }
        curl_close($curl);
        return $markup;
    }

    /**
     * checks the given url against the black and white lists of the route
     *
     * @param string $url url to verify
     * @param array $routeOptions current route options
     * @return bool
     */
    protected function routeAllows($url, array $routeOptions)
    {
        $whitelist = $this->getOption('whitelist', $routeOptions);
        if (isset($whitelist) && is_array($whitelist) && count($whitelist)) {
            foreach ($whitelist as $pattern) {
                if (preg_match('{'.$pattern.'}i', $url)) {
                    goto blacklist;
                }
            }
            return false;
        }

        blacklist:
        $blacklist = $this->getOption('blacklist', $routeOptions);
        if (isset($blacklist) && is_array($blacklist) && count($blacklist)) {
            foreach ($blacklist as $pattern) {
                if (preg_match('{'.$pattern.'}i', $url)) {
                    return false;
                }
            }
        }

        return true;
    }

    /**
     * checks the given url against the domain policy of the current route
     *
     * @param string $url url to verify
     * @param array $routeOptions current route options
     * @return bool
     */
    protected function domainPolicyAllows($url, array $routeOptions)
    {
        $policy = $this->getOption('domain', $routeOptions);
        // flexible policy (any domain)
        if ($policy === 'any') {
            return true;
        }
        $current = parse_url($url);
        $source = parse_url($routeOptions['url']);
        // strict host policy
        if ($policy === 'same') {
            return $current['host'] === $source['host'];
        }
        // flexible policy (match TLD)
        if ($policy === 'same-tld') {
            $pos = strrpos($source['host'], '.');
            return substr($current['host'], -strlen($current['host'])+$pos) === substr($source['host'], -strlen($source['host'])+$pos);
        }
        if ($policy !== 'same-sld') {
            throw new UnexpectedValueException('Domain policy "'.$policy.'"" is unknown, use one of "any, same-sld, same-tld, strict"');
        }
        // flexible policy (match SLD.TLD)
        $pos = strrpos($source['host'], '.', strrpos($source['host'], '.') + 1);
        if ($pos !== false) {
            $source['host'] = substr($source['host'], $pos - strlen($source['host']) - 1);
        }
        return substr($current['host'], -strlen($source['host'])-1) === $source['host'];
    }

    /**
     * returns the next route to be processed
     *
     * @return array route
     */
    protected function getRoute()
    {
        return array_shift($this->routes);
    }

    /**
     * reads an option merging the route options with the defaults
     *
     * @param string $name option name
     * @param array $routeOptions current route options
     * @return mixed value
     */
    protected function getOption($name, array $routeOptions)
    {
        return isset($routeOptions[$name]) ? $routeOptions[$name] : $this->routeDefaults[$name];
    }

    /**
     * logs a message
     *
     * @param string $message
     */
    protected function log($message)
    {
        if (PHP_SAPI === 'cli') {
            echo $message . PHP_EOL;
            return;
        }
        echo str_replace(' ', '&nbsp;', $message).'<br />';
        flush();
    }

    /**
     * logs an error
     *
     * @param string $message
     */
    protected function error($message)
    {
        if (PHP_SAPI === 'cli') {
            file_put_contents('php://stderr', 'ERROR: '.$message . PHP_EOL);
            return;
        }
        echo 'ERROR: '.$message.'<br />';
    }
}
