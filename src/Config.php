<?php

class Config {

	public static $elbName = null;
	public static $command = null;
	public static $awsKey = null;
	public static $awsSecret = null;
	public static $region = 'ap-northeast-1';
	public static $healthCheckInterval = 10.0;
	public static $gracefulPeriod = 5.0;

	public static function setCommandLineOptions($argv) {

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
			self::$elbName = strval($options['n']);

		if (array_key_exists('elb-name', $options))
			self::$elbName = strval($options['elb-name']);

		if (array_key_exists('c', $options))
			self::$command = strval($options['c']);

		if (array_key_exists('command', $options))
			self::$command = strval($options['command']);

		if (array_key_exists('k', $options))
			self::$awsKey = strval($options['k']);

		if (array_key_exists('aws-key', $options))
			self::$awsKey = strval($options['aws-key']);

		if (array_key_exists('s', $options))
			self::$awsSecret = strval($options['s']);

		if (array_key_exists('aws-secret', $options))
			self::$awsSecret = strval($options['aws-secret']);

		if (array_key_exists('r', $options))
			self::$region = strval($options['r']);

		if (array_key_exists('region', $options))
			self::$region = strval($options['region']);

		if (array_key_exists('h', $options))
			self::$healthCheckInterval = doubleval($options['h']);

		if (array_key_exists('health-check-interval', $options))
			self::$healthCheckInterval = doubleval($options['health-check-interval']);

		if (array_key_exists('g', $options))
			self::$gracefulPeriod = doubleval($options['g']);

		if (array_key_exists('graceful-period', $options))
			self::$gracefulPeriod = doubleval($options['graceful-period']);

	}

}
