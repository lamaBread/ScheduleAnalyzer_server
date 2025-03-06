<?php
// 시간당 접속 제한용 모듈.
// rate_limit.php 파일이 직접 실행되었는지 확인
if (basename(__FILE__) == basename($_SERVER['PHP_SELF'])) {
    http_response_code(403); // Forbidden
    echo "올바르지 않은 접근입니다. wrong access.";
    exit();
}

// ALLOW_ACCESS 상수 정의 여부 확인
if (!defined('ALLOW_ACCESS')) {
    http_response_code(403); // Forbidden
    echo "올바르지 않은 접근입니다. wrong access.";
    exit();
}

// 데이터베이스 연결
function getDbConnection() {
    // 환경변수 이름 수정: DB-NAME -> DB_NAME (PHP 환경변수 이름 규칙에 맞게)
    $host = getenv('DB-NAME') ?: 'localhost';
    $password = getenv('SC_P_REQ') ?: 'onlyRate!';
    
    $conn = new mysqli($host, 'rate_limiter', $password, 'schedule', 3307);
    if ($conn->connect_error) {
        error_log("Rate limit DB connection failed: " . $conn->connect_error);
        die();
    }
    return $conn;
}

// 요청 제한을 처리하는 함수
function checkRateLimit($ip, $limit = 1, $interval = 1) {  //1초에 1회만 가능하도록!
    $conn = getDbConnection();

    // 현재 시간
    $currentTime = new DateTime();
    $currentTime->setTimezone(new DateTimeZone('UTC'));
    $currentTimestamp = $currentTime->format('Y-m-d H:i:s');

    // 제한 시간 계산
    $timeLimit = $currentTime->sub(new DateInterval("PT{$interval}S"))->format('Y-m-d H:i:s');

    // 요청 로그 테이블에서 IP 주소와 제한 시간 내의 요청 수를 확인
    $sql = "SELECT COUNT(*) AS request_count FROM __request_log WHERE ip_address = ? AND request_time > ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ss", $ip, $timeLimit);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();
    $requestCount = $row['request_count'];

    // 요청 수가 제한을 초과한 경우
    if ($requestCount >= $limit) {
        $stmt->close();
        $conn->close();
        return false; // 요청이 너무 많음
    }

    // 요청 기록 추가
    $sql = "INSERT INTO __request_log (ip_address) VALUES (?)";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("s", $ip);
    $stmt->execute();

    // 데이터베이스 연결 종료
    $stmt->close();
    $conn->close();

    return true; // 요청이 허용됨
}
?>