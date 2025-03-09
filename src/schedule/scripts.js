document.addEventListener('DOMContentLoaded', () => {
    const timetableBody = document.getElementById('timetable-body');

    const createTimetable = () => {
        for (let hour = 0; hour < 24; hour++) {
            const row = document.createElement('tr');
            const timeCell = document.createElement('td');
            timeCell.textContent = `${hour}:00~${hour + 1}:00`;
            timeCell.rowSpan = 60;
            row.appendChild(timeCell);  //병합된 셀 추가.

            for (let j = 0; j < 7; j++) {
                const cell = document.createElement('td');
                cell.classList.add('timetable-cell');
                cell.classList.add('day'+j);  //어느 요일인지를 클래스로 지정.
                row.appendChild(cell);
            }
            timetableBody.appendChild(row);

            //한 시간 단위로 셀 추가.
            for (let minute = 1; minute < 60; minute++) {
                const row = document.createElement('tr');
                for (let j = 0; j < 7; j++) {
                    const cell = document.createElement('td');
                    if(minute == 59){
                        cell.style.borderBottom = 'solid 1px #ccc';
                    }
                    cell.classList.add('timetable-cell');
                    cell.classList.add('day'+j);  //어느 요일인지를 클래스로 지정.
                    row.appendChild(cell);
                }
                timetableBody.appendChild(row);
            }
            //현재까지 생성한 표에서, 맨 마지막 셀의 인라인 스타일에, border-bottom: solid 1px 을 추가하는 코드.
        }
    };

    createTimetable();
});


function resetDay(day){
    const dayIndex = day;  //0: 일요일.

    if (dayIndex < 0 || dayIndex > 6) {
        console.error('Invalid day provided');
        return;
    }

    // dayIndex에 해당하는 모든 셀을 선택
    const timetableBody = document.getElementById('timetable-body');
    const cells = timetableBody.getElementsByClassName(`day${dayIndex}`);

    // 각 셀 초기화
    for (let cell of cells) {
        cell.textContent = '';
        cell.style.backgroundColor = '';
        cell.classList.remove('class-cell');
    }

    //아래는 hidden input에서 지정된 요일의 모든 기록을 삭제하는 코드.
    const weekTimetableInput = document.getElementById('weekTimetable');
    if (weekTimetableInput) {
        // 현재 hidden input의 값을 가져옵니다.
        let entries = weekTimetableInput.value.split('&');

        // 지정된 요일의 기록을 필터링합니다.
        entries = entries.filter(entry => {
            const parts = entry.split('+');
            const entryDayIndex = parseInt(parts[1], 10);  // 요일 인덱스를 가져옵니다.
            return entryDayIndex !== dayIndex;  // 지정된 요일의 기록만 제거합니다.
        });

        // 필터링된 기록을 hidden input에 다시 설정합니다.
        weekTimetableInput.value = entries.join('&');
    }
}

//AI는 조금만 복잡해지면 코드를 수정 못한다... 짜증나네...
//사용자가 선택한 시간 범위에, 선택한 색상을 칠하는 함수.
const addClassToTimetable = (color, day, startTime, endTime) => {
    //표 객체 저장.
    const timetableBody = document.getElementById('timetable-body');

    //사용자가 선택한 요일 저장. 일요일: 0
    const dayIndex = day;

    //구조 분해 할당. 4개의 변수 생성.
    const [startHour, startMinute] = startTime.split(':').map(Number);
    const [endHour, endMinute] = endTime.split(':').map(Number);

    //색칠 범위를 지정하는 인덱스 번호 계산.
    const startIdx = startHour * 60 + startMinute;
    const endIdx = endHour * 60 + endMinute;

    //tr <- 행. 즉, 모든 행을 가져온다.
    const rows = timetableBody.getElementsByTagName('tr');

    //시작 인덱스부터 끝 인덱스까지 색칠 수행.
    for (let i = startIdx; i < endIdx; i++) {
        const row = rows[i];

        //1열은 rowspan으로 병합되어 있으므로, 각 시간의 첫 셀은 dayIndex+1 위치를 칠해야 하고,
        //나머지 셀은 dayIndex 위치를 칠해야 한다.
        let targetDayIndex = dayIndex;
        if(i%60 == 0){  //정각인 경우.
            targetDayIndex++;  //한칸 오른쪽 셀에 색칠.
        }
        const cell = row.getElementsByTagName('td')[targetDayIndex]; // 정확한 요일의 셀 선택
        targetDayIndex = dayIndex;  //다시 초기화.
        if (cell) {
            cell.style.backgroundColor = color;
        }
    }
};

function addClass() {
    const color = document.getElementById('class-color').value;
    const day = document.getElementById('day').value;
    const startTime = document.getElementById('start-time').value;
    const endTime = document.getElementById('end-time').value;

    if(!startTime || !endTime){
        alert('시작시간과 종료시간을 모두 입력해 주세요');
        return ;
    } else if(startTime >= endTime){
        alert("시작 시각 이후의 종료 시각을 선택해 주세요");
        return ;
    }

    console.log(startTime);

    addClassToTimetable(color, day, startTime, endTime);
    writeClass(color, day, startTime, endTime);
}

//hidden form에 사용자 입력을 기록한다.
function writeClass(color, day, startTime, endTime){
    const dayIndex = day;
    if (dayIndex === -1) {
        console.error('Invalid day provided');
        return;
    }

    // 시간 포맷을 변경합니다.
    const [startHour, startMinute] = startTime.split(':');
    const [endHour, endMinute] = endTime.split(':');

    // 추가할 문자열을 생성합니다.
    const newEntry = `${color}+${dayIndex}+${startHour}-${startMinute}+${endHour}-${endMinute}`;

    // hidden input의 기존 값에 '&' 구분자로 추가합니다.
    const weekTimetableInput = document.getElementById('weekTimetable');
    if (weekTimetableInput) {
        weekTimetableInput.value = weekTimetableInput.value ? 
            `${weekTimetableInput.value}&${newEntry}` : newEntry;
    }
}

function addUnavailableDay() {
    //시간 범위를 갖고 있는 두 input 태그 저장.
    const start = document.getElementById('start_datetime');
    const end = document.getElementById('end_datetime');

    if (!start.value || !end.value) {
        alert('시작시간과 종료시간을 모두 입력해 주세요');
        return;
    }

    if (start.value >= end.value) {
        alert("시작 시각 이후의 종료 시각을 선택해 주세요");
        return;
    }

    //추가된 일정을 사용자에게 보여주는 부분 저장. (id: schedule-list)
    const pushPoint = document.getElementById('schedule_list');

    //일정을 문자열로 저장.
    let startDateTime = start.value;
    let endDateTime = end.value;
    //console.log(scheduleText);

    //문자열을 원하는 형식으로 변환.
    let formattedStart = startDateTime.replace(/^(\d{4})-(\d{2})-(\d{2})T(\d{2}):(\d{2})$/, "$1-$2-$3 $4:$5");
    let formattedEnd = endDateTime.replace(/^(\d{4})-(\d{2})-(\d{2})T(\d{2}):(\d{2})$/, "$1-$2-$3 $4:$5");

    //년도를 2자리 형식으로 변환
    formattedStart = formattedStart.replace(/^(\d{2})(\d{2})-(\d{2})-(\d{2}) (\d{2}:\d{2})$/, "$2-$3-$4 $5");
    formattedEnd = formattedEnd.replace(/^(\d{2})(\d{2})-(\d{2})-(\d{2}) (\d{2}:\d{2})$/, "$2-$3-$4 $5");
    
    //변환된 문자열 하나로 합치기.
    let scheduleText = formattedStart + " ~ " + formattedEnd;

    if(scheduleText == " ~ "){
        return;
    }
    if(startDateTime == "" || endDateTime == ""){
        alert("시작 시간과 종료 시간을 모두 입력해 주세요");
        return;
    }


    // 기존 일정을 확인하여 중복을 방지합니다.
    const options = pushPoint.getElementsByTagName('option');
    for (let option of options) {
        if (option.id !== 'selectedOptionOne' && option.text === scheduleText) {
            alert("이미 동일한 일정이 추가되어 있습니다.");
            return;
        }
    }

    //일정 문자열을 갖고 있는 option 태그 생성.
    let newOption = document.createElement('option');
    newOption.text = scheduleText;

    //option 태그 삽입.
    pushPoint.insertBefore(newOption, pushPoint.firstChild);;

    //새로 추가된 option 태그가 자동으로 선택되도록 인덱스 조정.
    pushPoint.selectedIndex = 0;

    //hidden input에 모든 option 태그를 가져와 기록하는 함수.
    writeUnavailableDays();
}


//hidden input에 사용자가 추가한 일정을 기록하는 함수.
function writeUnavailableDays(){
    const pushPoint = document.getElementById('schedule_list');
    const unavailableDaysField = document.getElementById('unavailableDays');

    // 모든 option 태그를 가져옵니다.
    const options = pushPoint.getElementsByTagName('option');
    let schedules = [];

    // 각 option 태그를 확인하여 id가 'selectedOptionOne'이 아닌 경우만 처리합니다.
    for (let option of options) {
        if (option.id !== 'selectedOptionOne' && option.value) {
            schedules.push(option.text);
        }
    }

    // 배열을 '&'로 구분된 문자열로 변환합니다.
    let schedulesText = schedules.join('&');

    // 숨겨진 입력 필드에 값을 설정합니다.
    unavailableDaysField.value = schedulesText;
}

function deleteUnavailableDay(){
    //select 태그 선택.
    let selectElement = document.getElementById('schedule_list');

    //select 태그에서 선택되어 있는 option 태그 선택.
    let selectedOption = selectElement.options[selectElement.selectedIndex];
    if(selectedOption.id == "selectedOptionOne"){
        return;  //최초 옵션은 삭제 차단.
    }

    //select 태그에 선택되어 있는 option태그 삭제.
    selectElement.remove(selectElement.selectedIndex);

    writeUnavailableDays();  //hidden input을 갱신한다.
}

function validate_schedule_list() {
    const scheduleList = document.getElementById('schedule_list');
    if (scheduleList.options.length === 1) {
        // Only the disabled placeholder option is present
        alert('\'일정 추가\'로 이동하여, 하나 이상의 일정을 등록해 주세요');
        return false;
    }
    return true;
}