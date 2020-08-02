# Persistent Storage with Docker bind Mount

- [Introduction](#Introduction)
- [Prerequisites](#Prerequisites)
- [Steps](#Steps)
- [Notes](#Notes)




## Introduction

Using Bind Mounts, a file or directory on the host machine can be mounted into a container. The file or directory is referenced by its full or relative path on the host machine. The file or directory can be created on demand if it does not yet exist. Bind mounts works better in terms of performance, but they rely on the host machine’s filesystem having a specific directory structure available. Below section uses example of a newly created Azure Managed disk added to the VM. Once the filesystem is created disk, it can be mapped to the containers using absolute directory path on the host.



![v](/persistentstorage/azuredisks/bindmount.PNG)

## Prerequisites

> 1. Azure VM with Docker installed and system managed identitiy enabled. Follow [link](/Docker%20Host%20Configuration/README.md) to deploy Azure VM using packer.
> 2. Existing Container Images pulled from Azure Continer Registry.
> 3. Use Azure cloud PowerShell or az cli from local machine connected to the azure subscription to run below AZ cli commands.
> 4. Update the values for below variables as required 
```
vmrgname="app-rg"
vmname="dkvm01"
```
## Steps
1. Open a cloud shell from portal or login to azure using cli from local machine
2. Add an additional data disk on Docker VM
```
az vm disk attach -g $vmrgname --vm-name $vmname --name myDataDisk  --new --size-gb 50
```
3. Login to VM using serial console or ssh and mount the new data disk

- get the list of physical disks attached to VM to find the newly attached disk
```
dmesg | grep SCSI
```

- Use fdisk to create partition. Accept the defaults. The output will display the partition name to be used to create filesystem on it
```
sudo fdisk /dev/sdc
partprobe 
```
- create a filesystem
```
sudo mkfs -t ext4 /dev/sdc1
```
- Mount the filesystem
```
sudo mkdir /datadrive
sudo mount /dev/sdc1 /datadrive
```
- add the entry in fstab to ensure drive is remounted automatically after reboot. Get the UUID using "blkid"
```
sudo blkid
sudo vi /etc/fstab

#- Add below line by replacing actual UUID

UUID=33333333-3b3b-3c3c-3d3d-3e3e3e3e3e3e   /datadrive   ext4   defaults,nofail   1   2

```


4. Mount the drive to docker containers

- Run the container with bound option
```
~ sudo docker run -it --mount type=bind,source=/datadrive,target=/mnt testacr02.azurecr.io/samples/demoapp /bin/bash

```

- Create a new directory on the mounted path in the container and then stop container by exit or stopping

```
# cd mnt
# mkdir mydir
# exit
```

5. Check for data persistency


- Validate the folder "mydir" is created on Host VM drive

```
~ cd /mnt
~ ls

```

- Run the container again to validate if folder persists
```
~ sudo docker run -it --mount type=bind,source=/datadrive,target=/mnt testacr02.azurecr.io/samples/demoapp /bin/bash

# cd mnt
# ls

```

6. Mount  on different container simultaneously. Change the image names as required.

- run two containers with same Bind Mount
```
~ sudo docker run -d --mount type=bind,source=/datadrive,target=/mnt nginx
~ sudo docker run -d --mount type=bind,source=/datadrive,target=/mnt bulletinboard
```
- validate both containers are running
```
~ sudo docker ps
```


### NOTES
1. Docker Volumes and Bind Mount are different ways of attaching storage on containers. The actual data resides on Host VM disks i.e. Azure managed disks in both the cases.
2. For Clarity, Commands running on VM are prefixed by ~ and the commands running on Containers are prefixed by #
3. To understand used cases for Bind Mount in detail, refer to the [documentation](https://docs.docker.com/storage/)