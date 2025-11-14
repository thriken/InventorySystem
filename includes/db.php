<?php
require_once __DIR__ . '/../config/database.php';

// 全局PDO连接变量
$pdo = null;

/**
 * 获取PDO数据库连接
 */
function getPdoConnection() {
    global $pdo;
    
    if ($pdo === null) {
        try {
            $pdo = new PDO('mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=' . DB_CHARSET, DB_USER, DB_PASS);
            $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            throw new Exception('PDO数据库连接失败: ' . $e->getMessage());
        }
    }
    
    return $pdo;
}

// 初始化全局PDO连接
$pdo = getPdoConnection();

/**
 * 获取数据库连接
 */
function getDbConnection() {
    static $conn = null;
    
    if ($conn === null) {
        $conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
        
        if ($conn->connect_error) {
            die("数据库连接失败: " . $conn->connect_error);
        }
        
        $conn->set_charset(DB_CHARSET);
    }
    
    return $conn;
}

/**
 * 执行SQL查询
 */
// 在query函数中，将die()改为抛出异常
function query($sql, $params = []) {
    $conn = getDbConnection();
    
    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        throw new Exception("准备查询失败: " . $conn->error . "<br>SQL: " . $sql);
    }
    
    if (!empty($params)) {
        $types = '';
        foreach ($params as $param) {
            if (is_int($param)) {
                $types .= 'i';
            } elseif (is_float($param)) {
                $types .= 'd';
            } elseif (is_string($param)) {
                $types .= 's';
            } else {
                $types .= 'b';
            }
        }
        
        if (!$stmt->bind_param($types, ...$params)) {
            throw new Exception("参数绑定失败: " . $stmt->error);
        }
    }
    
    if (!$stmt->execute()) {
        throw new Exception("执行查询失败: " . $stmt->error . "<br>SQL: " . $sql);
    }
    
    $result = $stmt->get_result();
    $stmt->close();
    
    return $result;
}

// 添加事务包装函数
function executeInTransaction($callback) {
    try {
        beginTransaction();
        $result = $callback();
        commitTransaction();
        return $result;
    } catch (Exception $e) {
        rollbackTransaction();
        throw $e;
    }
}

/**
 * 获取单行数据
 */
function fetchRow($sql, $params = []) {
    $result = query($sql, $params);
    $row = $result->fetch_assoc();
    $result->free();
    return $row;
}

/**
 * 获取多行数据
 */
function fetchAll($sql, $params = []) {
    $result = query($sql, $params);
    $rows = [];
    while ($row = $result->fetch_assoc()) {
        $rows[] = $row;
    }
    $result->free();
    return $rows;
}

/**
 * 获取单个值
 * 用于COUNT(), SUM()等聚合函数查询
 */
function fetchOne($sql, $params = []) {
    $result = query($sql, $params);
    $row = $result->fetch_row();
    $value = $row ? $row[0] : null;
    $result->free();
    return $value;
}

/**
 * 插入数据
 */
function insert($table, $data) {
    $conn = getDbConnection();
    
    $columns = implode(', ', array_keys($data));
    $placeholders = implode(', ', array_fill(0, count($data), '?'));
    
    $sql = "INSERT INTO $table ($columns) VALUES ($placeholders)";
    
    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        die("准备插入失败: " . $conn->error . "<br>SQL: " . $sql);
    }
    
    $types = '';
    $values = [];
    foreach ($data as $value) {
        if ($value === null) {
            $types .= 's'; // 对于NULL值，使用's'类型，MySQL会自动处理
        } elseif (is_int($value)) {
            $types .= 'i';
        } elseif (is_float($value)) {
            $types .= 'd';
        } elseif (is_string($value)) {
            $types .= 's';
        } else {
            $types .= 'b';
        }
        $values[] = $value;
    }
    
    error_log("DEBUG: insert函数 - bind类型: " . $types);
    error_log("DEBUG: insert函数 - 绑定值: " . print_r($values, true));
    
    $stmt->bind_param($types, ...$values);
    
    $result = $stmt->execute();
    
    if (!$result) {
        error_log("ERROR: 执行插入失败: " . $stmt->error . " SQL: " . $sql);
    }
    
    $insertId = $conn->insert_id;
    $affectedRows = $stmt->affected_rows;
    
    error_log("DEBUG: insert函数 - 执行结果: " . ($result ? '成功' : '失败'));
    error_log("DEBUG: insert函数 - 影响行数: " . $affectedRows);
    error_log("DEBUG: insert函数 - 插入ID: " . $insertId);
    
    $stmt->close();
    
    return $insertId;
}

/**
 * 更新数据
 */
function update($table, $data, $where, $whereParams = []) {
    $conn = getDbConnection();
    
    $setClauses = [];
    foreach (array_keys($data) as $column) {
        $setClauses[] = "$column = ?";
    }
    $setClause = implode(', ', $setClauses);
    
    $sql = "UPDATE $table SET $setClause WHERE $where";
    
    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        die("准备更新失败: " . $conn->error . "<br>SQL: " . $sql);
    }
    
    $types = '';
    $values = [];
    
    foreach ($data as $value) {
        if (is_int($value)) {
            $types .= 'i';
        } elseif (is_float($value)) {
            $types .= 'd';
        } elseif (is_string($value)) {
            $types .= 's';
        } else {
            $types .= 'b';
        }
        $values[] = $value;
    }
    
    foreach ($whereParams as $value) {
        if (is_int($value)) {
            $types .= 'i';
        } elseif (is_float($value)) {
            $types .= 'd';
        } elseif (is_string($value)) {
            $types .= 's';
        } else {
            $types .= 'b';
        }
        $values[] = $value;
    }
    
    $stmt->bind_param($types, ...$values);
    $stmt->execute();
    
    $affectedRows = $stmt->affected_rows;
    $stmt->close();
    
    return $affectedRows;
}

/**
 * 删除数据
 */
function delete($table, $where, $params = []) {
    $conn = getDbConnection();
    
    $sql = "DELETE FROM $table WHERE $where";
    
    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        die("准备删除失败: " . $conn->error . "<br>SQL: " . $sql);
    }
    
    if (!empty($params)) {
        $types = '';
        foreach ($params as $param) {
            if (is_int($param)) {
                $types .= 'i';
            } elseif (is_float($param)) {
                $types .= 'd';
            } elseif (is_string($param)) {
                $types .= 's';
            } else {
                $types .= 'b';
            }
        }
        
        $stmt->bind_param($types, ...$params);
    }
    
    $stmt->execute();
    
    $affectedRows = $stmt->affected_rows;
    $stmt->close();
    
    return $affectedRows;
}

/**
 * 执行插入并返回插入ID
 */
function executeInsert($sql, $params = []) {
    $conn = getDbConnection();
    
    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        die("准备插入失败: " . $conn->error . "<br>SQL: " . $sql);
    }
    
    if (!empty($params)) {
        $types = '';
        foreach ($params as $param) {
            if (is_int($param)) {
                $types .= 'i';
            } elseif (is_float($param)) {
                $types .= 'd';
            } elseif (is_string($param)) {
                $types .= 's';
            } else {
                $types .= 'b';
            }
        }
        
        $stmt->bind_param($types, ...$params);
    }
    
    $stmt->execute();
    
    $insertId = $conn->insert_id;
    $stmt->close();
    
    return $insertId;
}

/**
 * 执行SQL语句（用于UPDATE、DELETE等）
 */
function execute($sql, $params = []) {
    $conn = getDbConnection();
    
    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        die("准备执行失败: " . $conn->error . "<br>SQL: " . $sql);
    }
    
    if (!empty($params)) {
        $types = '';
        foreach ($params as $param) {
            if (is_int($param)) {
                $types .= 'i';
            } elseif (is_float($param)) {
                $types .= 'd';
            } elseif (is_string($param)) {
                $types .= 's';
            } else {
                $types .= 'b';
            }
        }
        
        $stmt->bind_param($types, ...$params);
    }
    
    $result = $stmt->execute();
    $stmt->close();
    
    return $result;
}

/**
 * 开始事务
 */
function beginTransaction() {
    $conn = getDbConnection();
    $conn->autocommit(false);
}

/**
 * 提交事务
 */
function commitTransaction() {
    $conn = getDbConnection();
    $conn->commit();
    $conn->autocommit(true);
}

/**
 * 回滚事务
 */
function rollbackTransaction() {
    $conn = getDbConnection();
    $conn->rollback();
    $conn->autocommit(true);
}
?>