<?php
/**
 * @filesource modules/repair/controllers/statuses.php
 *
 * @copyright 2026 Goragod.com
 * @license https://www.kotchasan.com/license/
 *
 * @see https://www.kotchasan.com/
 */

namespace Repair\Statuses;

use Gcms\Api as ApiController;
use Kotchasan\Http\Request;
use Kotchasan\Text;

/**
 * API จัดการสถานะการซ่อม (ชื่อ สี และการเปิดใช้งาน)
 *
 * @author Goragod Wiriya <admin@goragod.com>
 *
 * @since 1.0
 */
class Controller extends ApiController
{
    /**
     * GET api/repair/statuses/get
     * อ่านสถานะการซ่อมทั้งหมด
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
            if (!ApiController::hasPermission($login, 'can_config')) {
                return $this->errorResponse('Permission required', 403);
            }

            $datas = \Repair\Status\Model::all(false);
            if (empty($datas)) {
                $datas = [
                    [
                        'category_id' => 1,
                        'topic' => '',
                        'color' => '#000000',
                        'is_active' => 1
                    ]
                ];
            }

            return $this->successResponse([
                'data' => [
                    'options' => [
                        'data' => $datas
                    ]
                ]
            ], 'Repair statuses retrieved');
        } catch (\Exception $e) {
            return $this->errorResponse(self::exceptionMessage($e), $e->getCode() ?: 500, $e);
        }
    }

    /**
     * POST api/repair/statuses/save
     * บันทึกสถานะการซ่อมทั้งหมด
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
            if (!ApiController::canModify($login, ['can_config'])) {
                return $this->errorResponse('Permission required', 403);
            }

            $ids = $request->post('category_id', [])->topic();
            $topics = $request->post('topic', [])->topic();
            $colors = $request->post('color', [])->topic();
            $actives = $request->post('is_active', [])->toBoolean();
            $tables = $request->post('_editable_tables', [])->topic();
            $table = $tables[0] ?? '';

            $errors = [];
            $items = [];
            foreach ($ids as $key => $id) {
                $category_id = Text::topic($id);
                if ($category_id === '') {
                    continue;
                }

                if (isset($items[$category_id])) {
                    $errors[$table.'_category_id_'.$key] = 'Category ID '.$category_id.' already exists';
                    continue;
                }

                if ($topics[$key] === '') {
                    $errors[$table.'_topic_'.$key] = 'Please fill in';
                    continue;
                }

                $items[$category_id] = [
                    'category_id' => $category_id,
                    'topic' => $topics[$key],
                    'color' => isset($colors[$key]) ? $colors[$key] : '',
                    'is_active' => $actives[$key]
                ];
            }

            if (!empty($errors)) {
                return $this->formErrorResponse($errors, 400);
            }

            if (empty($items)) {
                return $this->errorResponse('Please fill in', 400);
            }

            \Repair\Status\Model::save($items);

            \Index\Log\Model::add(0, 'repair', 'Save', 'Repair statuses saved ('.count($items).' rows)', $login->id);

            return $this->redirectResponse('reload', 'Saved successfully');
        } catch (\Exception $e) {
            return $this->errorResponse(self::exceptionMessage($e), $e->getCode() ?: 500, $e);
        }
    }
}
