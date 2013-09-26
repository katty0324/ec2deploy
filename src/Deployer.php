<?php

class Deployer {

	private $amazonELB;

	public function __construct($region) {
		$this->amazonELB = new AmazonELB();
		$this->amazonELB->set_region($region);
	}

	public function deploy($elbName, $command) {

		$this->listInstances($elbName);

	}

	private function listInstances($elbName) {

		return $this->amazonELB->describe_instance_health($elbName);

	}

}
