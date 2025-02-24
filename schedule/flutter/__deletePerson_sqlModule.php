<?php
// __deletePerson_sqlModule.php
// 이 모듈은 특정 테이블에 속한 사람을 삭제하는 역할을 한다.
//mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);  //오류 직접 보고.
mysqli_report(MYSQLI_REPORT_OFF);  //오류 보고 차단.

// ALLOW_ACCESS 상수 정의 여부 확인
if (!defined('ALLOW_ACCESS')) {
    http_response_code(403); // Forbidden
    echo "올바르지 않은 접근입니다. wrong access.";
    exit();
}

// 데이터베이스 연결 설정
$conn2 = new mysqli("localhost", "SCdeleteUser", getenv('SC_P_DDLtS'), "schedule");

if ($conn2->connect_error) {
    $responseStatus = "error";  //sql connection fail
    http_response_code(500);
} else {
    // __meetings_data 테이블에 대한 접근 차단. (혹시 현재 모듈을 강제로 실행할 가능성 때문에 한번 더 차단.)
    if ($table_name !== '__meetings_data') {

        // 테이블 속 사람 삭제 쿼리 실행
        $stmt2 = $conn2->prepare("DELETE FROM `$table_name` WHERE user_name = ?");

        if($stmt2){
            $stmt2->bind_param("s", $person_name);  //$person_name 은 현재 모듈을 include 한 PHP파일에 존재.
        
            if ($stmt2->execute()) {
                $responseStatus = "person deleted successfully.";
                http_response_code(200);
            } else {
                $responseStatus = "failed to delete person.";
                http_response_code(500);
            }
        } else {
            $responseStatus = "failed to delete person.";  //failed to prepare statemant.
            http_response_code(500);
        }
        $stmt2->close();  //$stmt2 선언 블록의 마지막 행이다.
    } else {
        $responseStatus = "wrong access!";  //해당 테이블에 대한 삭제 요청이 들어왔다는 것은, 서버 내부 구조가 유출된 상황이다. 중대한 보안사고로 해킹이 시도되는 것이므로 PHP 코드를 종료한다.
        http_response_code(403);
    }

    // 연결 종료
    $conn2->close();
}
?>
