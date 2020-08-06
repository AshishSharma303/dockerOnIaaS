FROM ubuntu:18.04
ENV TZ=Asia/Calcutta
RUN ln -snf /usr/share/zoneinfo/$TZ /etc/localtime && echo $TZ > /etc/timezone
RUN apt-get update -y \
    && apt install apache2 -y
RUN apt -y install software-properties-common -y \
    && add-apt-repository ppa:ondrej/php -y \
    && apt-get update -y
RUN apt -y install php7.4 \
    && apt-get install -y php7.4-mysql
RUN a2enmod ssl
RUN apt-get install jq -y \
    && apt-get install curl -y \
    && apt-get install -y cifs-utils -y
EXPOSE 443 80
CMD ["/usr/sbin/apache2ctl", "-DFOREGROUND"]