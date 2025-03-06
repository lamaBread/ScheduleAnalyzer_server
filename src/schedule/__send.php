<?php
// mysqli_report를 설정하여 오류를 자동으로 출력하지 않도록 설정
// mysqli_report(MYSQLI_REPORT_OFF);
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type");
//echo "<br><br>잠시 DB와 통신하는 코드 모듈을 막아두었다.";

if(empty($meeting_name) || empty($name) || empty($date)){
    echo "<br>";
    echo "<p>올바르지 않은 접근입니다. wrong access.</p>";
    http_response_code(403); // Forbidden
    die();
}

// ALLOW_ACCESS 상수 정의 여부 확인
if (!defined('ALLOW_ACCESS')) {
    http_response_code(403); // Forbidden
    echo "올바르지 않은 접근입니다. wrong access.";
    exit();
}

if (('__meetings_data' === strtolower($meeting_name)) || ('__request_log' === strtolower($meeting_name))){
    http_response_code(400);
    echo "올바르지 않은 접근입니다. wrong access.";
    exit();
}

// 데이터베이스 연결 정보 - 한 번만 정의
$db_host = 'localhost';
$select_password = 'onlySelect!';
$insert_password = 'onlyInsert!';

// 1. 모임 데이터 조회 연결 생성 (SELECT 권한)
$conn_select = new mysqli($db_host, 'SCselectUser', $select_password, 'schedule', 3307);
if ($conn_select->connect_error) {
    echo "<br>";
    echo "<p>알 수 없는 오류가 발생했습니다. 1</p>" . $conn_select->connect_error;
    die();
}

// 모임 존재 여부 확인
$stmt = $conn_select->prepare("SELECT COUNT(*) FROM __meetings_data WHERE meeting_name = ?");
$stmt->bind_param("s", $meeting_name);
$stmt->execute();
$stmt->bind_result($count);
$stmt->fetch();
$stmt->close();

if ($count === 0) {
    //모임이 없다.
    echo "<br>";
    echo "<p>해당 이름의 모임이 존재하지 않습니다.</p>";
    $conn_select->close();
    die();
}

//모임의 최대 인원 제한 확인.
$stmt = $conn_select->prepare("SELECT max_person FROM __meetings_data WHERE meeting_name = ?");
$stmt->bind_param("s", $meeting_name);  //모임명 바인딩.
$stmt->execute();
$stmt->bind_result($maxRows);  // 설정할 최대 행 수
$stmt->fetch();
$stmt->close();

// 현재 테이블의 행 수를 확인
$sql = "SELECT COUNT(*) AS row_count FROM `" . $conn_select->real_escape_string($meeting_name) . "`";
$result = $conn_select->query($sql);

if ($result && $result->num_rows > 0) {
    $row = $result->fetch_assoc();
    $currentRows = $row['row_count'];

    if ($currentRows >= $maxRows) {
        echo "<br>";
        echo "<p>응답 가능한 최대 인원수에 도달했습니다. 더 이상 응답이 불가능합니다.</p>";
        $conn_select->close();
        die();
    }
} else {
    echo "<br>";
    echo "<p>알 수 없는 오류가 발생했습니다. 3</p>";
    $conn_select->close();
    die();
}

$conn_select->close();
//현재 die() 실행 없이, PHP 코드가 종료되지 않았다면, 최대 인원 수 제한을 넘기지 않은 것이다.

// 2. 데이터 삽입을 위한 연결 생성 (INSERT 권한)
$conn_insert = new mysqli($db_host, 'SCinsertUser', $insert_password, 'schedule', 3307);
if ($conn_insert->connect_error) {
    echo "<br>";
    echo "<p>알 수 없는 오류가 발생했습니다. 2</p>";
    die();
}

//meeting_name(DB-Table name) -> name(varchar20, PRIMARY KEY) / unavailableDays(TEXT) / weekTimetable(TEXT) / date(DATETIME)
$sql = "INSERT INTO `" . $conn_insert->real_escape_string($meeting_name) . "` (user_name, user_unavailDays, weekTime, send_date) VALUES (?, ?, ?, ?)";
$stmt = $conn_insert->prepare($sql);

if($stmt){
    //변수를 쿼리문의 템플릿에 바인딩. 인자 ssss는 4개 변수 모두 string타입임을 알려준다.
    $stmt->bind_param("ssss", $name, $unavailableDays, $weekTimetable, $date);

    //바인딩된 쿼리문 실행.
    if($stmt->execute()){
        //성공
        echo "<p>전송에 성공했습니다!</p>";
    }else{
        $errno = $conn_insert->errno;
        if ($errno == 1062) {  // 에러 메시지를 가져와서 PRIMARY KEY 중복인 경우를 확인
            echo "<br>";
            echo "<p>동일한 이름의 사용자가 이미 응답했습니다. 다른 이름으로 시간표를 공유해 주세요.</p>";
        } elseif ($errno == 1146) {  //테이블이 존재하지 않는 경우 확인.
            echo "<br>";
            echo "<p>".$meeting_name." 모임이 존재하지 않습니다.</p>";
        } else {
            echo "<p>알 수 없는 이유로 시간표 공유에 실패했습니다. 다시 시도해 주십시오.</p>";
        }
    }
    $stmt->close();
} else {
    $errno = $conn_insert->errno;
    if($errno == 1146){
        echo "<br>";
        echo "<p>모임 '".$meeting_name."'(이)가 존재하지 않습니다.</p>";
    } else {
        echo "<p>알 수 없는 이유로 시간표 공유에 실패했습니다. 다시 시도해 주십시오.</p>";
    }
}
$conn_insert->close();
?>
