# Ingress For Docker Host

- [Ingress For Docker Host](#ingress-for-docker-host)
  - [Introduction](#introduction)
  - [Prerequisites](#prerequisites)
  - [Infrastructure Deployment](#infrastructure-deployment)
    - [Base infrastructure Deployment](#base-infrastructure-deployment)
    - [Docker Host Deployment](#docker-host-deployment)
    - [Docker Host Post Deployment Configuration](#docker-host-post-deployment-configuration)
    - [Ingress Deployment](#ingress-deployment)
      - [Internal Ingress Deployment](#internal-ingress-deployment)
      - [External Ingress Deployment](#external-ingress-deployment)
  - [Testing](#testing)
  - [Reference](#reference)



## Introduction

This article provides a walk thorough of how to configure ingress to a container environment hosted on virtual machine.
Azure standard load balancer is used behind private link for internal access and azure application gateway is used for external access of the application.

![v](/attachments/Ingress_infra.png)

## Prerequisites

:heavy_check_mark: [Powershell Version 5.1](https://www.microsoft.com/en-us/download/details.aspx?id=54616)\
:heavy_check_mark: [az-cli Version 2.9.1](https://docs.microsoft.com/en-us/cli/azure/install-azure-cli-windows?view=azure-cli-latest&tabs=azure-cli)\
:heavy_check_mark: [Docker Desktop Version 2.3.0.3](https://www.docker.com/products/docker-desktop)

The entire deployment is done through PowerShell and variable declaration follows PowerShell variable declaration syntax.

## Infrastructure Deployment

### Base infrastructure Deployment

We will start by logging into azure.

```
#region Login into azure
az login
# Set azure context
az account set --subscription "<subscription id>"
#endregion
```
> Note: Replace `<subscription id>` with appropriate subscription id to set the context

Declare necessary variables.

```
#region Variables
# Declare Variables
$location = "eastus2"
$Dock_rg_name = "Dockerrg"
$Dock_vnet_name = "Docker-vnet"
$Dock_vnet_cidr = "10.10.0.0/16"
$Dock_host_snet = "workload-subnet"
$Dock_host_snet_cidr = "10.10.10.0/24"
$Dock_lb_snet = "lb-subnet"
$Dock_lb_snet_cidr = "10.10.20.0/24"
$Dock_appgw_snet = "appgw-subnet"
$Dock_appgw_snet_cidr = "10.10.30.0/24"
$Dock_pls_snet = "pls-subnet"
$Dock_pls_snet_cidr = "10.10.40.0/24"
$Dock_pe_snet = "pe-subnet"
$Dock_pe_snet_cidr = "10.10.50.0/24"
$Dock_host_name = "dockerhost"
$Dock_lb_name = "Docker-lb"
$Dock_pls_name = "Docker-pls"
$Dock_storage_acc_name = $("docker" + (Get-Random))
$Dock_acr_name = $("acr" + (Get-Random))
$Dock_kv_name = $("kv" + (Get-Random))
$Dock_mysql_name = $("mysql" + (Get-Random))
$Dock_appgw_name = "Docker-appgw"
$Dock_appgw_pfx = "D:\cert\auth.pfx"
$Dock_appgw_pfx_pass = "P@ssw0rd@123"
$Dock_appgw_cer = "D:\cert\root.cer"
#endregion
```
>Note: `$Dock_appgw_pfx` and `$Dock_appgw_cer` are provided as artifacts. Please change the path according to git clone directory.

Create resource group.

```
#region Resource Group Deployment
# Create resource group
az group create -n $Dock_rg_name -l $location
#endregion
```
Create the virtual network with subnets for docker host deployment, loadbalancer, private link service, private endpoint and application gateway deployment.

```
#region Virtual Network resource Deployment
# Create virtual network
az network vnet create -n $Dock_vnet_name -g $Dock_rg_name --address-prefixes $Dock_vnet_cidr -l $location --subnet-name $Dock_host_snet --subnet-prefixes $Dock_host_snet_cidr
# Create subnets
# Create lb subnet
az network vnet subnet create -n $Dock_lb_snet -g $Dock_rg_name --address-prefixes $Dock_lb_snet_cidr --vnet-name $Dock_vnet_name
# Create application gateway subnet
az network vnet subnet create -n $Dock_appgw_snet -g $Dock_rg_name --address-prefixes $Dock_appgw_snet_cidr --vnet-name $Dock_vnet_name
# Create private link service subnet
az network vnet subnet create -n $Dock_pls_snet -g $Dock_rg_name --address-prefixes $Dock_pls_snet_cidr --vnet-name $Dock_vnet_name
# Update subnet configuration for PLS
az network vnet subnet update -n $Dock_pls_snet -g $Dock_rg_name --vnet-name $Dock_vnet_name  --disable-private-link-service-network-policies
# Create Private endpoint subnet
az network vnet subnet create -n $Dock_pe_snet -g $Dock_rg_name --address-prefixes $Dock_pe_snet_cidr --vnet-name $Dock_vnet_name
#update PE subnet
az network vnet subnet update -n $Dock_pe_snet -g $Dock_rg_name --vnet-name $Dock_vnet_name --disable-private-endpoint-network-policies
#endregion
```

Create storage account for docker host boot diagnostics.

```
#region Storage Account Creation
# Create storage account
az storage account create -n $Dock_storage_acc_name -g $Dock_rg_name --kind StorageV2 -l $location --sku Standard_GRS
$storage_id = az storage account show -n $Dock_storage_acc_name -g $Dock_rg_name --query id -o tsv
$storage_key = az storage account keys list --account-name $Dock_storage_acc_name -g $Dock_rg_name --query [0].value -o tsv
#endregion
```

Create azure container registry for the docker host to fetch images.

```
#region ACR Deployment
# Create acr
az acr create -n $Dock_acr_name -g $Dock_rg_name --sku Premium --admin-enabled true -l $location
$acr_id = az acr show -n $Dock_acr_name -g $Dock_rg_name --query id -o tsv
# Get ACR username
$acr_username = az acr credential show -n $Dock_acr_name -g $Dock_rg_name --query username -o tsv
# Get ACR password
$acr_password = az acr credential show -n $Dock_acr_name -g $Dock_rg_name --query passwords[0].value -o tsv
#endregion
```

Create azure keyvault and store acr credentials as secrets.

```
#region KeyVault Deployment
# Create AKV
az keyvault create -n $Dock_kv_name -g $Dock_rg_name --location $location --sku standard
$kv_id = az keyvault show -n $Dock_kv_name -g $Dock_rg_name --query id -o tsv
# Create secret for storing acr username and password
az keyvault secret set --vault-name $Dock_kv_name --name "acrusername" --value $acr_username
az keyvault secret set --vault-name $Dock_kv_name --name "acrpassword" --value $acr_password
#endregion
```

Create azure database for MYSQL as backend for the application with pocdb as database. Public access is denied and ssl is enforced.
```
#region MySQL deploymentt
az mysql server create --admin-user corpsmgr --admin-password P@ssw0rd@123 -n $Dock_mysql_name -g $Dock_rg_name --sku-name GP_Gen5_4 -l $location --public-network-access Disabled --ssl-enforcement Enabled
$mysql_id = az mysql server show -n $Dock_mysql_name -g $Dock_rg_name --query id -o tsv
# Add database
az mysql db create -n pocdb -g $Dock_rg_name --server-name $Dock_mysql_name
#endregion
```
Create private endpoints for the PaaS resources(storage account, akv, acr and mysql).
```
#region Private Endpoint Deployment
# Create Table storage private endpoint
az network private-endpoint create --connection-name table-storage -n pe-table --private-connection-resource-id $storage_id -g $Dock_rg_name --subnet $Dock_pe_snet --group-id table -l $location --vnet-name $Dock_vnet_name
$pe_table_ip = az network private-endpoint show -g $Dock_rg_name -n pe-table --query customDnsConfigs[0].ipAddresses[0] -o tsv
# Create File storage private endpoint
az network private-endpoint create --connection-name blob-storage -n pe-blob --private-connection-resource-id $storage_id -g $Dock_rg_name --subnet $Dock_pe_snet --group-id blob -l $location --vnet-name $Dock_vnet_name
$pe_blob_ip = az network private-endpoint show -g $Dock_rg_name -n pe-blob --query customDnsConfigs[0].ipAddresses[0] -o tsv
# Create aks private endpoint
az network private-endpoint create --connection-name akv -n pe-akv --private-connection-resource-id $kv_id -g $Dock_rg_name --subnet $Dock_pe_snet --group-id vault -l $location --vnet-name $Dock_vnet_name
$pe_kv_ip = az network private-endpoint show -g $Dock_rg_name -n pe-akv --query customDnsConfigs[0].ipAddresses[0] -o tsv
# Create acr private endpoint
az network private-endpoint create --connection-name acr -n pe-acr --private-connection-resource-id $acr_id -g $Dock_rg_name --subnet $Dock_pe_snet --group-id registry -l $location --vnet-name $Dock_vnet_name
$pe_acr_data_ip = az network private-endpoint show -g $Dock_rg_name -n pe-acr --query customDnsConfigs[0].ipAddresses[0] -o tsv
$pe_acr_ip = az network private-endpoint show -g $Dock_rg_name -n pe-acr --query customDnsConfigs[1].ipAddresses[0] -o tsv
# Create mysql private endpoint
az network private-endpoint create --connection-name mysql -n pe-mysql --private-connection-resource-id $mysql_id -g $Dock_rg_name --subnet $Dock_pe_snet --group-id mysqlServer -l $location --vnet-name $Dock_vnet_name
$pe_mysql_ip = az network private-endpoint show -g $Dock_rg_name -n pe-mysql --query customDnsConfigs[0].ipAddresses[0] -o tsv
#endregion
```
Create necessary private dns zones and private dns records for the private endpoints and link the private dns zones to the virtual network.
```
#region Private DNS Deployment
# Create private dns zone
az network private-dns zone create -n privatelink.table.core.windows.net -g $Dock_rg_name
az network private-dns zone create -n privatelink.blob.core.windows.net -g $Dock_rg_name
az network private-dns zone create -n privatelink.vaultcore.azure.net -g $Dock_rg_name
az network private-dns zone create -n privatelink.azurecr.io -g $Dock_rg_name
az network private-dns zone create -n privatelink.mysql.database.azure.com -g $Dock_rg_name
# Add A records
az network private-dns record-set a add-record -g $Dock_rg_name -z privatelink.table.core.windows.net -n $Dock_storage_acc_name -a $pe_table_ip
az network private-dns record-set a add-record -g $Dock_rg_name -z privatelink.blob.core.windows.net -n $Dock_storage_acc_name -a $pe_blob_ip
az network private-dns record-set a add-record -g $Dock_rg_name -z privatelink.vaultcore.azure.net -n $Dock_kv_name -a $pe_kv_ip
az network private-dns record-set a add-record -g $Dock_rg_name -z privatelink.azurecr.io -n $Dock_acr_name -a $pe_acr_ip
az network private-dns record-set a add-record -g $Dock_rg_name -z privatelink.azurecr.io -n $($Dock_acr_name + "." + $location + ".data") -a $pe_acr_data_ip
az network private-dns record-set a add-record -g $Dock_rg_name -z privatelink.mysql.database.azure.com -n $Dock_mysql_name -a $pe_mysql_ip
# Create vnet link
az network private-dns link vnet create -g $Dock_rg_name -n storagenetlink -z privatelink.table.core.windows.net -v $Dock_vnet_name -e false
az network private-dns link vnet create -g $Dock_rg_name -n blobnetlink -z privatelink.blob.core.windows.net  -v $Dock_vnet_name -e false
az network private-dns link vnet create -g $Dock_rg_name -n kvnetlink -z privatelink.vaultcore.azure.net  -v $Dock_vnet_name -e false
az network private-dns link vnet create -g $Dock_rg_name -n acrnetlink -z privatelink.azurecr.io  -v $Dock_vnet_name -e false
az network private-dns link vnet create -g $Dock_rg_name -n acrnetlink -z privatelink.mysql.database.azure.com  -v $Dock_vnet_name -e false
#endregion
```
### Docker Host Deployment

Once the base infrastructure is deployed, its time to prepare the host.
To prepare the host from a golden image kept in a shared image gallery, packer can be used. To prepare the image from packer follow this [link](https://github.com/AshishSharma303/dockerOnIaaS/tree/master/Docker%20Host%20Configuration#prepare-docker-engine-for-iaas-workload). Modify the [HostConfig.sh]() to incorporate installation of az cli, azr login, docker pull and docker run commands.

For simplicity purpose, in this demo we will proceed with the vm creation using a market place image and az cli.
```
# Create NIC 
az network nic create -g $Dock_rg_name -n "dockerhost-nic" --vnet-name $Dock_vnet_name --subnet $Dock_host_snet --accelerated-networking true
# Get privae ip of dockerhost vm
$Dock_host_ip = az network nic show -n "dockerhost-nic" -g $Dock_rg_name --query ipConfigurations[0].privateIpAddress -o tsv
$Dock_host_ipconfig = az network nic show -n "dockerhost-nic" -g $Dock_rg_name --query ipConfigurations[0].name -o tsv
# Create a ubuntu 18.04 vm
az vm create -g $Dock_rg_name -n $Dock_host_name --nics "dockerhost-nic" --image UbuntuLTS --admin-username "corpsmgr" --admin-password "P@ssw0rd@123" --zone 1 -l $location --size Standard_F4s_v2 --boot-diagnostics-storage $Dock_storage_acc_name
# Enable managed identity on the docker host with read permission on akv
az vm identity assign -g $Dock_rg_name -n $Dock_host_name --role Reader --scope $kv_id
$vm_msi_id = az vm identity show -n $Dock_host_name -g $Dock_rg_name --query principalId
# Provide access permission on akv
az keyvault set-policy -n $Dock_kv_name --secret-permissions get --object-id $vm_msi_id
#endregion
```

Before proceeding any further we need to pull the application image from public repository and push the application to the acr created for this demo.

```
# Pull application image
docker pull maanan/external:apache2_mysql_ssl
docker image tag maanan/external:apache2_mysql_ssl <acr>.azurecr.io/poc:apache2_mysql_ssl
az acr login -n <acr> -u <username> -p <password>
docker push <acr>.azurecr.io/poc:apache2_mysql_ssl
```

>Note: The image needs to be in the exact naming convention of `<acr>.azurecr.io/poc:apache2_mysql_ssl` for the post deployment to work. Replace `<acr>` with container registry name, `<username>` with acr admin username and `<password>` with acr admin password

Once the application image is pushed to azure container registry proceed to the post deployment configuration section

### Docker Host Post Deployment Configuration

Once the virtual machine is deployed, we need to install az cli on it so that it can use managed identity to fetch secrets from azure key vault to login in to azure container registry and pull the application image. Docker is needed to be installed to run the containers. All of this is achieved through custom script extension.

```
#region Post deployment script for Docker host
$script=@"
#!/bin/bash
/usr/bin/apt-get update -y
/usr/bin/apt-get install apt-transport-https ca-certificates curl gnupg-agent software-properties-common -y
curl -fsSL https://download.docker.com/linux/ubuntu/gpg | apt-key add -
add-apt-repository "deb [arch=amd64] https://download.docker.com/linux/ubuntu `$(lsb_release -cs) stable"
/usr/bin/apt-get update -y
/usr/bin/apt-get install docker-ce docker-ce-cli containerd.io -y
curl -sL https://aka.ms/InstallAzureCLIDeb | sudo bash
/usr/bin/apt-get install jq -y
k=`$(curl 'http://169.254.169.254/metadata/identity/oauth2/token?api-version=2018-02-01&resource=https%3A%2F%2Fvault.azure.net' -H Metadata:true | jq --raw-output -r '.access_token')
usrname=`$(curl $("https://" + $Dock_kv_name  + ".vault.azure.net//secrets/acrusername?api-version=2016-10-01") -H "Authorization: Bearer `$k" | jq --raw-output -r '.value')
pass=`$(curl $("https://" + $Dock_kv_name  + ".vault.azure.net//secrets/acrpassword?api-version=2016-10-01") -H "Authorization: Bearer `$k" | jq --raw-output -r '.value')
az acr login -n $Dock_acr_name -u `$usrname -p `$pass
docker pull $($Dock_acr_name + ".azurecr.io/poc:apache2_mysql_ssl")
docker run -d -p 443:443 $($Dock_acr_name + ".azurecr.io/poc:apache2_mysql_ssl")
"@
$utf8 = [System.Text.Encoding]::UTF8.GetBytes($script)
$base64 = [System.Convert]::ToBase64String($utf8)
$settings = New-Object psobject
Add-Member -InputObject $settings -Name script -MemberType NoteProperty -Value $base64
$settings | ConvertTo-Json | Out-File script_payload.json
# Apply custom script extension
az vm extension set -n customScript --publisher Microsoft.Azure.Extensions -g $Dock_rg_name --vm-name $Dock_host_name --settings 'script_payload.json'
#endregion
```

### Ingress Deployment

For internal access of the application deployed on the container we will use azure standard loadbalancer behind private link. And for external access we will use application gateway.

#### Internal Ingress Deployment

```
#region Load Balancer Resource Deployment
# Deploy az lb
az network lb create -n $Dock_lb_name -g $Dock_rg_name --backend-pool-name "dockerhost" --frontend-ip-name "lb-frontend" -l $location --private-ip-address "10.10.20.5" --sku Standard --subnet $Dock_lb_snet --vnet-name $Dock_vnet_name
# Create lb health probe
az network lb probe create -g $Dock_rg_name --lb-name $Dock_lb_name -n "DockerHostHealth" --protocol tcp --port 443
# Create lb rule
az network lb rule create -g $Dock_rg_name -n "dockerlbrule" --lb-name $Dock_lb_name --protocol tcp --frontend-port 443 --backend-port 443 --frontend-ip-name "lb-frontend" --backend-pool-name "dockerhost" --probe-name "DockerHostHealth" --disable-outbound-snat true
# Create NSG for standard loadbalancer 
az network nsg create -g $Dock_rg_name -n lb-ngs
# Create NSG rule for inbound
az network nsg rule create -g $Dock_rg_name --nsg-name lb-ngs -n allow-inbound-https --protocol tcp --direction inbound --source-address-prefix '*' --source-port-range '*' --destination-address-prefix '*' --destination-port-range 443 --access allow --priority 2000
# Update lb subnet nsg association
az network vnet subnet update -n $Dock_lb_snet --vnet-name $Dock_vnet_name -g $Dock_rg_name --network-security-group lb-ngs
# Add docker host nic to the backend pool
az network nic ip-config update -n $Dock_host_ipconfig -g $Dock_rg_name --lb-address-pools "dockerhost" --lb-name $Dock_lb_name --nic-name "dockerhost-nic"
#endregion
#region Private Link Service Deployment
# Create private link service
az network private-link-service create --lb-frontend-ip-configs "lb-frontend" -n $Dock_pls_name -g $Dock_rg_name --subnet $Dock_pls_snet --vnet-name $Dock_vnet_name --enable-proxy-protocol false --lb-name $Dock_lb_name -l $location --private-ip-address "10.10.40.5" --private-ip-address-version IPv4 --private-ip-allocation-method Static
#endregion
```

#### External Ingress Deployment

```
#region Application Gateway deployment
# Create frontend public ip
az network public-ip create -n "docker-appgw-ip" -g $Dock_rg_name -l $location --sku Standard
# Create AppGw V2
az network application-gateway create -n $Dock_appgw_name -l $location -g $Dock_rg_name --capacity 2 --cert-file $Dock_appgw_pfx --cert-password $Dock_appgw_pfx_pass --frontend-port 443 --http-settings-port 443 --http-settings-protocol https --public-ip-address "docker-appgw-ip" --routing-rule-type Basic --servers $Dock_host_ip --sku Standard_v2 --subnet $Dock_appgw_snet --vnet-name $Dock_vnet_name
# Create Health Probe
az network application-gateway probe create --gateway-name $Dock_appgw_name -n hp --protocol Https -g $Dock_rg_name --host attdemo.azure.com --port 443 --path "/"
# Create root certificate for https setting
az network application-gateway root-cert create --gateway-name $Dock_appgw_name -n rootcert -g $Dock_rg_name --cert-file $Dock_appgw_cer
# Update http settings to use custom root cert and health probe
az network application-gateway http-settings update --gateway-name $Dock_appgw_name --probe hp -g $Dock_rg_name -n appGatewayBackendHttpSettings --root-certs rootcert --host-name attdemo.azure.com
#endregion
```

## Testing

Apache web server is acting as the front end for the application with SSL mode enabled and the certificate installed on the server is published on [CN=attdemo.azure.com](). Create appropriate dns records
- For internal access - [attdemo.azure.com]() should point to private endpoint connected to the PLS of standard load balancer.
- For external access - [attdemo.azure.com]() should point to application gateway front end public ip.

Once the DNS records are in place access the application with [https://attdemo.azure.com]()
The webpage should prompt the user with six data field to populate.
![v](/attachments/index.PNG)
Identifier - A value to be inserted to the database table
Key - A value to be inserted to the database table
dbservername - Name of the mysql db created in the demo
databasename - pocdb
dbusername  - Mysql database username
dbpassword - P@ssw0rd@123

Once the appropriate data field are populated, click on `Submit`.
The user should be greeted with `Connected Successfully` and `New record created successfully` message.
![v](/attachments/connect.PNG)
To verify the records in the database, enable public access and add you client ip to the mysql firewall. Use any db connector tool to connect to the database to browse to `pocdb` database. Locate `dockerpoc` table and check the data inside it.
![v](/attachments/db.PNG)

## Reference
[Azure Database for MySql - SSL configuration](https://docs.microsoft.com/en-us/azure/mysql/howto-configure-ssl)
