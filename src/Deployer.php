<?php

namespace ec2deploy;

use Aws\Credentials\Credentials;
use Aws\Ec2\Ec2Client;
use Aws\ElasticLoadBalancing\ElasticLoadBalancingClient;
use Aws\ElasticLoadBalancingV2\ElasticLoadBalancingV2Client;
use Exception;

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
                $instanceIds = $this->listInstanceIds($elbName);
                $this->logger->info(count($instanceIds) . ' instances on ELB ' . $elbName);
            }

            foreach (array_chunk($this->listInstanceIds($mainElbName), $this->config->getConcurrency()) as $instanceIds) {

                foreach ($allElbNames as $elbName) {
                    $this->waitUntilHealthy($elbName);
                }

                $relatedElbNames = array();

                foreach ($instanceIds as $instanceId) {
                    foreach ($allElbNames as $elbName) {
                        if (!$this->registeredInstance($elbName, $instanceId)) {
                            continue;
                        }
                        $relatedElbNames[$instanceId][] = $elbName;
                        $this->deregisterInstance($elbName, $instanceId);
                        $this->logger->info("Deregistered instance ${instanceId} from ELB ${elbName}");
                    }
                }

                $this->logger->info("Wait for graceful period.");
                usleep($this->config->getGracefulPeriod() * 1e6);

                foreach ($instanceIds as $instanceId) {
                    $instance = $this->describeInstance($instanceId);
                    $variables = $this->extractVariables($instance);
                    $command = $this->render($this->config->getCommand(), $variables);
                    $this->logger->info("Run command `${command}` on instance ${instanceId}");
                    $output = $this->execute($command);
                    $this->logger->info($output);
                }

                $this->logger->info("Wait for graceful period.");
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
        $instanceIds = $this->listInstanceIds($elbName);
        return in_array($instanceId, $instanceIds);
    }

    private function waitUntilHealthy($elbName)
    {
        while (!$this->isHealthy($elbName)) {
            $this->logger->info("${elbName} is currently not healthy...");
            usleep($this->config->getHealthCheckInterval() * 1e6);
        }
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

    private function listInstanceIds($elbName)
    {
        $instanceIds = [];
        switch ($this->config->getElbVersion()) {
            case 1:
                $result = $this->amazonELB->describeInstanceHealth([
                    'LoadBalancerName' => $elbName,
                ]);

                if (count($result['InstanceStates']) == 0)
                    throw new Exception("No instance is registered in load balancer ${elbName}.");

                foreach ($result['InstanceStates'] as $instanceState) {
                    $instanceIds[] = $instanceState['InstanceId'];
                }
                break;
            case 2:
                $targetGroup = $this->describeTargetGroup($elbName);
                $result = $this->amazonELBV2->describeTargetHealth([
                    'TargetGroupArn' => $targetGroup['TargetGroupArn'],
                ]);
                foreach ($result['TargetHealthDescriptions'] as $targetHealthDescription) {
                    $instanceIds[] = $targetHealthDescription['Target']['Id'];
                }
                break;
            default:
                throw new Exception('Invalid elb version.');
        }
        return $instanceIds;
    }

    private function isHealthy($elbName)
    {
        switch ($this->config->getElbVersion()) {
            case 1:
                $result = $this->amazonELB->describeInstanceHealth([
                    'LoadBalancerName' => $elbName,
                ]);

                if (count($result['InstanceStates']) == 0) {
                    return false;
                }

                foreach ($result['InstanceStates'] as $instanceState) {
                    if ($instanceState['State'] != 'InService') {
                        return false;
                    }
                }

                return true;
            case 2:
                $targetGroup = $this->describeTargetGroup($elbName);
                $result = $this->amazonELBV2->describeTargetHealth([
                    'TargetGroupArn' => $targetGroup['TargetGroupArn'],
                ]);

                if (count($result['TargetHealthDescriptions']) == 0) {
                    return false;
                }

                foreach ($result['TargetHealthDescriptions'] as $targetHealthDescription) {
                    if ($targetHealthDescription['TargetHealth']['State'] != 'healthy') {
                        return false;
                    }
                }

                return true;
            default:
                throw new Exception('Invalid elb version.');
        }
    }

    private function deregisterInstance($elbName, $instanceId)
    {
        switch ($this->config->getElbVersion()) {
            case 1:
                $this->amazonELB->deregisterInstancesFromLoadBalancer([
                    'Instances' => [
                        [
                            'InstanceId' => $instanceId,
                        ],
                    ],
                    'LoadBalancerName' => $elbName,
                ]);
                break;
            case 2:
                $targetGroup = $this->describeTargetGroup($elbName);
                $this->amazonELBV2->deregisterTargets([
                    'TargetGroupArn' => $targetGroup['TargetGroupArn'],
                    'Targets' => [
                        [
                            'Id' => $instanceId,
                        ],
                    ],
                ]);
                break;
            default:
                throw new Exception('Invalid elb version.');
        }
    }

    private function registerInstance($elbName, $instanceId)
    {
        switch ($this->config->getElbVersion()) {
            case 1:
                $this->amazonELB->registerInstancesWithLoadBalancer([
                    'Instances' => [
                        [
                            'InstanceId' => $instanceId,
                        ],
                    ],
                    'LoadBalancerName' => $elbName,
                ]);
                break;
            case 2:
                $targetGroup = $this->describeTargetGroup($elbName);
                $this->amazonELBV2->registerTargets([
                    'TargetGroupArn' => $targetGroup['TargetGroupArn'],
                    'Targets' => [
                        [
                            'Id' => $instanceId,
                        ],
                    ],
                ]);
                break;
            default:
                throw new Exception('Invalid elb version.');
        }
    }

    private function describeTargetGroup($elbName)
    {
        switch ($this->config->getElbVersion()) {
            case 2:
                $result = $this->amazonELBV2->describeTargetGroups([
                    'Names' => [$elbName],
                ]);
                return $result['TargetGroups'][0];
            default:
                throw new Exception('Invalid elb version.');
        }
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
}
