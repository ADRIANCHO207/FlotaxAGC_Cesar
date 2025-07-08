<?php
session_start();
require_once('../../conecct/conex.php');
include '../../includes/validarsession.php';
$db = new Database();
$con = $db->conectar();

$documento = $_SESSION['documento'] ?? null;
if (!$documento) {
    header('Location: ../../login.php');
    exit;
}

$nombre_completo = $_SESSION['nombre_completo'] ?? null;
$foto_perfil = $_SESSION['foto_perfil'] ?? null;
if (!$nombre_completo || !$foto_perfil) {
    $user_query = $con->prepare("SELECT * FROM usuarios WHERE documento = :documento");
    $user_query->bindParam(':documento', $documento, PDO::PARAM_STR);
    $user_query->execute();
    $user = $user_query->fetch(PDO::FETCH_ASSOC);
    $nombre_completo = $user['nombre_completo'] ?? 'Usuario';
    $foto_perfil = $user['foto_perfil'] ?: 'css/img/perfil.jpg';
    $_SESSION['nombre_completo'] = $nombre_completo;
    $_SESSION['foto_perfil'] = $foto_perfil;
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Panel de Administrador</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css" />
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
  <link rel="stylesheet" href="css/usuarios.css" />
</head>
<body>
  <?php include 'menu.php'; ?>

  <div class="content">
    <div class="buscador mb-3">
      <input type="text" id="buscar" class="form-control" placeholder="Buscar por nombre, documento o correo" onkeyup="filtrarYPaginar()">
    </div>
    <div class="table-responsive">
      <table class="table table-striped table-bordered" id="tablaUsuarios">
        <thead class="text-center">
            <tr>
                <th>#</th>
                <th>Documento</th>
                <th>Nombre Completo</th>
                <th>Email</th>
                <th>Telefono</th>
                <th>Estado</th>
                <th>Rol</th>
                <th>Licencia</th>
                <th>Accion</th>
            </tr>
        </thead>
        <tbody>
            <?php
            $sql = $con->prepare("SELECT u.*, r.tip_rol, es.tipo_stade, 
                                        IF(ul.id_acceso IS NOT NULL AND ul.estado = 1, 'con acceso', 'sin acceso') AS estado_acceso
                                 FROM usuarios AS u
                                 JOIN roles AS r ON u.id_rol = r.id_rol
                                 JOIN estado_usuario AS es ON u.id_estado_usuario = es.id_estado
                                 LEFT JOIN usuarios_licencias AS ul ON u.documento = ul.doc_usu AND ul.estado = 1
                                 WHERE u.id_rol = 2
                                 GROUP BY u.documento
                                 ORDER BY u.nombre_completo ASC");
            $sql->execute();
            $resultados = $sql->fetchAll(PDO::FETCH_ASSOC);
            $count = 1;
            foreach ($resultados as $resu) {
            ?>
            <tr class="text-center">    
              <td><?php echo $count++; ?></td>
              <td><?php echo htmlspecialchars($resu['documento']); ?></td>
              <td><?php echo htmlspecialchars($resu['nombre_completo']); ?></td>
              <td><?php echo htmlspecialchars($resu['email']); ?></td>
              <td><?php echo htmlspecialchars($resu['telefono']); ?></td>
              <td><?php echo htmlspecialchars($resu['tipo_stade']); ?></td>
              <td><?php echo htmlspecialchars($resu['tip_rol']); ?></td>
              <td>
                <?php
                  if ($resu['estado_acceso'] == 'con acceso') {
                      echo '<button class="btn btn-info btn-sm btn-quitar-acceso" 
                                  data-bs-toggle="modal" 
                                  data-bs-target="#modalQuitarAcceso"
                                  data-documento="' . htmlspecialchars($resu['documento']) . '"
                                  data-nombre="' . htmlspecialchars($resu['nombre_completo']) . '">
                                  Con Acceso
                            </button>';
                  } else {
                      echo '<button class="btn btn-danger btn-sm btn-dar-acceso" 
                                  data-bs-toggle="modal" 
                                  data-bs-target="#modalConfirmarAcceso"
                                  data-documento="' . htmlspecialchars($resu['documento']) . '"
                                  data-nombre="' . htmlspecialchars($resu['nombre_completo']) . '">
                                  Dar Acceso
                            </button>';
                  }
                ?>
              </td>
              <td>
                <div class="d-flex justify-content-center action-buttons">
                  <button class="text-primary me-2 edit-user" data-id="<?php echo htmlspecialchars($resu['documento']); ?>">
                    <i class="bi bi-pencil-square action-icon" title="Editar"></i>
                  </button>
                  <button class="text-danger delete-user" data-id="<?php echo htmlspecialchars($resu['documento']); ?>">
                    <i class="bi bi-trash action-icon" title="Eliminar"></i>
                  </button>
                </div>
              </td>
            </tr>
            <?php } ?>
        </tbody>
      </table>
      <nav>
        <ul class="pagination justify-content-center" id="paginacion"></ul>
      </nav>
      <div class="boton-agregar">
            <a id="btnAgregarUsuario" href="agregar_usuario.php" class="boton">
                <i class="bi bi-plus-circle"></i> Agregar Usuario
            </a>
      </div>
    </div>
  </div>

  <!-- Modal para DAR Acceso -->
  <div class="modal fade" id="modalConfirmarAcceso" tabindex="-1" aria-labelledby="modalDarLabel" aria-hidden="true">
    <div class="modal-dialog">
      <div class="modal-content">
        <div class="modal-header">
          <h5 class="modal-title" id="modalDarLabel">Confirmar Acceso</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>
        <div class="modal-body">
          <p>¿Seguro que deseas dar acceso al sistema al usuario?</p>
          <p><strong>Nombre:</strong> <span id="nombreUsuarioDar"></span></p>
          <p><strong>Documento:</strong> <span id="documentoUsuarioDar"></span></p>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cerrar</button>
          <button type="button" class="btn btn-primary" id="btnConfirmarDarAcceso">Aceptar</button>
        </div>
      </div>
    </div>
  </div>

  <!-- Modal para QUITAR Acceso -->
  <div class="modal fade" id="modalQuitarAcceso" tabindex="-1" aria-labelledby="modalQuitarLabel" aria-hidden="true">
    <div class="modal-dialog">
      <div class="modal-content">
        <div class="modal-header">
          <h5 class="modal-title" id="modalQuitarLabel">Confirmar Revocación de Acceso</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>
        <div class="modal-body">
          <p>¿Seguro que deseas quitar el acceso al sistema al usuario?</p>
          <p><strong>Nombre:</strong> <span id="nombreUsuarioQuitar"></span></p>
          <p><strong>Documento:</strong> <span id="documentoUsuarioQuitar"></span></p>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cerrar</button>
          <button type="button" class="btn btn-danger" id="btnConfirmarQuitarAcceso">Quitar Acceso</button>
        </div>
      </div>
    </div>
  </div>

  <?php include 'modals_usuarios/usuario_modals.php'; ?>
  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
  <script src="modals_usuarios/usuarios-scripts.js"></script>
  
  <script>
    document.addEventListener('DOMContentLoaded', function () {
        // Lógica para DAR acceso
        const modalDarAcceso = document.getElementById('modalConfirmarAcceso');
        let docParaDarAcceso = null;
        modalDarAcceso.addEventListener('show.bs.modal', function (event) {
            const button = event.relatedTarget;
            docParaDarAcceso = button.getAttribute('data-documento');
            modalDarAcceso.querySelector('#nombreUsuarioDar').textContent = button.getAttribute('data-nombre');
            modalDarAcceso.querySelector('#documentoUsuarioDar').textContent = docParaDarAcceso;
        });
        document.getElementById('btnConfirmarDarAcceso').addEventListener('click', function () {
            if (docParaDarAcceso) {
                fetch('dar_acceso.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                    body: 'documento=' + encodeURIComponent(docParaDarAcceso)
                }).then(res => res.json()).then(data => {
                    alert(data.message);
                    if (data.success) window.location.reload();
                });
            }
        });

        // Lógica para QUITAR acceso
        const modalQuitarAcceso = document.getElementById('modalQuitarAcceso');
        let docParaQuitarAcceso = null;
        modalQuitarAcceso.addEventListener('show.bs.modal', function (event) {
            const button = event.relatedTarget;
            docParaQuitarAcceso = button.getAttribute('data-documento');
            modalQuitarAcceso.querySelector('#nombreUsuarioQuitar').textContent = button.getAttribute('data-nombre');
            modalQuitarAcceso.querySelector('#documentoUsuarioQuitar').textContent = docParaQuitarAcceso;
        });
        document.getElementById('btnConfirmarQuitarAcceso').addEventListener('click', function () {
            if (docParaQuitarAcceso) {
                fetch('quitar_acceso.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                    body: 'documento=' + encodeURIComponent(docParaQuitarAcceso)
                }).then(res => res.json()).then(data => {
                    alert(data.message);
                    if (data.success) window.location.reload();
                });
            }
        });

        // Lógica de paginación y filtro (sin cambios)
        const filasPorPagina = 5;
        const tabla = document.getElementById('tablaUsuarios');
        const filas = Array.from(tabla.querySelectorAll('tbody tr'));
        const paginacionContainer = document.getElementById('paginacion');
        let filasFiltradas = filas;

        function mostrarPagina(pagina) {
            const inicio = (pagina - 1) * filasPorPagina;
            const fin = inicio + filasPorPagina;
            filas.forEach(fila => fila.style.display = 'none');
            filasFiltradas.slice(inicio, fin).forEach(fila => fila.style.display = '');
            const botones = paginacionContainer.querySelectorAll('.page-item');
            botones.forEach(btn => btn.classList.remove('active'));
            if(botones[pagina - 1]) botones[pagina - 1].classList.add('active');
        }

        function crearBotones() {
            paginacionContainer.innerHTML = '';
            const totalPaginas = Math.ceil(filasFiltradas.length / filasPorPagina);
            for (let i = 1; i <= totalPaginas; i++) {
                const li = document.createElement('li');
                li.className = 'page-item';
                const a = document.createElement('a');
                a.className = 'page-link';
                a.href = '#';
                a.textContent = i;
                a.addEventListener('click', (e) => { e.preventDefault(); mostrarPagina(i); });
                li.appendChild(a);
                paginacionContainer.appendChild(li);
            }
        }

        window.filtrarYPaginar = function() {
            const filtro = document.getElementById('buscar').value.toLowerCase();
            filasFiltradas = filas.filter(fila => fila.textContent.toLowerCase().includes(filtro));
            crearBotones();
            mostrarPagina(1);
        }

        filtrarYPaginar();
    });
  </script>
</body>
</html>