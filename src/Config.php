<?php

class Config {

	public static $awsKey = null;
	public static $awsSecret = null;
	public static $region = 'ap-northeast-1';

	public static function setCredential($awsKey, $awsSecret) {
		self::$awsKey = $awsKey;
		self::$awsSecret = $awsSecret;
	}

}
