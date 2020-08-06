# Persistent Storage -Azure File Share directly mounted on Containers


- [Introduction](#Introduction)
- [About Privilged Mode](#About-Privilged-Mode)
- [Prerequisites](#Prerequisites)
- [Steps](#Steps)
- [Notes](#Notes)



## Introduction

In this document, we will demonstrate the steps to mount Azure File Share directly on Docker Container. This covers creation of required azure components and makes use of AZ CLI & bash commands.

## About Privilged Mode 

By default, Docker containers are “unprivileged”. However, there is an option to run containers in privileged mode using "--privilged" switch. Running a container in privileged mode allow containers to access the host devices and provide level of access to containers on host similar to the processes running outside containers. Some of the examples include running docker deamon inside containers and direct host hardware access from container. Privileged mode is considered to be insecure and risky as it enables root level access to containers on host, as a result increasing potential attack surface. Therefore, it is not advisable to use privileged mode.
In case if it is absolutely needed to mount a share directly on container, it is recommended to use ["--cap-add"](https://docs.docker.com/engine/reference/run/#runtime-privilege-and-linux-capabilities) option in "docker run" to add granualar level host capabilities on container. If we try to mount file share on container without either privilged mode or capability option, it gives error "Unable to apply new capability set" while mounting.

In the below example we will see two option to mount fileshare directly on to the container

1. With "--privileged" mode
2. Using "--cap-add" option


![v](/persistentstorage/azurefileshare/directmount.PNG)

## Prerequisites
> 1. Use Azure cloud PowerShell or az cli from local machine connected to the azure subscription to run below AZ cli commands.
> 1. Azure VM with Docker installed and system managed identitiy enabled. Follow [link](/Docker%20Host%20Configuration/README.md) to deploy Azure VM using packer or shell commands.
> 3. Container Image with below components pre-installed
> - JQ
> - curl
> - cifsutil
> 3. Update the values for below variables as required 
```
rg="dk-poc-01"
kvname="dk-poc-kv02"
saname="dkpocmysa012"

vnetname="appvnet"
subnet="pe"
vnetrg="app-rg"

location="eastus2"
kvpename="pe-kv-01"
sapename="pe-mysa-01"
sharename="appdata"
vmname="dkvm01"
```
## Steps 
1. Create a Resource group for Key vault and MySql
```
az group create --name $rg --location $location
```

2. Complete the below storage account tasks and get the required properties

- Create storage account get ID
```
said=$(az storage account create -n $saname -g $rg --allow-blob-public-access false --bypass AzureServices Logging Metrics --default-action Deny --https-only --kind StorageV2 -l $location --min-tls-version TLS1_2 --encryption-services file blob table queue --query "id" -o tsv)
```
- Get the access key
```
key=$(az storage account keys list -g $rg -n $saname --query "[0].value" -o tsv)
```
3. Create a file share on storage account. For this POC, we will add cloudshell Public IP to Storage account firewall just to create fileshare. This will not be required in actual deployements using CI/CD as agent pools VMs will be part of the virtual network

```
cloudshellpip=$(dig +short myip.opendns.com @resolver1.opendns.com)
az storage account network-rule add -g $rg --account-name $saname --ip-address $cloudshellpip
az storage share create -n $sharename --account-key $key --account-name $saname
```
3. Create a private endpoint for storage account from docker VM vnet
```
subnetref=$(az network vnet subnet list --resource-group $vnetrg --vnet-name $vnetname --query "[?name=='$subnet'].id" -o tsv)
vnetref=$(az network vnet show -g $vnetrg -n $vnetname | jq --raw-output -r '.id')
> disable netwwork policy if its not done for the PE subnet: az network vnet subnet update --name $subnetagent --resource-group $rg --vnet-name $vnetname --disable-private-endpoint-network-policies true
peip=$(az network private-endpoint create -g $rg -n $sapename --subnet $subnetref --private-connection-resource-id $said --connection-name sa-pec01 -l $location --group-id "file" --query 'customDnsConfigs[0].ipAddresses[0]' -o tsv)
```
4. Create Private DNS zone and HOST A record for mysql
```

az network private-dns zone create -g $rg -n privatelink.file.core.windows.net
az network private-dns link vnet create -g $rg -n MyDNSLink -z privatelink.file.core.windows.net -v $vnetref -e true --registration-enabled false
az network private-dns record-set a add-record -g $rg -z privatelink.file.core.windows.net -n $saname -a $peip

```
5. Create a key vault and add storage account name and key as a secret. For this POC, we will add cloudshell Public IP to Key Vault firewall just to upload secrets. This will not be required in actual deployements using CI/CD as agent pools VMs will be part of the virtual network.
```
cloudshellpip=$(dig +short myip.opendns.com @resolver1.opendns.com)/32
kvid=$(az keyvault create --location $location --name $kvname --resource-group $rg --default-action Deny --sku Standard --bypass AzureServices --query "id" -o tsv)
az keyvault network-rule add --name $kvname --ip-address $cloudshellpip --resource-group $rg
az keyvault secret set --name accountname --value $saname --vault-name $kvname
az keyvault secret set --name accountkey --value $key --vault-name $kvname
```

6. Create a private endpoint for Key Vault
```

kvpeip=$(az network private-endpoint create -g $rg -n $kvpename --subnet $subnetref --private-connection-resource-id $kvid --connection-name mykv-pec01 -l $location --group-id "vault" --query 'customDnsConfigs[0].ipAddresses[0]' -o tsv)
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

9. Run the container with any of the below options i.e 9a or 9b

- login to docker VM via serial console or ssh.
- Run the container in privileged modeand login to bash shell

9a. Use "--privilged" switch
```
~ sudo docker run --privileged -it testacr02.azurecr.io/samples/demoapp /bin/bash
```
9b. Use "--cap-add" option and add two capabilites. i.e. SYS_ADMIN and DAC_READ_SEARCH

```
sudo docker run --cap-add SYS_ADMIN --cap-add DAC_READ_SEARCH -it testacr02.azurecr.io/samples/demoapp /bin/bash

```
- set the required variables inside the container
```
# kvname="dk-poc-kv02.vault.azure.net"
# saname="dkpocmysa012.file.core.windows.net"
# sanamesecret="accountname"
# sakeysecret="accountkey"
# sharename="appdata"
```
- Ping Key vault and Storage account DNS names to ensure it is resolving to privte endpoint.

```
ping $kvname
ping $saname
```

- Get the access token for Key Vault resource using the metadata URL. THe metadata URL by default uses system managed identity of VM to fetch the token. Hence, no additional arguement is required to specify MSI object ID.
```
# token=$(curl 'http://169.254.169.254/metadata/identity/oauth2/token?api-version=2018-02-01&resource=https%3A%2F%2Fvault.azure.net' -H Metadata:true | jq --raw-output -r '.access_token')
```
- Fetch the storage account name and Key vault using the access token
```
# sauser=$(curl https://$kvname//secrets/$sanamesecret?api-version=2016-10-01 -H "Authorization: Bearer $token" | jq --raw-output -r '.value')
# sakey=$(curl https://$kvname//secrets/$sakeysecret?api-version=2016-10-01 -H "Authorization: Bearer $token" | jq --raw-output -r '.value')
```
- create a cred file by running below
```
cat << EOF > /etc/smb.cred
username=$sauser
password=$sakey
EOF

```
- Mount the share on required directory on Container
```
# mount -t cifs //$saname/$sharename /mnt/share -o vers=3.0,credentials=/etc/smb.cred
```
- Create a file in the share directory
```
# cd /mnt/share
# touch testfile.txt

```
- Validate that file is create on share from cloudshell using cli command
```
az storage file list -s $sharename --account-key $key --account-name $saname --output table
```
### NOTES
1. For Clarity, Commands running on VM are prefixed by ~ and the commands running on Containers are prefixed by #
2. Mounting an azure file share directory on container requires container to be run in "privilged" mode which is not recommended. Another option is to mount File share on Host VM and   use Docker Bind Mount to mount share directory from VN host to the container. This option is explained in detail [here](/persistentstorage/azurefileshare/mountviahost.md). 


