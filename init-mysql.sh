#!/bin/bash

# MySQL 서비스가 시작될 때까지 대기
max_attempts=30
attempt=0

while [ $attempt -lt $max_attempts ]; do
    if mysqladmin ping -h127.0.0.1 -uroot -p${MYSQL_ROOT_PASSWORD} --silent; then
        echo "MySQL 서비스가 시작되었습니다."
        break
    fi
    echo "MySQL 서비스를 기다리는 중... ($((attempt+1))/$max_attempts)"
    sleep 3
    attempt=$((attempt+1))
done

if [ $attempt -eq $max_attempts ]; then
    echo "MySQL 서비스 시작 타임아웃. 로그를 확인하세요."
    exit 1
fi

# 환경변수를 적용한 SQL 파일 생성
cp /init.sql /init-with-vars.sql
sed -i "s/onlyInsert!/${SC_P_I}/g" /init-with-vars.sql
sed -i "s/onlyCreate!/${SC_P_C}/g" /init-with-vars.sql
sed -i "s/onlySelect!/${SC_P_SS}/g" /init-with-vars.sql
sed -i "s/onlyDelete!/${SC_P_DDLtS}/g" /init-with-vars.sql
sed -i "s/onlyRate!/${SC_P_REQ}/g" /init-with-vars.sql
sed -i "s/onlyUpdate?/${SC_P_U}/g" /init-with-vars.sql

# 초기화 SQL 실행
echo "MySQL 초기화 스크립트 실행 중..."
mysql -h127.0.0.1 -uroot -p${MYSQL_ROOT_PASSWORD} < /init-with-vars.sql

echo "MySQL 초기화가 완료되었습니다."
