<?php $widget = (is_superadmin_loggedin() ? 3 : 4); ?>
<section class="panel">
	<?php echo form_open($this->uri->uri_string());?>
	<header class="panel-heading">
		<h4 class="panel-title"><?=translate('select_ground')?></h4>
	</header>
	<div class="panel-body">
		<div class="row mb-sm">
		<?php if (is_superadmin_loggedin() ): ?>
			<div class="col-md-3 mb-sm">
				<div class="form-group">
					<label class="control-label"><?=translate('branch')?> <span class="required">*</span></label>
					<?php
						$arrayBranch = $this->app_lib->getSelectList('branch');
						echo form_dropdown("branch_id", $arrayBranch, set_value('branch_id'), "class='form-control' onchange='getClassByBranch(this.value)'
						data-plugin-selectTwo data-width='100%' data-minimum-results-for-search='Infinity'");
					?>
				</div>
				<span class="error"><?=form_error('branch_id')?></span>
			</div>
		<?php endif; ?>
			<div class="col-md-<?php echo $widget; ?> mb-sm">
				<div class="form-group">
					<label class="control-label"><?=translate('class')?> <span class="required">*</span></label>
					<?php
						$arrayClass = $this->app_lib->getClass($branch_id);
						echo form_dropdown("class_id", $arrayClass, set_value('class_id'), "class='form-control' id='class_id' onchange='getSectionByClass(this.value,0)'
					 	data-plugin-selectTwo data-width='100%' data-minimum-results-for-search='Infinity' ");
					?>
					<span class="error"><?=form_error('class_id')?></span>
				</div>
			</div>
			<div class="col-md-<?php echo $widget; ?> mb-sm">
				<div class="form-group">
					<label class="control-label"><?=translate('section')?> <span class="required">*</span></label>
					<?php
						$arraySection = $this->app_lib->getSections(set_value('class_id'), false);
						echo form_dropdown("section_id", $arraySection, set_value('section_id'), "class='form-control' id='section_id'
						data-plugin-selectTwo data-width='100%' data-minimum-results-for-search='Infinity' ");
					?>
					<span class="error"><?=form_error('section_id')?></span>
				</div>
			</div>
			<div class="col-md-<?php echo $widget; ?> mb-sm">
				<div class="form-group">
					<label class="control-label"><?=translate('month')?> <span class="required">*</span></label>
					<div class="input-group">
						<input type="text" class="form-control" name="timestamp" value="<?=set_value('timestamp', date('Y-F'))?>" data-plugin-datepicker required
						data-plugin-options='{ "format": "yyyy-MM", "minViewMode": "months", "orientation": "bottom"}' />
						<span class="input-group-addon"><i class="icon-event icons"></i></span>
					</div>
				</div>
			</div>
		</div>
	</div>
	<footer class="panel-footer">
		<div class="row">
			<div class="col-md-offset-10 col-md-2">
				<button type="submit" name="submit" value="search" class="btn btn btn-default btn-block"> <i class="fas fa-filter"></i> <?=translate('filter')?></button>
			</div>
		</div>
	</footer>
    <?php echo form_close();?>
</section>

<?php if (isset($studentlist)): ?>
	<section class="panel appear-animation mt-sm" data-appear-animation="<?=$global_config['animations'] ?>" data-appear-animation-delay="100">
		<header class="panel-heading">
			<h4 class="panel-title"><i class="fas fa-users"></i> <?=translate('attendance_report')?></h4>
		</header>
		<div class="panel-body">
			<style type="text/css">
				table.dataTable.table-condensed > thead > tr > th { padding-right: 3px !important; }
			</style>
			<div class="export_title">Monthly Attendance Sheet — <?=date("F Y", strtotime($year.'-'.$month))?> <?php
				echo translate('class') . ' : ' . get_type_name_by_id('class', $class_id);
				echo ' (' . translate('section') . ' : ' . get_type_name_by_id('section', $section_id) . ')';
			?></div>

			<div class="row mt-sm">
				<div class="col-md-offset-6 col-md-6">
					<table class="table table-condensed table-bordered text-dark text-center">
						<tbody>
							<tr>
								<td><strong>W:</strong> Weekend</td>
								<td><strong><i class="far fa-check-circle text-success hidden-print"></i><span class="visible-print">P</span></strong> Present</td>
								<td><strong><i class="far fa-times-circle text-danger hidden-print"></i><span class="visible-print">A</span></strong> Absent</td>
								<td><strong><i class="fas fa-hospital-symbol text-info hidden-print"></i><span class="visible-print">H</span></strong> Holiday</td>
								<td><strong><i class="far fa-clock text-warning hidden-print"></i><span class="visible-print">L</span></strong> Late</td>
								<td><strong><i class="fas fa-star-half-alt text-tertiary hidden-print"></i><span class="visible-print">HD</span></strong> Half Day</td>
								<td><strong><i class="fas fa-user-check text-primary hidden-print"></i><span class="visible-print">EA</span></strong> Excused</td>
							</tr>
						</tbody>
					</table>
				</div>
			</div>

			<div class="row">
				<div class="col-md-12">
					<div class="mb-lg">
						<div class="table-responsive">
						<table class="table table-bordered table-hover table-condensed mb-none text-dark table-export">
							<thead>
								<tr>
									<th><?=translate('student_name')?></th>
<?php
// Weekend days as day-of-week integers — used for column highlighting
$weekendNums   = array_map('intval', array_filter(explode(',', $this->application_model->getWeekends($branch_id)), 'strlen'));
$getHolidays   = $this->attendance_model->getHolidaysArray($branch_id);

for ($i = 1; $i <= $days; $i++) {
    $date    = date('Y-m-d', strtotime($year . '-' . $month . '-' . $i));
    $dowNum  = (int)date('w', strtotime($date));
    $isWkend = in_array($dowNum, $weekendNums, true);
?>
									<th <?php if ($isWkend) echo "style='background-color:#f99'"; ?> class="text-center no-sort">
										<?=date('D', strtotime($date))?><br><?=date('d', strtotime($date))?>
									</th>
<?php } ?>
									<th class="text-center" style="padding-right:15px !important;">(%)</th>
									<th class="text-center" style="padding-right:15px !important;">W</th>
									<th class="text-center text-success" style="padding-right:15px !important;">P</th>
									<th class="text-center text-danger" style="padding-right:15px !important;">A</th>
									<th class="text-center text-warning" style="padding-right:15px !important;">L</th>
									<th class="text-center text-tertiary" style="padding-right:15px !important;">HD</th>
									<th class="text-center text-primary">EA</th>
								</tr>
							</thead>
							<tbody>
<?php
foreach ($studentlist as $row):
    $total_present  = 0;
    $total_absent   = 0;
    $total_late     = 0;
    $total_half_day = 0;
    $total_excused  = 0;
    $total_weekends = 0;
    $enrollID       = $row['enroll_id'];
    // Pull from pre-loaded batch map — O(1) lookup, no extra DB query
    $enrollMap      = $attendance_map[$enrollID] ?? [];
?>
								<tr>
									<td><?=html_escape($row['first_name'] . ' ' . $row['last_name'])?> <div class="visible-print"> / <?=translate('register_no') . ' ' . $row['register_no']?></div></td>
<?php
for ($i = 1; $i <= $days; $i++) {
    $date    = date('Y-m-d', strtotime($year . '-' . $month . '-' . $i));
    $dowNum  = (int)date('w', strtotime($date));
    $isWkend = in_array($dowNum, $weekendNums, true);
    $isHol   = in_array($date, $getHolidays, true);
    $atten   = $enrollMap[$date] ?? null;
?>
								<td class="center">
<?php if ($atten !== null): ?>
									<span data-toggle="popover" data-trigger="hover" data-placement="top" data-content="<?=html_escape($atten['remark'])?>">
<?php
    switch ($atten['status']) {
        case 'A':  $total_absent++;   echo '<i class="far fa-times-circle text-danger"></i><span class="visible-print">A</span>'; break;
        case 'P':  $total_present++;  echo '<i class="far fa-check-circle text-success"></i><span class="visible-print">P</span>'; break;
        case 'L':  $total_late++;     echo '<i class="far fa-clock text-warning"></i><span class="visible-print">L</span>'; break;
        case 'HD': $total_half_day++; echo '<i class="fas fa-star-half-alt text-tertiary"></i><span class="visible-print">HD</span>'; break;
        case 'EA': $total_excused++;  echo '<i class="fas fa-user-check text-primary"></i><span class="visible-print">EA</span>'; break;
        case 'H':                     echo '<i class="fas fa-hospital-symbol text-info"></i><span class="visible-print">H</span>'; break;
    }
?>
									</span>
<?php elseif ($isHol): ?>
									<i class="fas fa-hospital-symbol text-info"></i><span class="visible-print">H</span>
<?php elseif ($isWkend): ?>
									<?php $total_weekends++; ?><span class="text-success">W</span>
<?php endif; ?>
								</td>
<?php } ?>
									<?php
									// Attendance % formula:
									// Numerator:   P + L + (HD × 0.5)   — EA excluded (authorised, not penalised)
									// Denominator: P + A + L + (HD × 0.5) — EA excluded from working day count
									$working_num  = $total_present + $total_late + ($total_half_day * 0.5);
									$working_den  = $total_present + $total_absent + $total_late + ($total_half_day * 0.5);
									?>
									<td class="center"><?php
										if ($working_den == 0) { echo '-'; }
										else { echo round(($working_num / $working_den) * 100); }
									?></td>
									<td class="center"><?=$total_weekends?></td>
									<td class="center"><?=$total_present?></td>
									<td class="center"><?=$total_absent?></td>
									<td class="center"><?=$total_late?></td>
									<td class="center"><?=$total_half_day?></td>
									<td class="center"><?=$total_excused?></td>
								</tr>
<?php endforeach; ?>
							</tbody>
						</table>
						</div>
					</div>
				</div>
			</div>
		</div>
	</section>
<?php endif; ?>
