## docker compose 명령
- sudo docker compose down  # 현재 docker compose 파일로 실행된 모든 컨테이너와 볼륨 제거.
- sudo docker compose up -d  # 현재 docker compose 파일 기반으로 작업 시작. (build가 필요하다면 함께 수행됨.)
- sudo docker comose build  # 현재 dockerfile 빌드.


## mariadb container access
- docker exec -it SA_mariadb bash
    - mariadb -u SAuser -p
    - password_sa

