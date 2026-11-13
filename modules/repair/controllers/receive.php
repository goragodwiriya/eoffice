<?php
/**
 * @filesource modules/repair/controllers/receive.php
 *
 * @copyright 2026 Goragod.com
 * @license https://www.kotchasan.com/license/
 *
 * @see https://www.kotchasan.com/
 */

namespace Repair\Receive;

use Gcms\Api as ApiController;
use Kotchasan\Http\Request;

/**
 * API ฟอร์มแจ้งซ่อม
 *
 * @author Goragod Wiriya <admin@goragod.com>
 *
 * @since 1.0
 */
class Controller extends ApiController
{
    /**
     * GET api/repair/receive/get
     * อ่านข้อมูลใบแจ้งซ่อมสำหรับฟอร์ม
     *
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

            $index = Model::get($request->get('id', 0)->toInt());
            if ($index === null) {
                return $this->redirectResponse('/404', 'No data available', 404);
            }

            // ใหม่, ของตัวเอง หรือ ผู้จัดการงานซ่อม
            if ($index->id > 0
                && (int) $index->customer_id !== (int) $login->id
                && !ApiController::hasPermission($login, 'can_manage_repair')
            ) {
                return $this->errorResponse('Permission required', 403);
            }

            // ไฟล์แนบของใบแจ้งซ่อม (datas/repair/<id>/ ที่เดียวกับระบบเดิม)
            $index->repair = $index->id > 0 ? \Download\Index\Controller::getAttachments($index->id, 'repair', self::fileTypies()) : [];
            $index->file_comment = \Kotchasan\Language::replace('Upload :type files', [':type' => implode(', ', self::fileTypies())]);
            $index->accept = '.'.implode(',.', self::fileTypies());

            return $this->successResponse([
                'data' => $index
            ], 'Repair details retrieved');
        } catch (\Exception $e) {
            return $this->errorResponse(self::exceptionMessage($e), $e->getCode() ?: 500, $e);
        }
    }

    /**
     * POST api/repair/receive/save
     * บันทึกใบแจ้งซ่อม
     *
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
            if (!ApiController::isNotDemoMode($login)) {
                return $this->errorResponse('Unable to complete the transaction', 403);
            }

            $id = $request->post('id', 0)->toInt();
            $product_no = $request->post('product_no')->topic();

            $errors = [];
            if ($product_no === '') {
                $errors['product_no'] = 'Please select from the search results';
            }

            $repair = [
                'job_description' => $request->post('job_description')->textarea(),
                'appraiser' => 0
            ];
            if ($repair['job_description'] === '') {
                $errors['job_description'] = 'Please fill in';
            }

            if (!empty($errors)) {
                return $this->formErrorResponse($errors, 400);
            }

            $canManage = ApiController::hasPermission($login, 'can_manage_repair');

            if ($id === 0) {
                // ใหม่ ตรวจสอบเลขครุภัณฑ์
                $product = Model::findProduct($product_no, (int) $login->id);
                if (!$product) {
                    return $this->formErrorResponse(['product_no' => 'Please select from the search results'], 400);
                }

                $repair['product_no'] = $product->product_no;
                $id = Model::createJob($repair, $request->post('comment')->topic(), (int) $login->id);

                // ไฟล์แนบ
                $ret = [];
                \Download\Upload\Model::execute($ret, $request, $id, 'repair', self::fileTypies());

                // แจ้งเตือนผู้ที่เกี่ยวข้อง
                $message = \Repair\Email\Model::send($id);
                if (!empty($ret['repair'])) {
                    $message = $ret['repair'];
                }

                \Index\Log\Model::add($id, 'repair', 'Save', 'New repair job ID : '.$id, $login->id);

                return $this->redirectResponse('/repair-history', $message, 200, 1000);
            }

            // แก้ไข
            $index = Model::get($id);
            if ($index === null) {
                return $this->errorResponse('No data available', 404);
            }
            if ((int) $index->customer_id !== (int) $login->id && !$canManage) {
                return $this->errorResponse('Permission required', 403);
            }

            $repair['product_no'] = $index->product_no;
            Model::updateJob($id, $repair);

            // ไฟล์แนบเพิ่มเติม
            $ret = [];
            \Download\Upload\Model::execute($ret, $request, $id, 'repair', self::fileTypies());
            if (!empty($ret['repair'])) {
                return $this->errorResponse($ret['repair'], 400);
            }

            \Index\Log\Model::add($id, 'repair', 'Save', 'Edit repair job ID : '.$id, $login->id);

            return $this->redirectResponse($canManage ? '/repair-jobs' : '/repair-history', 'Saved successfully', 200, 1000);
        } catch (\Exception $e) {
            return $this->errorResponse(self::exceptionMessage($e), $e->getCode() ?: 500, $e);
        }
    }

    /**
     * POST api/repair/receive/remove-file
     * ลบไฟล์แนบของใบแจ้งซ่อม — เจ้าหน้าที่ลบได้เสมอ ผู้แจ้งลบได้เฉพาะตอนที่งานยังอยู่สถานะแรก (แบบระบบเดิม)
     *
     * @param Request $request
     *
     * @return \Kotchasan\Http\Response
     */
    public function removeFile(Request $request)
    {
        try {
            ApiController::validateMethod($request, 'POST');
            $this->validateCsrfToken($request);

            $login = $this->authenticateRequest($request);
            if (!$login) {
                return $this->redirectResponse('/login', 'Unauthorized', 401);
            }
            if (!ApiController::isNotDemoMode($login)) {
                return $this->errorResponse('Unable to complete the transaction', 403);
            }

            $json = json_decode($request->post('id')->toString());
            // ชื่อไฟล์มาจากผู้ใช้ — รับเฉพาะ "ชื่อ.นามสกุล" กันการส่ง ../ ไปลบไฟล์อื่น
            if (!$json || !isset($json->id, $json->file) || !preg_match('/^[a-z0-9]+\.[a-z0-9]+$/', (string) $json->file)) {
                return $this->errorResponse('No data available', 404);
            }
            $index = \Repair\Detail\Model::get((int) $json->id);
            if ($index === null) {
                return $this->errorResponse('No data available', 404);
            }
            $isStaff = ApiController::hasPermission($login, ['can_manage_repair', 'can_repair']);
            $isOwner = (int) $index->customer_id === (int) $login->id
                && (int) $index->status === (int) self::$cfg->repair_first_status;
            if (!$isStaff && !$isOwner) {
                return $this->errorResponse('Permission required', 403);
            }

            $file = ROOT_PATH.DATA_FOLDER.'repair/'.(int) $index->id.'/'.$json->file;
            if (!is_file($file)) {
                return $this->errorResponse('No data available', 404);
            }
            @unlink($file);

            \Index\Log\Model::add((int) $index->id, 'repair', 'Delete', 'Removed repair attachment: '.$json->file, $login->id);

            return $this->successResponse([], 'Attachment removed successfully');
        } catch (\Exception $e) {
            return $this->errorResponse(self::exceptionMessage($e), $e->getCode() ?: 500, $e);
        }
    }

    /**
     * ชนิดไฟล์แนบของใบแจ้งซ่อม (ค่าเดียวกับระบบเดิม repair_file_typies)
     *
     * @return array
     */
    public static function fileTypies()
    {
        $typies = self::$cfg->repair_file_typies ?? ['jpg', 'jpeg', 'png', 'webp', 'pdf'];

        return empty($typies) ? ['jpg', 'jpeg', 'png', 'webp', 'pdf'] : (array) $typies;
    }
}
