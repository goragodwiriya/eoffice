<?php
/**
 * @filesource modules/telegramgroup/controllers/groups.php
 *
 * @copyright 2026 Goragod.com
 * @license https://www.kotchasan.com/license/
 *
 * @see https://www.kotchasan.com/
 */

namespace Telegramgroup\Groups;

use Gcms\Api as ApiController;
use Kotchasan\Http\Request;
use Kotchasan\Language;

/**
 * GET api/telegramgroup/groups · POST api/telegramgroup/groups/action
 * รายการกลุ่ม Telegram (ระบบเดิม module=lines)
 *
 * @author Goragod Wiriya <admin@goragod.com>
 *
 * @since 1.0
 */
class Controller extends \Gcms\Table
{
    /**
     * @var array
     */
    protected $allowedSortColumns = ['id', 'name', 'department', 'is_active'];

    /**
     * @param Request $request
     * @param object|null $login
     *
     * @return mixed
     */
    protected function checkAuthorization(Request $request, $login)
    {
        return ApiController::hasPermission($login, 'can_config') ? true : $this->errorResponse('Permission required', 403);
    }

    /**
     * @param array $params
     * @param object|null $login
     *
     * @return \Kotchasan\QueryBuilder\QueryBuilder
     */
    protected function toDataTable($params, $login = null)
    {
        $query = \Kotchasan\Model::createQuery()
            ->select('id', 'name', 'department', 'chat_id', 'modules', 'is_active')
            ->from('telegram_group');
        if (!empty($params['search'])) {
            $query->where([['name', 'LIKE', '%'.$params['search'].'%'], ['chat_id', 'LIKE', '%'.$params['search'].'%']], 'OR');
        }

        return $query;
    }

    /**
     * @param array $datas
     * @param object|null $login
     *
     * @return array
     */
    protected function formatDatas(array $datas, $login = null): array
    {
        $category = \Gcms\Category::init();
        $modules = \Telegramgroup\Group\Model::modules();
        foreach ($datas as $item) {
            $department = (string) $item->department === '' ? '' : $category->get('department', $item->department);
            $item->name = $item->name !== '' ? $item->name : $department;
            $item->department_text = $department === '' ? Language::get('All departments') : $department;
            $names = [];
            foreach (array_filter(explode(',', (string) $item->modules)) as $module) {
                if (isset($modules[$module])) {
                    $names[] = $modules[$module];
                }
            }
            $item->modules_text = implode(', ', $names);
        }

        return $datas;
    }

    /**
     * @param Request $request
     * @param object $login
     *
     * @return \Kotchasan\Http\Response
     */
    protected function handleEditAction(Request $request, $login)
    {
        if (!ApiController::hasPermission($login, 'can_config')) {
            return $this->errorResponse('Failed to process request', 403);
        }
        $group = \Telegramgroup\Group\Model::get($request->post('id')->toInt());
        if ($group === null) {
            return $this->errorResponse('No data available', 404);
        }

        return $this->successResponse([
            'data' => (array) $group,
            'options' => \Telegramgroup\Group\Controller::formOptions(),
            'actions' => [
                'type' => 'modal',
                'template' => 'telegramgroup/group.html',
                'title' => '{LNG_Telegram groups}',
                'titleClass' => 'icon-telegram'
            ]
        ], '');
    }

    /**
     * ส่งข้อความทดสอบเข้ากลุ่ม
     *
     * @param Request $request
     * @param object $login
     *
     * @return \Kotchasan\Http\Response
     */
    protected function handleTestAction(Request $request, $login)
    {
        if (!ApiController::canModify($login, ['can_config'])) {
            return $this->errorResponse('Failed to process request', 403);
        }
        $group = \Telegramgroup\Group\Model::get($request->post('id')->toInt());
        if ($group === null || $group->id == 0 || $group->chat_id === '') {
            return $this->errorResponse('No data available', 404);
        }
        if (empty(self::$cfg->telegram_bot_token)) {
            return $this->errorResponse(Language::get('Please set up the Telegram Bot Token first'), 400);
        }
        $err = \Gcms\Telegram::sendTo($group->chat_id, \Kotchasan\Text::plainText(self::$cfg->web_title)."\n".Language::get('Test message'));
        if ($err !== '') {
            return $this->errorResponse($err, 400);
        }

        return $this->successResponse([], 'Your message was sent successfully');
    }

    /**
     * @param Request $request
     * @param object $login
     *
     * @return \Kotchasan\Http\Response
     */
    protected function handleDeleteAction(Request $request, $login)
    {
        if (!ApiController::canModify($login, ['can_config'])) {
            return $this->errorResponse('Failed to process request', 403);
        }
        $ids = array_values(array_filter($request->request('ids', [])->toInt()));
        if (empty($ids)) {
            $ids = array_values(array_filter([$request->post('id')->toInt()]));
        }
        if (empty($ids)) {
            return $this->errorResponse('Delete action failed', 400);
        }
        \Kotchasan\DB::create()->delete('telegram_group', [['id', $ids]], 0);
        \Index\Log\Model::add(0, 'telegramgroup', 'Delete', 'Deleted Telegram group ID(s): '.implode(', ', $ids), $login->id);

        return $this->redirectResponse('reload', 'Deleted successfully');
    }

    /**
     * เปิด/ปิดกลุ่ม
     *
     * @param Request $request
     * @param object $login
     *
     * @return \Kotchasan\Http\Response
     */
    protected function handleActiveAction(Request $request, $login)
    {
        if (!ApiController::canModify($login, ['can_config'])) {
            return $this->errorResponse('Failed to process request', 403);
        }
        $group = \Telegramgroup\Group\Model::get($request->post('id')->toInt());
        if ($group === null || $group->id == 0) {
            return $this->errorResponse('No data available', 404);
        }
        $value = (int) $group->is_active === 1 ? 0 : 1;
        if ($value === 1 && $group->chat_id === '') {
            return $this->errorResponse(Language::get('Please fill in').' '.Language::get('Chat ID'), 400);
        }
        \Kotchasan\DB::create()->update('telegram_group', [['id', (int) $group->id]], ['is_active' => $value]);

        return $this->redirectResponse('reload', 'Saved successfully');
    }
}
