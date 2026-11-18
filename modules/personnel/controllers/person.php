<?php
/**
 * @filesource modules/personnel/controllers/person.php
 *
 * @copyright 2026 Goragod.com
 * @license https://www.kotchasan.com/license/
 *
 * @see https://www.kotchasan.com/
 */

namespace Personnel\Person;

use Gcms\Api as ApiController;
use Kotchasan\File;
use Kotchasan\Http\Request;
use Kotchasan\Language;

/**
 * api/personnel/person/get|save
 * ฟอร์มเพิ่ม/แก้ไขบุคลากร — เดิมคือ module=personnel-write
 * ผู้มีสิทธิ์ can_manage_personnel แก้ไขได้ทุกคน คนอื่นแก้ไขได้เฉพาะของตัวเอง
 *
 * @author Goragod Wiriya <admin@goragod.com>
 *
 * @since 1.0
 */
class Controller extends ApiController
{
    /**
     * @param Request $request
     *
     * @return \Kotchasan\Http\Response
     */
    public function get(Request $request)
    {
        try {
            ApiController::validateMethod($request, 'GET');
            $login = $this->authenticateRequest($request);
            if (!$login) {
                return $this->errorResponse('Unauthorized', 401);
            }
            $index = Model::get($request->get('id')->toInt());
            if (!$index) {
                return $this->redirectResponse('/404', 'Sorry, Item not found It&#39;s may be deleted', 404);
            }
            if (!self::canEdit($login, $index->id)) {
                return $this->errorResponse('Permission required', 403);
            }
            $isAdmin = self::canSetPermission($login, $index->id);
            $category = \Personnel\Category\Model::init();

            $details = [];
            foreach (Model::details() as $key => $label) {
                $details[] = [
                    'name' => 'custom['.$key.']',
                    'label' => $label,
                    'value' => isset($index->custom[$key]) ? (string) $index->custom[$key] : ''
                ];
            }
            $picture = Model::picture($index->id);
            $data = [
                'id' => (int) $index->id,
                'name' => $index->name,
                // รหัสพนักงานส่งเฉพาะผู้จัดการบุคลากร (ไม่แสดงสาธารณะ)
                'can_manage' => ApiController::hasPermission($login, 'can_manage_personnel') ? 1 : 0,
                'employee_code' => ApiController::hasPermission($login, 'can_manage_personnel') ? (string) $index->employee_code : '',
                'phone' => (string) $index->phone,
                'order' => (int) $index->order,
                'details' => $details,
                'picture' => [[
                    'url' => $picture ?? WEB_URL.'images/no-image.webp',
                    'name' => $picture ? $index->id.self::$cfg->stored_img_type : 'Choose file'
                ]],
                'picture_comment' => Language::replace('Browse image uploaded, type :type size :width*:height pixel (automatic resize)', [
                    ':type' => implode(', ', self::$cfg->member_img_typies),
                    ':width' => self::$cfg->personnel_w,
                    ':height' => self::$cfg->personnel_h
                ]),
                'isAdmin' => $isAdmin,
                'permission' => $isAdmin ? \Index\Auth\Model::parsePermission($index->permission) : []
            ];
            $options = [];
            // ตำแหน่ง แผนก (หมวดหมู่ตามคีย์ภาษา PERSONNEL_CATEGORIES)
            foreach ($category->typies() as $type) {
                $data[$type] = (string) (int) $index->$type;
                $options[$type] = array_merge([['value' => '0', 'text' => Language::get('Please select')]], $category->toOptions($type));
            }
            if ($isAdmin) {
                $options['permission'] = \Gcms\Controller::getPermissionOptions($login);
            }

            return $this->successResponse([
                'data' => $data,
                'options' => $options
            ], 'OK');
        } catch (\Exception $e) {
            return $this->errorResponse(self::exceptionMessage($e), $e->getCode() ?: 500, $e);
        }
    }

    /**
     * @param Request $request
     *
     * @return \Kotchasan\Http\Response
     */
    public function save(Request $request)
    {
        try {
            ApiController::validateMethod($request, 'POST');
            $this->validateCsrfToken($request);
            $login = $this->authenticateRequest($request);
            if (!$login) {
                return $this->redirectResponse('/login', 'Unauthorized', 401);
            }
            $index = Model::get($request->post('id')->toInt());
            if (!$index) {
                return $this->errorResponse('Sorry, Item not found It&#39;s may be deleted', 404);
            }
            if (!self::canEdit($login, $index->id) || !ApiController::isNotDemoMode($login) || (int) $login->active !== 1) {
                return $this->errorResponse('Permission required', 403);
            }
            $canManage = ApiController::hasPermission($login, 'can_manage_personnel');

            $user = [
                'name' => $request->post('name')->topic(),
                'phone' => \Personnel\Account\Model::digits($request->post('phone')->topic())
            ];
            $personnel = [
                'order' => max(0, min(255, $request->post('order')->toInt()))
            ];
            // รหัสพนักงาน — ไม่แสดงสาธารณะ ผู้จัดการบุคลากรเท่านั้นที่เห็นและแก้ได้
            if ($canManage) {
                $personnel['employee_code'] = Model::normalizeEmployeeCode($request->post('employee_code')->topic());
            }
            foreach (array_keys(\Personnel\Category\Model::items()) as $type) {
                $personnel[$type] = $request->post($type)->toInt();
            }
            $postedCustom = $request->post('custom', [])->topic();
            $custom = [];
            foreach (array_keys(Model::details()) as $key) {
                $custom[$key] = is_array($postedCustom) && isset($postedCustom[$key]) ? $postedCustom[$key] : '';
            }

            $errors = [];
            if ($user['name'] === '') {
                $errors['name'] = 'Please fill in';
            }
            if (isset($personnel['employee_code']) && Model::employeeCodeExists($index->id, $personnel['employee_code'])) {
                $errors['employee_code'] = Language::replace('This :name already exist', [':name' => Language::get('Employee ID')]);
            }
            if (!\Personnel\Account\Model::isUnique('phone', $user['phone'], $index->id)) {
                $errors['phone'] = Language::replace('This :name already exist', [':name' => Language::get('Phone')]);
            }
            // ไฟล์รูป ตรวจก่อนบันทึก
            $picture = null;
            foreach ($request->getUploadedFiles() as $item => $file) {
                if ($item !== 'picture') {
                    continue;
                }
                /** @var \Kotchasan\Http\UploadedFile $file */
                if ($file->hasUploadFile()) {
                    if (!$file->validFileExt(self::$cfg->member_img_typies)) {
                        $errors['picture'] = 'The type of file is invalid';
                    } else {
                        $picture = $file;
                    }
                } elseif ($file->hasError()) {
                    $errors['picture'] = $file->getErrorMessage();
                }
            }
            if (!empty($errors)) {
                return $this->formErrorResponse($errors, 400);
            }

            $db = \Kotchasan\DB::create();
            $personnel['custom'] = json_encode($custom, JSON_UNESCAPED_UNICODE);
            if (self::canSetPermission($login, $index->id)) {
                $permission = $request->post('permission', [])->filter('a-z0-9_');
                $user['permission'] = empty($permission) ? '' : ','.implode(',', (array) $permission).',';
            }
            if ($index->id == 0) {
                // บุคลากรใหม่ได้สถานะครู-อาจารย์
                $user['status'] = (int) self::$cfg->teacher_status;
                // ไม่สร้างชื่อผู้ใช้/รหัสผ่านจากข้อมูลส่วนตัว — ผู้ดูแลตั้งการเข้าระบบเองที่หน้าสมาชิก
                $personnel['id'] = \Personnel\Account\Model::create($db, $user);
                $db->insert('personnel', $personnel);
            } else {
                $personnel['id'] = (int) $index->id;
                \Personnel\Account\Model::update($db, $index->id, $user);
                $db->update('personnel', [['id', (int) $index->id]], $personnel);
            }
            if ($picture) {
                $dir = ROOT_PATH.DATA_FOLDER.'personnel/';
                if (!File::makeDirectory($dir)) {
                    return $this->formErrorResponse(['picture' => Language::replace('Directory %s cannot be created or is read-only.', DATA_FOLDER.'personnel/')], 400);
                }
                try {
                    $picture->cropImage(self::$cfg->member_img_typies, $dir.$personnel['id'].self::$cfg->stored_img_type, self::$cfg->personnel_w, self::$cfg->personnel_h);
                } catch (\Exception $exc) {
                    return $this->formErrorResponse(['picture' => Language::get(self::exceptionMessage($exc))], 400);
                }
            }
            if (isset($personnel['department'])) {
                Model::syncDepartment($personnel['id'], $personnel['department']);
            }
            \Index\Log\Model::add($personnel['id'], 'personnel', 'Save', '{LNG_'.($index->id == 0 ? 'Add' : 'Edit').'} {LNG_Personnel} ID : '.$personnel['id'], $login->id);

            // กลับไปหน้ารายการที่มาจาก
            $back = $canManage ? '/personnel-setup' : '/personnel';

            return $this->redirectResponse($back, 'Saved successfully', 200, 1000);
        } catch (\Exception $e) {
            return $this->errorResponse(self::exceptionMessage($e), $e->getCode() ?: 500, $e);
        }
    }

    /**
     * ผู้มีสิทธิ์จัดการบุคลากรแก้ไขได้ทุกคน คนอื่นแก้ไขได้เฉพาะของตัวเอง
     * บัญชี id 1 แก้ไขได้โดยตัวเองเท่านั้น (กฎเดียวกับหน้าสมาชิกของแกน)
     *
     * @param object $login
     * @param int $id
     *
     * @return bool
     */
    public static function canEdit($login, $id)
    {
        $id = (int) $id;
        if ($id === (int) $login->id) {
            return true;
        }

        return $id !== 1 && ApiController::hasPermission($login, 'can_manage_personnel');
    }

    /**
     * กำหนดสิทธิ์การใช้งานได้เฉพาะผู้ดูแลระบบ (กฎเดียวกับฟอร์มสมาชิกของแกน)
     *
     * @param object $login
     * @param int $id
     *
     * @return bool
     */
    public static function canSetPermission($login, $id)
    {
        return \Index\Users\Model::canManage($login) && \Index\Users\Model::canEdit($login, $id);
    }
}
