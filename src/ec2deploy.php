<?php

require_once dirname(__FILE__) . '/../aws-sdk-for-php/sdk.class.php';
require_once dirname(__FILE__) . '/Logger.php';
require_once dirname(__FILE__) . '/Config.php';
require_once dirname(__FILE__) . '/Deployer.php';
require_once dirname(__FILE__) . '/../config.inc.php';

$deployer = new Deployer();
$deployer->deploy($argv[1], $argv[2]);