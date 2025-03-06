-- 데이터베이스 생성
CREATE DATABASE IF NOT EXISTS schedule;

-- 사용자 생성 및 권한 부여
CREATE USER IF NOT EXISTS 'sa-user'@'%' IDENTIFIED BY 'sa-user-pw!';
GRANT ALL PRIVILEGES ON schedule.* TO 'sa-user'@'%';
FLUSH PRIVILEGES;

-- schedule 데이터베이스 사용
USE schedule;

-- 테이블 생성: 비밀번호 관리
CREATE TABLE IF NOT EXISTS __meetings_data (
    id INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
    meeting_name VARCHAR(160) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    expire_day CHAR(10) NOT NULL,
    max_person INT NOT NULL
);

-- 테이블 생성: Rate limit 로그
CREATE TABLE IF NOT EXISTS __request_log (
    id INT AUTO_INCREMENT PRIMARY KEY,
    ip_address VARCHAR(45) NOT NULL,
    request_time TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- 계정 생성 및 권한 부여
-- INSERT만 가능한 계정: SC_P_I
CREATE USER IF NOT EXISTS 'SCinsertUser'@'%' IDENTIFIED BY 'onlyInsert!';
GRANT INSERT ON schedule.* TO 'SCinsertUser'@'%';
FLUSH PRIVILEGES;

-- CREATE만 가능한 계정: SC_P_C
CREATE USER IF NOT EXISTS 'SCcreateUser'@'%' IDENTIFIED BY 'onlyCreate!';
GRANT CREATE ON schedule.* TO 'SCcreateUser'@'%';
FLUSH PRIVILEGES;

-- SELECT & SHOW 권한이 있는 계정: SC_P_SS
CREATE USER IF NOT EXISTS 'SCselectUser'@'%' IDENTIFIED BY 'onlySelect!';
GRANT SELECT ON schedule.* TO 'SCselectUser'@'%';
FLUSH PRIVILEGES;

-- DELETE, DROP, LOCK TABLES, SELECT 권한이 있는 계정: SC_P_DDLtS
CREATE USER IF NOT EXISTS 'SCdeleteUser'@'%' IDENTIFIED BY 'onlyDelete!';
GRANT DELETE ON schedule.* TO 'SCdeleteUser'@'%';
GRANT DROP ON schedule.* TO 'SCdeleteUser'@'%';
GRANT LOCK TABLES ON schedule.* TO 'SCdeleteUser'@'%';
GRANT SELECT ON schedule.* TO 'SCdeleteUser'@'%';
FLUSH PRIVILEGES;

-- __request_log 테이블에 대해 SELECT, INSERT 권한이 있는 계정: SC_P_REQ
CREATE USER IF NOT EXISTS 'rate_limiter'@'%' IDENTIFIED BY 'onlyRate!';
GRANT SELECT ON schedule.__request_log TO 'rate_limiter'@'%';
GRANT INSERT ON schedule.__request_log TO 'rate_limiter'@'%';
FLUSH PRIVILEGES;

-- __meetings_data 테이블에 대해 SELECT, UPDATE 권한이 있는 계정: SC_P_U
CREATE USER IF NOT EXISTS 'SCupdateUser'@'%' IDENTIFIED BY 'onlyUpdate?';
GRANT SELECT ON schedule.__meetings_data TO 'SCupdateUser'@'%';
GRANT UPDATE ON schedule.__meetings_data TO 'SCupdateUser'@'%';
FLUSH PRIVILEGES;

-- 이벤트 등록: 만료된 모임 테이블 삭제 프로시저 및 이벤트
DELIMITER //

CREATE PROCEDURE DeleteExpiredTables()
BEGIN
    DECLARE done INT DEFAULT FALSE;
    DECLARE tableName VARCHAR(50);
    DECLARE cur CURSOR FOR 
        SELECT meeting_name FROM __meetings_data WHERE expire_day < CURDATE();
    DECLARE CONTINUE HANDLER FOR NOT FOUND SET done = TRUE;

    OPEN cur;

    read_loop: LOOP
        FETCH cur INTO tableName;
        IF done THEN
            LEAVE read_loop;
        END IF;
        
        -- 동적 SQL을 사용해 테이블 삭제
        SET @query = CONCAT('DROP TABLE IF EXISTS ', tableName);
        PREPARE stmt FROM @query;
        EXECUTE stmt;
        DEALLOCATE PREPARE stmt;
        
        -- 관련 데이터도 삭제
        DELETE FROM __meetings_data WHERE meeting_name = tableName;
    END LOOP;

    CLOSE cur;
END//

DELIMITER ;

-- 이벤트 등록: 만료된 모임 삭제 이벤트
CREATE EVENT IF NOT EXISTS clean_expired_meetings
ON SCHEDULE EVERY 1 DAY
DO
  CALL DeleteExpiredTables();

SET GLOBAL event_scheduler = ON;
