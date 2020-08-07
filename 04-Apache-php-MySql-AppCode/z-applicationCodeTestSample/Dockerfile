FROM registry.access.redhat.com/ubi8/ubi:8.1

RUN yum --disableplugin=subscription-manager -y module enable php:7.3 \
  && yum --disableplugin=subscription-manager -y install httpd httpd-tools php \
  && yum --disableplugin=subscription-manager clean all

ADD index.php /var/www/html/index.php
RUN rm /etc/httpd/conf/httpd.conf
ADD httpd.conf /etc/httpd/conf/httpd.conf

RUN mkdir /run/php-fpm
#  && chgrp -R 0 /var/log/httpd /var/run/httpd /run/php-fpm \
#  && chmod -R g=u /var/log/httpd /var/run/httpd /run/php-fpm

EXPOSE 80
# USER 1001
CMD php-fpm & httpd -D FOREGROUND