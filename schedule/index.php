<!DOCTYPE html>
<html lang="ko">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">

  <title>일정분석기</title>
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@picocss/pico@1/css/pico.min.css">
  <link rel="stylesheet" href="styles.css">
  <script src="scripts.js" defer></script>
  <?php
    //현재의 index.html을 실행하여 보낸 정상 요청만을 PHP 모듈이 받을 수 있도록 토큰 추가.
    //즉, 현재의 index.html이 아닌 다른 수단으로 PHP 모듈에 요청을 보내면 차단됨.
    session_start();

    // CSRF 토큰 생성.
    if (empty($_SESSION['csrf_token'])) {  //세션에 토큰이 없다면 실행.
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));  //세션에 토큰 저장.
    }
    
    // CSRF 토큰을 폼에 포함
    function generateCSRFToken() {
        return $_SESSION['csrf_token'];
    }
  ?>
<script async src="https://pagead2.googlesyndication.com/pagead/js/adsbygoogle.js?client=ca-pub-1074439863900363"
     crossorigin="anonymous"></script>
</head>

<body>
  <h4 style="text-align: center;">일정을 공유해 보세요!</h4>

  <form id="main-form" method="post" action="./schedule.php">

    <!-- token -->
    <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">

    <label>
      모임명
      <input id="meeting_name" name="meeting_name" 
      placeholder="<?php
        $name = isset($_GET['meeting_name']) ? htmlspecialchars($_GET['meeting_name']) : '';
        echo $name;
      ?>" 
      autocomplete="off" aria-label="Read-only input" value="<?php echo $name;?>" readonly />
    </label>
    <label>
      참여자 이름
      <input type="text" name="name" placeholder="이름을 입력해주세요" autocomplete="name" required 
        title="한글, 영문 대소문자, 숫자, 밑줄문자(_)만 입력 가능합니다."
        pattern="[가-힣ㅏ-ㅣㄱ-ㅎa-zA-Z0-9_]+"
        oninvalid="setCustomErrorMessage(this)"
        oninput="this.setCustomValidity('')"
      />
      <script>
        function setCustomErrorMessage(input) {
          if (!input.value) {
            input.setCustomValidity('참여자의 이름을 입력해 주세요. (모임 내에 동명이인이 있으면 안됩니다.)');
          } else if (input.validity.patternMismatch) {
            input.setCustomValidity('한글, 영문 대소문자, 숫자, 밑줄문자(_)만 입력 가능합니다.');
          } else {
            input.setCustomValidity(''); // 유효할 경우 오류 메시지 초기화
          }
        }
      </script>

    </label>

    <fieldset>
      <label>추가된 일정 목록
        <select id="schedule_list" name="schedule_list">
          <option id="selectedOptionOne" selected disabled value="">
            일정을 추가해 주세요
          </option>
          <!-- 이곳에 option 태그를 JS코드로 생성. -->
        </select>
      </label>

      <button type="button" onclick="deleteUnavailableDay()">선택 일정 삭제</button>

      <!-- 이곳에 사용자가 입력한 일정 목록을 누적하여 기록. -->
      <input type="hidden" name="unavailableDays" id="unavailableDays">

      <label>
        일정 시작 시각
        <input id="start_datetime" type="datetime-local" name="start_datetime" aria-label="Datetime local" />
      </label>
      <label>
        일정 종료 시각
        <input id="end_datetime" type="datetime-local" name="end_datetime" aria-label="Datetime local" />
      </label>
      <button type="button" onclick="addUnavailableDay()">일정 추가</button>
    </fieldset>
  <div id="timetable_section">
    <label>
      주간 시간표 (시간표 입력은 필수가 아닙니다)
    </label>
    <div class="timetable">
      <table>
        <thead>
          <tr>
            <th>시간/요일</th>
            <th>일요일</th>
            <th>월요일</th>
            <th>화요일</th>
            <th>수요일</th>
            <th>목요일</th>
            <th>금요일</th>
            <th>토요일</th>
          </tr>
        </thead>
        <tbody id="timetable-body">
          <!-- 시간표 셀은 JavaScript로 동적으로 추가될 예정 -->
        </tbody>
      </table>
      <div class="reset-buttons">
        <span style="font-size: 0.9em; margin-right: 3%;">초기화:</span>
        <button type="button" onclick="resetDay(0)">일</button>
        <button type="button" onclick="resetDay(1)">월</button>
        <button type="button" onclick="resetDay(2)">화</button>
        <button type="button" onclick="resetDay(3)">수</button>
        <button type="button" onclick="resetDay(4)">목</button>
        <button type="button" onclick="resetDay(5)">금</button>
        <button type="button" onclick="resetDay(6)">토</button>
      </div>
    </div>
    <div class="form-container">
        <label for="class-color">일정 색상</label>
        <input id="class-color" type="color" value="#ff9500" aria-label="Color picker">
        <label for="day">추가할 요일</label>
        <select id="day">
          <option value="0">일요일</option>
          <option value="1">월요일</option>
          <option value="2">화요일</option>
          <option value="3">수요일</option>
          <option value="4">목요일</option>
          <option value="5">금요일</option>
          <option value="6">토요일</option>
        </select>
        <input type="hidden" name="weekTimetable" id="weekTimetable" >
        <label for="start-time">시작 시간</label> 
        <input type="time" name="time" id="start-time" aria-label="Time" >
        <label for="end-time">종료 시간</label>
        <input type="time" name="time" id="end-time" aria-label="Time" >
        <button type="button" onclick="addClass()">주간 일정 추가</button>        
    </div>
  </div>

  <div>
    <label for="comment-box">공지사항</label> 
    <input id="comment-box" name="comment-box"
    placeholder="<?php
      $comment = isset($_GET['comment']) ? htmlspecialchars($_GET['comment']) : '';
      echo $comment;
    ?>" 
    autocomplete="off" aria-label="Read-only input" value="<?php echo $comment;?>" readonly />
  </div>
  <input type="submit" value="제출" />
  <script>
        document.getElementById('main-form').addEventListener('submit', function(event) {
            var selectElement = document.getElementById('schedule_list');
            
            if (selectElement.value === '') {
                alert('하나 이상의 일정을 추가해 주세요');
                event.preventDefault(); // Prevent form submission
            }
        });
    </script>
  </form>

</body>

</html>
