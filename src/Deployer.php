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

	public function deploy($elbName, $commandTemplate) {

		$instances = $this->listInstances($elbName);

		foreach ($instances as $instance) {

			while (!$this->isHealthy($instances))
				sleep(5);

			// TODO deregister instance

			$instance = $this->describeInstance($instance->InstanceId->to_string());
			$variables = $this->extractVariables($instance);
			$command = $this->render($commandTemplate, $variables);

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

	private function extractVariables($instance) {

		$item = $instance->instancesSet->item;

		$variables = array(
			'instanceId' => $item->instanceId->to_string(),
			'imageId' => $item->imageId->to_string(),
			'instanceState' => $item->instanceState->name->to_string(),
			'privateDnsName' => $item->privateDnsName->to_string(),
			'dnsName' => $item->dnsName->to_string(),
			'keyName' => $item->keyName->to_string(),
			'instanceType' => $item->instanceType->to_string(),
			'launchTime' => $item->launchTime->to_string(),
			'availabilityZone' => $item->placement->availabilityZone->to_string(),
			'kernelId' => $item->kernelId->to_string(),
			'subnetId' => $item->subnetId->to_string(),
			'vpcId' => $item->vpcId->to_string(),
			'privateIpAddress' => $item->privateIpAddress->to_string(),
			'ipAddress' => $item->ipAddress->to_string(),
		);

		foreach ($item->tagSet->item as $tag)
			$variables['tag.' . $tag->key->to_string()] = $tag->value->to_string();

		return $variables;

	}

	private function render($template, $variables) {

		foreach ($variables as $key => $value)
			$template = str_replace('${' . $key . '}', $value, $template);

		return $template;

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
