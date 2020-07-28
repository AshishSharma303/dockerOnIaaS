# dockerOnIaaS
Docker engine POC on Azure IaaS

The following is the scope of the POC


1. VM is only accessible through secure FE
    - VM injected inside a Vnet, and cover all of the actual requirements using secure private access.
    - Docker engine on IaaS VM - use Packer. 
2. Secure conenct with PaaS service
    - Secure Backend Access to Azure resources using PEP
    - Azure DB PaaS service.
3. Container repo and deployment considerations
    - Secure storage of container images
    - Secure deployment of container into the hosting VM
4. Creds managemnet
    - secret access from KV.
    - KV access though MSI, SP
5. Ingres/Egress
    - Ingress (External "appGw" and Internal "Direct PE")
6. Persistent Storage mapping
    - Azure files.
    - Azure disks.
7. Security
    - Privileged mode "disable"
    - TLS considrations.
8. Application code

