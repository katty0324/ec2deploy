<?php

CFCredentials::set(array(
	'development' => array(
		'key' => 'key',
		'secret' => 'secret-key',
		'default_cache_config' => '',
		'certificate_authority' => false
	),
	'@default' => 'development'
));
