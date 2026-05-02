<?php
// API para dashboard - Conexión a SQL Server
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

// ============================================
// CONFIGURACIÓN - TUS DATOS CORRECTOS
// ============================================
$server = "celcomtux.freedynamicdns.org,53100";
$database = "Mybusiness20";
$username = "sa";
$password = "12345678";

// ============================================
// FUNCIÓN PARA CONECTAR (CORREGIDA)
// ============================================
function conectarSQL($server, $database, $username, $password) {
    $connectionInfo = array(
        "Database" => $database,   // ✅ Usa la variable $database
        "UID" => $username,        // ✅ Usa la variable $username
        "PWD" => $password,        // ✅ Usa la variable $password, no uses comillas dobles adicionales
        "CharacterSet" => "UTF-8"
    );
    
    $conn = sqlsrv_connect($server, $connectionInfo);
    
    if (!$conn) {
        $errors = sqlsrv_errors();
        return ['error' => true, 'message' => $errors[0]['message'] ?? 'Error de conexión'];
    }
    
    return ['error' => false, 'conn' => $conn];
}

// ============================================
// ENDPOINTS
// ============================================
$action = $_GET['action'] ?? '';

switch($action) {
    
    case 'ping':
        echo json_encode([
            'status' => 'ok',
            'timestamp' => date('Y-m-d H:i:s'),
            'message' => 'API funcionando correctamente'
        ]);
        break;
    
    case 'test_conexion':
        $result = conectarSQL($server, $database, $username, $password);
        if ($result['error']) {
            echo json_encode(['error' => $result['message']]);
        } else {
            echo json_encode(['success' => 'Conexión exitosa a SQL Server']);
            sqlsrv_close($result['conn']);
        }
        break;
    
    case 'resumen':
        $result = conectarSQL($server, $database, $username, $password);
        if ($result['error']) {
            echo json_encode(['error' => $result['message']]);
            break;
        }
        $conn = $result['conn'];
        
        // Prueba simple: contar ventas
        $sql = "SELECT COUNT(*) as total FROM ventas";
        $stmt = sqlsrv_query($conn, $sql);
        
        if (!$stmt) {
            echo json_encode(['error' => 'Error en consulta SQL']);
            sqlsrv_close($conn);
            break;
        }
        
        $row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC);
        
        echo json_encode([
            'status' => 'ok',
            'total_ventas' => $row['total']
        ]);
        
        sqlsrv_close($conn);
        break;
    
    default:
        echo json_encode([
            'status' => 'ok',
            'message' => 'API funcionando correctamente',
            'endpoints' => ['ping', 'test_conexion', 'resumen']
        ]);
        break;
}
?>
