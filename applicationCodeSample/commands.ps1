# Docker Build code:
docker.exe build -t apache_php_image:v2 .
docker.exe rmi apache_php_image:v2 --force
docker.exe run -it -p 8100:80 apache_php_image:v2 --name apache_php_container
docker.exe rm $(docker ps -a -q)




