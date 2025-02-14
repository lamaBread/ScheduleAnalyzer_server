<?php
// mysqli_report를 설정하여 오류를 자동으로 출력하지 않도록 설정
mysqli_report(MYSQLI_REPORT_OFF);

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

//mariaDB 접속.
$connect = mysqli_connect(getenv('DB_HOST'), 'SCinsertUser', getenv('SC_P_I'), 'schedule');
if(!$connect){
    echo "<br>";
    echo "<p>알 수 없는 오류가 발생했습니다.</p>";
    die();
}else{
    //연결 성공함.
    //테이블의 최대 응답 인원수 제한을 초과했는지 검사하는 코드.
    $conn_checkMax = new mysqli(getenv('DB_HOST'), 'SCselectUser', getenv('SC_P_SS'), 'schedule');

    // Check connection
    if ($conn_checkMax->connect_error) {
        echo "<br>";
        echo "<p>알 수 없는 오류가 발생했습니다.</p>";
        die();
        //die("Connection failed: " . $conn_checkMax->connect_error);
    }

    $stmt = $conn_checkMax->prepare("SELECT COUNT(*) FROM __meetings_data WHERE meeting_name = ?");
    $stmt->bind_param("s", $meeting_name);
    $stmt->execute();
    $stmt->bind_result($count);
    $stmt->fetch();
    $stmt->close();

    if ($count === 0) {
        //모임이 없다.
        echo "<br>";
        echo "<p>해당 이름의 모임이 존재하지 않습니다.</p>";
        die();
        //die("not exist meeting");
    }

    //모임의 최대 인원 제한 확인.
    $stmt = $conn_checkMax->prepare("SELECT max_person FROM __meetings_data WHERE meeting_name = ?");
    $stmt->bind_param("s", $meeting_name);  //모임명 바인딩.
    $stmt->execute();
    $stmt->bind_result($maxRows);  // 설정할 최대 행 수
    $stmt->fetch();
    $stmt->close();

    // 현재 테이블의 행 수를 확인
    $sql = "SELECT COUNT(*) AS row_count FROM " . $meeting_name;  //테이블명은 prepared statment 할당 안됨.
    $stmt = $conn_checkMax->prepare($sql);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows > 0) {
        $row = $result->fetch_assoc();
        $currentRows = $row['row_count'];

        if ($currentRows >= $maxRows) {
            echo "<br>";
            echo "<p>응답 가능한 최대 인원수에 도달했습니다. 더 이상 응답이 불가능합니다.</p>";
            die();
            //die("Error: Maximum row limit reached.");
        }
    } else {
        echo "<br>";
        echo "<p>알 수 없는 오류가 발생했습니다.</p>";
        die();
        //die("Error: Could not retrieve row count.");
    }
    $stmt->close();
    $conn_checkMax->close();
}  
//현재 die() 실행 없이, PHP 코드가 종료되지 않았다면, 최대 인원 수 제한을 넘기지 않은 것이다.

//meeting_name(DB-Table name) -> name(varchar20, PRIMARY KEY) / unavailableDays(TEXT) / weekTimetable(TEXT) / date(DATETIME)
$sql = "INSERT INTO ".$meeting_name." (user_name, user_unavailDays, weekTime, send_date) VALUES (?, ?, ?, ?)";
$preStmt = mysqli_prepare($connect, $sql);

if($preStmt){
    //변수를 쿼리문의 템플릿에 바인딩. 인자 ssss는 4개 변수 모두 string타입임을 알려준다.
    mysqli_stmt_bind_param($preStmt, "ssss", $name, $unavailableDays, $weekTimetable, $date);

    //바인딩된 쿼리문 실행.
    if(mysqli_stmt_execute($preStmt)){
        //성공
        echo "<p>전송에 성공했습니다!</p>";
    }else{

        $errno = mysqli_errno($connect);
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
    mysqli_stmt_close($preStmt);
} else {
    $errno = mysqli_errno($connect);
    if($errno == 1146){
        echo "<br>";
        echo "<p>모임 '".$meeting_name."'(이)가 존재하지 않습니다.</p>";
    } else {
        echo "<p>알 수 없는 이유로 시간표 공유에 실패했습니다. 다시 시도해 주십시오.</p>";
    }
}
mysqli_close($connect);
?>
