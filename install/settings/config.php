<?php
/* config.php */
return [
    'version' => '7.0.4',
    'web_title' => 'E-Office',
    'web_description' => 'ระบบจองห้องประชุม จองรถ แจ้งซ่อม งานสารบรรณ',
    'timezone' => 'Asia/Bangkok',
    // สถานะสมาชิกของ E-Office (ข้อมูลตัวอย่างใช้สถานะ 2 บุคลากร และ 3 พนักงานขับรถ)
    'member_status' => [
        0 => 'สมาชิก',
        1 => 'ผู้ดูแลระบบ',
        2 => 'บุคลากร',
        3 => 'พนักงานขับรถ'
    ],
    'color_status' => [
        0 => '#259B24',
        1 => '#FF0000',
        2 => '#0000FF',
        3 => '#FFD600'
    ],
    // บุคลากร = สมาชิกที่มีสถานะเหล่านี้ · บุคลากรที่เพิ่มจากหน้าบุคลากรได้สถานะ teacher_status
    'personnel_status' => [1, 2],
    'teacher_status' => 2,
    // ค่าตั้งต้นของโมดูล — ไซต์ที่ติดตั้งใหม่ต้องมีครบ (ตัวปรับรุ่นเติมให้ไซต์เดิมเอง)
    // การอนุมัติจอง 1 ขั้น โดยผู้ดูแลระบบ (สถานะ 1) — แก้ได้ที่ตั้งค่าของแต่ละโมดูล
    'booking_approve_level' => 1,
    'booking_approve_status' => [1 => 1],
    'booking_approve_department' => [1 => ''],
    'car_approve_level' => 1,
    'car_approve_status' => [1 => 1],
    'car_approve_department' => [1 => ''],
    'repair_first_status' => 1,
    'repair_status_success' => 7,
    'repair_job_no' => 'JOB%04d',
    'repair_prefix' => '',
    'personnel_w' => 500,
    'personnel_h' => 500,
    'inventory_w' => 800
];
