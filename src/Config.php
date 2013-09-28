<?php

class Config {

	public static $awsKey = null;
	public static $awsSecret = null;
	public static $region = 'ap-northeast-1';
	public static $healthCheckInterval = 10.0;
	public static $gracefulPeriod = 5.0;

	public static function setCredential($awsKey, $awsSecret) {
		self::$awsKey = $awsKey;
		self::$awsSecret = $awsSecret;
	}

	public static function setRegion($region) {
		self::$region = $region;
	}

	public static function setHealthCheckInterval($healthCheckInterval) {
		self::$healthCheckInterval = $healthCheckInterval;
	}

	public static function setGracefulPeriod($gracefulPeriod) {
		self::$gracefulPeriod = $gracefulPeriod;
	}

}
