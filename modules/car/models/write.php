<?php
/**
 * @filesource modules/car/models/write.php
 *
 * @copyright 2016 Goragod.com
 * @license https://www.kotchasan.com/license/
 *
 * @see https://www.kotchasan.com/
 */

namespace Car\Write;

use Gcms\Login;
use Kotchasan\File;
use Kotchasan\Http\Request;
use Kotchasan\Language;

/**
 * module=car-write
 *
 * @author Goragod Wiriya <admin@goragod.com>
 *
 * @since 1.0
 */
class Model extends \Kotchasan\Model
{
    /**
     * อ่านข้อมูลรายการที่เลือก
     * ถ้า $id = 0 หมายถึงรายการใหม่
     * คืนค่าข้อมูล object ไม่พบคืนค่า null
     *
     * @param int $id ID
     *
     * @return object|null
     */
    public static function get($id)
    {
        if (empty($id)) {
            // ใหม่
            return (object) array(
                'id' => 0
            );
        } else {
            // แก้ไข อ่านรายการที่เลือก
            $query = static::createQuery()
                ->from('vehicles R')
                ->where(array('R.id', $id));
            $select = array('R.*');
            $n = 1;
            $metas = Language::get('CAR_SELECT', []);
            foreach ($metas as $key => $label) {
                $query->join('vehicles_meta M'.$n, 'LEFT', array(array('M'.$n.'.vehicle_id', 'R.id'), array('M'.$n.'.name', $key)));
                $select[] = 'M'.$n.'.value '.$key;
                ++$n;
            }
            return $query->first($select);
        }
    }

    /**
     * บันทึกข้อมูลที่ส่งมาจากฟอร์ม (write.php)
     *
     * @param Request $request
     */
    public function submit(Request $request)
    {
        $ret = [];
        // session, token, can_manage_car, ไม่ใช่สมาชิกตัวอย่าง
        if ($request->initSession() && $request->isSafe() && $login = Login::isMember()) {
            if (Login::notDemoMode($login) && Login::checkPermission($login, 'can_manage_car')) {
                try {
                    // ค่าที่ส่งมา
                    $save = array(
                        'number' => $request->post('number')->topic(),
                        'seats' => $request->post('seats')->toInt(),
                        'color' => $request->post('color')->filter('\#A-Z0-9'),
                        'detail' => $request->post('detail')->textarea()
                    );
                    $id = $request->post('id')->toInt();
                    // ตรวจสอบรายการที่เลือก
                    $index = self::get($id);
                    if ($index) {
                        if ($save['number'] == '') {
                            // ไม่ได้กรอก number
                            $ret['ret_number'] = 'Please fill in';
                        } else {
                            $metas = [];
                            foreach (Language::get('CAR_SELECT', []) as $key => $label) {
                                $metas[$key] = \Car\Category\Model::save($key, $request->post($key.'_text')->topic());
                            }
                            // Database
                            $db = $this->db();
                            // table
                            $table = $this->getTableName('vehicles');
                            if ($index->id == 0) {
                                $save['id'] = $db->getNextId($table);
                            } else {
                                $save['id'] = $index->id;
                            }
                            // ไดเร็คทอรี่เก็บไฟล์
                            $dir = ROOT_PATH.DATA_FOLDER.'car/';
                            // อัปโหลดไฟล์
                            foreach ($request->getUploadedFiles() as $item => $file) {
                                /* @var $file \Kotchasan\Http\UploadedFile */
                                if ($file->hasUploadFile()) {
                                    if (!File::makeDirectory($dir)) {
                                        // ไดเรคทอรี่ไม่สามารถสร้างได้
                                        $ret['ret_'.$item] = Language::replace('Directory %s cannot be created or is read-only.', DATA_FOLDER.'car/');
                                    } elseif (!$file->validFileExt(self::$cfg->car_img_typies)) {
                                        // ชนิดของไฟล์ไม่ถูกต้อง
                                        $ret['ret_'.$item] = Language::get('The type of file is invalid');
                                    } elseif ($item == 'picture') {
                                        try {
                                            $file->resizeImage(self::$cfg->car_img_typies, $dir, $save['id'].'.jpg', self::$cfg->car_w);
                                        } catch (\Exception $exc) {
                                            // ไม่สามารถอัปโหลดได้
                                            $ret['ret_'.$item] = Language::get($exc->getMessage());
                                        }
                                    }
                                } elseif ($file->hasError()) {
                                    // ข้อผิดพลาดการอัปโหลด
                                    $ret['ret_'.$item] = Language::get($file->getErrorMessage());
                                }
                            }
                            if (empty($ret)) {
                                if ($index->id == 0) {
                                    // ใหม่
                                    $db->insert($table, $save);
                                } else {
                                    // แก้ไข
                                    $db->update($table, $save['id'], $save);
                                }
                                // log
                                \Index\Log\Model::add($save['id'], 'car', 'Save', '{LNG_Vehicle} ID : '.$save['id'], $login['id'], $save);
                                // อัปเดต meta
                                $meta_table = $this->getTableName('vehicles_meta');
                                $db->delete($meta_table, array('vehicle_id', $save['id']), 0);
                                foreach ($metas as $key => $value) {
                                    if ($value > 0) {
                                        $db->insert($meta_table, array(
                                            'vehicle_id' => $save['id'],
                                            'name' => $key,
                                            'value' => $value
                                        ));
                                    }
                                }
                                // คืนค่า
                                $ret['alert'] = Language::get('Saved successfully');
                                $ret['location'] = $request->getUri()->postBack('index.php', array('module' => 'car-setup'));
                                // เคลียร์
                                $request->removeToken();
                            }
                        }
                    }
                } catch (\Kotchasan\InputItemException $e) {
                    $ret['alert'] = $e->getMessage();
                }
            }
        }
        if (empty($ret)) {
            $ret['alert'] = Language::get('Unable to complete the transaction');
        }
        // คืนค่าเป็น JSON
        echo json_encode($ret);
    }
}
