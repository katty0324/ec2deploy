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
git submodule update --init
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
imageId
instanceState
privateDnsName
dnsName
keyName
instanceType
launchTime
availabilityZone
kernelId
subnetId
vpcId
privateIpAddress
ipAddress
tag.XXX (XXX is EC2 instance tag)
```

If the application name is specified with EC2 instance tag "ContextName" and you will deploy through ssh, you can use command template. 

```shell
ec2deploy -k 'AWS-ACCESS-KEY' -s 'AWS-ACCESS-SECRET' -n 'ELB-NAME' -c 'ssh ${dnsName} deploy ${tag.ContextName}'
```

