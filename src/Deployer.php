<?php

class Deployer {

	private $amazonELB;
	private $amazonEC2;

	public function __construct($region) {
		
		$this->amazonELB = new AmazonELB();
		$this->amazonEC2 = new AmazonEC2();
		$this->amazonELB->set_region("elasticloadbalancing.${region}.amazonaws.com");
		$this->amazonEC2->set_region("ec2.${region}.amazonaws.com");

	}

	public function deploy($elbName, $command) {

		$instances = $this->listInstances($elbName);

		foreach ($instances as $instance) {

			while (!$this->isHealthy($instances))
				sleep(5);

			// TODO deregister instance

			$instance = $this->describeInstance($instance->InstanceId->to_string());

			// TODO register instance

		}

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

	private function describeInstance($instanceId) {

		$response = $this->amazonEC2->describe_instances(array('InstanceId' => $instanceId));

		if (!$response->isOK())
			throw new Exception($response->body->Error->Message);

		return $response->body->reservationSet->item;

	}

}
