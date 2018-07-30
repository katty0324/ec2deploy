<?php

namespace ec2deploy;

use Aws\Credentials\Credentials;
use Aws\Ec2\Ec2Client;
use Aws\ElasticLoadBalancing\ElasticLoadBalancingClient;
use Aws\ElasticLoadBalancingV2\ElasticLoadBalancingV2Client;

class Deployer
{

    private $config;
    private $amazonELB;
    private $amazonELBV2;
    private $amazonEC2;
    private $logger;

    public function __construct($config)
    {

        $this->config = $config;

        $clientArguments = [
            'version' => 'latest',
            'region' => $this->config->getRegion(),
            'credentials' => new Credentials($this->config->getAwsKey(), $this->config->getAwsSecret()),
        ];

        $this->amazonELB = new ElasticLoadBalancingClient($clientArguments);
        $this->amazonELBV2 = new ElasticLoadBalancingV2Client($clientArguments);
        $this->amazonEC2 = new Ec2Client($clientArguments);

        $this->logger = new Logger();
    }

    public function deploy()
    {

        try {

            $mainElbName = $this->config->getElbName();
            $dependentElbNames = $this->config->getDependentElbNames();
            $allElbNames = array_merge(array($mainElbName), $dependentElbNames);

            foreach ($allElbNames as $elbName) {
                $instances = $this->listInstances($elbName);
                $this->logger->info($instances->count() . ' instances on ELB ' . $elbName);
            }

            foreach (array_chunk($this->listInstances($mainElbName)->getArrayCopy(), $this->config->getConcurrency()) as $instances) {

                foreach ($allElbNames as $elbName)
                    $this->waitUntilHealthy($elbName);

                $relatedElbNames = array();
                $instanceIds = array();

                foreach ($instances as $instance) {
                    $instanceId = $instance['InstanceId'];
                    $instanceIds[] = $instanceId;

                    foreach ($allElbNames as $elbName) {
                        if (!$this->registeredInstance($elbName, $instanceId))
                            continue;
                        $relatedElbNames[$instanceId][] = $elbName;
                        $this->deregisterInstance($elbName, $instanceId);
                        $this->logger->info("Deregistered instance ${instanceId} from ELB ${elbName}");
                    }
                }

                usleep($this->config->getGracefulPeriod() * 1e6);

                foreach ($instanceIds as $instanceId) {
                    $instance = $this->describeInstance($instanceId);
                    $variables = $this->extractVariables($instance);
                    $command = $this->render($this->config->getCommand(), $variables);
                    $this->logger->info("Run command `${command}` on instance ${instanceId}");
                    $output = $this->execute($command);
                    $this->logger->info($output);
                }

                usleep($this->config->getGracefulPeriod() * 1e6);

                foreach ($instanceIds as $instanceId) {
                    foreach ($relatedElbNames[$instanceId] as $relatedElbName) {
                        $this->registerInstance($relatedElbName, $instanceId);
                        $this->logger->info("Registered instance ${instanceId} to ELB ${relatedElbName}");
                    }
                }

            }

        } catch (Exception $e) {
            $this->logger->error('Deployer is terminated due to unexpected error.');
            $this->logger->error($e->getMessage());
            return false;
        }

        return true;

    }

    private function registeredInstance($elbName, $instanceId)
    {

        $instances = $this->listInstances($elbName);

        foreach ($instances as $instance)
            if ($instance['InstanceId'] == $instanceId)
                return true;

        return false;

    }

    private function waitUntilHealthy($elbName)
    {

        while (!$this->isHealthy($this->listInstances($elbName))) {
            $this->logger->info("${elbName} is currently not healthy...");
            usleep($this->config->getHealthCheckInterval() * 1e6);
        }

    }

    private function isHealthy($instances)
    {

        if ($instances->count() == 0)
            return false;

        foreach ($instances as $instance)
            if ($instance->State != 'InService')
                return false;

        return true;

    }

    private function extractVariables($instance)
    {
        $variables = array(
            'instanceId' => $instance['InstanceId'],
            'instanceState' => $instance['State']['Name'],
            'privateDnsName' => $instance['PrivateDnsName'],
            'dnsName' => $instance['PublicDnsName'],
            'privateIpAddress' => $instance['PrivateIpAddress'],
            'ipAddress' => $instance['PublicIpAddress'],
        );

        foreach ($instance['Tags'] as $tag)
            $variables['tag.' . $tag['Key']] = $tag['Value'];

        return $variables;

    }

    private function render($template, $variables)
    {

        foreach ($variables as $key => $value)
            $template = str_replace('${' . $key . '}', $value, $template);

        return $template;

    }

    private function execute($command)
    {

        exec($command, $output, $status);

        if ($status != 0)
            throw new Exception('Command exit code is not zero.');

        return implode("\n", $output);

    }

    private function listInstances($elbName)
    {
        $result = $this->amazonELB->describeInstanceHealth([
            'LoadBalancerName' => $elbName,
        ]);

        if (count($result['InstanceStates']) == 0)
            throw new Exception("No instance is registered in load balancer ${elbName}.");

        return $result['InstanceStates'];
    }

    private function describeInstance($instanceId)
    {
        $result = $this->amazonEC2->describeInstances([
            'InstanceIds' => [
                $instanceId,
            ],
        ]);

        return $result['Reservations'][0]['Instances'][0];
    }

    private function deregisterInstance($elbName, $instanceId)
    {

        $response = $this->amazonELB->deregisterInstancesFromLoadBalancer([
            'Instances' => [
                [
                    'InstanceId' => $instanceId,
                ],
            ],
            'LoadBalancerName' => $elbName,
        ]);

        if (!$response->isOK())
            throw new Exception($response->body->Error->Message);

        return $response->body->DeregisterInstancesFromLoadBalancerResult->Instances->member();

    }

    private function registerInstance($elbName, $instanceId)
    {

        $response = $this->amazonELB->registerInstancesWithLoadBalancer([
            'Instances' => [
                [
                    'InstanceId' => $instanceId,
                ],
            ],
            'LoadBalancerName' => $elbName,
        ]);

        if (!$response->isOK())
            throw new Exception($response->body->Error->Message);

        return $response->body->RegisterInstancesWithLoadBalancerResult->Instances->member();
    }

}
