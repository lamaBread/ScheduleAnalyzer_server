<?php
// fetchData.php
// 이 모듈은 flutter 앱에게 특정 테이블의 정보를 전송한다.

// 에러 보고 설정
ini_set('display_errors', 0);  // 사용자에게 에러 메시지 숨기기
ini_set('log_errors', 1);  // 오류 로그를 활성화
ini_set('error_log', '/var/log/project-schedule/fetch.log');  // 오류 로그 파일의 경로 설정
error_reporting(0);

header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type");

// HTTPS 검증 코드 제거됨 (개발 환경에서는 HTTP 허용)

if ($_SERVER['REQUEST_METHOD'] !== 'POST'){  // POST가 아니면 검사 시작.
    if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {  // OPTIONS 허용.
        http_response_code(200);
        exit;
    } else {
        http_response_code(405); // Method Not Allowed
        exit;
    }
}

//rate limit 설정.
define('ALLOW_ACCESS', true);
include 'rate_limit.php';  // rate_limit.php 파일 포함
$ip = $_SERVER['REMOTE_ADDR'];  // 클라이언트 IP 주소
if (!checkRateLimit($ip)) {  // 요청 제한 체크
    http_response_code(429); // Too Many Requests
    $responseStatus = "Too many requests. Please try again later.";
    $data = 'none';
    goto nullException;
}

header('Content-Type: application/json');

$input = json_decode(file_get_contents('php://input'), true);

$responseStatus = 'none';  //클라이언트에게 전송할 상태 메시지.
$isMeetingThere = false;  //검증단계에서 사용되는 플래그 변수.
$correctPass = false;  //검증단계에서 사용되는 플래스 변수.
$data = array();  //클라이언트에게 전송할 데이터가 저장되는 변수.

if (!empty($input['group_name']) && !empty($input['password'])) {
    $table_name = htmlspecialchars($input['group_name']);  //앱이 지정한 테이블 이름.
    if(('null' === strtolower($table_name)) || ('__meetings_data' === strtolower($table_name)) || ('__request_log' === strtolower($table_name))){
        http_response_code(400);
        $responseStatus = 'wrong meeting name!';
        goto nullException;  //주의!!! __meetings_data 내부에 null값을 넣지 않기 위한 특수 예외처리임.
    }
    $pass = htmlspecialchars($input['password']);  //앱이 지정한 '테이블 관련자임을 증명하는 비밀번호'.

    $host = getenv('DB-NAME') ?: 'localhost';
    $password = getenv('SC_P_SS') ?: 'onlySelect!';
    $conn = new mysqli($host, "SCselectUser", $password, "schedule", 3307);

    if ($conn->connect_error) {  //연결을 총 1회 수행. 먼저 예외처리.
        $responseStatus = "error";  //sql connection fail
        error_log("DB connection failed: " . $conn->connect_error);
        http_response_code(500);
    } else {
        // 모임 존재 확인
        $stmt = $conn->prepare("SELECT COUNT(*) FROM __meetings_data WHERE meeting_name = ?");
        $stmt->bind_param("s", $table_name);
        $stmt->execute();
        $stmt->bind_result($count);
        $stmt->fetch();
        $stmt->close();

        if ($count !== 0) {
            $isMeetingThere = true;  //meeting_name에 해당하는 모임이 존재하는 경우
        } else {
            $responseStatus = "not exist meeting";
            http_response_code(400);
        }
        // 비밀번호 검증
        if ($isMeetingThere) {  //테이블이 존재한다. (__meetings_data 에서 확인함.) -> $conn 은 아직 안 닫힘.
            $stmt = $conn->prepare("SELECT password_hash FROM __meetings_data WHERE meeting_name = ?");
            $stmt->bind_param("s", $table_name);
            $stmt->execute();
            $stmt->bind_result($hashed_password);
            $stmt->fetch();
            $stmt->close();

            if (password_verify($pass, $hashed_password)) {
                $correctPass = true;
            } else {
                $responseStatus = "wrong password!";
                http_response_code(401);
            }
        }

        // 데이터 가져오기 모듈 포함
        if ($correctPass) {  //비밀번호가 일치할 때만 실행.
            include_once '__fetchData_sqlModule.php';
            // __fetchData_sqlModule.php 내부에서 $data에 테이블의 모든 정보를 저장함.
            //내부적으로 $responseStatus 와 $data 와 response_code를 작성한다.
        }

        $conn->close();
    }
} else {
    $responseStatus = "invalid input";
    http_response_code(400);
}

nullException:
$response = array('status' => $responseStatus, 'data' => $data);
echo json_encode($response);
?>
