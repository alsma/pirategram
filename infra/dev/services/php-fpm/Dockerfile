FROM ubuntu:24.04

ENV DEBIAN_FRONTEND=noninteractive
ENV LC_ALL=C.UTF-8
ARG PHP_VER=8.4

RUN apt-get autoremove && apt-get update -q && apt-get \
    install -yq --no-install-recommends build-essential apt-utils libffi-dev libssl-dev software-properties-common \
    curl unzip && \
    add-apt-repository ppa:ondrej/php -yn && \
    apt-get update -q && \
    apt-get install -yq --no-install-recommends php${PHP_VER}-fpm php${PHP_VER}-cli php${PHP_VER}-xml php${PHP_VER}-bcmath php${PHP_VER}-curl \
    php${PHP_VER}-gd php${PHP_VER}-imagick php${PHP_VER}-mbstring php${PHP_VER}-mysql php${PHP_VER}-opcache php${PHP_VER}-redis php${PHP_VER}-zip php${PHP_VER}-gmp && \
    ln -s /usr/sbin/php-fpm${PHP_VER} /usr/bin/php-fpm

COPY www.conf /etc/php/${PHP_VER}/fpm/pool.d/www.conf

# XDebug
ARG INSTALL_XDEBUG=false
RUN if [ ${INSTALL_XDEBUG} = true ]; then \
    apt-get install -y php${PHP_VER}-xdebug \
    ;fi

RUN mkdir -p /run/php && \
    touch /run/php/php${PHP_VER}-fpm.pid  && \
    sed -i -e 's/;daemonize = yes/daemonize = no/' /etc/php/${PHP_VER}/fpm/php-fpm.conf

RUN usermod -u 1001 www-data

RUN apt-get clean && \
    rm -rf /var/lib/apt/lists/* /tmp/* /var/tmp/* && \
    rm /var/log/lastlog /var/log/faillog

WORKDIR /var/www

CMD ["php-fpm"]

EXPOSE 9000
