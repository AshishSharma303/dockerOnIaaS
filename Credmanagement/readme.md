# Docker on IaaS - Secret Management with Key Vault and MSI





## Introduction

This document illustrates how to manage and use secrets with Docker containers on IaaS VMs. Docker containers running on a virtual machine genrally inherit the properties of virtual machine. Managed service Identity which is assigned on Azure VM can be used by deployed containers to fetch application secrets from Key Vault using Code. With the use of Managed service Identity, application/ containers can access storage account data or key vault secrets without the need of SPNs.


![test](/ACI-secretmgmt/secret-mount/aci_secret.PNG)

## Prerequisites
> 1. Use Azure cloud PowerShell or though local machine connected to the azure subscription to run below AZ cli commands.
> 2. 
> 2. Update the values for below variables as required 
```
rg="dk-poc-01"
kvname="dk-poc-kv01"
mysqlname="dk-poc-mysql-01"

vnetname="appvnet"
subnet="pe"
vnetrg="app-rg"

kvdnszone="mytestaci01"
mysqldnszone="eastus2"
location="eastus2"
kvpename="pe-kv-01"
mysqlpename="pe-mysql-01"
mysqluser="mysqladmin"
mysqlpassword=$(openssl rand -base64 14)


1. Create a Resource group for Key vault and MySql
```
az group create --name $rg --location $location
```

2. Create a azure DB for mysql in the resource group
```
mysqlid=$(az mysql server create --resource-group $rg --name $mysqlname --location $location --admin-user $mysqluser --admin-password $mysqlpassword --sku-name GP_Gen5_2 --public-network-access "Disabled" --minimal-tls-version TLS1_2 | jq --raw-output -r '.id')
```

3. Create a private endpoint for mysql server
```
subnetref=$(az network vnet subnet list --resource-group $vnetrg --vnet-name $vnetname --query "[?name=='$subnet'].id" -o tsv)
vnetref=$(az network vnet show -g $vnetrg -n $vnetname | jq --raw-output -r '.id')

peip=$(az network private-endpoint create -g $rg -n $mysqlpename --subnet $subnetref --private-connection-resource-id $mysqlid --connection-name mysql-pec01 -l $location --group-id "mysqlserver" | jq '.customDnsConfigs[0].ipAddresses[0]' | xargs)
```
4. Create Private DNS zone and HOST A record for mysql
```

az network private-dns zone create -g $rg -n privatelink.mysql.database.azure.com
az network private-dns link vnet create -g $rg -n MyDNSLink -z privatelink.mysql.database.azure.com -v $vnetref -e true --registration-enabled false
az network private-dns record-set a add-record -g $rg -z privatelink.mysql.database.azure.com -n $mysqlname -a $peip

```
5. Create a key vault and add mysql user name and password as a secret. For this POC, we will add cloudshell Public IP to Key Vault firewall just to upload secrets. This will not be required in actual deployements using CI/CD as agent pools VMs will be part of the virtual network.
```
cloudshellpip=$(dig +short myip.opendns.com @resolver1.opendns.com)/32
kvid=$(az keyvault create --location $location --name $kvname --resource-group $rg --default-action Deny --sku Standard --bypass AzureServices --query "id" -o tsv)
az keyvault network-rule add --name $kvname --ip-address $cloudshellpip --resource-group $rg
az keyvault secret set --name username --value $mysqluser --vault-name $kvname
az keyvault secret set --name password --value $mysqlpassword --vault-name $kvname
```

6. Create a private endpoint for Key Vault
```

kvpeip=$(az network private-endpoint create -g $rg -n $kvpename --subnet $subnetref --private-connection-resource-id $kvid --connection-name mykv-pec01 -l $location --group-id "vault" | jq '.customDnsConfigs[0].ipAddresses[0]' | xargs)
```

7. Create Private DNS zone and HOST A record for Key Vault

```
az network private-dns zone create -g $rg -n privatelink.vaultcore.azure.net
az network private-dns link vnet create -g $rg -n MyDNSLinkKV -z privatelink.vaultcore.azure.net -v $vnetref -e true --registration-enabled false
az network private-dns record-set a add-record -g $rg -z privatelink.vaultcore.azure.net -n $kvname -a $kvpeip

```

8. Configure container on IaaS VM to access secrets.
```
#login to docker VM via serial console or ssh and connect to the container shell.



```
az container exec \
  --resource-group $rg \
  --name $aciname --exec-command "/bin/sh"
```

6. Validate the secrets
```
ls /mnt/secrets
cat /mnt/secrets/username
cat /mnt/secrets/password

```
7. Clean-up the resources

```
az group delete -n $rg --yes

```

### NOTE
Azure Container Instance supports Managed Service Identity which can be used to access Key Vault from container run time and fetch the secrets. However, this option is not recommended due to below reasons:

1. Managed Service Identity is not supported with Azure Container Instances deployed inside virtual network.
2. Azure Container Instance is not a trusted service for Key Vault.Hence it requires Key Vault firewall to be enabled.

