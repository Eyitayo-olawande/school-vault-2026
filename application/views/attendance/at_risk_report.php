<section class="panel">
	<header class="panel-heading">
		<h4 class="panel-title">
			<i class="fas fa-exclamation-triangle text-danger"></i>
			At-Risk Attendance Report — students below <?=(int)$threshold?>% threshold
		</h4>
	</header>
	<div class="panel-body">
		<div class="alert alert-info">
			<i class="fas fa-info-circle"></i>
			Showing students whose year-to-date attendance (P + L + 0.5×HD) is below
			<strong><?=(int)$threshold?>%</strong> for the current session.
			Excused absences (EA) are excluded from the calculation.
			The threshold can be changed via <strong>Settings → Branch Settings → Attendance Threshold</strong>.
		</div>

		<?php if (empty($at_risk)): ?>
			<div class="alert alert-success text-center">
				<i class="fas fa-check-circle"></i>
				No students are currently below the <?=(int)$threshold?>% attendance threshold.
			</div>
		<?php else: ?>
		<div class="export_title">At-Risk Attendance Report — Threshold: <?=(int)$threshold?>%</div>
		<div class="table-responsive">
			<table class="table table-bordered table-hover table-condensed table-export mb-none text-dark">
				<thead>
					<tr>
						<th>#</th>
						<th><?=translate('student_name')?></th>
						<th><?=translate('register_no')?></th>
						<th><?=translate('class')?></th>
						<th><?=translate('section')?></th>
						<th class="text-center text-success">P</th>
						<th class="text-center text-danger">A</th>
						<th class="text-center text-warning">L</th>
						<th class="text-center">HD</th>
						<th class="text-center"><strong>Attendance %</strong></th>
						<th class="text-center">Gap to threshold</th>
					</tr>
				</thead>
				<tbody>
				<?php $i = 1; foreach ($at_risk as $row): ?>
					<?php
						$pct = (float)$row['attendance_pct'];
						$gap = $threshold - $pct;
						if ($pct < 50)       $rowClass = 'danger';
						elseif ($pct < 65)   $rowClass = 'warning';
						else                 $rowClass = '';
					?>
					<tr class="<?=$rowClass?>">
						<td><?=$i++?></td>
						<td><?=html_escape($row['first_name'] . ' ' . $row['last_name'])?></td>
						<td><?=html_escape($row['register_no'])?></td>
						<td><?=html_escape($row['class_name'])?></td>
						<td><?=html_escape($row['section_name'])?></td>
						<td class="text-center"><?=(int)$row['cnt_present']?></td>
						<td class="text-center"><?=(int)$row['cnt_absent']?></td>
						<td class="text-center"><?=(int)$row['cnt_late']?></td>
						<td class="text-center"><?=(float)$row['cnt_half']?></td>
						<td class="text-center">
							<strong class="<?= $pct < 65 ? 'text-danger' : 'text-warning' ?>">
								<?=number_format($pct, 1)?>%
							</strong>
						</td>
						<td class="text-center text-danger">
							−<?=number_format($gap, 1)?>%
						</td>
					</tr>
				<?php endforeach; ?>
				</tbody>
				<tfoot>
					<tr>
						<th colspan="9" class="text-right">Total at-risk students:</th>
						<th class="text-center"><?=count($at_risk)?></th>
						<th></th>
					</tr>
				</tfoot>
			</table>
		</div>
		<?php endif; ?>
	</div>
</section>
