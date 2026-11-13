<?php
/**
 * @filesource modules/repair/controllers/settings.php
 *
 * @copyright 2026 Goragod.com
 * @license https://www.kotchasan.com/license/
 *
 * @see https://www.kotchasan.com/
 */

namespace Repair\Settings;

use Gcms\Api as ApiController;
use Gcms\Config;
use Kotchasan\Http\Request;
use Kotchasan\Language;

/**
 * API ตั้งค่าโมดูลแจ้งซ่อม
 *
 * @author Goragod Wiriya <admin@goragod.com>
 *
 * @since 1.0
 */
class Controller extends ApiController
{
    /**
     * GET api/repair/settings/get
     * อ่านค่ากำหนดของโมดูล
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

            return $this->successResponse([
                'data' => (object) [
                    'repair_first_status' => (int) self::$cfg->repair_first_status,
                    'repair_prefix' => self::$cfg->repair_prefix ?? '',
                    'repair_job_no' => self::$cfg->repair_job_no ?? 'JOB%04d',
                    'repair_status_success' => (int) (self::$cfg->repair_status_success ?? 0),
                    'repair_send_mail' => (int) (self::$cfg->repair_send_mail ?? 1)
                ],
                'options' => [
                    'repair_first_status' => \Repair\Status\Model::toOptions(false),
                    'repair_status_success' => \Repair\Status\Model::toOptions(false)
                ]
            ], 'Repair settings loaded');
        } catch (\Exception $e) {
            return $this->errorResponse(self::exceptionMessage($e), $e->getCode() ?: 500, $e);
        }
    }

    /**
     * POST api/repair/settings/save
     * บันทึกค่ากำหนดของโมดูล
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

            $config = Config::load(ROOT_PATH.'settings/config.php');
            $config->repair_first_status = $request->post('repair_first_status')->toInt();
            $config->repair_prefix = $request->post('repair_prefix')->topic();
            $config->repair_job_no = $request->post('repair_job_no')->topic();
            $config->repair_status_success = $request->post('repair_status_success')->toInt();
            $config->repair_send_mail = $request->post('repair_send_mail')->toInt() === 1 ? 1 : 0;

            if (!Config::save($config, ROOT_PATH.'settings/config.php')) {
                return $this->errorResponse(Language::replace('File %s cannot be created or is read-only.', 'settings/config.php'), 500);
            }

            \Index\Log\Model::add(0, 'repair', 'Save', 'Repair settings saved', $login->id);

            return $this->redirectResponse('reload', 'Saved successfully', 200, 0, 'form');
        } catch (\Exception $e) {
            return $this->errorResponse(self::exceptionMessage($e), $e->getCode() ?: 500, $e);
        }
    }
}
