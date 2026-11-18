<?php
/**
 * @filesource modules/repair/controllers/autocomplete.php
 *
 * @copyright 2026 Goragod.com
 * @license https://www.kotchasan.com/license/
 *
 * @see https://www.kotchasan.com/
 */

namespace Repair\Autocomplete;

use Gcms\Api as ApiController;
use Kotchasan\Http\Request;

/**
 * API ค้นหาพัสดุสำหรับฟอร์มแจ้งซ่อม
 *
 * @author Goragod Wiriya <admin@goragod.com>
 *
 * @since 1.0
 */
class Controller extends ApiController
{
    /**
     * GET api/repair/autocomplete/find
     * ค้นหาพัสดุที่ยังใช้งานอยู่จากชื่อพัสดุหรือเลขครุภัณฑ์
     *
     * @param Request $request
     *
     * @return \Kotchasan\Http\Response
     */
    public function find(Request $request)
    {
        try {
            ApiController::validateMethod($request, 'GET');

            $login = $this->authenticateRequest($request);
            if (!$login) {
                return $this->errorResponse('Unauthorized', 401);
            }

            $search = $request->get('q')->topic();
            if ($search === '') {
                $search = $request->get('search')->topic();
            }

            $limit = min(50, max(1, $request->get('count', 20)->toInt()));

            // Autocomplete ฝั่ง Now.js อ่านรายการจาก response.data โดยตรง
            return $this->successResponse(Model::find($search, $limit, (int) $login->id), 'Search completed');
        } catch (\Exception $e) {
            return $this->errorResponse(self::exceptionMessage($e), $e->getCode() ?: 500, $e);
        }
    }
}
