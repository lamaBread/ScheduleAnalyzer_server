<?php
// __modifyTable_sqlModule.php

// ALLOW_ACCESS 상수 정의 여부 확인
if (!defined('ALLOW_ACCESS')) {
    http_response_code(403); // Forbidden
    echo "올바르지 않은 접근입니다. wrong access.";
    if (isset($conn)) {
        $conn->close();
    }
    exit();
}

//모임은 확실히 존재한다. 앞서, 현 모듈을 불러낸 코드에서 검증이 끝났다.
//$conn 도 존재한다. ($conn은 select 계정으로 만들어졌으므로, update계정으로 새로 만들어야 함.)
// new_expire_day, new_max_person 은 이미 존재하는 것이 검증되었다.

// 기존 연결 제거.
if (isset($conn)) {
    $conn->close();
}
if (isset($stmt)) {
    $stmt->close();
}

// 데이터베이스 연결
$host = getenv('DB-NAME') ?: 'localhost';
$password = getenv('SC_P_U') ?: 'onlyUpdate?';
$conn = new mysqli($host, 'SCupdateUser', $password, 'schedule', 3307);

// 연결 확인
if (!$conn->connect_error) {
    // 사용자 입력 값 처리 (Prepared Statement 사용)
    $newExpire = $input['new_expire_day'];
    $newMaxperson = $input['new_max_person'];

    $sql = "UPDATE __meetings_data SET expire_day = ?, max_person = ? WHERE meeting_name = ?";
    $stmt = $conn->prepare($sql);

    if ($stmt) {
        // Prepared Statement에 매개변수 바인딩
        $stmt->bind_param('sis', $newExpire, $newMaxperson, $table_name);

        // 쿼리 실행
        if ($stmt->execute()) {
            $responseStatus = "success";
            http_response_code(200);
        } else {
            $responseStatus = "error"; // stmt execute error
            http_response_code(500);
        }
        $stmt->close();
    } else {
        $responseStatus = "error"; // stmt error.
        http_response_code(500);
    }
} else {
    $responseStatus = "error"; // connection error.
    http_response_code(500);
}

// 연결 닫기
$conn->close();
?>