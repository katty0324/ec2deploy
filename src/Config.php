<?php

class Config {

	private $elbName = null;
	private $command = null;
	private $awsKey = null;
	private $awsSecret = null;
	private $region = 'ap-northeast-1';
	private $healthCheckInterval = 10.0;
	private $gracefulPeriod = 5.0;
	private $help = false;

	public function __construct($argv) {
		$this->setCommandLineOptions($argv);
	}

	public function setCommandLineOptions($argv) {

		$options = getopt('n:c:k:s:', array(
			'elb-name:',
			'command:',
			'aws-key:',
			'aws-secret:',
			'region:',
			'health-check-interval:',
			'graceful-period:',
			'help',
		));

		if (array_key_exists('n', $options))
			$this->elbName = strval($options['n']);

		if (array_key_exists('elb-name', $options))
			$this->elbName = strval($options['elb-name']);

		if (array_key_exists('c', $options))
			$this->command = strval($options['c']);

		if (array_key_exists('command', $options))
			$this->command = strval($options['command']);

		if (array_key_exists('k', $options))
			$this->awsKey = strval($options['k']);

		if (array_key_exists('aws-key', $options))
			$this->awsKey = strval($options['aws-key']);

		if (array_key_exists('s', $options))
			$this->awsSecret = strval($options['s']);

		if (array_key_exists('aws-secret', $options))
			$this->awsSecret = strval($options['aws-secret']);

		if (array_key_exists('r', $options))
			$this->region = strval($options['r']);

		if (array_key_exists('region', $options))
			$this->region = strval($options['region']);

		if (array_key_exists('health-check-interval', $options))
			$this->healthCheckInterval = doubleval($options['health-check-interval']);

		if (array_key_exists('graceful-period', $options))
			$this->gracefulPeriod = doubleval($options['graceful-period']);

		if (array_key_exists('help', $options))
			$this->help = true;

		if (!$this->validate())
			$this->help = true;

	}

	private function validate() {

		if (!$this->elbName)
			return false;

		if (!$this->command)
			return false;

		if (!$this->awsKey)
			return false;

		if (!$this->awsSecret)
			return false;

		return true;

	}

	public function getElbName() {
		return $this->elbName;
	}

	public function getCommand() {
		return $this->command;
	}

	public function getAwsKey() {
		return $this->awsKey;
	}

	public function getAwsSecret() {
		return $this->awsSecret;
	}

	public function getRegion() {
		return $this->region;
	}

	public function getHealthCheckInterval() {
		return $this->healthCheckInterval;
	}

	public function getGracefulPeriod() {
		return $this->gracefulPeriod;
	}

	public function getHelp() {
		return $this->help;
	}

}
