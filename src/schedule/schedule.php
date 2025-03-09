<?php

// 에러 보고 설정
ini_set('display_errors', 0);  // 사용자에게 에러 메시지 숨기기
ini_set('log_errors', 1);  // 오류 로그를 활성화
ini_set('error_log', '/var/log/project-schedule/webClient.log');  // 오류 로그 파일의 경로 설정
error_reporting(0);

/*
// 현재 요청이 HTTPS인지 확인. => HTTPS가 아니라면 접속 차단.
if (empty($_SERVER['HTTPS']) || $_SERVER['HTTPS'] === 'off') {
    http_response_code(403);
    echo json_encode(array('status' => 'not allow HTTP'));
    exit;
}
*/

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

$status = ''; // 결과 상태 메시지
$statusCode = 200; // HTTP 상태 코드

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // CSRF 토큰 검증
    if (empty($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {  //세션에 저장된 
        http_response_code(403); // 권한 없음
        $status = '올바르지 않은 접근입니다. wrong access';
        $statusCode = 403;
    } else {
        // 토큰 검증 통과 후 처리 시작.
        $meeting_name = htmlspecialchars($_POST['meeting_name']);
        $name = htmlspecialchars(trim($_POST['name']));
        if (empty($name)){
            $status = '이름을 입력해 주세요.';
            $statusCode = 400;
        } elseif (!preg_match("/^[가-힣a-zA-Z0-9_]+$/", $name)) {
            $status = '이름에는 한글, 영문 대소문자, 숫자, 밑줄문자(_)만 입력 가능합니다.';
            $statusCode = 400;
        } else {
            $unavailableDays = htmlspecialchars($_POST['unavailableDays']);
            if(empty($unavailableDays)){
                $status = '회의에 참석 불가능한 일정을 하나 이상 입력해주세요.';
                $statusCode = 400;
            } else {
                $weekTimetable = htmlspecialchars($_POST['weekTimetable']);
                $date = date('Y-m-d H:i:s');

                /* 
                // 디버깅용 코드.
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
                */

                require_once "__send.php";  //실제로 서버의 DB에 정보를 넣는 모듈.
                
                // __send.php 실행 후 설정된 $status와 $statusCode 사용
                // $status와 $statusCode가 __send.php에서 설정되지 않았으면 기본값 설정
                if (empty($status) && $statusCode === 200) {
                    $status = "일정이 성공적으로 제출되었습니다!";
                }

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
            }
        }
    }
} else {
    $status = '올바르지 않은 접근입니다. wrong access.';
    $statusCode = 403;
}
?>

<!DOCTYPE html>
<html lang="ko">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">

  <title>일정분석기 - 결과</title>
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@picocss/pico@1/css/pico.min.css">
  <link rel="stylesheet" href="styles.css">
</head>

<body>
  <main class="container">
    <div class="result-container" style="text-align: center; margin-top: 2rem;">
        <?php
        echo "<br>";
        // 결과 메시지 출력
            $successText = '
            <div class="success">
            <h4>완료</h4>
            <p>' . $status . '</p>
            <a href="index.php" role="button">처음으로 돌아가기</a>
            </div>';

            $errorText = '
            <div class="error">
            <h4>오류</h4>
            <p>' . $status . '</p>
            <a href="javascript:history.back()" role="button">이전 페이지로 돌아가기</a>
            </div>';

            if ($statusCode != 200) {
                echo $errorText;
            } else {
                echo $successText;
            }
        ?>
    </div>
  </main>
</body>

</html>

<?php
http_response_code($statusCode);
?>
