<?php
$byClass = [];
foreach ($students as $st) {
    $key = $st['class_name'] . ' — ' . $st['section_name'];
    $byClass[$key][] = $st;
}
$statusLabel = ['P' => 'IN', 'A' => 'OUT', 'L' => 'LATE', 'EA' => 'EXCUSED', '' => '—'];
$statusColor = ['P' => '#27ae60', 'A' => '#e74c3c', 'L' => '#f39c12', 'EA' => '#3498db', '' => '#999'];
$total   = count($students);
$present = count(array_filter($students, fn($s) => $s['att_status'] === 'P'));
$absent  = count(array_filter($students, fn($s) => $s['att_status'] === 'A'));
$unknown = count(array_filter($students, fn($s) => $s['att_status'] === ''));
?>
<div class="row mb-sm">
	<div class="col-md-3">
		<div class="panel panel-default text-center" style="padding:14px;">
			<div style="font-size:12px;color:#888;">Total Enrolled</div>
			<div style="font-size:26px;font-weight:700;"><?=$total?></div>
		</div>
	</div>
	<div class="col-md-3">
		<div class="panel panel-default text-center" style="padding:14px;">
			<div style="font-size:12px;color:#888;">In School (Present)</div>
			<div style="font-size:26px;font-weight:700;color:#27ae60;"><?=$present?></div>
		</div>
	</div>
	<div class="col-md-3">
		<div class="panel panel-default text-center" style="padding:14px;">
			<div style="font-size:12px;color:#888;">Absent / Out</div>
			<div style="font-size:26px;font-weight:700;color:#e74c3c;"><?=$absent?></div>
		</div>
	</div>
	<div class="col-md-3">
		<div class="panel panel-default text-center" style="padding:14px;">
			<div style="font-size:12px;color:#888;">Not Yet Marked</div>
			<div style="font-size:26px;font-weight:700;color:#999;"><?=$unknown?></div>
		</div>
	</div>
</div>

<section class="panel">
	<header class="panel-heading" style="display:flex;justify-content:space-between;align-items:center;">
		<h4 class="panel-title"><i class="fas fa-fire-extinguisher text-danger"></i> Fire Register — <?=date('d M Y', strtotime($today))?></h4>
		<button onclick="window.print()" class="btn btn-default btn-sm"><i class="fas fa-print"></i> Print</button>
	</header>
	<div class="panel-body">
		<?php if (empty($students)): ?>
		<div class="alert alert-info"><i class="fas fa-info-circle"></i> No students enrolled for this branch.</div>
		<?php else: foreach ($byClass as $className => $rows): ?>
		<h5 style="margin-top:20px;padding:6px 10px;background:#f5f5f5;border-left:4px solid #e74c3c;">
			<i class="fas fa-users"></i> <?=html_escape($className)?>
			<span class="badge"><?=count($rows)?></span>
		</h5>
		<div class="table-responsive">
			<table class="table table-condensed table-bordered mb-sm">
				<thead>
					<tr>
						<th>#</th>
						<th>Name</th>
						<th>Reg No</th>
						<th style="width:100px;text-align:center;">Status</th>
					</tr>
				</thead>
				<tbody>
					<?php $i = 1; foreach ($rows as $st): ?>
					<tr>
						<td><?=$i++?></td>
						<td><?=html_escape($st['student_name'])?></td>
						<td><?=html_escape($st['register_no'])?></td>
						<td style="text-align:center;">
							<strong style="color:<?=$statusColor[$st['att_status']]?>">
								<?=$statusLabel[$st['att_status']]?>
							</strong>
						</td>
					</tr>
					<?php endforeach; ?>
				</tbody>
			</table>
		</div>
		<?php endforeach; endif; ?>
	</div>
</section>
<style>
@media print {
	.panel-heading button, .sidebar, nav { display:none!important; }
	.panel { border:none; }
}
</style>
