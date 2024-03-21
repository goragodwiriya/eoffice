<?php
/**
 * @filesource modules/personnel/models/setup.php
 *
 * @copyright 2016 Goragod.com
 * @license https://www.kotchasan.com/license/
 *
 * @see https://www.kotchasan.com/
 */

namespace Personnel\Setup;

use Gcms\Login;
use Kotchasan\Http\Request;
use Kotchasan\Language;

/**
 * โมเดลสำหรับแสดงรายการบุคลากร (setup.php)
 *
 * @author Goragod Wiriya <admin@goragod.com>
 *
 * @since 1.0
 */
class Model extends \Kotchasan\Model
{
    /**
     * Query ข้อมูลสำหรับส่งให้กับ DataTable
     *
     * @return \Kotchasan\Database\QueryBuilder
     */
    public static function toDataTable()
    {
        return static::createQuery()
            ->select('U.name', 'U.phone', 'M.value department', 'P.value position', 'U.id')
            ->from('user U')
            ->join('user_meta M', 'LEFT', array(array('M.member_id', 'U.id'), array('M.name', 'department')))
            ->join('user_meta P', 'LEFT', array(array('P.member_id', 'U.id'), array('P.name', 'position')))
            ->where(array(
                array('U.active', 1),
                array('U.status', self::$cfg->personnel_status)
            ));
    }

    /**
     * รับค่าจาก action (setup.php)
     *
     * @param Request $request
     */
    public function action(Request $request)
    {
        $ret = [];
        // session, referer, member
        if ($request->initSession() && $request->isReferer() && $login = Login::isMember()) {
            // รับค่าจากการ POST
            $action = $request->post('action')->toString();
            if (preg_match_all('/,?([0-9]+),?/', $request->post('id')->toString(), $match)) {
                if ($action == 'view') {
                    // ดูรายละเอียดบุคลากร
                    $search = \Personnel\Write\Model::get((int) $match[1][0]);
                    if ($search) {
                        $ret['modal'] = Language::trans(\Personnel\Personnelinfo\View::create()->render($search, $login));
                    }
                }
            }
        }
        if (empty($ret)) {
            $ret['alert'] = Language::get('Unable to complete the transaction');
        }
        // คืนค่าเป็น JSON
        echo json_encode($ret);
    }
}
