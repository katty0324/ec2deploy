<?php

class Deployer {

	private $amazonELB;

	public function __construct($region) {
		$this->amazonELB = new AmazonELB();
		$this->amazonELB->set_region($region);
	}

	public function deploy($elbName, $command) {

		$instances = $this->listInstances($elbName);
		
	}

	private function isHealthy($instances) {

		if ($instances->count() == 0)
			return false;

		foreach ($instances as $instance)
			if ($instance->State != 'InService')
				return false;

		return true;

	}

	private function listInstances($elbName) {

		$response = $this->amazonELB->describe_instance_health($elbName);

		if (!$response->isOK())
			throw new Exception($response->body->Error->Message);

		return $response->body->DescribeInstanceHealthResult->InstanceStates->member;

	}

}
