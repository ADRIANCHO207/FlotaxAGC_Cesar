<?php
session_start();
header('Content-Type: application/json');

require_once('../../conecct/conex.php');
$db = new Database();
$con = $db->conectar();

$response = ['success' => false, 'message' => 'Petición inválida.'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $admin_doc = $_SESSION['documento'] ?? null;
    $doc_usu_a_activar = $_POST['documento'] ?? null;

    if (!$admin_doc) {
        $response['message'] = 'No se ha iniciado sesión o la sesión ha expirado.';
        echo json_encode($response);
        exit;
    }

    if (!$doc_usu_a_activar) {
        $response['message'] = 'No se ha especificado el documento del usuario.';
        echo json_encode($response);
        exit;
    }

    try {
        $con->beginTransaction();

        $sql_empresa = $con->prepare("SELECT id_empresa FROM sistema_licencias WHERE usuario_asignado = :admin_doc AND estado = 'activa' LIMIT 1");
        $sql_empresa->bindParam(':admin_doc', $admin_doc, PDO::PARAM_STR);
        $sql_empresa->execute();
        $empresa = $sql_empresa->fetch(PDO::FETCH_ASSOC);

        if (!$empresa) {
            throw new Exception('El administrador no tiene una licencia activa para asignar accesos.');
        }
        
        $id_empresa_admin = $empresa['id_empresa'];
        $estado_acceso = 1;

        $sql_insert = $con->prepare("INSERT INTO usuarios_licencias (id_empresa, doc_usu, estado) VALUES (:id_empresa, :doc_usu, :estado)");
        $sql_insert->bindParam(':id_empresa', $id_empresa_admin, PDO::PARAM_INT);
        $sql_insert->bindParam(':doc_usu', $doc_usu_a_activar, PDO::PARAM_STR);
        $sql_insert->bindParam(':estado', $estado_acceso, PDO::PARAM_INT);
        $sql_insert->execute();

        if ($sql_insert->rowCount() > 0) {
            $con->commit();
            $response['success'] = true;
            $response['message'] = 'Acceso concedido correctamente al usuario ' . $doc_usu_a_activar;
        } else {
            throw new Exception('No se pudo insertar el registro de acceso.');
        }

    } catch (Exception $e) {
        $con->rollBack();
        $response['message'] = 'Error en la base de datos: ' . $e->getMessage();
    }
}

echo json_encode($response);
?>