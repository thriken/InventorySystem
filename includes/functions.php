<?php
require_once __DIR__ . '/../config/config.php';

/**
 * 检查是否为移动设备
 */
function isMobile() {
    $userAgent = $_SERVER['HTTP_USER_AGENT'];
    return preg_match('/(android|bb\d+|meego).+mobile|avantgo|bada\/|blackberry|blazer|compal|elaine|fennec|hiptop|iemobile|ip(hone|od)|iris|kindle|lge |maemo|midp|mmp|mobile.+firefox|netfront|opera m(ob|in)i|palm( os)?|phone|p(ixi|re)\/|plucker|pocket|psp|series(4|6)0|symbian|treo|up\.(browser|link)|vodafone|wap|windows ce|xda|xiino/i', $userAgent) || preg_match('/1207|6310|6590|3gso|4thp|50[1-6]i|770s|802s|a wa|abac|ac(er|oo|s\-)|ai(ko|rn)|al(av|ca|co)|amoi|an(ex|ny|yw)|aptu|ar(ch|go)|as(te|us)|attw|au(di|\-m|r |s )|avan|be(ck|ll|nq)|bi(lb|rd)|bl(ac|az)|br(e|v)w|bumb|bw\-(n|u)|c55\/|capi|ccwa|cdm\-|cell|chtm|cldc|cmd\-|co(mp|nd)|craw|da(it|ll|ng)|dbte|dc\-s|devi|dica|dmob|do(c|p)o|ds(12|\-d)|el(49|ai)|em(l2|ul)|er(ic|k0)|esl8|ez([4-7]0|os|wa|ze)|fetc|fly(\-|_)|g1 u|g560|gene|gf\-5|g\-mo|go(\.w|od)|gr(ad|un)|haie|hcit|hd\-(m|p|t)|hei\-|hi(pt|ta)|hp( i|ip)|hs\-c|ht(c(\-| |_|a|g|p|s|t)|tp)|hu(aw|tc)|i\-(20|go|ma)|i230|iac( |\-|\/)|ibro|idea|ig01|ikom|im1k|inno|ipaq|iris|ja(t|v)a|jbro|jemu|jigs|kddi|keji|kgt( |\/)|klon|kpt |kwc\-|kyo(c|k)|le(no|xi)|lg( g|\/(k|l|u)|50|54|\-[a-w])|libw|lynx|m1\-w|m3ga|m50\/|ma(te|ui|xo)|mc(01|21|ca)|m\-cr|me(rc|ri)|mi(o8|oa|ts)|mmef|mo(01|02|bi|de|do|t(\-| |o|v)|zz)|mt(50|p1|v )|mwbp|mywa|n10[0-2]|n20[2-3]|n30(0|2)|n50(0|2|5)|n7(0(0|1)|10)|ne((c|m)\-|on|tf|wf|wg|wt)|nok(6|i)|nzph|o2im|op(ti|wv)|oran|owg1|p800|pan(a|d|t)|pdxg|pg(13|\-([1-8]|c))|phil|pire|pl(ay|uc)|pn\-2|po(ck|rt|se)|prox|psio|pt\-g|qa\-a|qc(07|12|21|32|60|\-[2-7]|i\-)|qtek|r380|r600|raks|rim9|ro(ve|zo)|s55\/|sa(ge|ma|mm|ms|ny|va)|sc(01|h\-|oo|p\-)|sdk\/|se(c(\-|0|1)|47|mc|nd|ri)|sgh\-|shar|sie(\-|m)|sk\-0|sl(45|id)|sm(al|ar|b3|it|t5)|so(ft|ny)|sp(01|h\-|v\-|v )|sy(01|mb)|t2(18|50)|t6(00|10|18)|ta(gt|lk)|tcl\-|tdg\-|tel(i|m)|tim\-|t\-mo|to(pl|sh)|ts(70|m\-|m3|m5)|tx\-9|up(\.b|g1|si)|utst|v400|v750|veri|vi(rg|te)|vk(40|5[0-3]|\-v)|vm40|voda|vulc|vx(52|53|60|61|70|80|81|83|85|98)|w3c(\-| )|webc|whit|wi(g |nc|nw)|wmlb|wonu|x700|yas\-|your|zeto|zte\-/i', substr($userAgent, 0, 4));
}

/**
 * 重定向到指定URL
 */
function redirect($url) {
    header("Location: $url");
    exit;
}

/**
 * 生成随机字符串
 */
function generateRandomString($length = 10) {
    $characters = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
    $randomString = '';
    for ($i = 0; $i < $length; $i++) {
        $randomString .= $characters[rand(0, strlen($characters) - 1)];
    }
    return $randomString;
}

/**
 * 格式化日期时间
 */
function formatDateTime($datetime, $format = 'Y-m-d H:i:s') {
    $date = new DateTime($datetime);
    return $date->format($format);
}

/**
 * 输出JSON响应
 */
function jsonResponse($data, $statusCode = 200) {
    http_response_code($statusCode);
    header('Content-Type: application/json');
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

/**
 * 验证请求方法
 */
function validateRequestMethod($method) {
    if ($_SERVER['REQUEST_METHOD'] !== $method) {
        jsonResponse(['error' => '不支持的请求方法'], 405);
    }
}

/**
 * 获取POST数据
 */
function getPostData() {
    $contentType = isset($_SERVER["CONTENT_TYPE"]) ? trim($_SERVER["CONTENT_TYPE"]) : '';
    
    if (stripos($contentType, 'application/json') !== false) {
        $content = file_get_contents("php://input");
        $data = json_decode($content, true);
        return $data;
    }
    
    return $_POST;
}

/**
 * 获取GET参数
 */
function getQueryParams() {
    return $_GET;
}

/**
 * 验证必填字段
 */
function validateRequiredFields($data, $requiredFields) {
    $missingFields = [];
    
    foreach ($requiredFields as $field) {
        if (!isset($data[$field]) || trim($data[$field]) === '') {
            $missingFields[] = $field;
        }
    }
    
    if (!empty($missingFields)) {
        // 使用统一的响应格式
        if (class_exists('ApiCommon')) {
            ApiCommon::sendResponse(400, '缺少必填字段', [
                'missing_fields' => $missingFields
            ]);
        } else {
            // 备用方案：保持向后兼容
            jsonResponse([
                'error' => '缺少必填字段',
                'missing_fields' => $missingFields
            ], 400);
        }
    }
}

/**
 * 获取包操作历史记录（替代package_operation_history视图）
 */
function getPackageOperationHistory($conditions = [], $limit = null) {
    $sql = "
        SELECT 
            ior.id, ior.record_no, ior.operation_type, ior.package_id,
            ior.glass_type_id, ior.base_id, ior.operation_quantity,
            ior.before_quantity, ior.after_quantity, ior.from_rack_id,
            ior.to_rack_id, ior.unit_area, ior.total_area, ior.operator_id,
            ior.operation_date, ior.operation_time, ior.status,
            ior.scrap_reason, ior.notes, ior.related_record_id,
            ior.created_at, ior.updated_at,
            gp.package_code, gt.name as glass_name, gt.thickness,
            gt.color, gt.brand, b.name as base_name, u.real_name as operator_name,
            fr.code as from_rack_code, tr.code as to_rack_code
        FROM inventory_operation_records ior
        LEFT JOIN glass_packages gp ON ior.package_id = gp.id
        LEFT JOIN glass_types gt ON ior.glass_type_id = gt.id
        LEFT JOIN bases b ON ior.base_id = b.id
        LEFT JOIN users u ON ior.operator_id = u.id
        LEFT JOIN storage_racks fr ON ior.from_rack_id = fr.id
        LEFT JOIN storage_racks tr ON ior.to_rack_id = tr.id
    ";
    
    $params = [];
    if (!empty($conditions)) {
        $sql .= " WHERE " . implode(' AND ', array_keys($conditions));
        $params = array_values($conditions);
    }
    
    $sql .= " ORDER BY ior.operation_date DESC, ior.operation_time DESC";
    
    if ($limit) {
        $sql .= " LIMIT $limit";
    }
    
    return query($sql, $params);
}
?>