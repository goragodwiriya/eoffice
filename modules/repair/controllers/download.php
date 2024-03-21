<?php
/**
 * @filesource modules/repair/controllers/index.php
 *
 * @copyright 2016 Goragod.com
 * @license https://www.kotchasan.com/license/
 *
 * @see https://www.kotchasan.com/
 */

namespace Repair\Download;

use Kotchasan\Mime;

/**
 * ลิสต์รายการไฟล์แนบสำหรับดาวน์โหลด.
 *
 * @author Goragod Wiriya <admin@goragod.com>
 *
 * @since 1.0
 */
class Controller extends \Gcms\Controller
{
    /**
     * ลิสต์รายการไฟล์ คืนค่าเป็น HTML สำหรับแสดงผล.
     *
     * @param int $id     ไดเร็คทอรี่เก็บไฟล์ ปกติจะเป็น ID ของไฟล์
     * @param int $delete true ลบได้
     *
     * @return string
     */
    public static function files($id, $delete)
    {
        $files = [];
        \Kotchasan\File::listFiles(ROOT_PATH.DATA_FOLDER.'repair/'.$id.'/', $files);
        $elem = uniqid();
        $content = '<div class="file_list clear" id='.$elem.'>';
        foreach ($files as $i => $item) {
            if (preg_match('/.*\/('.$id.')\/([0-9]+)\.('.implode('|', self::$cfg->repair_file_typies).')$/', $item, $match)) {
                // id ของไฟล์
                $uid = uniqid();
                // MIME สำหรับการดาวน์โหลด
                $mime = Mime::get($match[3]);
                $_SESSION[$uid] = array(
                    'file' => $item,
                    'mime' => $mime === null || !in_array($match[3], array('pdf', 'png', 'gif', 'jpg', 'jpeg')) ? 'application/octet-stream' : $mime
                );
                $img = WEB_URL.'skin/ext/'.(is_file(ROOT_PATH.'skin/ext/'.$match[3].'.png') ? $match[3] : 'file').'.png';
                $content .= '<div id="item_'.$uid.'"><a href="'.WEB_URL.'modules/repair/download.php?id='.$uid.'" target="preview"><img src="'.$img.'" alt="'.$match[3].'">&nbsp;{LNG_Download}</a>';
                if ($delete) {
                    $content .= '<a class="icon-delete" id=delete_'.$uid.' title="{LNG_Delete}"></a>';
                }
                $content .= '</div>';
            }
        }
        $content .= '</div><script>initRepairDownload("'.$elem.'")</script>';
        // คืนค่า HTML

        return $content;
    }
}
