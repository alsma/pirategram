FROM ubuntu:24.04

ENV DEBIAN_FRONTEND=noninteractive
ENV LC_ALL=C.UTF-8
ARG PHP_VER=8.4

RUN apt-get autoremove && apt-get update -q && \
    apt-get install -yq --no-install-recommends build-essential apt-utils less libffi-dev libssl-dev software-properties-common \
    wget curl git vim ssh iputils-ping zip unzip mysql-client && \
    add-apt-repository ppa:ondrej/php -yn && apt-get update -q && \
    apt-get install -yq --no-install-recommends \
    php${PHP_VER}-cli php${PHP_VER}-xml php${PHP_VER}-bcmath php${PHP_VER}-curl \
    php${PHP_VER}-gd php${PHP_VER}-imagick php${PHP_VER}-mbstring php${PHP_VER}-mysql \
    php${PHP_VER}-opcache php${PHP_VER}-redis php${PHP_VER}-zip php${PHP_VER}-gmp && \
    apt-get clean && \
    rm -rf /var/lib/apt/lists/* /tmp/* /var/tmp/* && \
    rm /var/log/lastlog /var/log/faillog

RUN php -r "copy('https://getcomposer.org/installer', 'composer-setup.php');" \
    && php composer-setup.php --quiet && rm composer-setup.php \
    && mv composer.phar /usr/local/bin/composer

WORKDIR /var/www

CMD ["bash"]
