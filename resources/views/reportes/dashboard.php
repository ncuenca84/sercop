<div class="d-flex justify-content-between align-items-center mb-4">
  <div><h4 class="fw-bold mb-1"><i class="bi bi-bar-chart-line me-2 text-primary"></i>Reportes y Business Intelligence</h4>
  <p class="text-muted small mb-0">Análisis de ingresos, contratos y comportamiento de pago</p></div>
  <a href="javascript:window.print()" class="btn btn-outline-secondary no-print">
    <i class="bi bi-printer me-1"></i>Imprimir Reporte
  </a>
</div>

<!-- KPI rápidos -->
<div class="row g-3 mb-4">
  <?php
  $totalMonto = array_sum(array_column($resumen['por_estado'], 'monto'));
  $totalPagado = array_sum(array_map(fn($r)=>$r['estado']==='pagado'?$r['monto']:0, $resumen['por_estado']));
  $ingMes = end($resumen['ingresos_mensual']);
  ?>
  <div class="col-6 col-md-3"><div class="card text-center p-3">
    <div class="fs-4 fw-bold text-primary"><?= money($totalMonto) ?></div>
    <small class="text-muted">Monto Total Contratos</small>
  </div></div>
  <div class="col-6 col-md-3"><div class="card text-center p-3">
    <div class="fs-4 fw-bold text-success"><?= money($totalPagado) ?></div>
    <small class="text-muted">Total Cobrado</small>
  </div></div>
  <div class="col-6 col-md-3"><div class="card text-center p-3">
    <div class="fs-4 fw-bold text-info"><?= round($resumen['tiempo_pago_prom'] ?? 0) ?> días</div>
    <small class="text-muted">Promedio Cobro</small>
  </div></div>
  <div class="col-6 col-md-3"><div class="card text-center p-3">
    <div class="fs-4 fw-bold text-warning"><?= money($ingMes['total'] ?? 0) ?></div>
    <small class="text-muted">Ingresos Último Mes</small>
  </div></div>
</div>

<div class="row g-4 mb-4">
  <!-- Ingresos mensuales -->
  <div class="col-lg-8">
    <div class="card h-100">
      <div class="card-header fw-semibold"><i class="bi bi-graph-up me-2 text-success"></i>Ingresos Mensuales <?= date('Y') ?></div>
      <div class="card-body"><canvas id="chartMensual" height="100"></canvas></div>
    </div>
  </div>
  <!-- Por estado -->
  <div class="col-lg-4">
    <div class="card h-100">
      <div class="card-header fw-semibold"><i class="bi bi-pie-chart me-2 text-primary"></i>Contratos por Estado</div>
      <div class="card-body"><canvas id="chartEstado"></canvas></div>
    </div>
  </div>
</div>

<div class="row g-4">
  <!-- Por institución -->
  <div class="col-lg-6">
    <div class="card">
      <div class="card-header fw-semibold"><i class="bi bi-building me-2 text-primary"></i>Top Instituciones por Monto</div>
      <div class="card-body">
        <?php foreach(array_slice($resumen['por_institucion'],0,8) as $i): ?>
        <div class="mb-3">
          <div class="d-flex justify-content-between mb-1">
            <small class="fw-semibold"><?= e(truncate($i['nombre'],35)) ?></small>
            <small class="text-muted"><?= money($i['monto']) ?></small>
          </div>
          <div class="progress" style="height:8px">
            <div class="progress-bar" style="width:<?= $totalMonto>0?min(100,round($i['monto']/$totalMonto*100)):0 ?>%;background:#2E86C1"></div>
          </div>
          <small class="text-muted"><?= $i['total'] ?> proceso<?= $i['total']!=1?'s':'' ?></small>
        </div>
        <?php endforeach; ?>
        <?php if(empty($resumen['por_institucion'])): ?>
        <p class="text-muted text-center">Sin datos</p>
        <?php endif; ?>
      </div>
    </div>
  </div>

  <!-- Detalle por estado -->
  <div class="col-lg-6">
    <div class="card">
      <div class="card-header fw-semibold"><i class="bi bi-list-check me-2 text-primary"></i>Detalle por Estado</div>
      <div class="table-responsive">
        <table class="table table-sm mb-0">
          <thead><tr><th>Estado</th><th>Contratos</th><th>Monto Total</th><th>%</th></tr></thead>
          <tbody>
          <?php foreach($resumen['por_estado'] as $e): ?>
          <tr>
            <td><?= estadoBadge($e['estado']) ?></td>
            <td><?= $e['total'] ?></td>
            <td class="fw-semibold"><?= money($e['monto']) ?></td>
            <td><small class="text-muted"><?= $totalMonto>0?round($e['monto']/$totalMonto*100):0 ?>%</small></td>
          </tr>
          <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>
</div>

<script>
// Gráfico ingresos mensuales
const mensualData = <?= json_encode(array_values($resumen['ingresos_mensual'])) ?>;
new Chart(document.getElementById('chartMensual'), {
  type: 'bar',
  data: {
    labels: mensualData.map(d => d.mes),
    datasets: [{
      label: 'Ingresos (USD)',
      data: mensualData.map(d => parseFloat(d.total)),
      backgroundColor: '#2E86C1',
      borderRadius: 6,
    }]
  },
  options: {
    responsive: true,
    plugins: { legend: { display: false } },
    scales: { y: { ticks: { callback: v => '$'+v.toLocaleString() } } }
  }
});

// Gráfico por estado
const estadoData = <?= json_encode(array_values($resumen['por_estado'])) ?>;
const colores = {'pagado':'#27ae60','en_ejecucion':'#2E86C1','facturado':'#8e44ad','adjudicado':'#e67e22','cerrado':'#95a5a6','cancelado':'#e74c3c','entregado_definitivo':'#16a085'};
new Chart(document.getElementById('chartEstado'), {
  type: 'doughnut',
  data: {
    labels: estadoData.map(d => d.estado.replace(/_/g,' ')),
    datasets: [{
      data: estadoData.map(d => d.total),
      backgroundColor: estadoData.map(d => colores[d.estado] || '#bdc3c7'),
      borderWidth: 2,
    }]
  },
  options: { responsive: true, plugins: { legend: { position: 'bottom', labels: { font: { size: 11 } } } } }
});
</script>
