<?php

class Deployer {

	private $config;
	private $amazonELB;
	private $amazonEC2;
	private $logger;

	public function __construct($config) {

		$this->config = $config;

		CFCredentials::set(array(
			'development' => array(
				'key' => $this->config->getAwsKey(),
				'secret' => $this->config->getAwsSecret(),
				'default_cache_config' => '',
				'certificate_authority' => false
			),
			'@default' => 'development'
		));

		$this->amazonELB = new AmazonELB();
		$this->amazonEC2 = new AmazonEC2();
		$this->amazonELB->set_region('elasticloadbalancing.' . $this->config->getRegion() . '.amazonaws.com');
		$this->amazonEC2->set_region('ec2.' . $this->config->getRegion() . '.amazonaws.com');

		$this->logger = new Logger();

	}

	public function deploy() {

		try {

			$instances = $this->listInstances($this->config->getElbName());
			$this->logger->info($instances->count() . ' instances on ELB ' . $this->config->getElbName());

			if($this->config->getDependentElbName()) {
				$dependentElbInstances = $this->listInstances($this->config->getElbName());
				$this->logger->info($dependentElbInstances->count() . ' instances on dependent ELB ' . $this->config->getDependentElbName());
			}

			foreach ($instances as $instance) {

				$instanceId = $instance->InstanceId->to_string();
				$this->logger->info("Instance ID: ${instanceId}");

				$this->waitUntilHealthy($this->config->getElbName());
				if($this->config->getDependentElbName())
					$this->waitUntilHealthy($this->config->getDependentElbName());

				$this->deregisterInstance($this->config->getElbName(), $instanceId);
				$this->logger->info('Deregistered instance from ' . $this->config->getElbName());

				$registeredInstanceInDependentElb = $this->registerInstance($this->config->getDependentElbName(), $instanceId);
				if($registeredInstanceInDependentElb) {
					$this->deregisterInstance($this->config->getDependentElbName(), $instanceId);
					$this->logger->info('Deregistered instance from ' . $this->config->getDependentElbName());
				}

				usleep($this->config->getGracefulPeriod() * 1e6);

				$instance = $this->describeInstance($instanceId);
				$variables = $this->extractVariables($instance);
				$command = $this->render($this->config->getCommand(), $variables);
				$this->logger->info("Run: ${command}");
				$output = $this->execute($command);
				$this->logger->info($output);

				usleep($this->config->getGracefulPeriod() * 1e6);

				if($registeredInstanceInDependentElb) {
					$this->registerInstance($this->config->getDependentElbName(), $instanceId);
					$this->logger->info('Registered instance to ' . $this->config->getDependentElbName());
				}

				$this->registerInstance($this->config->getElbName(), $instanceId);
				$this->logger->info('Registered instance to ' . $this->config->getElbName());

			}

		} catch(Exception $e) {
			$this->logger->error('Deployer is terminated due to unexpected error.');
			$this->logger->error($e->getMessage());
			return false;
		}

		return true;

	}

	private function registeredInstance($elbName, $instanceId) {

		$instances = $this->listInstances(elbName);

		foreach ($instances as $instance)
			if($instance->InstanceId->to_string() == $instanceId)
				return true;
		
		return false;

	}

	private function waitUntilHealthy($elbName){

		while (!$this->isHealthy($this->listInstances($elbName))) {
			$this->logger->info("${elbName} is currently not healthy...");
			usleep($this->config->getHealthCheckInterval() * 1e6);
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

	private function execute($command) {

		exec($command, $output, $status);

		if ($status != 0)
			throw new Exception('Command exit code is not zero.');

		return implode("\n", $output);

	}

	private function listInstances($elbName) {

		$response = $this->amazonELB->describe_instance_health($elbName);

		if (!$response->isOK())
			throw new Exception($response->body->Error->Message);

		if ($response->body->DescribeInstanceHealthResult->InstanceStates->member->count() == 0)
			throw new Exception('No instance is registered in load balancer.');

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
