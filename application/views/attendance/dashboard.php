<?php
$stats = $stats ?? [];
$total   = (int)($stats['total_students'] ?? 0);
$present = (int)($stats['present'] ?? 0);
$absent  = (int)($stats['absent']  ?? 0);
$late    = (int)($stats['late']    ?? 0);
$excused = (int)($stats['excused'] ?? 0);
?>
<div class="row mb-md">
	<div class="col-md-12">
		<div class="tabs-custom">
			<ul class="nav nav-tabs">
				<li class="active"><a href="#tab-daily" data-toggle="tab"><i class="fas fa-calendar-day"></i> Daily Attendance</a></li>
				<?php if (get_permission('student_attendance', 'is_add')): ?>
				<li><a href="<?=base_url('attendance/bulk_action')?>"><i class="fas fa-tasks"></i> Bulk Actions</a></li>
				<li><a href="<?=base_url('attendance/fire_register')?>"><i class="fas fa-fire-extinguisher"></i> Fire Register</a></li>
				<?php endif; ?>
				<?php if (get_permission('student_attendance_report', 'is_view')): ?>
				<li><a href="<?=base_url('attendance/studentwise_report')?>"><i class="fas fa-chart-bar"></i> Reports & Analytics</a></li>
				<?php endif; ?>
			</ul>
			<div class="tab-content">
				<div id="tab-daily" class="tab-pane active">

	<!-- Stat tiles -->
	<div class="row mt-md mb-md">
		<div class="col-md-2 col-sm-4 col-xs-6 mb-sm">
			<div class="panel panel-default text-center" style="padding:16px 10px;">
				<div style="font-size:13px;color:#888;">Total Students</div>
				<div style="font-size:28px;font-weight:700;"><?=$total?></div>
				<i class="fas fa-users" style="font-size:22px;color:#aaa;"></i>
			</div>
		</div>
		<div class="col-md-2 col-sm-4 col-xs-6 mb-sm">
			<div class="panel panel-default text-center" style="padding:16px 10px;">
				<div style="font-size:13px;color:#888;">Present</div>
				<div style="font-size:28px;font-weight:700;color:#27ae60;"><?=$present?></div>
				<i class="fas fa-user-check" style="font-size:22px;color:#27ae60;"></i>
			</div>
		</div>
		<div class="col-md-2 col-sm-4 col-xs-6 mb-sm">
			<div class="panel panel-default text-center" style="padding:16px 10px;">
				<div style="font-size:13px;color:#888;">Absent</div>
				<div style="font-size:28px;font-weight:700;color:#e74c3c;"><?=$absent?></div>
				<i class="fas fa-exclamation-triangle" style="font-size:22px;color:#e74c3c;"></i>
			</div>
		</div>
		<div class="col-md-2 col-sm-4 col-xs-6 mb-sm">
			<div class="panel panel-default text-center" style="padding:16px 10px;">
				<div style="font-size:13px;color:#888;">Late</div>
				<div style="font-size:28px;font-weight:700;color:#f39c12;"><?=$late?></div>
				<i class="fas fa-clock" style="font-size:22px;color:#f39c12;"></i>
			</div>
		</div>
		<div class="col-md-2 col-sm-4 col-xs-6 mb-sm">
			<div class="panel panel-default text-center" style="padding:16px 10px;">
				<div style="font-size:13px;color:#888;">Excused</div>
				<div style="font-size:28px;font-weight:700;color:#3498db;"><?=$excused?></div>
				<i class="fas fa-calendar-check" style="font-size:22px;color:#3498db;"></i>
			</div>
		</div>
		<div class="col-md-2 col-sm-4 col-xs-6 mb-sm">
			<div class="panel panel-default text-center" style="padding:16px 10px;background:#f8f9fa;">
				<div style="font-size:12px;color:#888;">Today</div>
				<div style="font-size:14px;font-weight:600;"><?=date('d M Y', strtotime($today))?></div>
				<a href="<?=base_url('attendance/student_entry')?>" class="btn btn-default btn-xs mt-xs">
					<i class="fas fa-pencil-alt"></i> Enter
				</a>
			</div>
		</div>
	</div>

	<!-- Class cards -->
	<h5 class="text-muted mb-sm" style="font-size:13px;text-transform:uppercase;letter-spacing:1px;">Today's Classes</h5>
	<div class="row">
	<?php if (empty($class_cards)): ?>
		<div class="col-md-12">
			<div class="alert alert-info"><i class="fas fa-info-circle"></i> No classes enrolled for this branch.</div>
		</div>
	<?php else: foreach ($class_cards as $card):
		$exp  = (int)$card['expected'];
		$arr  = (int)$card['arrived'];
		$pct  = $exp > 0 ? round(($arr / $exp) * 100) : 0;
		$link = base_url('attendance/student_entry');
	?>
		<div class="col-md-3 col-sm-6 mb-sm">
			<a href="<?=$link?>" style="text-decoration:none;color:inherit;">
			<div class="panel panel-default" style="border-radius:6px;padding:14px 16px;">
				<div style="display:flex;justify-content:space-between;align-items:center;">
					<strong style="font-size:14px;"><?=html_escape($card['class_name'])?> — <?=html_escape($card['section_name'])?></strong>
					<i class="fas fa-users" style="font-size:18px;color:#bbb;"></i>
				</div>
				<div style="margin-top:10px;">
					<span style="font-size:28px;font-weight:700;color:<?=$arr > 0 ? '#27ae60' : 'inherit'?>"><?=$arr?></span>
					<span style="font-size:14px;color:#888;"> / <?=$exp?> Arrived</span>
				</div>
				<div style="display:flex;justify-content:space-between;margin-top:6px;font-size:12px;color:#999;">
					<span><?=$exp?> Expected</span>
					<span><?=$pct?>%</span>
				</div>
				<div style="height:4px;background:#eee;border-radius:2px;margin-top:6px;">
					<div style="height:4px;background:<?=$pct >= 80 ? '#27ae60' : ($pct >= 50 ? '#f39c12' : '#e74c3c')?>;border-radius:2px;width:<?=$pct?>%;"></div>
				</div>
				<?php if ($card['absent'] > 0 || $card['late'] > 0): ?>
				<div style="margin-top:6px;font-size:11px;">
					<?php if ($card['absent'] > 0): ?><span class="label label-danger"><?=$card['absent']?> absent</span> <?php endif; ?>
					<?php if ($card['late'] > 0):   ?><span class="label label-warning"><?=$card['late']?> late</span><?php endif; ?>
				</div>
				<?php endif; ?>
			</div>
			</a>
		</div>
	<?php endforeach; endif; ?>
	</div>

				</div><!-- /tab-pane -->
			</div>
		</div>
	</div>
</div>
