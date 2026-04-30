<?php
// API para dashboard - Conexión a SQL Server
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

// ============================================
// CONFIGURACIÓN - CAMBIA ESTOS DATOS
// ============================================
$server = "celcomtux.freedynamicdns.org";     // 👈 Tu dominio No-IP del Día 1
$database = "Mybusiness20";           // 👈 Nombre de tu base de datos
$username = "sa";         // 👈 Usuario que creaste
$password = "12345678";      // 👈 Contraseña

// ============================================
// FUNCIÓN PARA CONECTAR
// ============================================
function conectarSQL($server, $database, $username, $password) {
    $connectionInfo = array(
        "Database" => $Mybusiness20,
        "UID" => $sa,
        "PWD" => $"12345678",
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
            'message' => 'API conectando a SQL Server'
        ]);
        break;
    
    case 'resumen':
        $result = conectarSQL($server, $database, $username, $password);
        if ($result['error']) {
            echo json_encode(['error' => $result['message']]);
            break;
        }
        $conn = $result['conn'];
        
        // Ventas hoy
        $sql_hoy = "SELECT ISNULL(SUM(IMPORTE), 0) as total_hoy, COUNT(*) as num_ventas, ISNULL(AVG(IMPORTE), 0) as promedio_hoy 
                    FROM ventas WHERE CAST(F_EMISION AS DATE) = CAST(GETDATE() AS DATE) AND ESTADO = 'AC'";
        $stmt = sqlsrv_query($conn, $sql_hoy);
        $hoy = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC);
        
        // Ventas mes
        $sql_mes = "SELECT ISNULL(SUM(IMPORTE), 0) as total_mes 
                    FROM ventas WHERE MONTH(F_EMISION) = MONTH(GETDATE()) AND YEAR(F_EMISION) = YEAR(GETDATE()) AND ESTADO = 'AC'";
        $stmt = sqlsrv_query($conn, $sql_mes);
        $mes = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC);
        
        // Total clientes
        $sql_clientes = "SELECT COUNT(*) as total_clientes FROM clients";
        $stmt = sqlsrv_query($conn, $sql_clientes);
        $clientes = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC);
        
        // Cartera vencida
        $sql_cartera = "SELECT ISNULL(SUM(SALDO), 0) as cartera_vencida FROM clients WHERE ISNULL(SALDO, 0) > 0";
        $stmt = sqlsrv_query($conn, $sql_cartera);
        $cartera = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC);
        
        echo json_encode([
            'ventas_hoy' => floatval($hoy['total_hoy']),
            'num_ventas_hoy' => intval($hoy['num_ventas']),
            'promedio_hoy' => floatval($hoy['promedio_hoy']),
            'ventas_mes' => floatval($mes['total_mes']),
            'total_clientes' => intval($clientes['total_clientes']),
            'cartera_vencida' => floatval($cartera['cartera_vencida'])
        ]);
        
        sqlsrv_close($conn);
        break;
    
    case 'top_productos':
        $result = conectarSQL($server, $database, $username, $password);
        if ($result['error']) {
            echo json_encode(['error' => $result['message']]);
            break;
        }
        $conn = $result['conn'];
        $limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 10;
        
        $sql = "SELECT TOP $limit 
                    p.ARTICULO, 
                    LEFT(p.DESCRIP, 40) as DESCRIP, 
                    ISNULL(SUM(pv.CANTIDAD), 0) as unidades_vendidas,
                    ISNULL(SUM(pv.IMPORTE), 0) as total_vendido
                FROM partvta pv
                INNER JOIN prods p ON pv.ARTICULO = p.ARTICULO
                INNER JOIN ventas v ON pv.VENTA = v.VENTA
                WHERE v.ESTADO = 'AC' AND v.F_EMISION >= DATEADD(day, -30, GETDATE())
                GROUP BY p.ARTICULO, p.DESCRIP
                ORDER BY unidades_vendidas DESC";
        
        $stmt = sqlsrv_query($conn, $sql);
        $productos = [];
        while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
            $productos[] = [
                'ARTICULO' => $row['ARTICULO'],
                'DESCRIP' => $row['DESCRIP'],
                'unidades_vendidas' => floatval($row['unidades_vendidas']),
                'total_vendido' => floatval($row['total_vendido'])
            ];
        }
        
        echo json_encode($productos);
        sqlsrv_close($conn);
        break;
    
    case 'productos':
        $result = conectarSQL($server, $database, $username, $password);
        if ($result['error']) {
            echo json_encode(['error' => $result['message']]);
            break;
        }
        $conn = $result['conn'];
        
        $pagina = isset($_GET['pagina']) ? (int)$_GET['pagina'] : 1;
        $por_pagina = isset($_GET['por_pagina']) ? (int)$_GET['por_pagina'] : 50;
        $offset = ($pagina - 1) * $por_pagina;
        
        $sql = "SELECT ARTICULO, LEFT(DESCRIP, 60) as DESCRIP, PRECIO1, ISNULL(EXISTENCIA, 0) as EXISTENCIA
                FROM prods WHERE PARAVENTA = 1
                ORDER BY ARTICULO
                OFFSET $offset ROWS FETCH NEXT $por_pagina ROWS ONLY";
        
        $stmt = sqlsrv_query($conn, $sql);
        $productos = [];
        while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
            $productos[] = [
                'ARTICULO' => $row['ARTICULO'],
                'DESCRIP' => $row['DESCRIP'],
                'PRECIO1' => floatval($row['PRECIO1']),
                'EXISTENCIA' => floatval($row['EXISTENCIA'])
            ];
        }
        
        // Total de productos
        $sql_count = "SELECT COUNT(*) as total FROM prods WHERE PARAVENTA = 1";
        $stmt = sqlsrv_query($conn, $sql_count);
        $count = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC);
        
        echo json_encode([
            'productos' => $productos,
            'pagina_actual' => $pagina,
            'total_paginas' => ceil($count['total'] / $por_pagina),
            'total_productos' => intval($count['total'])
        ]);
        
        sqlsrv_close($conn);
        break;
    
    case 'buscar_productos':
        $result = conectarSQL($server, $database, $username, $password);
        if ($result['error']) {
            echo json_encode(['error' => $result['message']]);
            break;
        }
        $conn = $result['conn'];
        $busqueda = isset($_GET['q']) ? $_GET['q'] : '';
        $busqueda = '%' . $busqueda . '%';
        
        $sql = "SELECT TOP 20 ARTICULO, LEFT(DESCRIP, 60) as DESCRIP, PRECIO1, ISNULL(EXISTENCIA, 0) as EXISTENCIA
                FROM prods WHERE (DESCRIP LIKE ? OR ARTICULO LIKE ?) AND PARAVENTA = 1
                ORDER BY DESCRIP";
        
        $params = array($busqueda, $busqueda);
        $stmt = sqlsrv_query($conn, $sql, $params);
        $productos = [];
        while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
            $productos[] = [
                'ARTICULO' => $row['ARTICULO'],
                'DESCRIP' => $row['DESCRIP'],
                'PRECIO1' => floatval($row['PRECIO1']),
                'EXISTENCIA' => floatval($row['EXISTENCIA'])
            ];
        }
        
        echo json_encode($productos);
        sqlsrv_close($conn);
        break;
    
    default:
        echo json_encode([
            'status' => 'ok',
            'message' => 'API funcionando correctamente',
            'endpoints' => ['ping', 'resumen', 'top_productos', 'productos', 'buscar_productos']
        ]);
        break;
}
?>
