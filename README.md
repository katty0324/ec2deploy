EC2Deploy
=========

Zero-downtime deploy tool for Amazon ELB and backend Amazon EC2 instances

## Usage

```shell
ec2deploy -k 'AWS-ACCESS-KEY' -s 'AWS-ACCESS-SECRET' -n 'ELB-NAME' -c 'DEPLOY-COMMAND'
```

## Install

```shell
git clone http://github.com/katty0324/ec2deploy.git
cd ec2deploy/
composer install
```

## Deploy process

ec2deploy works with following deploy process.

1. List instances in the load balancer.
2. Wait until statuses of all instances will be "In Service."
3. Deregister one instance from the load balancer.
4. Run your deploy command.
5. Register the instance with the load balancer.
6. Repeat them for all instances.

## Deploy command template

You can use variables for deploy command.

```
instanceId
instanceState
privateDnsName
dnsName
privateIpAddress
ipAddress
tag.XXX (XXX is EC2 instance tag)
```

If the application name is specified with EC2 instance tag "ContextName" and you will deploy through ssh, you can use command template. 

```shell
ec2deploy -k 'AWS-ACCESS-KEY' -s 'AWS-ACCESS-SECRET' -n 'ELB-NAME' -c 'ssh ${dnsName} deploy ${tag.ContextName}'
```

## Options

```shell
Usage: ec2deploy -n <elb-name> -c <command> -k <aws-key> -s <aws-secret>

-n  --elb-name              instance name of Amazon ELB
-d  --dependent-elb-names   instance names of dependent Amazon ELBs (comma separated)
-c  --command               shell command
-k  --aws-key               AWS access key
-s  --aws-secret            AWS access secret
-r  --region                region of Amazon EC2 and ELB (default: ap-northeast-1)
    --elb-version           ELB version (default: 1)
    --health-check-interval interval of Amazon ELB health check (default: 10.0)
    --graceful-period       sleep time for register and deregister instance for Amazon ELB (default: 5.0)
    --concurrency           the number of hosts to be deployed at the same time (default: 1)
    --help                  help
```

Dependent ELB means ELB having the same instance as the target ELB. The instance to be deployed will be also deregistered from dependent ELBs.
