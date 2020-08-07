# Docker on IaaS - Secret Management with Key Vault and MSI


- [Introduction](#Introduction)
- [Prerequisites](#Prerequisites)
- [Steps](#Steps)
- [Notes](#Notes)


## Introduction

This document illustrates how to manage and use secrets with Docker containers on IaaS VMs. Docker containers running on a virtual machine genrally inherit the properties of virtual machine. Managed service Identity which is assigned on Azure VM can be used by deployed containers to fetch application secrets from Key Vault using Code. With the use of Managed service Identity, application/ containers can access storage account data or key vault secrets without the need of SPNs.


![v](/01-Cred Management/secretmgmt.PNG)

## Prerequisites
> 1. Use Azure cloud PowerShell or az cli from local machine connected to the azure subscription to run below AZ cli commands.
> 2. Azure VM with Docker installed and system managed identitiy enabled. Follow link to deploy Azure VM using packer.
> 3. Container Image with below components preinstalled
> - Mysql client
> - JQ
> - curl
> - Azure MySQL public cert downloaded from [here](https://www.digicert.com/CACerts/BaltimoreCyberTrustRoot.crt.pem)
> 4. Update the values for below variables as required 
```
rg="dk-poc-01"
kvname="dk-poc-kv01"
mysqlname="dk-poc-mysql-01"

vnetname="appvnet"
subnet="pe"
vnetrg="app-rg"

location="eastus2"
kvpename="pe-kv-01"
mysqlpename="pe-mysql-01"
mysqluser="mysqladmin"
mysqlpassword=$(openssl rand -base64 14)

vmname="dkvm01"
```
## Steps

1. Create a Resource group for Key vault and MySql
```
az group create --name $rg --location $location
```

2. Create a azure DB for mysql in the resource group
```
mysqlid=$(az mysql server create --resource-group $rg --name $mysqlname --location $location --admin-user $mysqluser --admin-password $mysqlpassword --sku-name GP_Gen5_2 --public-network-access "Disabled" | jq --raw-output -r '.id')
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

8. Assign permission to VM managed Identity on Key Vault secrets

```
spID=$(az resource list -n $vmname --query [*].identity.principalId --out tsv)
az keyvault set-policy --name $kvname --secret-permissions "get" --object-id $spID

```

9. Configure container on IaaS VM to access secrets.

- login to docker VM via serial console or ssh.
- Run the container and login to bash shell
```
~ sudo docker run -it myacr01.azurecr.io/samples/demoapp /bin/bash 
```

- set the required variables inside the container
```
# kvname="dk-poc-kv01.vault.azure.net"
# mysqlname="dk-poc-mysql-01.mysql.database.azure.com"
# dbusersecret="username"
# dbpasswordsecret="password"
```
- Ping Key vault and Mysql DNS names to ensure it is resolving to privte endpoint. Please change the names as required

```
# ping $kvname
# ping $mysqlname
```

- Get the access token for Key Vault resource using the metadata URL
```
# token=$(curl 'http://169.254.169.254/metadata/identity/oauth2/token?api-version=2018-02-01&resource=https%3A%2F%2Fvault.azure.net' -H Metadata:true | jq --raw-output -r '.access_token')
```
- Fetch the db user name and password from Key vault using the access token
```
# dbuser=$(curl https://$kvname//secrets/$dbusersecret?api-version=2016-10-01 -H "Authorization: Bearer $token" | jq --raw-output -r '.value')
# dbpassword=$(curl https://$kvname//secrets/$dbpasswordsecret?api-version=2016-10-01 -H "Authorization: Bearer $token" | jq --raw-output -r '.value')
```
- Connect to mysql PaaS using mysql client
```
# mysql --host=$mysqlname --user=$dbuser@$(echo $mysqlname | cut -d "." -f 1) --password=$dbpassword --ssl-mode=REQUIRED --ssl-ca=BaltimoreCyberTrustRoot.crt.pem
```


### NOTESS
1. For Clarity, Commands running on VM are prefixed by ~ and the commands running on Containers are prefixed by #
2. Docker containers support [native-secret-functionality](https://docs.docker.com/engine/swarm/secrets/) but only with Swarm Manager. This POC is executed for stand alone docker containers on VM by inheriting its managed service identity and use of Key Vaults to manage secrets.

