<?php
// __deleteTable_sqlModule.php
// 이 모듈은 특정 테이블을 삭제하는 역할을 한다.
//mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);  //오류 직접 보고.
mysqli_report(MYSQLI_REPORT_OFF | MYSQLI_REPORT_STRICT);  //오류 보고 차단.
// MYSQLI_REPORT_STRICT <- 이 옵션이 있어야 try-catch문 사용 가능할지도?

// ALLOW_ACCESS 상수 정의 여부 확인
if (!defined('ALLOW_ACCESS')) {
    http_response_code(403); // Forbidden
    echo "올바르지 않은 접근입니다. wrong access.";
    $conn->close();
    exit();
}

// 데이터베이스 연결 설정
$conn2 = new mysqli("localhost", "SCdeleteUser", getenv('SC_P_DDLtS'), "schedule");

if ($conn2->connect_error) {
    $responseStatus = "error";  //sql connection fail
    http_response_code(500);
} else {
    // 트랜잭션 시작
    $conn2->begin_transaction(MYSQLI_TRANS_START_READ_WRITE);

    try {
        // __meetings_data 테이블의 행에 접근 제한 (__meetings_data 내부에서 테이블이름이 삭제된 순간, createTable.php가 실행되는 것을 막아야 한다.)
        // 즉, __meetings_data 내부의 테이블 이름 목록과, 실제 존재하는 테이블 이름 목록을 동일하게 관리해야 함.
        $conn2->query("LOCK TABLES __meetings_data WRITE, `$table_name` WRITE");

        // 테이블 삭제 쿼리 실행 전, __meetings_data에서 관련 행 삭제
        if ($table_name !== '__meetings_data') {
            $stmt2 = $conn2->prepare("DELETE FROM __meetings_data WHERE meeting_name = ?");
            $stmt2->bind_param("s", $table_name);

            if (!$stmt2->execute()) {
                throw new Exception("Failed to delete from __meetings_data");
            }

            $stmt2->close();

            // 테이블 삭제 쿼리 실행
            $stmt2 = $conn2->prepare("DROP TABLE IF EXISTS `$table_name`");
            
            if ($stmt2->execute()) {
                $responseStatus = "table deleted successfully";
                http_response_code(200);
            } else {
                throw new Exception("Failed to delete table");
            }

            $stmt2->close();
        } else {
            $responseStatus = "wrong access!";  //해당 테이블에 대한 삭제 요청이 들어왔다는 것은, 서버 내부 구조가 유출된 상황이다. 중대한 보안사고로 해킹이 시도되는 것이므로 PHP 코드를 종료한다.
            http_response_code(403);
        }

        // 트랜잭션 커밋
        $conn2->commit();

    } catch (Exception $e) {
        //echo "MySQL error: " . $e->getMessage();  //에러 내용 출력.
        if ($stmt2) {  // prepared statment는 트랜잭션의 종료와 상관 없이 관리해야 함.
            $stmt2->close();
        }
        // 오류 발생 시 롤백
        $conn2->rollback();
        $responseStatus = "Failed to delete table";
        http_response_code(500);
        $conn2->close();
    } finally {  //예외처리 여부와 관계 없이 실행되는 코드블록.

        if ($stmt2) {  // prepared statment는 트랜잭션의 종료와 상관 없이 관리해야 함.
            $stmt2->close();
        }

        // 테이블 잠금 해제
        $conn2->query("UNLOCK TABLES");

        // 연결 종료
        $conn2->close();
    }
}
?>
