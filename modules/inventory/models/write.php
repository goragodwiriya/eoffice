<?php
/**
 * @filesource modules/inventory/models/write.php
 *
 * @copyright 2016 Goragod.com
 * @license https://www.kotchasan.com/license/
 *
 * @see https://www.kotchasan.com/
 */

namespace Inventory\Write;

use Gcms\Login;
use Kotchasan\Database\Sql;
use Kotchasan\File;
use Kotchasan\Http\Request;
use Kotchasan\Language;

/**
 * module=inventory-write
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
                'id' => $id,
                'stock' => 1,
                'status' => 1,
                'device_user' => '',
                'create_date' => date('Y-m-d'),
                'member_id' => 0
            );
        } else {
            // แก้ไข อ่านรายการที่เลือก
            $q1 = static::createQuery()
                ->select('S.id stock_id', 'U.id user_id', 'S.inventory_id', 'S.product_no', 'S.stock', 'U.member_id', Sql::DATE('U.create_date', 'create_date'))
                ->from('inventory_stock S')
                ->join('inventory_user U', 'LEFT', array('U.stock_id', 'S.id'))
                ->where(array(
                    array('S.inventory_id', $id),
                    Sql::ISNULL('U.return_date')
                ))
                ->order('U.create_date DESC')
                ->limit(1);
            $query = static::createQuery()
                ->from('inventory R')
                ->join(array($q1, 'S'), 'LEFT', array('S.inventory_id', 'R.id'))
                ->join('user U', 'LEFT', array('U.id', 'S.member_id'))
                ->where(array('R.id', $id));
            $select = array(
                'R.id',
                'R.topic',
                'R.detail',
                'R.unit',
                'R.status',
                'S.stock_id',
                'S.product_no',
                'S.stock',
                'S.create_date',
                'S.user_id',
                'U.id member_id',
                'U.name device_user'
            );
            $n = 1;
            foreach (Language::get('INVENTORY_CATEGORIES', []) as $key => $label) {
                $query->join('inventory_meta M'.$n, 'LEFT', array(array('M'.$n.'.inventory_id', 'R.id'), array('M'.$n.'.name', $key)));
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
        // session, token, can_manage_inventory, ไม่ใช่สมาชิกตัวอย่าง
        if ($request->initSession() && $request->isSafe() && $login = Login::isMember()) {
            if (Login::checkPermission($login, 'can_manage_inventory') && Login::notDemoMode($login)) {
                try {
                    // รับค่าจากการ POST
                    $save = array(
                        'topic' => $request->post('topic')->topic(),
                        'detail' => $request->post('detail')->textarea(),
                        'status' => $request->post('status')->toBoolean()
                    );
                    $stock = array(
                        'product_no' => $request->post('product_no')->topic(),
                        'stock' => $request->post('stock')->toDouble()
                    );
                    $user = array(
                        'member_id' => $request->post('member_id')->toInt(),
                        'create_date' => $request->post('create_date')->date()
                    );
                    // ตรวจสอบรายการที่เลือก
                    $index = self::get($request->post('id')->toInt());
                    if ($index) {
                        // Database
                        $db = $this->db();
                        // ตาราง
                        $table_inventory = $this->getTableName('inventory');
                        $table_stock = $this->getTableName('inventory_stock');
                        $table_user = $this->getTableName('inventory_user');
                        $table_meta = $this->getTableName('inventory_meta');
                        if ($stock['product_no'] == '') {
                            // ไม่ได้กรอก product_no
                            $ret['ret_product_no'] = 'Please fill in';
                        } else {
                            // ค้นหา product_no ซ้ำ
                            $search = $db->first($table_stock, array('product_no', $stock['product_no']));
                            if ($search && ($index->stock_id == 0 || $index->stock_id != $search->id)) {
                                $ret['ret_product_no'] = Language::replace('This :name already exist', array(':name' => Language::get('Serial/Registration No.')));
                            }
                        }
                        if ($save['topic'] == '') {
                            // ไม่ได้กรอก topic
                            $ret['ret_topic'] = 'Please fill in';
                        }
                        if ($index->id == 0 && $stock['stock'] == 0) {
                            // ใหม่ ไม่ได้กรอก stock
                            $ret['ret_stock'] = 'Please fill in';
                        }
                        if (empty($ret)) {
                            if ($index->id == 0) {
                                $save['id'] = $db->getNextId($table_inventory);
                            } else {
                                $save['id'] = $index->id;
                            }
                            // อัปโหลดไฟล์
                            $dir = ROOT_PATH.DATA_FOLDER.'inventory/';
                            foreach ($request->getUploadedFiles() as $item => $file) {
                                /* @var $file \Kotchasan\Http\UploadedFile */
                                if ($item == 'picture') {
                                    if ($file->hasUploadFile()) {
                                        if (!File::makeDirectory($dir)) {
                                            // ไดเรคทอรี่ไม่สามารถสร้างได้
                                            $ret['ret_'.$item] = Language::replace('Directory %s cannot be created or is read-only.', DATA_FOLDER.'inventory/');
                                        } else {
                                            try {
                                                $file->resizeImage(self::$cfg->inventory_img_typies, $dir, $save['id'].'.jpg', self::$cfg->inventory_w);
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
                            }
                        }
                        if (empty($ret)) {
                            // หมวดหมู่
                            $meta = [];
                            $category = \Inventory\Category\Model::init(false);
                            foreach (Language::get('INVENTORY_CATEGORIES', []) as $key => $label) {
                                $meta[$key] = $category->save($key, $request->post($key.'_text')->topic());
                            }
                            $save['unit'] = $category->save('unit', $request->post('unit_text')->topic());
                            if ($index->id == 0) {
                                // ใหม่
                                $db->insert($table_inventory, $save);
                            } else {
                                // แก้ไข
                                $db->update($table_inventory, $index->id, $save);
                            }
                            // อัปเดต meta
                            $db->delete($table_meta, array('inventory_id', $save['id']), 0);
                            foreach ($meta as $key => $value) {
                                if ($value != '') {
                                    $db->insert($table_meta, array(
                                        'inventory_id' => $save['id'],
                                        'name' => $key,
                                        'value' => $value
                                    ));
                                }
                            }
                            // stock
                            if (empty($index->stock_id)) {
                                $stock['inventory_id'] = $save['id'];
                                $stock['create_date'] = date('Y-m-d H:i:s');
                                $index->stock_id = $db->insert($table_stock, $stock);
                            } else {
                                $db->update($table_stock, $index->stock_id, $stock);
                            }
                            // user
                            if ($user['member_id'] > 0) {
                                if (empty($index->user_id)) {
                                    $user['stock_id'] = $index->stock_id;
                                    $index->stock_id = $db->insert($table_user, $user);
                                } else {
                                    $db->update($table_user, $index->user_id, $user);
                                }
                            } elseif (!empty($index->user_id)) {
                                // ลบรายการ
                                $db->delete($table_user, $index->user_id, 0);
                            }
                            // log
                            \Index\Log\Model::add($save['id'], 'inventory', 'Save', '{LNG_Equipment} ID : '.$save['id'], $login['id']);
                            // คืนค่า
                            $ret['alert'] = Language::get('Saved successfully');
                            $ret['location'] = $request->getUri()->postBack('index.php', array('module' => 'inventory-setup'));
                            // เคลียร์
                            $request->removeToken();
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
