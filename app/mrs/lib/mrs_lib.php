<?php
/**
 * MRS Package Management System - Core Library
 * 文件路径: app/mrs/lib/mrs_lib.php
 * 说明: 核心业务逻辑函数
 */

// ============================================
// 认证相关函数 (共享用户数据库)
// ============================================

/**
 * 验证用户登录
 * @param PDO $pdo
 * @param string $username
 * @param string $password
 * @return array|false
 */
function mrs_authenticate_user($pdo, $username, $password) {
    try {
        $stmt = $pdo->prepare("SELECT user_id, user_login, user_secret_hash, user_email, user_display_name, user_status FROM sys_users WHERE user_login = :username LIMIT 1");
        $stmt->bindValue(':username', $username);
        $stmt->execute();

        $user = $stmt->fetch();

        if (!$user) {
            mrs_log("登录失败: 用户不存在 - {$username}", 'WARNING');
            return false;
        }

        if ($user['user_status'] !== 'active') {
            mrs_log("登录失败: 账户未激活 - {$username}", 'WARNING');
            return false;
        }

        if (password_verify($password, $user['user_secret_hash'])) {
            $update = $pdo->prepare("UPDATE sys_users SET user_last_login_at = NOW(6) WHERE user_id = :user_id");
            $update->bindValue(':user_id', $user['user_id'], PDO::PARAM_INT);
            $update->execute();

            unset($user['user_secret_hash']);
            mrs_log("登录成功: {$username}", 'INFO');
            return $user;
        }

        mrs_log("登录失败: 密码错误 - {$username}", 'WARNING');
        return false;
    } catch (PDOException $e) {
        mrs_log('用户认证失败: ' . $e->getMessage(), 'ERROR');
        return false;
    }
}

/**
 * 创建用户会话
 * @param array $user
 */
function mrs_create_user_session($user) {
    mrs_start_secure_session();

    $_SESSION['user_id'] = $user['user_id'];
    $_SESSION['user_login'] = $user['user_login'];
    $_SESSION['user_display_name'] = $user['user_display_name'];
    $_SESSION['user_email'] = $user['user_email'];
    $_SESSION['logged_in'] = true;
    $_SESSION['login_time'] = time();
    $_SESSION['last_activity'] = time();
}

/**
 * 检查用户是否登录
 * @return bool
 */
function mrs_is_user_logged_in() {
    mrs_start_secure_session();

    if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
        return false;
    }

    $timeout = MRS_SESSION_TIMEOUT;
    if (isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity']) > $timeout) {
        mrs_destroy_user_session();
        return false;
    }

    $_SESSION['last_activity'] = time();
    return true;
}

/**
 * 销毁会话
 */
function mrs_destroy_user_session() {
    mrs_start_secure_session();

    $_SESSION = [];

    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();
        setcookie(
            session_name(),
            '',
            time() - 42000,
            $params['path'],
            $params['domain'],
            $params['secure'],
            $params['httponly']
        );
    }

    session_destroy();
}

/**
 * 登录保护
 */
function mrs_require_login() {
    if (!mrs_is_user_logged_in()) {
        header('Location: /mrs/ap/index.php?action=login');
        exit;
    }
}

// ============================================
// Express 数据查询函数（只读，松耦合）
// ============================================

/**
 * 获取 Express 数据库连接（与 MRS 共享同一数据库）
 * @return PDO
 * @throws PDOException
 */
function get_express_db_connection() {
    // Express 和 MRS 表在同一个数据库中，直接返回 MRS 的连接
    return get_mrs_db_connection();
}

/**
 * 获取 Express 批次列表（只读查询）
 * @return array
 */
function mrs_get_express_batches() {
    try {
        $express_pdo = get_express_db_connection();

        // 暂时显示所有批次，不过滤状态
        // TODO: 根据实际 Express 批次状态调整过滤条件
        $stmt = $express_pdo->prepare("
            SELECT
                batch_id,
                batch_name,
                status,
                total_count,
                counted_count,
                created_at
            FROM express_batch
            ORDER BY created_at DESC
            LIMIT 100
        ");

        $stmt->execute();
        return $stmt->fetchAll();
    } catch (PDOException $e) {
        mrs_log('Failed to get Express batches: ' . $e->getMessage(), 'ERROR');
        return [];
    }
}

/**
 * 获取 Express 批次中已清点的包裹（排除已入库的）
 * @param PDO $mrs_pdo MRS 数据库连接
 * @param string $batch_name 批次名称
 * @return array
 */
function mrs_get_express_counted_packages($mrs_pdo, $batch_name) {
    try {
        $express_pdo = get_express_db_connection();

        // 查询 Express 中已清点的包裹
        $stmt = $express_pdo->prepare("
            SELECT
                b.batch_name,
                p.tracking_number,
                p.content_note,
                p.package_status,
                p.counted_at
            FROM express_package p
            INNER JOIN express_batch b ON p.batch_id = b.batch_id
            WHERE b.batch_name = :batch_name
              AND p.package_status IN ('counted', 'adjusted')
            ORDER BY p.tracking_number ASC
        ");

        $stmt->execute(['batch_name' => $batch_name]);
        $express_packages = $stmt->fetchAll();

        // 过滤掉已入库的包裹
        $available_packages = [];

        foreach ($express_packages as $pkg) {
            // 检查是否已入库
            $check_stmt = $mrs_pdo->prepare("
                SELECT 1 FROM mrs_package_ledger
                WHERE batch_name = :batch_name
                  AND tracking_number = :tracking_number
                LIMIT 1
            ");

            $check_stmt->execute([
                'batch_name' => $pkg['batch_name'],
                'tracking_number' => $pkg['tracking_number']
            ]);

            // 如果不存在，则可入库
            if (!$check_stmt->fetch()) {
                $available_packages[] = $pkg;
            }
        }

        return $available_packages;
    } catch (PDOException $e) {
        mrs_log('Failed to get Express counted packages: ' . $e->getMessage(), 'ERROR');
        return [];
    }
}

// ============================================
// 包裹台账管理函数
// ============================================

/**
 * 获取批次中下一个可用的箱号
 * @param PDO $pdo
 * @param string $batch_name
 * @return string 4位箱号，如 '0001'
 */
function mrs_get_next_box_number($pdo, $batch_name) {
    try {
        $stmt = $pdo->prepare("
            SELECT box_number
            FROM mrs_package_ledger
            WHERE batch_name = :batch_name
            ORDER BY box_number DESC
            LIMIT 1
        ");

        $stmt->execute(['batch_name' => $batch_name]);
        $last_box = $stmt->fetch();

        if (!$last_box) {
            return '0001';
        }

        $last_number = intval($last_box['box_number']);
        $next_number = $last_number + 1;

        return str_pad($next_number, 4, '0', STR_PAD_LEFT);
    } catch (PDOException $e) {
        mrs_log('Failed to get next box number: ' . $e->getMessage(), 'ERROR');
        return '0001';
    }
}

/**
 * 创建入库记录（批量，从 Express 包裹）
 * @param PDO $pdo
 * @param array $packages 包裹数组，每个元素包含: batch_name, tracking_number, content_note
 * @param string $spec_info 规格信息（可选）
 * @param string $operator 操作员
 * @return array ['success' => bool, 'created' => int, 'errors' => array]
 */
function mrs_inbound_packages($pdo, $packages, $spec_info = '', $operator = '') {
    $created = 0;
    $errors = [];

    try {
        $pdo->beginTransaction();

        foreach ($packages as $pkg) {
            try {
                $batch_name = $pkg['batch_name'];
                $tracking_number = $pkg['tracking_number'];
                $content_note = trim((string)($pkg['content_note'] ?? ''));
                if ($content_note === '') {
                    $content_note = '未填写';
                }

                // 自动生成箱号
                $box_number = mrs_get_next_box_number($pdo, $batch_name);

                $stmt = $pdo->prepare("
                    INSERT INTO mrs_package_ledger
                    (batch_name, tracking_number, content_note, box_number, spec_info,
                     status, inbound_time, created_by)
                    VALUES (:batch_name, :tracking_number, :content_note, :box_number, :spec_info,
                            'in_stock', NOW(), :operator)
                ");

                $stmt->execute([
                    'batch_name' => trim($batch_name),
                    'tracking_number' => trim($tracking_number),
                    'content_note' => trim($content_note),
                    'box_number' => $box_number,
                    'spec_info' => trim($spec_info),
                    'operator' => $operator
                ]);

                $created++;

                mrs_log("Package inbound: batch={$batch_name}, tracking={$tracking_number}, box={$box_number}", 'INFO');

            } catch (PDOException $e) {
                if ($e->getCode() == 23000) {
                    $errors[] = "快递单号 {$tracking_number} 已入库";
                } else {
                    $errors[] = "快递单号 {$tracking_number} 入库失败: " . $e->getMessage();
                }
            }
        }

        $pdo->commit();

        mrs_log("Inbound batch completed: created=$created, errors=" . count($errors), 'INFO');

        return [
            'success' => true,
            'created' => $created,
            'errors' => $errors
        ];

    } catch (PDOException $e) {
        $pdo->rollBack();
        mrs_log('Failed to inbound packages: ' . $e->getMessage(), 'ERROR');
        return [
            'success' => false,
            'created' => 0,
            'message' => '入库失败: ' . $e->getMessage()
        ];
    }
}

/**
 * 获取已入库的批次列表（在库箱数）
 * @param PDO $pdo
 * @return array
 */
function mrs_get_instock_batches($pdo) {
    try {
        $stmt = $pdo->prepare("SELECT
                batch_name,
                COUNT(*) AS in_stock_boxes,
                MAX(inbound_time) AS last_inbound_time
            FROM mrs_package_ledger
            WHERE status = 'in_stock'
            GROUP BY batch_name
            ORDER BY last_inbound_time DESC, batch_name ASC");

        $stmt->execute();
        return $stmt->fetchAll();
    } catch (PDOException $e) {
        mrs_log('Failed to get instock batches: ' . $e->getMessage(), 'ERROR');
        return [];
    }
}

/**
 * 获取批次下的包裹（可选状态过滤）
 * @param PDO $pdo
 * @param string $batch_name
 * @param string $status
 * @return array
 */
function mrs_get_packages_by_batch($pdo, $batch_name, $status = 'in_stock') {
    try {
        $sql = "SELECT
                    ledger_id,
                    batch_name,
                    tracking_number,
                    content_note,
                    box_number,
                    spec_info,
                    status,
                    inbound_time
                FROM mrs_package_ledger
                WHERE batch_name = :batch_name";

        $params = ['batch_name' => $batch_name];

        if (!empty($status)) {
            $sql .= " AND status = :status";
            $params['status'] = $status;
        }

        $sql .= " ORDER BY box_number ASC";

        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);

        return $stmt->fetchAll();
    } catch (PDOException $e) {
        mrs_log('Failed to get packages by batch: ' . $e->getMessage(), 'ERROR');
        return [];
    }
}

/**
 * 获取可用库存 (按物料分组)
 * @param PDO $pdo
 * @param string $content_note 可选,筛选特定物料
 * @return array
 */
function mrs_get_inventory_summary($pdo, $content_note = '') {
    try {
        $sql = "
            SELECT
                content_note AS sku_name,
                COUNT(*) as total_boxes
            FROM mrs_package_ledger
            WHERE status = 'in_stock'
        ";

        if (!empty($content_note)) {
            $sql .= " AND content_note = :content_note";
        }

        $sql .= " GROUP BY content_note ORDER BY content_note ASC";

        $stmt = $pdo->prepare($sql);

        if (!empty($content_note)) {
            $stmt->bindValue(':content_note', $content_note, PDO::PARAM_STR);
        }

        $stmt->execute();
        return $stmt->fetchAll();
    } catch (PDOException $e) {
        mrs_log('Failed to get inventory summary: ' . $e->getMessage(), 'ERROR');
        return [];
    }
}

/**
 * 获取库存明细 (某个物料的所有在库包裹)
 * @param PDO $pdo
 * @param string $content_note 物料名称（content_note）
 * @param string $order_by 排序方式: 'fifo' (先进先出), 'batch' (按批次)
 * @return array
 */
function mrs_get_inventory_detail($pdo, $content_note, $order_by = 'fifo') {
    try {
        $sql = "
            SELECT
                ledger_id,
                batch_name,
                tracking_number,
                content_note,
                box_number,
                spec_info,
                warehouse_location,
                status,
                inbound_time,
                DATEDIFF(NOW(), inbound_time) as days_in_stock
            FROM mrs_package_ledger
            WHERE content_note = :content_note AND status = 'in_stock'
        ";

        if ($order_by === 'fifo') {
            $sql .= " ORDER BY inbound_time ASC, batch_name ASC, box_number ASC";
        } else {
            $sql .= " ORDER BY batch_name ASC, box_number ASC";
        }

        $stmt = $pdo->prepare($sql);
        $stmt->bindValue(':content_note', $content_note, PDO::PARAM_STR);
        $stmt->execute();

        return $stmt->fetchAll();
    } catch (PDOException $e) {
        mrs_log('Failed to get inventory detail: ' . $e->getMessage(), 'ERROR');
        return [];
    }
}

/**
 * 出库操作 (批量)
 * @param PDO $pdo
 * @param array $ledger_ids 要出库的台账ID数组
 * @param string $operator 操作员
 * @param int $destination_id 去向ID（可选）
 * @param string $destination_note 去向备注（可选）
 * @return array ['success' => bool, 'shipped' => int, 'message' => string]
 */
function mrs_outbound_packages($pdo, $ledger_ids, $operator = '', $destination_id = null, $destination_note = '') {
    try {
        $pdo->beginTransaction();

        $placeholders = implode(',', array_fill(0, count($ledger_ids), '?'));

        $stmt = $pdo->prepare("
            UPDATE mrs_package_ledger
            SET status = 'shipped',
                outbound_time = NOW(),
                destination_id = ?,
                destination_note = ?,
                updated_by = ?
            WHERE ledger_id IN ($placeholders)
              AND status = 'in_stock'
        ");

        $params = array_merge([$destination_id, $destination_note, $operator], $ledger_ids);
        $stmt->execute($params);

        $shipped = $stmt->rowCount();

        $pdo->commit();

        mrs_log("Outbound completed: shipped=$shipped, destination_id=$destination_id", 'INFO', ['operator' => $operator]);

        return [
            'success' => true,
            'shipped' => $shipped,
            'message' => "成功出库 {$shipped} 个包裹"
        ];

    } catch (PDOException $e) {
        $pdo->rollBack();
        mrs_log('Failed to outbound packages: ' . $e->getMessage(), 'ERROR');
        return [
            'success' => false,
            'shipped' => 0,
            'message' => '出库失败: ' . $e->getMessage()
        ];
    }
}

/**
 * 状态变更 (损耗/作废)
 * @param PDO $pdo
 * @param int $ledger_id 台账ID
 * @param string $new_status 'void' (损耗)
 * @param string $reason 原因
 * @param string $operator
 * @return array
 */
function mrs_change_status($pdo, $ledger_id, $new_status, $reason = '', $operator = '') {
    try {
        $pdo->beginTransaction();

        $stmt = $pdo->prepare("
            UPDATE mrs_package_ledger
            SET status = :new_status,
                void_reason = :reason,
                updated_by = :operator,
                outbound_time = NOW()
            WHERE ledger_id = :ledger_id
        ");

        $stmt->execute([
            'new_status' => $new_status,
            'reason' => $reason,
            'operator' => $operator,
            'ledger_id' => $ledger_id
        ]);

        $pdo->commit();

        mrs_log("Status changed: ledger_id=$ledger_id, new_status=$new_status", 'INFO');

        return [
            'success' => true,
            'message' => '状态已更新'
        ];

    } catch (PDOException $e) {
        $pdo->rollBack();
        mrs_log('Failed to change status: ' . $e->getMessage(), 'ERROR');
        return [
            'success' => false,
            'message' => '状态更新失败: ' . $e->getMessage()
        ];
    }
}

// ============================================
// 统计报表函数
// ============================================

/**
 * 月度入库统计
 * @param PDO $pdo
 * @param string $month 格式: '2025-11'
 * @return array
 */
function mrs_get_monthly_inbound($pdo, $month) {
    try {
        $stmt = $pdo->prepare("
            SELECT
                content_note AS sku_name,
                COUNT(*) as package_count,
                COUNT(DISTINCT batch_name) as batch_count
            FROM mrs_package_ledger
            WHERE DATE_FORMAT(inbound_time, '%Y-%m') = :month
            GROUP BY content_note
            ORDER BY package_count DESC
        ");

        $stmt->bindValue(':month', $month, PDO::PARAM_STR);
        $stmt->execute();

        return $stmt->fetchAll();
    } catch (PDOException $e) {
        mrs_log('Failed to get monthly inbound: ' . $e->getMessage(), 'ERROR');
        return [];
    }
}

/**
 * 月度出库统计
 * @param PDO $pdo
 * @param string $month 格式: '2025-11'
 * @return array
 */
function mrs_get_monthly_outbound($pdo, $month) {
    try {
        $stmt = $pdo->prepare("
            SELECT
                content_note AS sku_name,
                COUNT(*) as package_count
            FROM mrs_package_ledger
            WHERE DATE_FORMAT(outbound_time, '%Y-%m') = :month
              AND status = 'shipped'
            GROUP BY content_note
            ORDER BY package_count DESC
        ");

        $stmt->bindValue(':month', $month, PDO::PARAM_STR);
        $stmt->execute();

        return $stmt->fetchAll();
    } catch (PDOException $e) {
        mrs_log('Failed to get monthly outbound: ' . $e->getMessage(), 'ERROR');
        return [];
    }
}

/**
 * 月度汇总统计
 * @param PDO $pdo
 * @param string $month
 * @return array
 */
function mrs_get_monthly_summary($pdo, $month) {
    try {
        // 入库总数
        $stmt = $pdo->prepare("
            SELECT COUNT(*) as total
            FROM mrs_package_ledger
            WHERE DATE_FORMAT(inbound_time, '%Y-%m') = :month
        ");
        $stmt->execute(['month' => $month]);
        $inbound_total = $stmt->fetch()['total'] ?? 0;

        // 出库总数
        $stmt = $pdo->prepare("
            SELECT COUNT(*) as total
            FROM mrs_package_ledger
            WHERE DATE_FORMAT(outbound_time, '%Y-%m') = :month
              AND status = 'shipped'
        ");
        $stmt->execute(['month' => $month]);
        $outbound_total = $stmt->fetch()['total'] ?? 0;

        return [
            'month' => $month,
            'inbound_total' => $inbound_total,
            'outbound_total' => $outbound_total
        ];
    } catch (PDOException $e) {
        mrs_log('Failed to get monthly summary: ' . $e->getMessage(), 'ERROR');
        return [];
    }
}

/**
 * 获取包裹详情
 * @param PDO $pdo
 * @param int $ledger_id 台账ID
 * @return array|null
 */
function mrs_get_package_by_id($pdo, $ledger_id) {
    try {
        $stmt = $pdo->prepare("SELECT * FROM mrs_package_ledger WHERE ledger_id = :ledger_id");
        $stmt->execute(['ledger_id' => $ledger_id]);
        return $stmt->fetch();
    } catch (PDOException $e) {
        mrs_log('Failed to get package: ' . $e->getMessage(), 'ERROR');
        return null;
    }
}

/**
 * 搜索包裹
 * @param PDO $pdo
 * @param string $content_note 物料名称
 * @param string $batch_name 批次名称
 * @param string $box_number 箱号
 * @param string $tracking_number 快递单号
 * @return array
 */
function mrs_search_packages($pdo, $content_note = '', $batch_name = '', $box_number = '', $tracking_number = '') {
    try {
        $sql = "SELECT * FROM mrs_package_ledger WHERE 1=1";
        $params = [];

        if (!empty($content_note)) {
            $sql .= " AND content_note = :content_note";
            $params['content_note'] = $content_note;
        }

        if (!empty($batch_name)) {
            $sql .= " AND batch_name LIKE :batch_name";
            $params['batch_name'] = '%' . $batch_name . '%';
        }

        if (!empty($box_number)) {
            $sql .= " AND box_number LIKE :box_number";
            $params['box_number'] = '%' . $box_number . '%';
        }

        if (!empty($tracking_number)) {
            $sql .= " AND tracking_number LIKE :tracking_number";
            $params['tracking_number'] = '%' . $tracking_number . '%';
        }

        $sql .= " ORDER BY inbound_time DESC LIMIT 100";

        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);

        return $stmt->fetchAll();
    } catch (PDOException $e) {
        mrs_log('Failed to search packages: ' . $e->getMessage(), 'ERROR');
        return [];
    }
}

/**
 * 搜索在库包裹（用于出库页面）
 * @param PDO $pdo
 * @param string $search_type 搜索类型: content_note|box_number|tracking_tail|batch_name
 * @param string $search_value 搜索值
 * @return array
 */
function mrs_search_instock_packages($pdo, $search_type, $search_value) {
    try {
        $sql = "SELECT
                    ledger_id,
                    batch_name,
                    tracking_number,
                    content_note,
                    box_number,
                    spec_info,
                    warehouse_location,
                    status,
                    inbound_time,
                    DATEDIFF(NOW(), inbound_time) as days_in_stock
                FROM mrs_package_ledger
                WHERE status = 'in_stock'";
        $params = [];

        if (!empty($search_value)) {
            switch ($search_type) {
                case 'content_note':
                    $sql .= " AND content_note LIKE :search_value";
                    $params['search_value'] = '%' . $search_value . '%';
                    break;
                case 'box_number':
                    $sql .= " AND box_number LIKE :search_value";
                    $params['search_value'] = '%' . $search_value . '%';
                    break;
                case 'tracking_tail':
                    // 搜索快递单号尾号（后4位或更多）
                    $sql .= " AND tracking_number LIKE :search_value";
                    $params['search_value'] = '%' . $search_value;
                    break;
                case 'batch_name':
                    $sql .= " AND batch_name LIKE :search_value";
                    $params['search_value'] = '%' . $search_value . '%';
                    break;
            }
        }

        $sql .= " ORDER BY inbound_time ASC LIMIT 200";

        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);

        return $stmt->fetchAll();
    } catch (PDOException $e) {
        mrs_log('Failed to search instock packages: ' . $e->getMessage(), 'ERROR');
        return [];
    }
}

// ============================================
// 去向管理函数
// ============================================

/**
 * 获取所有去向类型
 * @param PDO $pdo
 * @return array
 */
function mrs_get_destination_types($pdo) {
    try {
        $stmt = $pdo->query("
            SELECT * FROM mrs_destination_types
            WHERE is_enabled = 1
            ORDER BY sort_order ASC, type_id ASC
        ");
        return $stmt->fetchAll();
    } catch (PDOException $e) {
        mrs_log('Failed to get destination types: ' . $e->getMessage(), 'ERROR');
        return [];
    }
}

/**
 * 获取所有有效去向
 * @param PDO $pdo
 * @param string $type_code 可选：按类型筛选
 * @return array
 */
function mrs_get_destinations($pdo, $type_code = '') {
    try {
        $sql = "
            SELECT
                d.*,
                dt.type_name
            FROM mrs_destinations d
            LEFT JOIN mrs_destination_types dt ON d.type_code = dt.type_code
            WHERE d.is_active = 1
        ";
        $params = [];

        if (!empty($type_code)) {
            $sql .= " AND d.type_code = :type_code";
            $params['type_code'] = $type_code;
        }

        $sql .= " ORDER BY d.sort_order ASC, d.destination_id ASC";

        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);

        return $stmt->fetchAll();
    } catch (PDOException $e) {
        mrs_log('Failed to get destinations: ' . $e->getMessage(), 'ERROR');
        return [];
    }
}

/**
 * 获取去向详情
 * @param PDO $pdo
 * @param int $destination_id
 * @return array|null
 */
function mrs_get_destination_by_id($pdo, $destination_id) {
    try {
        $stmt = $pdo->prepare("
            SELECT
                d.*,
                dt.type_name
            FROM mrs_destinations d
            LEFT JOIN mrs_destination_types dt ON d.type_code = dt.type_code
            WHERE d.destination_id = :destination_id
        ");
        $stmt->execute(['destination_id' => $destination_id]);
        return $stmt->fetch();
    } catch (PDOException $e) {
        mrs_log('Failed to get destination: ' . $e->getMessage(), 'ERROR');
        return null;
    }
}

/**
 * 创建去向
 * @param PDO $pdo
 * @param array $data
 * @return array
 */
function mrs_create_destination($pdo, $data) {
    try {
        $stmt = $pdo->prepare("
            INSERT INTO mrs_destinations
            (type_code, destination_name, destination_code, contact_person,
             contact_phone, address, remark, sort_order, created_by)
            VALUES
            (:type_code, :destination_name, :destination_code, :contact_person,
             :contact_phone, :address, :remark, :sort_order, :created_by)
        ");

        $stmt->execute([
            'type_code' => $data['type_code'],
            'destination_name' => $data['destination_name'],
            'destination_code' => $data['destination_code'] ?? null,
            'contact_person' => $data['contact_person'] ?? null,
            'contact_phone' => $data['contact_phone'] ?? null,
            'address' => $data['address'] ?? null,
            'remark' => $data['remark'] ?? null,
            'sort_order' => $data['sort_order'] ?? 0,
            'created_by' => $data['created_by'] ?? 'system'
        ]);

        $destination_id = $pdo->lastInsertId();

        mrs_log("Destination created: id=$destination_id", 'INFO');

        return [
            'success' => true,
            'destination_id' => $destination_id,
            'message' => '去向创建成功'
        ];
    } catch (PDOException $e) {
        mrs_log('Failed to create destination: ' . $e->getMessage(), 'ERROR');
        return [
            'success' => false,
            'message' => '创建失败: ' . $e->getMessage()
        ];
    }
}

/**
 * 更新去向
 * @param PDO $pdo
 * @param int $destination_id
 * @param array $data
 * @return array
 */
function mrs_update_destination($pdo, $destination_id, $data) {
    try {
        $stmt = $pdo->prepare("
            UPDATE mrs_destinations
            SET type_code = :type_code,
                destination_name = :destination_name,
                destination_code = :destination_code,
                contact_person = :contact_person,
                contact_phone = :contact_phone,
                address = :address,
                remark = :remark,
                sort_order = :sort_order
            WHERE destination_id = :destination_id
        ");

        $stmt->execute([
            'type_code' => $data['type_code'],
            'destination_name' => $data['destination_name'],
            'destination_code' => $data['destination_code'] ?? null,
            'contact_person' => $data['contact_person'] ?? null,
            'contact_phone' => $data['contact_phone'] ?? null,
            'address' => $data['address'] ?? null,
            'remark' => $data['remark'] ?? null,
            'sort_order' => $data['sort_order'] ?? 0,
            'destination_id' => $destination_id
        ]);

        mrs_log("Destination updated: id=$destination_id", 'INFO');

        return [
            'success' => true,
            'message' => '去向更新成功'
        ];
    } catch (PDOException $e) {
        mrs_log('Failed to update destination: ' . $e->getMessage(), 'ERROR');
        return [
            'success' => false,
            'message' => '更新失败: ' . $e->getMessage()
        ];
    }
}

/**
 * 删除去向（软删除）
 * @param PDO $pdo
 * @param int $destination_id
 * @return array
 */
function mrs_delete_destination($pdo, $destination_id) {
    try {
        // 检查是否有关联的出库记录
        $stmt = $pdo->prepare("
            SELECT COUNT(*) as count
            FROM mrs_package_ledger
            WHERE destination_id = :destination_id
        ");
        $stmt->execute(['destination_id' => $destination_id]);
        $count = $stmt->fetch()['count'];

        if ($count > 0) {
            return [
                'success' => false,
                'message' => "该去向已被使用 {$count} 次，不能删除"
            ];
        }

        // 软删除
        $stmt = $pdo->prepare("
            UPDATE mrs_destinations
            SET is_active = 0
            WHERE destination_id = :destination_id
        ");
        $stmt->execute(['destination_id' => $destination_id]);

        mrs_log("Destination deleted: id=$destination_id", 'INFO');

        return [
            'success' => true,
            'message' => '去向已删除'
        ];
    } catch (PDOException $e) {
        mrs_log('Failed to delete destination: ' . $e->getMessage(), 'ERROR');
        return [
            'success' => false,
            'message' => '删除失败: ' . $e->getMessage()
        ];
    }
}

