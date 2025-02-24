<?php
// deletePerson.php
// 이 모듈은 flutter 앱의 요청에 따라 특정 테이블에서 특정 사람을 삭제한다.
define('ALLOW_ACCESS', true);

// 에러 보고 설정
ini_set('display_errors', 0);  // 사용자에게 에러 메시지 숨기기
ini_set('log_errors', 1);  // 오류 로그를 활성화
ini_set('error_log', '/var/log/project-schedule/delete_person.log');  // 오류 로그 파일의 경로 설정
error_reporting(0);

//mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);  //오류 직접 보고.
mysqli_report(MYSQLI_REPORT_OFF);  //오류 보고 차단.

// 현재 요청이 HTTPS인지 확인. => HTTPS가 아니라면 접속 차단.
if (empty($_SERVER['HTTPS']) || $_SERVER['HTTPS'] === 'off') {
    http_response_code(403);
    echo json_encode(array('status' => 'not allow HTTP'));
    exit;
}

header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type");

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
include 'rate_limit.php';  // rate_limit.php 파일 포함
$ip = $_SERVER['REMOTE_ADDR'];  // 클라이언트 IP 주소
if (!checkRateLimit($ip)) {  // 요청 제한 체크
    http_response_code(429); // Too Many Requests
    $responseStatus = "Too many requests. Please try again later.";
    goto nullException;
}

header('Content-Type: application/json');

$input = json_decode(file_get_contents('php://input'), true);

$responseStatus = 'none';  //클라이언트에게 전송할 상태 메시지.
$isMeetingThere = false;  //검증단계에서 사용되는 플래그 변수.
$correctPass = false;  //검증단계에서 사용되는 플래스 변수.

// 현재 파일에는 트랜잭션이 필요가 없다.
// 1-모임 존재 확인 -> 2-PW 검증 -> 3-테이블에서 행(사람 객체) 삭제 모듈 실행.
// 이때 1과 2 과정이 현재 파일이다.
// 1과 2과정은 한 파일에 존재하고, 복수의 파일이 실행되더라도 3과정의 모듈이 실행 즉시 트랜잭션으로 __meetings_data의 쓰기를 차단한다.

if (!empty($input['group_name']) && !empty($input['password']) && !empty($input['person_name'])) {
    $table_name = htmlspecialchars($input['group_name']);  //앱이 지정한 테이블 이름.  //현재는 유니코드로 인코딩 되어 있음.
    $person_name = htmlspecialchars($input['person_name']);
    if(('null' === strtolower($table_name)) || ('__meetings_data' === strtolower($table_name)) || ('__request_log' === strtolower($table_name))){
        http_response_code(400);
        $responseStatus = 'wrong meeting name!';
        goto nullException;  //주의!!! __meetings_data 내부에 null값을 넣지 않기 위한 특수 예외처리임.
    }
    $pass = htmlspecialchars($input['password']);  //앱이 지정한 '테이블 관련자임을 증명하는 비밀번호'.

    $conn = new mysqli("localhost", "SCselectUser", getenv('SC_P_SS'), "schedule");

    if ($conn->connect_error) {  //연결을 총 1회 수행. 먼저 예외처리.
        $responseStatus = "error";  //sql connection fail
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
            $responseStatus =  "not exist meeting";
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

        //하단의 모듈에서 $conn2 으로 다시 객체 선언함.
        //delete user로 재연결 할 예정. (하단에서 로드하는 모듈에서 연결함.)
        // 데이터 가져오기 모듈 포함
        if ($correctPass) {  //비밀번호가 일치할 때만 실행.
            include_once '__deletePerson_sqlModule.php';
            //내부적으로 $responseStatus 와 response_code를 작성한다.
        }
    }
    $conn->close();  // 모든 작업 종료. $conn 이 선언된 공간의 마지막 행이 현재 행.
} else {
    $responseStatus = "invalid input";
    http_response_code(400);
}

nullException:
$response = array('status' => $responseStatus);
echo json_encode($response);
?>
