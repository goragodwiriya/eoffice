<?php
/**
 * @filesource modules/car/controllers/catalog.php
 */

namespace Car\Catalog;

use Gcms\Api as ApiController;
use Kotchasan\Http\Request;

class Controller extends ApiController
{
    /**
     * Return active vehicles for the member-facing catalog.
     *
     * @param Request $request
     *
     * @return \Kotchasan\Http\Response
     */
    public function lists(Request $request)
    {
        try {
            ApiController::validateMethod($request, 'GET');

            $login = $this->authenticateRequest($request);
            if (!$login) {
                return $this->errorResponse('Unauthorized', 401);
            }

            return $this->successResponse([
                'items' => Model::getItems(true)
            ], 'Vehicle catalog retrieved');
        } catch (\Exception $e) {
            return $this->errorResponse(self::exceptionMessage($e), $e->getCode() ?: 500, $e);
        }
    }

    /**
     * Return a single vehicle detail modal payload.
     *
     * @param Request $request
     *
     * @return \Kotchasan\Http\Response
     */
    public function detail(Request $request)
    {
        try {
            ApiController::validateMethod($request, 'GET');

            $login = $this->authenticateRequest($request);
            if (!$login) {
                return $this->errorResponse('Unauthorized', 401);
            }

            $data = Model::get($request->get('id')->toInt(), true);
            if ($data === null) {
                return $this->redirectResponse('/404', 'No data available', 404);
            }

            return $this->successResponse([
                'data' => $data,
                'actions' => [
                    [
                        'type' => 'modal',
                        'action' => 'show',
                        'template' => '/car/catalog-detail.html',
                        'title' => '{LNG_Vehicle} '.$data->number,
                        'titleClass' => 'icon-car'
                    ]
                ]
            ], 'Vehicle details retrieved');
        } catch (\Exception $e) {
            return $this->errorResponse(self::exceptionMessage($e), $e->getCode() ?: 500, $e);
        }
    }
}
