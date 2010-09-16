<?php

require __DIR__.'/bootstrap.php';

// parameter parsing
if (!isset($_SERVER['argv'][1]) && !isset($_GET['config'])) {
    $msg = 'Missing parameter: config';
    if (PHP_SAPI === 'cli') {
        $msg .= PHP_EOL . 'Usage: php run.php path/to/config.yml' . PHP_EOL;
    } else {
        $msg .= '<br />Usage: http://*/run.php?config=path/to/config.yml';
    }
    die($msg);
}
$config = isset($_GET['config']) ? $_GET['config'] : $_SERVER['argv'][1];

if (!file_exists($config)) {
    die('Config file '.$config.' not found');
}

// configure and run primer
ini_set('memory_limit', '256M');
gc_enable();

$config = Symfony\Component\Yaml\Yaml::load($config);
$storage = new Primer\Storage\RuntimeStorage();

$primer = new Primer\Primer($config, $storage);
$primer->run();

// dump the data collected
//var_dump($primer->getStorage()->getData());