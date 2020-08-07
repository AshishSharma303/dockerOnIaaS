# dockerOnIaaS
Docker engine POC on Azure IaaS


###   Docker on IaaS workload POC high-level agenda:
- VM injected inside a Vnet, and cover all of the actual requirements using secure private access.
- Docker engine on IaaS VM - use Shell/Packer utility. 
- Azure DB PaaS service.
- Secure Backend Access to Azure resources using PEP.
- Secure deployment of container into the hosting VM.

---

### Details of the POC's are below:
0. Docker Host deployment
    - Deploy Docker host though CLI's
    - Deploy Docker host though Packer utility.

1. Creds managemnet
    - secret access from KV.
    - KV access though MSI, SP

2. Ingres/Egress
    - Ingress (External "appGw" and Internal "Direct PE")
    - secure access to BE PaaS service (Azure MySQL)
    - TLS considrations (FE and BE with self-sign certs).

3. Persistent Storage mapping
    - Azure disks.
    - Azure files.
        - Privileged mode "enable/disable" 
    - Docker volumes. 
      
4. Application code
    - Apache/PHP bases FE
    - MySQL PaaS DB
    - App and BE self-sign certs.
    - Dockerfile to build the docker container   

