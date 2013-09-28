<?php

class Deployer {

	private $amazonELB;
	private $amazonEC2;

	public function __construct() {

		CFCredentials::set(array(
			'development' => array(
				'key' => Config::$awsKey,
				'secret' => Config::$awsSecret,
				'default_cache_config' => '',
				'certificate_authority' => false
			),
			'@default' => 'development'
		));

		$this->amazonELB = new AmazonELB();
		$this->amazonEC2 = new AmazonEC2();
		$this->amazonELB->set_region('elasticloadbalancing.' . Config::$region . '.amazonaws.com');
		$this->amazonEC2->set_region('ec2.' . Config::$region . '.amazonaws.com');

	}

	public function deploy($elbName, $commandTemplate) {

		$instances = $this->listInstances($elbName);
		echo $instances->count() . " instances on ELB ${elbName}\n";

		foreach ($instances as $instance) {

			$instanceId = $instance->InstanceId->to_string();
			echo "Instance ID: ${instanceId}\n";

			while (!$this->isHealthy($instances)) {
				echo "Currently not healthy...\n";
				usleep(Config::$healthCheckInterval * 1e6);
			}

			$this->deregisterInstance($elbName, $instanceId);
			echo "Deregistered instance.\n";

			usleep(Config::$gracefulPeriod * 1e6);

			$instance = $this->describeInstance($instanceId);
			$variables = $this->extractVariables($instance);
			$command = $this->render($commandTemplate, $variables);
			echo "Run: ${command}\n";
			exec($command, $output);
			echo implode("\n", $output) . "\n";

			usleep(Config::$gracefulPeriod * 1e6);

			$this->registerInstance($elbName, $instanceId);
			echo "Registered instance.\n";

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

		return $response->body->DescribeInstanceHealthResult->InstanceStates->member();

	}

	private function describeInstance($instanceId) {

		$response = $this->amazonEC2->describe_instances(array('InstanceId' => $instanceId));

		if (!$response->isOK())
			throw new Exception($response->body->Error->Message);

		return $response->body->reservationSet->item;

	}

	private function deregisterInstance($elbName, $instanceId) {

		$response = $this->amazonELB->deregister_instances_from_load_balancer($elbName, array( array('InstanceId' => $instanceId)));

		if (!$response->isOK())
			throw new Exception($response->body->Error->Message);

		return $response->body->DeregisterInstancesFromLoadBalancerResult->Instances->member();

	}

	private function registerInstance($elbName, $instanceId) {

		$response = $this->amazonELB->register_instances_with_load_balancer($elbName, array( array('InstanceId' => $instanceId)));

		if (!$response->isOK())
			throw new Exception($response->body->Error->Message);

		return $response->body->RegisterInstancesWithLoadBalancerResult->Instances->member();
	}

}
