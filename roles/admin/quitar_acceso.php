<?php
session_start();
header('Content-Type: application/json');

require_once('../../conecct/conex.php');
$db = new Database();
$con = $db->conectar();

$response = ['success' => false, 'message' => 'Petición inválida.'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $admin_doc = $_SESSION['documento'] ?? null;
    $doc_usu_a_desactivar = $_POST['documento'] ?? null;

    if (!$admin_doc) {
        $response['message'] = 'No se ha iniciado sesión o la sesión ha expirado.';
        echo json_encode($response);
        exit;
    }

    if (!$doc_usu_a_desactivar) {
        $response['message'] = 'No se ha especificado el documento del usuario.';
        echo json_encode($response);
        exit;
    }

    try {
        $estado_inactivo = 2;

        $sql_update = $con->prepare("UPDATE usuarios_licencias SET estado = :estado WHERE doc_usu = :doc_usu AND estado = 1");
        $sql_update->bindParam(':estado', $estado_inactivo, PDO::PARAM_INT);
        $sql_update->bindParam(':doc_usu', $doc_usu_a_desactivar, PDO::PARAM_STR);
        $sql_update->execute();

        if ($sql_update->rowCount() > 0) {
            $response['success'] = true;
            $response['message'] = 'Se ha quitado el acceso al usuario ' . $doc_usu_a_desactivar;
        } else {
            $response['message'] = 'No se pudo quitar el acceso. Es posible que el usuario no tuviera un acceso activo.';
        }

    } catch (Exception $e) {
        $response['message'] = 'Error en la base de datos: ' . $e->getMessage();
    }
}

echo json_encode($response);
?>