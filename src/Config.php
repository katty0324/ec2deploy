<?php

class Config {

	public $elbName = null;
	public $command = null;
	public $awsKey = null;
	public $awsSecret = null;
	public $region = 'ap-northeast-1';
	public $healthCheckInterval = 10.0;
	public $gracefulPeriod = 5.0;

	public function __construct($argv) {
		$this->setCommandLineOptions($argv);
	}

	public function setCommandLineOptions($argv) {

		$options = getopt('n:c:k:s:r::h::g::', array(
			'elb-name:',
			'command:',
			'aws-key:',
			'aws-secret:',
			'region::',
			'health-check-interval::',
			'graceful-period::',
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

		if (array_key_exists('h', $options))
			$this->healthCheckInterval = doubleval($options['h']);

		if (array_key_exists('health-check-interval', $options))
			$this->healthCheckInterval = doubleval($options['health-check-interval']);

		if (array_key_exists('g', $options))
			$this->gracefulPeriod = doubleval($options['g']);

		if (array_key_exists('graceful-period', $options))
			$this->gracefulPeriod = doubleval($options['graceful-period']);

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

}
