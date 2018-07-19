#!/usr/bin/env php
<?php

use COREPOS\Recommend\Util\Config;
use COREPOS\Recommend\Command\Load;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Output\ConsoleOutput;

require(__DIR__ . '/vendor/autoload.php');

$app = new Application();
try {
    $config = new Config();
    $driver = $config->getDriver();
    $app->add(new Load($driver, $config->getNeo4j()));
    $app->run();
} catch (Exception $ex) {
    $out = new ConsoleOutput();
    $out->writeln('<error>' . $ex->getMessage() . '</error>');
}

