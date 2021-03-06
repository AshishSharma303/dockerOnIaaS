# Persistent Storage with Docker Volumes

- [Introduction](#Introduction)
- [Prerequisites](#Prerequisites)
- [Steps](#Steps)
- [Notes](#Notes)



## Introduction

Docker Volumes are the preferred mechanism for persisting data generated by and used by Docker containers.These volumes are completely managed by Docker. Volumes are often a better choice than persisting data in a container’s writable layer, because a volume does not increase the size of the containers using it, and the volume’s contents exist outside the lifecycle of a given container

![v](/03-persistentstorage/dockervolumes/dockervolumes.PNG)

## Prerequisites

> 1. Azure VM with Docker installed and system managed identitiy enabled. Follow [link](/Docker%20Host%20Configuration/README.md) to deploy Azure VM using packer.
> 2. Existing Container Images pulled from Azure Continer Registry.

## Steps
1. Login to Docker VM via serial console or ssh.
2. Create a Docker Volume.
```
~ sudo docker volume create data1
```
3. Inspect the volume to see its properties
```
~ sudo docker volume inspect data1
```

4. Run container with a volume
```
~ sudo docker run -it --mount source=data1,target=/mnt testacr02.azurecr.io/samples/demoapp /bin/bash
```

5. test the volume for data persistency

- Navigate to mnt directory and create a "test" directory and then stop container by exit or stopping
```
# cd mnt
# mkdir test
# ls
# exit
```
- Now run the container again with the volume. "test" folder should exists

```
~ sudo docker run -it --mount source=data1,target=/mnt testacr02.azurecr.io/samples/demoapp /bin/bash

# cd mnt
# ls
# exit

```

6. Mount volume on different container simultaneously. Change the image names as required.

- run two containers with same volume
```
~ sudo docker run -d --mount source=data1,target=/mnt nginx
~ sudo docker run -d --mount source=data1,target=/mnt bulletinboard
```
- validate both containers are running
```
~ sudo docker ps
```

### NOTES
1. Docker Volumes and Bind Mount are different ways of attaching storage on containers. The actual data resides on Host VM disks i.e. Azure managed disks in both the cases.
2. For Clarity, Commands running on VM are prefixed by ~ and the commands running on Containers are prefixed by #
3. To understand used cases for Docker volumes in detail, refer to the [documentation](https://docs.docker.com/storage/)