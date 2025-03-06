<?php
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With");

// makeTable.php
// 테이블(모임) 생성 모듈.
// flutter 에서는 https://lama.pe.kr/schedule/flutter/makeTable.php <-로 접근.
define('ALLOW_ACCESS', true);

// 에러 보고 설정
ini_set('display_errors', 0);  // 사용자에게 에러 메시지 숨기기
ini_set('log_errors', 1);  // 오류 로그를 활성화
ini_set('error_log', '/var/log/project-schedule/make.log');   // 오류 로그 파일의 경로 설정
error_reporting(1);

// HTTPS 검사 코드 제거됨 (개발 환경에서는 HTTP 허용)

if ($_SERVER['REQUEST_METHOD'] !== 'POST'){  // POST가 아니면 검사 시작.
    if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {  // OPTIONS 허용.
        http_response_code(200);
        exit;
    } else {
        http_response_code(405); // Method Not Allowed
        exit;
    }
}

// 클라이언트에게 반환할 데이터 형식 지정.
header('Content-Type: application/json');

try {
    // rate limit 설정.
    include_once 'rate_limit.php';  // rate_limit.php 파일 포함
    $ip = $_SERVER['REMOTE_ADDR'];  // 클라이언트 IP 주소
    if (!empty($ip) && !checkRateLimit($ip)) {  // 요청 제한 체크
        http_response_code(429); // Too Many Requests
        $responseStatus = "Too many requests. Please try again later.";
        throw new Exception($responseStatus);
    }
} catch (Exception $e) {
    http_response_code(500); // Internal Server Error
    $response = array("error" => $e->getMessage());
    echo json_encode($response);
    exit;
}

// file_get_content()는 JSON형식으로 쓰인 요청의 본문을 읽어온다.
// json_decode()는 JSON문자열을 PHP 배열로 변환한다.
$input = json_decode(file_get_contents('php://input'), true);

$responseStatus = "none";  //$response에 저장될 'status'키에 할당되는 상태 메시지.

// 데이터 유효성을 검사 후, 모든 데이터가 존재할 때 데이터를 처리함.
if ( !empty($input['group_name']) && !empty($input['password']) && !empty($input['end_date']) && !empty($input['max_person'])) {

    $table_name = htmlspecialchars($input['group_name']);
    if(('null' === strtolower($table_name)) || ('__meetings_data' === strtolower($table_name)) || ('__request_log' === strtolower($table_name))){
        http_response_code(400);
        $responseStatus = 'wrong meeting name!';
        goto nullException;  //주의!!! __meetings_data 내부에 null값을 넣지 않기 위한 특수 예외처리임.
    }
    $pass = htmlspecialchars($input['password']);
    $expire_day = htmlspecialchars($input['end_date']);
    $max_person = htmlspecialchars($input['max_person']);
    
    // __meetings_data 안에 테이블이 이미 존재하는지 검사하는 코드 시작.
    $host = getenv('DB-NAME') ?: 'localhost';
    $password = getenv('SC_P_SS') ?: 'onlySelect!';
    $conn = new mysqli($host, "SCselectUser", $password, "schedule", 3307);

    if (!$conn->connect_error) {  //에러가 없는 경우.
        $conn->begin_transaction();  // 트랜잭션 시작

        // 전역 락을 얻는다
        $lock_stmt = $conn->prepare("SELECT GET_LOCK('create_table_lock', 10)");
        $lock_stmt->execute();
        $lock_stmt->bind_result($lock_result);
        $lock_stmt->fetch();
        $lock_stmt->close();

        if ($lock_result) {
            // meeting_name 중복 확인
            $stmt = $conn->prepare("SELECT COUNT(*) FROM __meetings_data WHERE meeting_name = ?");
            $stmt->bind_param("s", $table_name);
            $stmt->execute();
            $stmt->bind_result($count);
            $stmt->fetch();
            $stmt->close();

            if ($count > 0) {
                // 중복된 meeting_name이 있는 경우
                $responseStatus = "meeting name duplicated";
                http_response_code(400);
            } else {
                // meeting_name 중복이 아닌 경우, __meetings_data에 삽입
                $insert_host = getenv('DB-NAME') ?: 'localhost';
                $insert_password = getenv('SC_P_I') ?: 'onlyInsert!';
                $conn_insert = new mysqli($insert_host, "SCinsertUser", $insert_password, "schedule", 3307);
                $stmt = $conn_insert->prepare("INSERT INTO __meetings_data (meeting_name, password_hash, expire_day, max_person) VALUES (?, ?, ?, ?)");
                $hashed_pass = password_hash($pass, PASSWORD_DEFAULT); // 비밀번호 해싱
                $stmt->bind_param("sssi", $table_name, $hashed_pass, $expire_day, $max_person);

                if ($stmt->execute()) {
                    // __meetings_data 삽입 성공 시 테이블 생성
                    $stmt->close();

                    $create_host = getenv('DB-NAME') ?: 'localhost';
                    $create_password = getenv('SC_P_C') ?: 'onlyCreate!';
                    $conn_create = new mysqli($create_host, "SCcreateUser", $create_password, "schedule", 3307);

                    if (!$conn_create->connect_error) {
                        // SQL 쿼리를 준비. 테이블명을 직접 바인딩할 수 없으므로 문자열 연결을 사용.
                        $query = "CREATE TABLE `" . $conn_create->real_escape_string($table_name) . "` (
                            user_name VARCHAR(20) PRIMARY KEY,
                            user_unavailDays TEXT,
                            weekTime TEXT,
                            send_date DATETIME
                        )";

                        // 쿼리를 실행.
                        if ($conn_create->query($query) === TRUE) {
                            $responseStatus = "success";
                            $conn->commit();  // 테이블 생성이 성공하면 트랜잭션 커밋
                            http_response_code(200);
                        } else {
                            $conn->rollback();  // 테이블 생성에 실패하면 롤백
                            $responseStatus = "error";  // table creation failed
                            http_response_code(500);
                        }

                        $conn_create->close();
                    } else { // 테이블 생성 DB 연결 실패 시
                        $conn->rollback();  // 테이블 생성에 실패하면 롤백
                        $responseStatus = "error";  // table creation DB connection failed
                        http_response_code(500);
                    }
                } else {
                    $conn->rollback();  // __meetings_data 삽입 실패 시 롤백
                    $responseStatus = "error";  //sql(insert meeting data) error
                    http_response_code(500);
                }
            }
        } else {
            $conn->rollback();  // 락을 얻지 못하면 롤백
            $responseStatus = "error";  // table lock failed
            http_response_code(500);
        }

        // 락을 해제
        $unlock_stmt = $conn->prepare("SELECT RELEASE_LOCK('create_table_lock')");
        $unlock_stmt->execute();
        $unlock_stmt->close();

        $conn->close();  // 연결 종료
    } else {  // table exist check -> connection fail
        $responseStatus = "error";  //sql(tableName exist check) connection fail
        http_response_code(500);
    }
} else {  // input values check -> invalid values
    $responseStatus = "invalid input";
    http_response_code(400);
}

nullException:  //주의!!! 모임명에 null이 입력되는 경우를 위한 특수 예외처리용 레이블임.
// 클라이언트(플러터 앱)에게 정보 반환.
$response = array("status" => $responseStatus);
echo json_encode($response);

?>
