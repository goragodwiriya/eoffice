<?php
/**
 * @filesource modules/car/controllers/vehicle.php
 */

namespace Car\Vehicle;

use Gcms\Api as ApiController;
use Kotchasan\Http\Request;

class Controller extends ApiController
{
    /**
     * Get vehicle details.
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
            if (!ApiController::hasPermission($login, ['can_manage_car', 'can_config'])) {
                return $this->errorResponse('Permission required', 403);
            }

            $data = Model::get($request->get('id', 0)->toInt());
            if ($data === null) {
                return $this->redirectResponse('/404', 'No data available', 404);
            }

            $categories = \Car\Category\Controller::init();

            return $this->successResponse([
                'data' => $data,
                'options' => [
                    'car_brand' => $categories->toOptions('car_brand', true, null, ['' => '{LNG_Please select}']),
                    'car_type' => $categories->toOptions('car_type', true, null, ['' => '{LNG_Please select}'])
                ],
                'actions' => [
                    [
                        'type' => 'modal',
                        'action' => 'show',
                        'template' => '/car/vehicle.html',
                        'title' => ($data->id > 0 ? '{LNG_Edit} {LNG_Vehicle}' : '{LNG_Add} {LNG_Vehicle}'),
                        'titleClass' => 'icon-car'
                    ]
                ]
            ]);
        } catch (\Exception $e) {
            return $this->errorResponse(self::exceptionMessage($e), $e->getCode() ?: 500, $e);
        }
    }

/**
 * Save vehicle details.
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
            if (!ApiController::canModify($login, ['can_manage_car', 'can_config'])) {
                return $this->errorResponse('Permission required', 403);
            }

            $id = $request->post('id', 0)->toInt();
            if ($id > 0 && Model::getRecord($id) === null) {
                return $this->errorResponse('No data available', 404);
            }

            $save = [
                'number' => $request->post('number')->topic(),
                'color' => $request->post('color')->toString(),
                'detail' => $request->post('detail')->textarea(),
                'seats' => max(1, $request->post('seats')->toInt()),
                'is_active' => $request->post('is_active')->toBoolean() ? 1 : 0
            ];
            $meta = [
                'car_brand' => $request->post('car_brand')->topic(),
                'car_type' => $request->post('car_type')->topic()
            ];

            $errors = [];
            if ($save['number'] === '') {
                $errors['number'] = 'Please fill in';
            }
            if ($meta['car_brand'] === '') {
                $errors['car_brand'] = 'Please select';
            }
            if ($meta['car_type'] === '') {
                $errors['car_type'] = 'Please select';
            }

            if (!empty($errors)) {
                return $this->formErrorResponse($errors, 400);
            }

            $id = Model::save($id, $save, $meta);

            $ret = [];
            \Download\Upload\Model::execute($ret, $request, $id, 'car', self::$cfg->img_typies, 0, self::$cfg->stored_img_size);

            // Log
            \Index\Log\Model::add($id, 'car', 'Save', 'Saved vehicle: '.$save['number'], $login->id);

            // Response
            return $this->successResponse([
                'actions' => [
                    [
                        'type' => 'notification',
                        'level' => empty($ret) ? 'success' : 'error',
                        'message' => empty($ret) ? 'Saved successfully' : $ret['car']
                    ],
                    [
                        'type' => 'redirect',
                        'url' => 'reload',
                        'target' => 'vehicles'
                    ],
                    [
                        'type' => 'modal',
                        'action' => 'close'
                    ]
                ]
            ], 'Saved successfully');
        } catch (\Exception $e) {
            return $this->errorResponse(self::exceptionMessage($e), $e->getCode() ?: 500, $e);
        }
    }

    /**
     * Remove an uploaded vehicle image.
     *
     * @param Request $request
     *
     * @return \Kotchasan\Http\Response
     */
    public function removeImage(Request $request)
    {
        try {
            ApiController::validateMethod($request, 'POST');
            $this->validateCsrfToken($request);

            $login = $this->authenticateRequest($request);
            if (!$login) {
                return $this->redirectResponse('/login', 'Unauthorized', 401);
            }
            if (!ApiController::canModify($login, ['can_manage_car', 'can_config'])) {
                return $this->errorResponse('Permission required', 403);
            }

            $json = json_decode($request->post('id')->toString());
            // ชื่อไฟล์มาจากผู้ใช้ — รับเฉพาะ "ชื่อ.นามสกุล" กันการส่ง ../ ไปลบไฟล์อื่นของเซิร์ฟเวอร์ (แบบเดียวกับ car/document.php)
            if (!$json || !isset($json->id, $json->file) || !preg_match('/^[a-z0-9]+\.[a-z0-9]+$/', (string) $json->file)) {
                return $this->errorResponse('No data available', 404);
            }
            if (Model::getRecord((int) $json->id) === null) {
                return $this->errorResponse('No data available', 404);
            }

            $file = ROOT_PATH.DATA_FOLDER.'car/'.(int) $json->id.'/'.$json->file;
            if (!file_exists($file)) {
                return $this->errorResponse('No data available', 404);
            }

            // Remove file
            @unlink($file);

            // Log
            \Index\Log\Model::add((int) $json->id, 'car', 'Delete', 'Removed vehicle image: '.$json->file, $login->id);

            // Response
            return $this->successResponse([], 'Image removed successfully');
        } catch (\Exception $e) {
            return $this->errorResponse(self::exceptionMessage($e), $e->getCode() ?: 500, $e);
        }
    }
}
