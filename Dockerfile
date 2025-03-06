FROM ubuntu:20.04

# 비대화형 설치 모드 설정
ENV DEBIAN_FRONTEND=noninteractive

# 시스템 업데이트 및 필요 패키지 설치
RUN apt-get update && apt-get install -y \
    apache2 \
    php \
    php-mysql \
    php-curl \
    php-gd \
    php-mbstring \
    php-xml \
    php-zip \
    mysql-server \
    supervisor \
    && apt-get clean \
    && rm -rf /var/lib/apt/lists/*

# Apache 설정
RUN a2enmod rewrite

# MySQL 초기 설정
RUN mkdir -p /var/run/mysqld \
    && chown -R mysql:mysql /var/run/mysqld \
    && chown -R mysql:mysql /var/lib/mysql \
    && chmod 777 /var/run/mysqld

# MySQL 설정 파일 복사
COPY ./my.cnf /etc/mysql/my.cnf

# Apache 문서 루트 설정
COPY ./src/ /var/www/html/
RUN chown -R www-data:www-data /var/www/html/

# MySQL 초기화 스크립트 복사
COPY ./init.sql /init.sql
COPY ./init-mysql.sh /init-mysql.sh
RUN chmod +x /init-mysql.sh

# Supervisor 설정
COPY ./supervisord.conf /etc/supervisor/conf.d/supervisord.conf

# 포트 노출
EXPOSE 80 3306

# Supervisor를 통해 Apache와 MySQL 시작
CMD ["/usr/bin/supervisord", "-c", "/etc/supervisor/conf.d/supervisord.conf"]
