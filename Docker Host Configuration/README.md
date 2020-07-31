# Prepare Docker-Engine for IaaS workload

- [Build Docker host](#Build-Docker-Host)
  - [Install Docker components on VM ](#Install-Docker-components-on-VM )
- [Insatll Docker components on VM](#Insatll-Docker-components-on-VM)
  - [Introduction to Packer image for Docker Host](#introduction-to-Packer-image-for-Docker-Host)
    - [Scenario](#scenario)
    - [Approach](#approach)
  - [Reference](#reference)


## Build Docker Host
There various ways to build Docker host. The two main methods are explained in this POC:
  1. install Docker coponents on IaaS through AZ CLI's/Shell commands
  2. Build Packer image for Docker host. 

## Install Docker components on VM 
Below provided method is for ubuntu VM, you can use any Docker supported flavor VM. Run the commands though Azure portal PowerShell:
```
$rg="kube-aks-rg01"
$vnetname="kube-vnet01"
$subneting="kube-subnet-ing01"
$subnetagent="kube-subnet-agent01"
$subnetnode="kube-subnet-node01"
$akscluster="kube-private-cls"
$vmname="kube-ubuntu-vm01"
$vmsku="Standard_DS2_v2"
$vmadmin="azureadmin"
$vmpassword="Password@123"

az group create --name $rg --location eastus2
az network vnet create -g $RG -n $vnetName --address-prefixes 10.10.4.0/22
az network vnet subnet create -g $RG --vnet-name $vnetname -n $subneting --address-prefix 10.10.4.0/24
az network vnet subnet create -g $RG --vnet-name $vnetname -n $subnetagent --address-prefix 10.10.5.0/24
az network vnet subnet create -g $RG --vnet-name $vnetname -n $subnetnode --address-prefix 10.10.6.0/24

$vnetid=$(az network vnet show --resource-group $rg --name $vnetname --query id --output tsv)
$subnetid=$(az network vnet subnet show --resource-group $rg --vnet-name $vnetname --name $subnetnode --query id --output tsv)
$subnetidagent=$(az network vnet subnet show --resource-group $rg --vnet-name $vnetname --name $subnetagent --query id --output tsv)
$subnetidnode=$(az network vnet subnet show --resource-group $rg --vnet-name $vnetname --name $subnetnode --query id --output tsv)

# build Docker Host agent for VM "Ubuntu 18.04.4 LTS"
az vm create --resource-group $rg --name $vmname --image UbuntuLTS --admin-username $vmadmin --admin-password $vmpassword --size $vmsku --subnet $subnetidnode --public-ip-address-dns-name "ubuntuvmpublicip"

#SSH to the Ubuntu VM
sudo apt-get update -y
sudo apt-get install apt-transport-https ca-certificates curl gnupg-agent software-properties-common
sudo curl -fsSL https://download.docker.com/linux/ubuntu/gpg | sudo apt-key add -
sudo apt-key fingerprint 0EBFCD88
sudo add-apt-repository "deb [arch=amd64] https://download.docker.com/linux/ubuntu $(lsb_release -cs) stable"
sudo apt-get update -y
sudo apt-get install docker-ce docker-ce-cli containerd.io
sudo docker run hello-world
```
> Note: you can use HostConfig.sh script on ubuntu server for automation pourpose.


## Introduction to Packer image for Docker Host

Packer is an open source tool for creating identical machine images for multiple platforms from a single source configuration, it is lightweight, runs on every major operating system, and is highly performing, creating machine images for multiple platforms in parallel.

Packer does not replace configuration management like Chef or Puppet & is able to use tools like Chef or Puppet to install software packages in the image.

### Scenario

AT&T mandates to use curated images kept in shared image gallery to deploy any IaaS workload in application subscription. To host docker containers on a IaaS virtual machine, the image should be curated and authorized by ATT. 

### Approach

Packer can be leveraged to fetch source image from a shared image gallery, do docker installation on the base image and push the artifact(managed image) to a shared image gallery. Share image gallery image definition version can be used to deploy docker host.

The below deployment json file refers an existing image definition version from a shared image gallery to run the in-line script to install docker on top of it and push it to and existing shared image gallery as a image definition version.

```
{
  "variables": {
    "clientid": "",
    "clientsecret": "",
    "tenantid": "",
    "subid": "",
    "managed_image_rg": "",
    "managed_image_name": "",
    "vmss_vm_size": "",
    "src_sig_sub": "",
    "scr_sig_rg": "",
    "scr_sig_name": "",
    "scr_sig_image_name": "",
    "src_sig_image_version": "",
    "dst_sig_rg": "",
    "dst_sig_name": "",
    "dst_sig_image": "",
    "dst_sig_version": ""
  },
  "builders": [
    {
      "type": "azure-arm",
      "client_id": "{{user `clientid`}}",
      "client_secret": "{{user `clientsecret`}}",
      "tenant_id": "{{user `tenantid`}}",
      "subscription_id": "{{user `subid`}}",
      "shared_image_gallery": {
        "subscription": "{{user `src_sig_sub`}}",
        "resource_group": "{{user `scr_sig_rg`}}",
        "gallery_name": "{{user `scr_sig_name`}}",
        "image_name": "{{user `scr_sig_image_name`}}",
        "image_version": "{{user `src_sig_image_version`}}"
      },
      "shared_image_gallery_destination": {
        "resource_group": "{{user `dst_sig_rg`}}",
        "gallery_name": "{{user `dst_sig_name`}}",
        "image_name": "{{user `dst_sig_image`}}",
        "image_version": "{{user `dst_sig_version`}}",
        "replication_regions": [
          "east us 2"
        ]
      },
      "os_type": "Linux",
      "managed_image_resource_group_name": "{{user `managed_image_rg`}}",
      "managed_image_name": "{{user `managed_image_name`}}",
      "location": "East US 2",
      "vm_size": "{{user `vmss_vm_size`}}"
    }
  ],
  "provisioners": [
    {
      "execute_command": "echo 'packer' | sudo -S sh -c '{{ .Vars }} {{ .Path }}'",
      "scripts": [
        "HostConfig.sh"
      ],
      "type": "shell"
    }
  ]
}
```

Variables can be declared in another json file.

```
{
	"clientid": "",
	"clientsecret": "",
	"tenantid": "",
	"subid": "",
	"sig_subid": "",
	"managed_image_rg": "",
	"managed_image_name": "",
	"vmss_vm_size": "",
	"src_sig_sub": "",
	"scr_sig_rg": "",
	"scr_sig_name": "",
	"scr_sig_image_name": "",
	"src_sig_image_version": "",
	"dst_sig_rg": "",
	"dst_sig_name": "",
	"dst_sig_image": "",
	"dst_sig_version": ""
}
```

The in-line script to install docker through a shell script in ubuntu.

```
/usr/bin/apt-get update -y
/usr/bin/apt-get install apt-transport-https ca-certificates curl gnupg-agent software-properties-common
curl -fsSL https://download.docker.com/linux/ubuntu/gpg | apt-key add -
add-apt-repository "deb [arch=amd64] https://download.docker.com/linux/ubuntu $(lsb_release -cs) stable"
/usr/bin/apt-get update -y
/usr/bin/apt-get install docker-ce docker-ce-cli containerd.io -y
/usr/sbin/waagent -force -deprovision+user && export HISTSIZE=0 && sync
```

A packer build can be created utilizing the deployment json, the variable json and inline script to create a image definition version in a target shared image gallery with docker pre installed on top of it.

```
packer build --varfile Packer-Var.json Packer-Deploy.json
```

## Reference
[Packer Azure Resource Manager Builder](https://www.packer.io/docs/builders/azure-arm.html#shared_image_gallery)\
[Install docker on Ubuntu](https://docs.docker.com/engine/install/ubuntu/)\
[Install Packer](https://www.packer.io/intro/getting-started/)\
[RAID 55327 - Decision: CI Artifacts for Shared Image Gallery Definitions will contain Packer Definitions](https://dev.azure.com/ATTDevOps/ATT%20Cloud/_workitems/edit/55327)\
[]()
