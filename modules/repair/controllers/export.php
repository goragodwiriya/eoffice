<?php
/**
 * @filesource modules/repair/controllers/export.php
 *
 * @copyright 2026 Goragod.com
 * @license https://www.kotchasan.com/license/
 *
 * @see https://www.kotchasan.com/
 */

namespace Repair\Export;

use Gcms\Api as ApiController;
use Kotchasan\Http\Request;
use Kotchasan\Language;

/**
 * export.php?module=repair&typ=receipt&id=<repair id>
 * พิมพ์ใบรับซ่อม (ระบบเดิม modules/repair/print.php) — ผู้แจ้งซ่อมพิมพ์ของตัวเองได้ เจ้าหน้าที่/ช่างพิมพ์ได้ทุกใบ
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
     * @return \Kotchasan\Http\Response|string
     */
    public function receipt(Request $request)
    {
        try {
            $login = $this->authenticateRequest($request);
            if (!$login) {
                return $this->errorResponse('Unauthorized', 401);
            }
            $index = \Repair\Detail\Model::get($request->get('id')->toInt());
            if ($index === null) {
                return $this->errorResponse('Not found', 404);
            }
            if ((int) $index->customer_id !== (int) $login->id
                && !ApiController::hasPermission($login, ['can_manage_repair', 'can_repair'])) {
                return $this->errorResponse('Permission required', 403);
            }

            return \Export\Export\Controller::printHtml(
                Language::get('Repair receipt').' '.$index->job_id,
                '<div class="print-bar noprint"><div><button type="button" class="print-button" onclick="window.print()">{LNG_Print}</button></div></div>'
                .'<div class="print-content">'.self::sheet($index).'</div>',
                [
                    'body_class' => 'repair-print paper-a4',
                    'page_style' => \Export\Export\Controller::pageStyle(\Export\Export\Controller::paper('a4')),
                    'stylesheets' => ['modules/repair/views/receipt.css']
                ]
            );
        } catch (\Exception $e) {
            return $this->errorResponse(self::exceptionMessage($e), $e->getCode() ?: 500, $e);
        }
    }

    /**
     * เนื้อหาใบรับซ่อม
     *
     * @param object $index
     *
     * @return string
     */
    protected static function sheet($index)
    {
        $url = WEB_URL.'repair-detail?id='.(int) $index->id;
        $operators = \Repair\Operator\Model::map();
        $operator = isset($operators[$index->operator_id]) ? $operators[$index->operator_id] : '';
        $title = \Kotchasan\Text::plainText(self::$cfg->web_title);

        $html = '<section class="sheet repair-receipt">';
        $html .= '<header class="sheet-head"><div class="brand"><div class="brand-text">';
        $html .= '<div class="eyebrow">'.self::e(Language::get('Repair jobs')).'</div>';
        $html .= '<h1>'.self::e($title).'</h1></div></div>';
        $html .= '<div class="doc-card"><h2>'.self::e(Language::get('Repair receipt')).'</h2>';
        $html .= '<div class="badge-row"><span class="badge">'.self::e($index->job_id).'</span></div>';
        $html .= '<div class="doc-meta"><div><div class="doc-label">'.self::e(Language::get('Transaction date')).'</div>';
        $html .= '<div class="doc-value doc-date">'.self::e(\Kotchasan\Date::format($index->created_at, 'd M Y')).'</div></div></div>';
        $html .= '</div></header>';

        $html .= '<section class="party">';
        $html .= '<div class="party-card"><h3>'.self::e(Language::get('Informer')).'</h3>';
        $html .= '<div class="party-name">'.self::e($index->name).'</div>';
        $html .= '<p>'.self::e(Language::get('Phone')).' '.self::e($index->phone).'</p></div>';
        $html .= '<div class="party-card"><h3>'.self::e(Language::get('Equipment')).'</h3>';
        $html .= '<div class="party-name">'.self::e($index->topic).'</div>';
        $html .= '<p>'.self::e(Language::get('Serial/Registration No.')).' '.self::e($index->product_no).'</p></div>';
        $html .= '</section>';

        $html .= '<div class="items-wrap"><table class="items"><thead><tr>';
        $html .= '<th>'.self::e(Language::get('Problems and repairs details')).'</th>';
        $html .= '</tr></thead><tbody><tr><td class="col-topic receipt-detail">';
        $html .= '<div class="item-title">'.nl2br(self::e($index->job_description)).'</div>';
        if ($index->comment !== null && $index->comment !== '') {
            $html .= '<div class="item-note">'.self::e(Language::get('Repair note')).' : '
                .nl2br(self::e(\Kotchasan\Text::untextarea($index->comment))).'</div>';
        }
        $html .= '</td></tr><tr class="filler"><td></td></tr></tbody></table></div>';

        $html .= '<section class="sheet-sum">';
        $html .= '<div class="note-card receipt-track"><div><h3>'.self::e(Language::get('Repair job description')).'</h3>';
        $html .= '<div class="comment">'.self::e($url).'</div></div>';
        $html .= '<img class="receipt-qr" src="'.self::qrcode($url).'" alt=""></div>';
        $html .= '</section>';

        $html .= '<section class="signs receipt-signs">';
        foreach (['Informer' => (string) $index->name, 'Operator' => $operator] as $role => $name) {
            $html .= '<div class="sign-box"><div class="sign-line"></div>';
            $html .= '<div class="sign-name">('.($name === '' ? str_repeat('&nbsp;', 16) : self::e($name)).')</div>';
            $html .= '<div class="sign-role">'.self::e(Language::get($role)).'</div></div>';
        }
        $html .= '</section>';

        $html .= '<footer class="sheet-foot"><span><b>'.self::e($index->job_id).'</b></span>';
        $html .= '<span>'.self::e(Language::get('Printed')).' '.self::e(\Kotchasan\Date::format(date('Y-m-d H:i:s'), 'd M Y H:i')).'</span></footer>';
        $html .= '</section>';

        return $html;
    }

    /**
     * QR Code ของลิงก์ใบแจ้งซ่อม เก็บไว้ใน datas/repair/ ไม่ต้องสร้างใหม่ทุกครั้ง
     *
     * @param string $url
     *
     * @return string URL ของรูป
     */
    protected static function qrcode($url)
    {
        $dir = ROOT_PATH.DATA_FOLDER.'repair/';
        if (!is_dir($dir)) {
            @mkdir($dir, 0755, true);
        }
        $name = md5($url.'|H|2').'.png';
        if (!is_file($dir.$name)) {
            include_once ROOT_PATH.'modules/repair/phpqrcode/qrlib.php';
            \QRcode::png($url, $dir.$name, 'H', 2, 2);
        }

        return WEB_URL.DATA_FOLDER.'repair/'.$name;
    }

    /**
     * @param string $text
     *
     * @return string
     */
    protected static function e($text)
    {
        return htmlspecialchars((string) $text, ENT_QUOTES, 'UTF-8');
    }
}
