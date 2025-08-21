<?php
// Configuración de conexión
$serverName = "192.168.2.244";
$connectionOptions = [
    "Database" => "master",
    "Uid" => "sa",
    "PWD" => "Sky2022*!",
    "TrustServerCertificate" => true // Solo para pruebas, no en producción
];
$refresh_interval = 60; // Intervalo de actualización en segundos

// Función para ejecutar consultas
function runQuery($query, $server, $db, $user, $pass) {
    $connOptions = [
        "Database" => $db,
        "Uid" => $user,
        "PWD" => $pass,
        "TrustServerCertificate" => true
    ];
    try {
        $conn = sqlsrv_connect($server, $connOptions);
        if ($conn === false) {
            throw new Exception(print_r(sqlsrv_errors(), true));
        }
        $result = sqlsrv_query($conn, $query);
        if ($result === false) {
            throw new Exception(print_r(sqlsrv_errors(), true));
        }
        $data = [];
        while ($row = sqlsrv_fetch_array($result, SQLSRV_FETCH_ASSOC)) {
            $data[] = $row;
        }
        sqlsrv_free_stmt($result);
        sqlsrv_close($conn);
        return $data;
    } catch (Exception $e) {
        return ["error" => "Error: " . $e->getMessage()];
    }
}

// Función para matar sesiones
function killSessions($session_ids, $server, $db, $user, $pass) {
    $connOptions = [
        "Database" => $db,
        "Uid" => $user,
        "PWD" => $pass,
        "TrustServerCertificate" => true
    ];
    try {
        $conn = sqlsrv_connect($server, $connOptions);
        if ($conn === false) {
            throw new Exception(print_r(sqlsrv_errors(), true));
        }
        $messages = [];
        foreach ($session_ids as $session_id) {
            $query = "KILL " . intval($session_id) . ";";
            $result = sqlsrv_query($conn, $query);
            if ($result === false) {
                $messages[] = "Error al detener la sesión $session_id: " . print_r(sqlsrv_errors(), true);
            } else {
                $messages[] = "Sesión $session_id detenida exitosamente.";
            }
        }
        sqlsrv_close($conn);
        return $messages;
    } catch (Exception $e) {
        return ["Error: " . $e->getMessage()];
    }
}

// Manejar solicitud de detener sesiones
if (isset($_POST['kill_session'])) {
    $session_id = isset($_POST['session_id']) ? intval($_POST['session_id']) : 0;
    if ($session_id > 0) {
        $results = killSessions([$session_id], $serverName, "master", $connectionOptions["Uid"], $connectionOptions["PWD"]);
        foreach ($results as $message) {
            echo "<div class='alert alert-info'>" . htmlspecialchars($message) . "</div>";
        }
    }
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Monitor SQL Server</title>
    <link rel="icon" type="image/png" href="https://img.freepik.com/vector-premium/diseno-logotipo-icono-pc-computadora_775854-1632.jpg">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.min.js"></script>
    <meta http-equiv="refresh" content="<?php echo $refresh_interval; ?>">
</head>
<body class="bg-light">
    <div class="container mt-4">
        <h1 class="text-center">Monitor Avanzado de SQL Server</h1>

        <!-- Pestañas -->
        <ul class="nav nav-tabs" id="myTab" role="tablist">
            <li class="nav-item">
                <a class="nav-link active" id="resumen-tab" data-bs-toggle="tab" href="#resumen" role="tab">Resumen</a>
            </li>
            <li class="nav-item">
                <a class="nav-link" id="rendimiento-tab" data-bs-toggle="tab" href="#rendimiento" role="tab">Rendimiento</a>
            </li>
            <li class="nav-item">
                <a class="nav-link" id="consultas-tab" data-bs-toggle="tab" href="#consultas" role="tab">Consultas Lentas</a>
            </li>
            <li class="nav-item">
                <a class="nav-link" id="bloqueos-tab" data-bs-toggle="tab" href="#bloqueos" role="tab">Bloqueos/Espera</a>
            </li>
            <li class="nav-item">
                <a class="nav-link" id="databases-tab" data-bs-toggle="tab" href="#databases" role="tab">Bases de Datos</a>
            </li>
            <li class="nav-item">
                <a class="nav-link" id="indices-tab" data-bs-toggle="tab" href="#indices" role="tab">Índices</a>
            </li>
        </ul>

        <div class="tab-content mt-3" id="myTabContent">
            <!-- Resumen General -->
            <div class="tab-pane fade show active" id="resumen" role="tabpanel">
                <h2>Resumen General</h2>
                <?php
                $query_version = "SELECT @@VERSION AS Version, sqlserver_start_time AS Uptime FROM sys.dm_os_sys_info;";
                $version_data = runQuery($query_version, $serverName, "master", $connectionOptions["Uid"], $connectionOptions["PWD"]);
                if (isset($version_data['error'])) {
                    echo "<div class='alert alert-danger'>" . $version_data['error'] . "</div>";
                } else {
                    echo "<table class='table table-bordered'><tr><th>Versión</th><th>Uptime</th></tr>";
                    foreach ($version_data as $row) {
                        echo "<tr><td>" . htmlspecialchars($row['Version']) . "</td><td>" . $row['Uptime']->format('Y-m-d H:i:s') . "</td></tr>";
                    }
                    echo "</table>";
                }

                $query_overview = "SELECT 
                    (SELECT COUNT(*) FROM sys.databases) AS TotalDatabases,
                    (SELECT COUNT(*) FROM sys.dm_exec_sessions WHERE is_user_process = 1) AS ActiveSessions,
                    (SELECT cntr_value FROM sys.dm_os_performance_counters WHERE counter_name = 'User Connections') AS UserConnections;";
                $overview_data = runQuery($query_overview, $serverName, "master", $connectionOptions["Uid"], $connectionOptions["PWD"]);
                if (isset($overview_data['error'])) {
                    echo "<div class='alert alert-danger'>" . $overview_data['error'] . "</div>";
                } else {
                    echo "<table class='table table-bordered'><tr><th>Total Bases de Datos</th><th>Sesiones Activas</th><th>Conexiones</th></tr>";
                    foreach ($overview_data as $row) {
                        echo "<tr><td>" . $row['TotalDatabases'] . "</td><td>" . $row['ActiveSessions'] . "</td><td>" . $row['UserConnections'] . "</td></tr>";
                    }
                    echo "</table>";
                    echo "<canvas id='overviewChart' class='mt-3'></canvas>";
                    $chart_data = json_encode(array_values($overview_data[0]));
                    ?>
                    <script>
                        new Chart(document.getElementById('overviewChart'), {
                            type: 'bar',
                            data: {
                                labels: ['Bases de Datos', 'Sesiones Activas', 'Conexiones'],
                                datasets: [{
                                    label: 'Métricas',
                                    data: <?php echo $chart_data; ?>,
                                    backgroundColor: ['#007bff', '#28a745', '#dc3545'],
                                    borderColor: ['#0056b3', '#218838', '#c82333'],
                                    borderWidth: 1
                                }]
                            },
                            options: { scales: { y: { beginAtZero: true } } }
                        });
                    </script>
                    <?php
                }
                ?>
            </div>

            <!-- Rendimiento -->
            <div class="tab-pane fade" id="rendimiento" role="tabpanel">
                <h2>Rendimiento: CPU y Memoria</h2>
                <?php
                $query_cpu = "SELECT 
                    (SELECT TOP 1 record.value('(./Record/SchedulerMonitorEvent/SystemHealth/ProcessUtilization)[1]', 'int') 
                     FROM (SELECT timestamp, CONVERT(XML, record) AS record FROM sys.dm_os_ring_buffers WHERE ring_buffer_type = 'RING_BUFFER_SCHEDULER_MONITOR') AS x) AS SQLCPU,
                    (SELECT cntr_value FROM sys.dm_os_performance_counters WHERE counter_name = 'Processor Time %' AND instance_name = '_Total') AS TotalCPU;";
                $cpu_data = runQuery($query_cpu, $serverName, "master", $connectionOptions["Uid"], $connectionOptions["PWD"]);
                if (isset($cpu_data['error'])) {
                    echo "<div class='alert alert-danger'>" . $cpu_data['error'] . "</div>";
                } else {
                    echo "<table class='table table-bordered'><tr><th>CPU SQL (%)</th><th>CPU Total (%)</th></tr>";
                    foreach ($cpu_data as $row) {
                        echo "<tr><td>" . $row['SQLCPU'] . "</td><td>" . $row['TotalCPU'] . "</td></tr>";
                    }
                    echo "</table>";
                }

                $query_memory = "SELECT 
                    total_physical_memory_kb / 1024 AS TotalMemoryMB,
                    available_physical_memory_kb / 1024 AS AvailableMemoryMB,
                    system_memory_state_desc AS MemoryState
                FROM sys.dm_os_sys_memory;";
                $memory_data = runQuery($query_memory, $serverName, "master", $connectionOptions["Uid"], $connectionOptions["PWD"]);
                if (isset($memory_data['error'])) {
                    echo "<div class='alert alert-danger'>" . $memory_data['error'] . "</div>";
                } else {
                    echo "<table class='table table-bordered'><tr><th>Memoria Total (MB)</th><th>Memoria Disponible (MB)</th><th>Estado</th></tr>";
                    foreach ($memory_data as $row) {
                        echo "<tr><td>" . $row['TotalMemoryMB'] . "</td><td>" . $row['AvailableMemoryMB'] . "</td><td>" . $row['MemoryState'] . "</td></tr>";
                    }
                    echo "</table>";
                    echo "<canvas id='memoryChart' class='mt-3'></canvas>";
                    $mem_values = [$memory_data[0]['TotalMemoryMB'], $memory_data[0]['AvailableMemoryMB']];
                    ?>
                    <script>
                        new Chart(document.getElementById('memoryChart'), {
                            type: 'pie',
                            data: {
                                labels: ['Total', 'Disponible'],
                                datasets: [{
                                    data: <?php echo json_encode($mem_values); ?>,
                                    backgroundColor: ['#007bff', '#28a745'],
                                    borderColor: ['#0056b3', '#218838'],
                                    borderWidth: 1
                                }]
                            }
                        });
                    </script>
                    <?php
                }
                ?>
            </div>

          <!-- Consultas Lentas -->
<div class="tab-pane fade" id="consultas" role="tabpanel">
    <h2>Consultas Activas con Alto Uso de CPU</h2>
    <div class="mb-3 mt-3">
        <label for="adminPass" class="form-label">Contraseña de administrador:</label>
        <input type="password" id="adminPass" class="form-control" placeholder="Ingrese contraseña">
        <button class="btn btn-primary mt-2" onclick="verificarPass()">Validar</button>
        <div id="passMsg" class="mt-2 text-danger"></div>
    </div>
    <script>
        // Contraseña definida (cámbiala por la que quieras)
        const PASSWORD = "Sky2025*";  

        function verificarPass() {
            const inputPass = document.getElementById("adminPass").value;
            const msg = document.getElementById("passMsg");

            if (inputPass === PASSWORD) {
                msg.innerHTML = "<span class='text-success'>Acceso concedido. Ahora puede detener sesiones.</span>";
                // Habilitar todos los botones de detener
                document.querySelectorAll(".btn-detener").forEach(btn => {
                    btn.disabled = false;
                });
            } else {
                msg.innerHTML = "Contraseña incorrecta.";
            }
        }
    </script>

    <?php
    $query_top_queries = "
        SELECT TOP 10 
            r.session_id,
            t.text AS QueryText,
            r.cpu_time AS CPUMilliseconds,
            r.total_elapsed_time / 60000.0 AS DurationMinutes,
            r.total_elapsed_time / 1000.0 AS ExecutionTimeSeconds,
            s.login_name
        FROM sys.dm_exec_requests r
        CROSS APPLY sys.dm_exec_sql_text(r.sql_handle) t
        INNER JOIN sys.dm_exec_sessions s ON r.session_id = s.session_id
        WHERE s.is_user_process = 1
        ORDER BY r.cpu_time DESC;";
    $top_queries = runQuery($query_top_queries, $serverName, "master", $connectionOptions["Uid"], $connectionOptions["PWD"]);
    
    // Calcular porcentaje de CPU relativo al máximo CPUMilliseconds
    $max_cpu = 0;
    if (!isset($top_queries['error']) && !empty($top_queries)) {
        foreach ($top_queries as $row) {
            if ($row['CPUMilliseconds'] > $max_cpu) {
                $max_cpu = $row['CPUMilliseconds'];
            }
        }
    }
    
    if (isset($top_queries['error'])) {
        echo "<div class='alert alert-danger'>" . $top_queries['error'] . "</div>";
    } else {
        echo "<table class='table table-bordered'><tr><th>Sesión</th><th>Consulta</th><th>Usuario</th><th>CPU (%)</th><th>Duración (min)</th><th>Tiempo de Ejecución (s)</th><th>Acción</th></tr>";
        foreach ($top_queries as $row) {
            $cpu_percent = $max_cpu > 0 ? ($row['CPUMilliseconds'] / $max_cpu * 100) : 0;
            echo "<tr>";
            echo "<td>" . $row['session_id'] . "</td>";
            echo "<td style='max-width: 300px; overflow: auto;'>" . htmlspecialchars(substr($row['QueryText'], 0, 100)) . "...</td>";
            echo "<td>" . htmlspecialchars($row['login_name']) . "</td>";
            echo "<td>" . number_format($cpu_percent, 2) . "</td>";
            echo "<td>" . number_format($row['DurationMinutes'], 4) . "</td>";
            echo "<td>" . number_format($row['ExecutionTimeSeconds'], 2) . "</td>";
            echo "<td>";
            if ($cpu_percent > 20) {
                echo "<form method='POST' style='display:inline;' onsubmit='return confirm(\"¿Estás seguro de que deseas detener la sesión " . $row['session_id'] . "?\");'>";
                echo "<input type='hidden' name='session_id' value='" . $row['session_id'] . "'>";
                echo "<button type='submit' name='kill_session' class='btn btn-danger btn-sm btn-detener' disabled>Detener</button>";
                echo "</form>";
            } else {
                echo "-";
            }
            echo "</td>";
            echo "</tr>";
        }
        echo "</table>";
        
        echo "<canvas id='queriesChart' class='mt-3'></canvas>";
        $labels = array_column($top_queries, 'QueryText');
        $labels = array_map(function($text) { return substr($text, 0, 20) . '...'; }, $labels);
        $data = array_map(function($row) use ($max_cpu) { return $max_cpu > 0 ? ($row['CPUMilliseconds'] / $max_cpu * 100) : 0; }, $top_queries);
        ?>
        <script>
            new Chart(document.getElementById('queriesChart'), {
                type: 'bar',
                data: {
                    labels: <?php echo json_encode($labels); ?>,
                    datasets: [{
                        label: 'CPU (%)',
                        data: <?php echo json_encode($data); ?>,
                        backgroundColor: '#007bff',
                        borderColor: '#0056b3',
                        borderWidth: 1
                    }]
                },
                options: { scales: { y: { beginAtZero: true, title: { display: true, text: 'CPU (%)' } } } }
            });
        </script>
        <?php
    }
    ?>
</div>

            <!-- Bloqueos y Esperas -->
            <div class="tab-pane fade" id="bloqueos" role="tabpanel">
                <h2>Bloqueos y Esperas</h2>
                <?php
                $query_waits = "SELECT TOP 10 
                    wait_type, 
                    waiting_tasks_count, 
                    wait_time_ms / 1000.0 AS WaitTimeSeconds
                FROM sys.dm_os_wait_stats
                WHERE waiting_tasks_count > 0
                ORDER BY wait_time_ms DESC;";
                $waits_data = runQuery($query_waits, $serverName, "master", $connectionOptions["Uid"], $connectionOptions["PWD"]);
                if (isset($waits_data['error'])) {
                    echo "<div class='alert alert-danger'>" . $waits_data['error'] . "</div>";
                } else {
                    echo "<table class='table table-bordered'><tr><th>Tipo de Espera</th><th>Tareas en Espera</th><th>Tiempo de Espera (s)</th></tr>";
                    foreach ($waits_data as $row) {
                        echo "<tr><td>" . $row['wait_type'] . "</td><td>" . $row['waiting_tasks_count'] . "</td><td>" . $row['WaitTimeSeconds'] . "</td></tr>";
                    }
                    echo "</table>";
                    echo "<canvas id='waitsChart' class='mt-3'></canvas>";
                    $wait_labels = array_column($waits_data, 'wait_type');
                    $wait_data = array_column($waits_data, 'WaitTimeSeconds');
                    ?>
                    <script>
                        new Chart(document.getElementById('waitsChart'), {
                            type: 'bar',
                            data: {
                                labels: <?php echo json_encode($wait_labels); ?>,
                                datasets: [{
                                    label: 'Tiempo de Espera (s)',
                                    data: <?php echo json_encode($wait_data); ?>,
                                    backgroundColor: '#dc3545',
                                    borderColor: '#c82333',
                                    borderWidth: 1
                                }]
                            },
                            options: { scales: { y: { beginAtZero: true } } }
                        });
                    </script>
                    <?php
                }

                $query_blocks = "SELECT 
                    session_id AS BlockedSession,
                    blocking_session_id AS BlockingSession,
                    wait_type,
                    wait_time / 1000.0 AS WaitSeconds
                FROM sys.dm_exec_requests
                WHERE blocking_session_id <> 0;";
                $blocks_data = runQuery($query_blocks, $serverName, "master", $connectionOptions["Uid"], $connectionOptions["PWD"]);
                if (isset($blocks_data['error'])) {
                    echo "<div class='alert alert-danger'>" . $blocks_data['error'] . "</div>";
                } else {
                    echo "<table class='table table-bordered'><tr><th>Sesión Bloqueada</th><th>Sesión Bloqueadora</th><th>Tipo de Espera</th><th>Tiempo (s)</th></tr>";
                    foreach ($blocks_data as $row) {
                        echo "<tr><td>" . $row['BlockedSession'] . "</td><td>" . $row['BlockingSession'] . "</td><td>" . $row['wait_type'] . "</td><td>" . $row['WaitSeconds'] . "</td></tr>";
                    }
                    echo "</table>";
                }
                ?>
            </div>

            <!-- Bases de Datos -->
            <div class="tab-pane fade" id="databases" role="tabpanel">
                <h2>Bases de Datos</h2>
                <?php
                $query_dbs = "SELECT 
                    name AS DatabaseName,
                    state_desc AS State,
                    recovery_model_desc AS RecoveryModel,
                    (SELECT SUM(size * 8 / 1024) FROM sys.master_files WHERE database_id = d.database_id) AS SizeMB
                FROM sys.databases d;";
                $dbs_data = runQuery($query_dbs, $serverName, "master", $connectionOptions["Uid"], $connectionOptions["PWD"]);
                if (isset($dbs_data['error'])) {
                    echo "<div class='alert alert-danger'>" . $dbs_data['error'] . "</div>";
                } else {
                    echo "<table class='table table-bordered'><tr><th>Base de Datos</th><th>Estado</th><th>Modelo de Recuperación</th><th>Tamaño (MB)</th></tr>";
                    foreach ($dbs_data as $row) {
                        echo "<tr><td>" . $row['DatabaseName'] . "</td><td>" . $row['State'] . "</td><td>" . $row['RecoveryModel'] . "</td><td>" . $row['SizeMB'] . "</td></tr>";
                    }
                    echo "</table>";
                    echo "<canvas id='dbsChart' class='mt-3'></canvas>";
                    $db_labels = array_column($dbs_data, 'DatabaseName');
                    $db_sizes = array_column($dbs_data, 'SizeMB');
                    ?>
                    <script>
                        new Chart(document.getElementById('dbsChart'), {
                            type: 'pie',
                            data: {
                                labels: <?php echo json_encode($db_labels); ?>,
                                datasets: [{
                                    data: <?php echo json_encode($db_sizes); ?>,
                                    backgroundColor: ['#007bff', '#28a745', '#dc3545', '#ffc107', '#17a2b8'],
                                    borderColor: ['#0056b3', '#218838', '#c82333', '#e0a800', '#138496'],
                                    borderWidth: 1
                                }]
                            }
                        });
                    </script>
                    <?php
                }
                ?>
            </div>

            <!-- Índices y Estadísticas -->
            <div class="tab-pane fade" id="indices" role="tabpanel">
                <h2>Índices y Estadísticas</h2>
                <?php
                $query_indexes = "SELECT 
                    OBJECT_NAME(i.object_id) AS TableName,
                    i.name AS IndexName,
                    ips.avg_fragmentation_in_percent AS Fragmentation
                FROM sys.dm_db_index_physical_stats(DB_ID(), NULL, NULL, NULL, NULL) ips
                INNER JOIN sys.indexes i ON ips.object_id = i.object_id AND ips.index_id = i.index_id
                WHERE ips.avg_fragmentation_in_percent > 10
                ORDER BY Fragmentation DESC;";
                $indexes_data = runQuery($query_indexes, $serverName, "master", $connectionOptions["Uid"], $connectionOptions["PWD"]);
                if (isset($indexes_data['error'])) {
                    echo "<div class='alert alert-danger'>" . $indexes_data['error'] . "</div>";
                } else {
                    echo "<table class='table table-bordered'><tr><th>Tabla</th><th>Índice</th><th>Fragmentación (%)</th></tr>";
                    foreach ($indexes_data as $row) {
                        echo "<tr><td>" . $row['TableName'] . "</td><td>" . $row['IndexName'] . "</td><td>" . $row['Fragmentation'] . "</td></tr>";
                    }
                    echo "</table>";
                }

                $query_stats = "SELECT 
                    OBJECT_NAME(s.object_id) AS TableName,
                    s.name AS StatisticName,
                    STATS_DATE(s.object_id, s.stats_id) AS LastUpdated
                FROM sys.stats s
                WHERE STATS_DATE(s.object_id, s.stats_id) < DATEADD(day, -7, GETDATE());";
                $stats_data = runQuery($query_stats, $serverName, "master", $connectionOptions["Uid"], $connectionOptions["PWD"]);
                if (isset($stats_data['error'])) {
                    echo "<div class='alert alert-danger'>" . $stats_data['error'] . "</div>";
                } else {
                    echo "<table class='table table-bordered'><tr><th>Tabla</th><th>Estadística</th><th>Última Actualización</th></tr>";
                    foreach ($stats_data as $row) {
                        echo "<tr><td>" . $row['TableName'] . "</td><td>" . $row['StatisticName'] . "</td><td>" . ($row['LastUpdated'] ? $row['LastUpdated']->format('Y-m-d H:i:s') : 'N/A') . "</td></tr>";
                    }
                    echo "</table>";
                }
                ?>
            </div>
        </div>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>