<?php
/**
 * @filesource modules/telegramgroup/controllers/group.php
 *
 * @copyright 2026 Goragod.com
 * @license https://www.kotchasan.com/license/
 *
 * @see https://www.kotchasan.com/
 */

namespace Telegramgroup\Group;

use Gcms\Api as ApiController;
use Kotchasan\Http\Request;
use Kotchasan\Language;

/**
 * GET api/telegramgroup/group/get · POST api/telegramgroup/group/save
 * เพิ่ม/แก้ไขกลุ่ม Telegram (ระบบเดิม modal ของ module=lines)
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
            if (!ApiController::hasPermission($login, 'can_config')) {
                return $this->errorResponse('Permission required', 403);
            }
            $group = Model::get($request->get('id', 0)->toInt());
            if ($group === null) {
                return $this->errorResponse('No data available', 404);
            }

            return $this->successResponse([
                'data' => $group,
                'options' => self::formOptions(),
                'actions' => [
                    [
                        'type' => 'modal',
                        'action' => 'show',
                        'template' => '/telegramgroup/group.html',
                        'title' => '{LNG_Telegram groups}',
                        'titleClass' => 'icon-telegram'
                    ]
                ]
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
            if (!ApiController::canModify($login, ['can_config'])) {
                return $this->errorResponse('Permission required', 403);
            }
            $group = Model::get($request->post('id')->toInt());
            if ($group === null) {
                return $this->errorResponse('No data available', 404);
            }
            $modules = array_intersect((array) $request->post('modules', [])->filter('a-z'), array_keys(Model::modules()));
            $save = [
                'department' => $request->post('department')->topic(),
                'name' => $request->post('name')->topic(),
                // รหัสแชทของกลุ่มเป็นเลขติดลบ (-100...) หรือ @ชื่อแชนแนล
                'chat_id' => preg_replace('/[^0-9\-@A-Za-z_]/', '', $request->post('chat_id')->toString()),
                'modules' => empty($modules) ? '' : ','.implode(',', $modules).',',
                'is_active' => $request->post('is_active')->toBoolean() ? 1 : 0
            ];
            $errors = [];
            if ($save['department'] === '' && $save['name'] === '') {
                $errors['name'] = 'Please fill in';
            }
            if ($save['chat_id'] === '') {
                $errors['chat_id'] = 'Please fill in';
            }
            if (!empty($errors)) {
                return $this->formErrorResponse($errors, 400);
            }
            if ($save['department'] === '') {
                $save['department'] = null;
            }
            $db = \Kotchasan\DB::create();
            if ((int) $group->id === 0) {
                $group->id = $db->insert('telegram_group', $save);
            } else {
                $db->update('telegram_group', [['id', (int) $group->id]], $save);
            }
            \Index\Log\Model::add((int) $group->id, 'telegramgroup', 'Save', 'Saved Telegram group ID : '.$group->id, $login->id);

            return $this->successResponse([
                'actions' => [
                    ['type' => 'notification', 'level' => 'success', 'message' => 'Saved successfully'],
                    ['type' => 'redirect', 'url' => 'reload', 'target' => 'table', 'delay' => 1000],
                    ['type' => 'modal', 'action' => 'close']
                ]
            ], 'Saved successfully');
        } catch (\Exception $e) {
            return $this->errorResponse(self::exceptionMessage($e), $e->getCode() ?: 500, $e);
        }
    }

    /**
     * ตัวเลือกของฟอร์ม
     *
     * @return array
     */
    public static function formOptions()
    {
        return [
            'department' => \Gcms\Category::init()->toOptions('department', true, null, ['' => Language::get('All departments')]),
            'modules' => \Gcms\Controller::arrayToOptions(Model::modules())
        ];
    }
}
