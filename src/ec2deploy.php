<?php

require_once dirname(__FILE__) . '/../aws-sdk-for-php/sdk.class.php';
require_once dirname(__FILE__) . '/Logger.php';
require_once dirname(__FILE__) . '/Config.php';
require_once dirname(__FILE__) . '/Deployer.php';

$config = new Config($argv);
if ($config->getHelp()) {
	echo <<<EOT
Usage: ec2deploy -n <elb-name> -c <command> -k <aws-key> -s <aws-secret>

-n  --elb-name              instance name of Amazon ELB
-d  --dependent-elb-names	instance names of dependent Amazon ELBs (comma separated)
-c  --command               shell command
-k  --aws-key               AWS access key
-s  --aws-secret            AWS access secret
    --region                region of Amazon EC2 and ELB
    --health-check-interval interval of Amazon ELB health check
    --graceful-period       sleep time for register and deregister instance for Amazon ELB
    --concurrency           the number of hosts to be deployed at the same time
    --help                  help

EOT;
	exit ;
}

$deployer = new Deployer($config);
if (!$deployer->deploy())
	exit(1);
