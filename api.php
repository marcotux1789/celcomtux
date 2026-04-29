<?php
// ============================================
// API PARA DASHBOARD - CONEXIÓN A SQL SERVER
// ============================================

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST');
header('Access-Control-Allow-Headers: Content-Type');

// ============================================
// CONFIGURACIÓN DE LA BASE DE DATOS
// ============================================
$config = [
    'server' => 'midashboard.ddns.net',  // 👈 CAMBIA ESTO por tu dominio de No-IP
    'database' => 'NombreDeTuBD',         // 👈 CAMBIA ESTO por el nombre de tu BD
    'username' => 'dashboard_user',       // 👈 El usuario que creamos en SQL Server
    'password' => 'TuClaveSegura123!'     // 👈 La contraseña de ese usuario
];

// ============================================
// FUNCIÓN PARA CONECTAR A SQL SERVER
// ============================================
function conectarSQL($config) {
    $connectionInfo = array(
        "Database" => $config['database'],
        "UID" => $config['username'],
        "PWD" => $config['password'],
        "CharacterSet" => "UTF-8"
    );
    
    $conn = sqlsrv_connect($config['server'], $connectionInfo);
    
    if (!$conn) {
        return ['error' => true, 'message' => sqlsrv_errors()];
    }
    
    return ['error' => false, 'conn' => $conn];
}

// ============================================
// ENDPOINTS DISPONIBLES
// ============================================
$action = $_GET['action'] ?? '';

switch($action) {
    
    // Ping para mantener vivo el servicio
    case 'ping':
        echo json_encode([
            'status' => 'ok',
            'timestamp' => date('Y-m-d H:i:s'),
            'message' => 'API funcionando correctamente'
        ]);
        break;
    
    // Ventas del día
    case 'ventas_hoy':
        $resultado = conectarSQL($config);
        if ($resultado['error']) {
            echo json_encode(['error' => 'No se pudo conectar a la base de datos']);
            break;
        }
        
        $conn = $resultado['conn'];
        $sql = "SELECT 
                    ISNULL(SUM(IMPORTE), 0) as total_hoy,
                    COUNT(*) as num_ventas,
                    ISNULL(AVG(IMPORTE), 0) as promedio
                FROM ventas 
                WHERE CAST(F_EMISION AS DATE) = CAST(GETDATE() AS DATE)
                AND ESTADO = 'AC'";
        
        $stmt = sqlsrv_query($conn, $sql);
        $row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC);
        
        echo json_encode($row);
        sqlsrv_close($conn);
        break;
    
    // Top productos más vendidos
    case 'top_productos':
        $resultado = conectarSQL($config);
        if ($resultado['error']) {
            echo json_encode(['error' => 'No se pudo conectar a la base de datos']);
            break;
        }
        
        $conn = $resultado['conn'];
        $limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 10;
        
        $sql = "SELECT TOP $limit 
                    p.ARTICULO,
                    LEFT(p.DESCRIP, 40) as DESCRIP,
                    ISNULL(SUM(pv.CANTIDAD), 0) as unidades_vendidas,
                    ISNULL(SUM(pv.IMPORTE), 0) as total_vendido
                FROM partvta pv
                INNER JOIN prods p ON pv.ARTICULO = p.ARTICULO
                INNER JOIN ventas v ON pv.VENTA = v.VENTA
                WHERE v.ESTADO = 'AC'
                AND v.F_EMISION >= DATEADD(day, -30, GETDATE())
                GROUP BY p.ARTICULO, p.DESCRIP
                ORDER BY unidades_vendidas DESC";
        
        $stmt = sqlsrv_query($conn, $sql);
        $productos = [];
        while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
            $productos[] = $row;
        }
        
        echo json_encode($productos);
        sqlsrv_close($conn);
        break;
    
    // Productos con paginación (para manejar 25 mil productos)
    case 'productos':
        $resultado = conectarSQL($config);
        if ($resultado['error']) {
            echo json_encode(['error' => 'No se pudo conectar a la base de datos']);
            break;
        }
        
        $conn = $resultado['conn'];
        $pagina = isset($_GET['pagina']) ? (int)$_GET['pagina'] : 1;
        $por_pagina = isset($_GET['por_pagina']) ? (int)$_GET['por_pagina'] : 50;
        
        $inicio = ($pagina - 1) * $por_pagina + 1;
        $fin = $pagina * $por_pagina;
        
        // Consulta paginada (solo trae 50 productos)
        $sql = "SELECT * FROM (
                    SELECT ROW_NUMBER() OVER(ORDER BY ARTICULO) AS RowNum,
                           ARTICULO,
                           LEFT(DESCRIP, 50) as DESCRIP,
                           PRECIO1,
                           EXISTENCIA,
                           CASE WHEN INVENT = 1 THEN 'Si' ELSE 'No' END as controla_inventario
                    FROM prods
                    WHERE PARAVENTA = 1
                ) AS Paginados
                WHERE RowNum BETWEEN ? AND ?";
        
        $params = array($inicio, $fin);
        $stmt = sqlsrv_query($conn, $sql, $params);
        
        $productos = [];
        while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
            $productos[] = $row;
        }
        
        // Total de productos (para calcular páginas)
        $sql_count = "SELECT COUNT(*) as total FROM prods WHERE PARAVENTA = 1";
        $count_stmt = sqlsrv_query($conn, $sql_count);
        $count_row = sqlsrv_fetch_array($count_stmt, SQLSRV_FETCH_ASSOC);
        $total_productos = $count_row['total'];
        $total_paginas = ceil($total_productos / $por_pagina);
        
        echo json_encode([
            'productos' => $productos,
            'pagina_actual' => $pagina,
            'total_paginas' => $total_paginas,
            'total_productos' => $total_productos,
            'por_pagina' => $por_pagina
        ]);
        sqlsrv_close($conn);
        break;
    
    // Buscar productos (rápido, solo 20 resultados)
    case 'buscar_productos':
        $resultado = conectarSQL($config);
        if ($resultado['error']) {
            echo json_encode(['error' => 'No se pudo conectar a la base de datos']);
            break;
        }
        
        $conn = $resultado['conn'];
        $busqueda = isset($_GET['q']) ? $_GET['q'] : '';
        $busqueda = '%' . $busqueda . '%';
        
        $sql = "SELECT TOP 20 
                    ARTICULO, 
                    LEFT(DESCRIP, 50) as DESCRIP, 
                    PRECIO1, 
                    EXISTENCIA
                FROM prods 
                WHERE DESCRIP LIKE ? OR ARTICULO LIKE ?
                ORDER BY DESCRIP";
        
        $params = array($busqueda, $busqueda);
        $stmt = sqlsrv_query($conn, $sql, $params);
        
        $productos = [];
        while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
            $productos[] = $row;
        }
        
        echo json_encode($productos);
        sqlsrv_close($conn);
        break;
    
    // Clientes con mayor saldo
    case 'clientes_moros':
        $resultado = conectarSQL($config);
        if ($resultado['error']) {
            echo json_encode(['error' => 'No se pudo conectar a la base de datos']);
            break;
        }
        
        $conn = $resultado['conn'];
        $sql = "SELECT TOP 20 
                    CLIENTE,
                    LEFT(NOMBRE, 40) as NOMBRE,
                    ISNULL(SALDO, 0) as SALDO,
                    ISNULL(DIAS, 0) as DIAS_CREDITO
                FROM clients 
                WHERE ISNULL(SALDO, 0) > 0
                ORDER BY SALDO DESC";
        
        $stmt = sqlsrv_query($conn, $sql);
        $clientes = [];
        while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
            $clientes[] = $row;
        }
        
        echo json_encode($clientes);
        sqlsrv_close($conn);
        break;
    
    // Resumen general (métricas rápidas)
    case 'resumen':
        $resultado = conectarSQL($config);
        if ($resultado['error']) {
            echo json_encode(['error' => 'No se pudo conectar a la base de datos']);
            break;
        }
        
        $conn = $resultado['conn'];
        
        // Ventas hoy
        $sql_hoy = "SELECT ISNULL(SUM(IMPORTE), 0) as total_hoy FROM ventas 
                    WHERE CAST(F_EMISION AS DATE) = CAST(GETDATE() AS DATE) AND ESTADO = 'AC'";
        $stmt_hoy = sqlsrv_query($conn, $sql_hoy);
        $hoy = sqlsrv_fetch_array($stmt_hoy, SQLSRV_FETCH_ASSOC);
        
        // Ventas mes
        $sql_mes = "SELECT ISNULL(SUM(IMPORTE), 0) as total_mes FROM ventas 
                    WHERE MONTH(F_EMISION) = MONTH(GETDATE()) 
                    AND YEAR(F_EMISION) = YEAR(GETDATE())
                    AND ESTADO = 'AC'";
        $stmt_mes = sqlsrv_query($conn, $sql_mes);
        $mes = sqlsrv_fetch_array($stmt_mes, SQLSRV_FETCH_ASSOC);
        
        // Total clientes
        $sql_clientes = "SELECT COUNT(*) as total_clientes FROM clients";
        $stmt_clientes = sqlsrv_query($conn, $sql_clientes);
        $clientes_count = sqlsrv_fetch_array($stmt_clientes, SQLSRV_FETCH_ASSOC);
        
        echo json_encode([
            'ventas_hoy' => $hoy['total_hoy'],
            'ventas_mes' => $mes['total_mes'],
            'total_clientes' => $clientes_count['total_clientes']
        ]);
        sqlsrv_close($conn);
        break;
    
    // Si no se especificó acción válida
    default:
        echo json_encode([
            'error' => 'Acción no válida',
            'acciones_disponibles' => [
                'ping', 'ventas_hoy', 'top_productos', 'productos', 
                'buscar_productos', 'clientes_moros', 'resumen'
            ],
            'ejemplo' => 'api.php?action=ventas_hoy'
        ]);
        break;
}
?>
