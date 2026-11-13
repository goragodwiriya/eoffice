<?php
/**
 * @filesource modules/repair/controllers/detail.php
 *
 * @copyright 2026 Goragod.com
 * @license https://www.kotchasan.com/license/
 *
 * @see https://www.kotchasan.com/
 */

namespace Repair\Detail;

use Gcms\Api as ApiController;
use Kotchasan\Http\Request;

/**
 * API รายละเอียดงานซ่อมและประวัติการดำเนินการ
 *
 * @author Goragod Wiriya <admin@goragod.com>
 *
 * @since 1.0
 */
class Controller extends ApiController
{
    /**
     * GET api/repair/detail/get
     * อ่านรายละเอียดงานซ่อม
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

            // ผู้แจ้งซ่อม หรือ เจ้าหน้าที่
            $isStaff = ApiController::hasPermission($login, ['can_manage_repair', 'can_repair']);
            if ((int) $index->customer_id !== (int) $login->id && !$isStaff) {
                return $this->errorResponse('Permission required', 403);
            }

            $statuses = \Repair\Status\Model::map(false);
            $timeline = [];
            foreach (Model::getAllStatus($index->id) as $item) {
                $timeline[] = [
                    'id' => $item['id'],
                    'name' => $item['name'],
                    'status' => $item['status'],
                    'status_text' => isset($statuses[$item['status']]) ? $statuses[$item['status']]['topic'] : '',
                    'status_color' => isset($statuses[$item['status']]) ? $statuses[$item['status']]['color'] : '',
                    'created_at' => $item['created_at'],
                    'comment' => \Kotchasan\Text::untextarea($item['comment']),
                    'cost' => $item['cost']
                ];
            }

            $data = (object) [
                'id' => $index->id,
                'job_id' => $index->job_id,
                'name' => $index->name,
                'phone' => $index->phone,
                'topic' => $index->topic,
                'product_no' => $index->product_no,
                'barcode' => self::barcode($index->product_no),
                'job_description' => $index->job_description,
                'created_at' => $index->created_at,
                'status' => $index->status,
                'status_text' => isset($statuses[$index->status]) ? $statuses[$index->status]['topic'] : '',
                'status_color' => isset($statuses[$index->status]) ? $statuses[$index->status]['color'] : '',
                'comment' => \Kotchasan\Text::untextarea($index->comment),
                'can_manage' => $isStaff ? 1 : 0,
                // ไฟล์แนบของผู้แจ้ง และลิงก์พิมพ์ใบรับซ่อม
                'files' => \Download\Index\Controller::getAttachments($index->id, 'repair', \Repair\Receive\Controller::fileTypies()),
                'print_url' => WEB_URL.'export.php?module=repair&typ=receipt&id='.(int) $index->id,
                'timeline' => [
                    'data' => $timeline
                ]
            ];

            return $this->successResponse([
                'data' => $data
            ], 'Repair details retrieved');
        } catch (\Exception $e) {
            return $this->errorResponse(self::exceptionMessage($e), $e->getCode() ?: 500, $e);
        }
    }

    /**
     * POST api/repair/detail/action
     * ลบประวัติการดำเนินการที่เลือก
     *
     * @param Request $request
     *
     * @return \Kotchasan\Http\Response
     */
    public function action(Request $request)
    {
        try {
            ApiController::validateMethod($request, 'POST');
            $this->validateCsrfToken($request);

            $login = $this->authenticateRequest($request);
            if (!$login) {
                return $this->redirectResponse('/login', 'Unauthorized', 401);
            }
            if (!ApiController::canModify($login, ['can_manage_repair', 'can_repair'])) {
                return $this->errorResponse('Permission required', 403);
            }

            if ($request->request('action')->filter('a-z_') !== 'delete') {
                return $this->errorResponse('Invalid action', 400);
            }

            $ids = $request->request('ids', [])->toInt();
            if (empty($ids)) {
                $ids = [$request->post('id')->toInt()];
            }

            $count = Model::removeStatus($ids);
            if (empty($count)) {
                return $this->errorResponse('Delete action failed', 400);
            }

            \Index\Log\Model::add(0, 'repair', 'Delete', 'Delete repair status ID(s) : '.implode(', ', $ids), $login->id);

            return $this->redirectResponse('reload', 'Deleted '.$count.' item(s) successfully');
        } catch (\Exception $e) {
            return $this->errorResponse(self::exceptionMessage($e), $e->getCode() ?: 500, $e);
        }
    }

    /**
     * สร้างบาร์โค้ดของเลขครุภัณฑ์เป็น data URI
     *
     * @param string $product_no
     *
     * @return string
     */
    protected static function barcode($product_no)
    {
        if (empty($product_no)) {
            return '';
        }

        return 'data:image/png;base64,'.base64_encode(\Kotchasan\Barcode::create($product_no, 40)->toPng());
    }
}
