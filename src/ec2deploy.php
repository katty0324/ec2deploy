<?php

require_once dirname(__FILE__) . '/../aws-sdk-for-php/sdk.class.php';
require_once dirname(__FILE__) . '/Logger.php';
require_once dirname(__FILE__) . '/Config.php';
require_once dirname(__FILE__) . '/Deployer.php';

Config::setCommandLineOptions($argv);

$deployer = new Deployer();
$deployer->deploy(Config::$elbName, Config::$command);
