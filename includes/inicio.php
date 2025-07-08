<?php
session_start();
require_once('../conecct/conex.php');

$db = new Database();
$con = $db->conectar();
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['status' => 'error', 'message' => 'Petición no válida']);
    exit;
}

$doc = $_POST['doc'] ?? '';
$passw = $_POST['passw'] ?? '';

if (empty($doc) || empty($passw)) {
    echo json_encode(['status' => 'error', 'message' => 'Error: Todos los campos son obligatorios.']);
    exit;
}

try {
    $sql = $con->prepare("SELECT * FROM usuarios WHERE documento = :doc");
    $sql->bindParam(':doc', $doc, PDO::PARAM_STR);
    $sql->execute();
    $fila = $sql->fetch(PDO::FETCH_ASSOC);

    if (!$fila) {
        echo json_encode(['status' => 'error', 'message' => 'Error: Documento no encontrado']);
        exit;
    }

    if (!password_verify($passw, $fila['password'])) {
        echo json_encode(['status' => 'error', 'message' => 'Error: Contraseña incorrecta']);
        exit;
    }

    if ($fila['id_estado_usuario'] != 1) {
        echo json_encode(['status' => 'error', 'message' => 'Error: Usuario inactivo']);
        exit;
    }

    $rol_id = $fila['id_rol'];
    $rol_nombre = '';

    if ($rol_id == 3) {
        $rol_nombre = 'superadmin';
        // El superadmin no necesita validación de licencia
    } elseif ($rol_id == 1) {
        $rol_nombre = 'admin';
        $licencia_sql = $con->prepare("SELECT estado FROM sistema_licencias WHERE usuario_asignado = :doc AND estado = 'activa' LIMIT 1");
        $licencia_sql->bindParam(':doc', $doc, PDO::PARAM_STR);
        $licencia_sql->execute();
        if ($licencia_sql->fetch() === false) {
            echo json_encode(['status' => 'error', 'message' => 'Error: No tiene una licencia de administrador activa.']);
            exit;
        }
    } elseif ($rol_id == 2) {
        $rol_nombre = 'usuario';
        $acceso_sql = $con->prepare("SELECT estado FROM usuarios_licencias WHERE doc_usu = :doc AND estado = 1 LIMIT 1");
        $acceso_sql->bindParam(':doc', $doc, PDO::PARAM_STR);
        $acceso_sql->execute();
        if ($acceso_sql->fetch() === false) {
            echo json_encode(['status' => 'error', 'message' => 'Error: No tiene acceso permitido al sistema.']);
            exit;
        }
    } else {
        echo json_encode(['status' => 'error', 'message' => 'Error: Rol de usuario no reconocido.']);
        exit;
    }
    
    $_SESSION['documento'] = $fila['documento'];
    $_SESSION['tipo'] = $rol_nombre;
    $_SESSION['id_rol'] = $rol_id;
    $_SESSION['nombre_completo'] = $fila['nombre_completo'];

    echo json_encode([
        'status' => 'success',
        'rol' => $rol_nombre
    ]);

} catch (PDOException $e) {
    echo json_encode(['status' => 'error', 'message' => 'Error en la base de datos: ' . $e->getMessage()]);
    exit;
}
?>