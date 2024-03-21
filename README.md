# ระบบ E-Office

เป็นระบบที่รวมหลายๆโมดูลที่แจกเข้าด้วยกัน ได้แก่ E-Booking, Inventory & Repair, E-Document และ Personnel โดยมีการปรับปรุงโปรแกรมตามที่มีหลายคนขอมา ซึ่งมีการทำงาน ไม่เหมือนกับตัวที่แจกแยก เหมาะสำหรับ หน่วยงานราชการ ทั่วไป

ออกแบบโดยใช้ คชสาร เฟรมเวอร์ค (PHP) รองรับ 3 ภาษา ไทย อังกฤษ และ ลาว สามารถถอดโมดูลที่ไม่ต้องการออกได้

รายละเอียดเพิ่มเติม https://goragod.com/index.php?module=knowledge&id=3878

## ความต้องการของระบบ

- PHP 5.6 ขึ้นไป
- ext-mbstring
- PDO Mysql

## การติดตั้งและการอัปเกรด

1.  ให้อัปโหลดโค้ดทั้งหมดจากที่ดาวน์โหลด ขึ้นไปบน Server
2.  เรียกตัวติดตั้ง http://domain.tld/install/ (เปลี่ยน domain.tld เป็นโดเมนรวมพาธที่ทำการติดตั้งไว้) และดำเนินการตามขั้นตอนการติดตั้งหรืออัปเกรดจนกว่าจะเสร็จสิ้น
3.  ลบไดเร็คทอรี่ install/ ออก

## การใช้งาน

- เข้าระบบเป็นผู้ดูแลระบบ : `admin@localhost` และ Password : `admin`
- เข้าระบบเป็นสมาชิก : `demo@localhost` และ Password : `demo`

## ข้อตกลงการนำไปใช้งาน

- สามารถนำไปศึกษาและทดสอบได้ฟรี
- สามารถพัฒนาต่อยอดได้
- การนำไปใช้งานจริง ไม่ว่าจะพัฒนาเพิ่มเติมหรือไม่ ต้องชำระค่านำไปใช้ 1,000 บาท (หนึ่งพันบาทถ้วน)
- รับพัฒนาเพิ่มเติม ให้ตรงกับความต้องการ (มีค่าใช้จ่าย)
- แจ้งปัญหา หรือ มีข้อสงสัยสามารถสอบถามได้ที่บอร์ดของคชสาร https://www.kotchasan.com
- ผู้เขียนไม่รับผิดชอบข้อผิดพลาดใดๆในการใช้งาน
- ```ห้ามขาย``` ถ้าต้องการนำไปพัฒนาต่อเพื่อขายให้ติดต่อผู้เขียนก่อน (เพื่อบริจาค)

## สามารถสนับสนุนผู้เขียนได้ที่

```
ธนาคาร กสิกรไทย สาขากาญจนบุรี
เลขที่บัญชี 221-2-78341-5
ชื่อบัญชี กรกฎ วิริยะ
```