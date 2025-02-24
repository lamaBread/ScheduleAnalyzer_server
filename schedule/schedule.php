<?php

// 에러 보고 설정
ini_set('display_errors', 0);  // 사용자에게 에러 메시지 숨기기
ini_set('log_errors', 1);  // 오류 로그를 활성화
ini_set('error_log', '/var/log/project-schedule/webClient.log');  // 오류 로그 파일의 경로 설정
error_reporting(0);

// 현재 요청이 HTTPS인지 확인. => HTTPS가 아니라면 접속 차단.
if (empty($_SERVER['HTTPS']) || $_SERVER['HTTPS'] === 'off') {
    http_response_code(403);
    echo json_encode(array('status' => 'not allow HTTP'));
    exit;
}

define('ALLOW_ACCESS', true);

/*  //token 사용을 중단한다면, POST가 아닌 요청 차단 코드 별도 작성 필요.
// POST가 아닌 모든 요청 차단.
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405); // Method Not Allowed
    exit;
}
*/

// verifying token start ###############
// hidden form으로 클라이언트가 token을 보낸다.

session_start();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // CSRF 토큰 검증
    if (empty($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {  //세션에 저장된 
        http_response_code(403); // 권한 없음
        //echo json_encode(['status' => 'error', 'message' => 'Invalid CSRF token']);
	echo "<p>올바르지 않은 접근입니다. wrong access</p>";
        exit();
    }

    // 토큰 검증 통과 후 처리 시작.
    $meeting_name = htmlspecialchars($_POST['meeting_name']);
    $name = htmlspecialchars(trim($_POST['name']));
    if (empty($name)){
        http_response_code(400);
        echo "<h4 style='margin-top: 20%; text-align: center;'>이름을 입력해 주세요.</h4>";
        exit();
    } elseif (!preg_match("/^[가-힣a-zA-Z0-9_]+$/", $name)) {
        http_response_code(400);
        echo "<h4 style='margin-top: 20%; text-align: center;'>이름에는 한글, 영문 대소문자, 숫자, 밑줄문자(_)만 입력 가능합니다.</h4>";
        exit();
    }

    $unavailableDays = htmlspecialchars($_POST['unavailableDays']);
    if(empty($unavailableDays)){
        http_response_code(400);
        echo "<h4 style='margin-top: 20%; text-align: center;'>회의에 참석 불가능한 일정을 하나 이상 입력해주세요.</h4>";
        exit();
    }
    $weekTimetable = htmlspecialchars($_POST['weekTimetable']);
    $date = date('Y-m-d H:i:s');


    echo "<h2>meeting_name: ";
    echo $meeting_name;
    echo "</h2><br><h2>name: ";
    echo $name;
    echo "</h2><br><h2>unavailableDays: ";
    echo $unavailableDays;
    echo "</h2><br><h2>weekTimetable: ";
    echo $weekTimetable;
    echo "</h2><br><h2>date: ";
    echo $date;
    echo "</h2>";

    echo "<br><br>";


    require_once "__send.php";  //실제로 서버의 DB에 정보를 넣는 모듈.

    // 세션 데이터 삭제 시작.
    $_SESSION = array();

    // 클라이언트 측의 세션 쿠키 삭제
    if (ini_get("session.use_cookies")) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000, 
            $params["path"], 
            $params["domain"], 
            $params["secure"], 
            $params["httponly"]
        );
    }

    // 세션 종료
    session_destroy();
} else {
    echo "<br><p>올바르지 않은 접근입니다. wrong access.</p>";
    http_response_code(403);
}

?>
