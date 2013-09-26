<?php

require_once dirname(__FILE__) . '/../aws-sdk-for-php/sdk.class.php';
require_once dirname(__FILE__) . '/config.inc.php';
require_once dirname(__FILE__) . '/src/Deployer.php';

$deployer = new Deployer(AmazonELB::REGION_APAC_NE1);
$deployer->deploy($argv[1], $argv[2]);
