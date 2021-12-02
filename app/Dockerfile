FROM php:7.4-fpm-alpine

EXPOSE 9000/tcp

ENV OWNER_UID=1000
ENV OWNER_GID=1000
ENV SCRIPT_ROOT=/opt/observer

RUN set -ex && \
  apk add --no-cache \
    apg \
    imagemagick \
    ffmpeg \
    libpng-dev \
    vorbis-tools \
    vim && \
  docker-php-ext-install pdo_mysql mysqli gd && \
  mkdir -p ${SCRIPT_ROOT}/app

COPY . ${SCRIPT_ROOT}/app

ADD docker/setup.sh ${SCRIPT_ROOT}