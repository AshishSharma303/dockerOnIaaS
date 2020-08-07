#Docker Commands for building an custom image from Doscker commit 
docker pull ubuntu
docker run --name ubuntucontainer ubuntu
docker ps -a
docker run -it --name ubuntucontainer ubuntu
# inside the container run following commands:
# apt install curl -y
# curl --version
# apt install -y cifs-utils
# sudo apt-get install jq -y
# Ctrl+p and Ctrl+Q -> detach without terminating
docker container
docker ps
docker stop b125aaa0e298
docker ps -a
docker commit b125aaa0e298 ubuntu_jq
docker images
docker run -it --name ubuntutest ubuntu_jq



# Docker Build code:
docker.exe build -t apache_php_image:v2 .
docker.exe rmi apache_php_image:v2 --force
docker.exe run -it -p 8100:80 apache_php_image:v2 --name apache_php_container
docker.exe rm $(docker ps -a -q)

# PHP INstall code
PHP insatall:
apt -y install software-properties-common -y
add-apt-repository ppa:ondrej/php -y
apt-get update -y
apt -y install php7.4
apt-get install -y php7.4-{bcmath,bz2,intl,gd,mbstring,mysql,zip}
sudo apt-get install php7.4-mysql
# insure mysqli.ini file has been loaded to directory "/etc/php/7.4/mods-available/" 

#Apache install
sudo apt install apache2
sudo ufw allow ssh
sudo ufw allow 80
sudo ufw allow 443
sudo ufw app list
curl -4 icanhazip.com

#once the conf is done, use a2ensite to enable the site on ubuntu 18.4
a2ensite attdemo.azure.com.conf

# use a2dissite to disable the site on ubuntu 18.4
sudo a2dissite 000-default.conf


