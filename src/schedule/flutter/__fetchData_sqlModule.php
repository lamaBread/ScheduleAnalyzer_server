<?php
// __fetchData_sqlModule.php

// ALLOW_ACCESS 상수 정의 여부 확인
if (!defined('ALLOW_ACCESS')) {
    http_response_code(403); // Forbidden
    echo "올바르지 않은 접근입니다. wrong access.";
    if (isset($conn)) {
        $conn->close();
    }
    exit();
}

$isMeetingThere = false;
// __meetings_data 안에 테이블이 이미 존재하는지 검사하는 코드 시작.

//모임은 확실히 존재한다. 앞서, 현 모듈을 불러낸 코드에서 검증이 끝났다.
//$conn 도 존재한다. ($conn은 select 계정으로 만들어졌으므로, 새로 생성할 필요 없음.)
// 쿼리 작성 (테이블 이름에 대하여는 인젝션에 대비한 prepared statment 준비 불가 -> 모임명이 유효한지를 __meetings_data를 활용해 검증.)
$sql = "SELECT user_name, user_unavailDays, weekTime, send_date FROM " . $table_name;

try {
    $result = $conn->query($sql);

    if ($result) {
        if ($result->num_rows > 0) {
            while ($row = $result->fetch_assoc()) {
                $data[] = $row;  //$data는 현재 모듈을 불러낸 fetchData.php에 선언되어 있다.
            }
            $responseStatus = "success";
            http_response_code(200);
        } else {
            $responseStatus = "no data found";
            http_response_code(404);
        }
    } else {
        throw new Exception("Query failed: " . $conn->error);
    }
} catch (Exception $e) {
    $responseStatus = "error";  //query failed
    error_log("Query error: " . $e->getMessage());
    http_response_code(500);
}
?>
