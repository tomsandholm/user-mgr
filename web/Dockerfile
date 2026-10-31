FROM php:8.3-apache
USER root
RUN docker-php-ext-install mysqli pdo pdo_mysql
RUN apt-get update && apt-get install -y --no-install-recommends \
  default-mysql-client \
  ansible \
  openssh-client \
  git \
  unzip \
  && rm -rf /var/lib/apt/lists/*

RUN a2enmod rewrite

RUN groupadd -r ansible 
RUN useradd -s /bin/bash -g ansible -d /home/ansible -m ansible
RUN mkdir -p /root/.ssh && chmod 700 /root/.ssh

WORKDIR /var/www/html

EXPOSE 80
