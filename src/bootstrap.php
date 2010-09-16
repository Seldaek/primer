<?php

require __DIR__.'/vendor/symfony/Symfony/Framework/UniversalClassLoader.php';

$loader = new Symfony\Framework\UniversalClassLoader();
$loader->registerNamespaces(array(
    'Symfony' => __DIR__.'/vendor/symfony',
    'Primer' => __DIR__.'/',
));
$loader->register();
