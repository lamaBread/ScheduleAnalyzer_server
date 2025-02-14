# Apache와 PHP를 포함한 공식 PHP 이미지 사용
FROM php:8.2-apache

# 필요한 확장 프로그램 설치
RUN apt-get update && apt-get install -y \
    libpng-dev \
    libjpeg-dev \
    libfreetype6-dev \
    default-mysql-client \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install gd mysqli pdo pdo_mysql

# Apache mod_rewrite 활성화
RUN a2enmod rewrite proxy proxy_http

# SSL 인증서 생성 및 Apache SSL 모듈 활성화
RUN apt-get update && apt-get install -y openssl \
    && mkdir /etc/apache2/ssl \
    && openssl req -x509 -nodes -days 365 -newkey rsa:2048 -keyout /etc/apache2/ssl/apache.key -out /etc/apache2/ssl/apache.crt -subj "/C=US/ST=Denial/L=Springfield/O=Dis/CN=schedule-analyzer.lama.pe.kr" \
    && a2enmod ssl

# Apache SSL 설정 파일 복사
COPY ./apache-ssl-config.conf /etc/apache2/sites-available/000-default-le-ssl.conf

# SSL 사이트 활성화
RUN a2ensite 000-default-le-ssl

# Apache 설정 파일 복사
COPY ./apache-config.conf /etc/apache2/sites-available/000-default.conf

# 작업 디렉토리 설정
WORKDIR /var/www/html

# PHP 파일을 컨테이너로 복사
COPY ./src/ /var/www/html/

# 컨테이너 내 권한 설정
RUN chown -R www-data:www-data /var/www/html
