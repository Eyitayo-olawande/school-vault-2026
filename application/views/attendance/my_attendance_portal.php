<section class="panel">
	<?php echo form_open($this->uri->uri_string()); ?>
	<header class="panel-heading">
		<h4 class="panel-title"><i class="fas fa-calendar-check"></i> My Attendance Record</h4>
	</header>
	<div class="panel-body">
		<div class="row mb-sm">
			<div class="col-md-4">
				<div class="form-group">
					<label class="control-label"><?=translate('month')?> <span class="required">*</span></label>
					<div class="input-group">
						<input type="text" class="form-control" name="timestamp"
							value="<?=set_value('timestamp', date('Y-F'))?>"
							data-plugin-datepicker required
							data-plugin-options='{"format":"yyyy-MM","minViewMode":"months","orientation":"bottom"}' />
						<span class="input-group-addon"><i class="icon-event icons"></i></span>
					</div>
				</div>
			</div>
			<div class="col-md-4" style="padding-top:24px;">
				<button type="submit" class="btn btn-default">
					<i class="fas fa-filter"></i> <?=translate('filter')?>
				</button>
			</div>
		</div>
	</div>
	<?php echo form_close(); ?>
</section>

<?php if (isset($month_data)): ?>
<section class="panel appear-animation mt-sm" data-appear-animation="fadeIn">
	<header class="panel-heading">
		<h4 class="panel-title">
			<?=html_escape($student_name)?> — <?=date('F Y', strtotime($year . '-' . $month . '-01'))?>
		</h4>
	</header>
	<div class="panel-body">

		<!-- Summary strip -->
		<div class="row mb-md">
			<div class="col-xs-6 col-sm-3 text-center">
				<h3 class="text-success mb-none"><?=$summary['present']?></h3>
				<small class="text-muted">Present</small>
			</div>
			<div class="col-xs-6 col-sm-3 text-center">
				<h3 class="text-danger mb-none"><?=$summary['absent']?></h3>
				<small class="text-muted">Absent</small>
			</div>
			<div class="col-xs-6 col-sm-3 text-center">
				<h3 class="text-warning mb-none"><?=$summary['late']?></h3>
				<small class="text-muted">Late</small>
			</div>
			<div class="col-xs-6 col-sm-3 text-center">
				<h3 class="mb-none <?= $summary['pct'] < 75 ? 'text-danger' : 'text-success' ?>">
					<?=number_format($summary['pct'], 1)?>%
				</h3>
				<small class="text-muted">Attendance</small>
			</div>
		</div>

		<?php if ($summary['pct'] < $threshold && $summary['working_den'] > 0): ?>
		<div class="alert alert-danger">
			<i class="fas fa-exclamation-triangle"></i>
			Your attendance this month is below the required <strong><?=(int)$threshold?>%</strong> threshold.
			Please speak to your class teacher.
		</div>
		<?php endif; ?>

		<!-- Calendar grid -->
		<div class="table-responsive">
		<table class="table table-bordered table-condensed text-center mb-none text-dark">
			<thead>
				<tr>
					<?php foreach (['Sun','Mon','Tue','Wed','Thu','Fri','Sat'] as $d): ?>
					<th class="text-center"><?=$d?></th>
					<?php endforeach; ?>
				</tr>
			</thead>
			<tbody>
			<?php
			$firstDow = (int)date('w', strtotime($year . '-' . $month . '-01'));
			$totalDays = cal_days_in_month(CAL_GREGORIAN, $month, $year);
			$cell = 0;
			echo '<tr>';
			for ($pad = 0; $pad < $firstDow; $pad++) {
				echo '<td></td>';
				$cell++;
			}
			for ($d = 1; $d <= $totalDays; $d++) {
				$date    = sprintf('%04d-%02d-%02d', $year, $month, $d);
				$dow     = (int)date('w', strtotime($date));
				$isWkend = in_array($dow, $weekend_nums, true);
				$isHol   = in_array($date, $holidays, true);
				$rec     = $month_data[$date] ?? null;

				if ($isWkend) {
					$bg = '#f5f5f5'; $icon = '<small class="text-muted">W</small>';
				} elseif ($isHol) {
					$bg = '#e8f4fd'; $icon = '<small class="text-info">H</small>';
				} elseif ($rec) {
					switch ($rec['status']) {
						case 'P':  $bg = '#dff0d8'; $icon = '<i class="far fa-check-circle text-success"></i>'; break;
						case 'A':  $bg = '#f2dede'; $icon = '<i class="far fa-times-circle text-danger"></i>'; break;
						case 'L':  $bg = '#fcf8e3'; $icon = '<i class="far fa-clock text-warning"></i>'; break;
						case 'HD': $bg = '#f9f0ff'; $icon = '<i class="fas fa-star-half-alt text-tertiary"></i>'; break;
						case 'EA': $bg = '#d9edf7'; $icon = '<i class="fas fa-user-check text-primary"></i>'; break;
						default:   $bg = '';         $icon = '';
					}
				} else {
					$bg = ''; $icon = '';
				}
				$remark = $rec ? html_escape($rec['remark']) : '';
				$tooltip = $remark ? " data-toggle='tooltip' title='{$remark}'" : '';
				echo "<td style='background:{$bg};padding:6px 4px;'{$tooltip}><strong>{$d}</strong><br>{$icon}</td>";
				$cell++;
				if ($cell % 7 === 0 && $d < $totalDays) echo '</tr><tr>';
			}
			// pad remaining cells
			while ($cell % 7 !== 0) { echo '<td></td>'; $cell++; }
			echo '</tr>';
			?>
			</tbody>
		</table>
		</div>

		<!-- Legend -->
		<div class="row mt-sm">
			<div class="col-md-12">
				<small>
					<i class="far fa-check-circle text-success"></i> Present &nbsp;
					<i class="far fa-times-circle text-danger"></i> Absent &nbsp;
					<i class="far fa-clock text-warning"></i> Late &nbsp;
					<i class="fas fa-star-half-alt text-tertiary"></i> Half Day &nbsp;
					<i class="fas fa-user-check text-primary"></i> Excused &nbsp;
					<i class="fas fa-hospital-symbol text-info"></i> Holiday &nbsp;
					<span class="text-muted">W</span> Weekend
				</small>
			</div>
		</div>
	</div>
</section>
<?php endif; ?>
