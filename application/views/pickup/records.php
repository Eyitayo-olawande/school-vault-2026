<section class="panel">
	<header class="panel-heading" style="display:flex;justify-content:space-between;align-items:center;">
		<h4 class="panel-title"><i class="fas fa-list-alt"></i> Pickup Records</h4>
		<form method="get" action="" class="form-inline">
			<div class="input-group input-group-sm">
				<input type="date" name="date" class="form-control" value="<?=html_escape($date)?>" max="<?=date('Y-m-d')?>" />
				<span class="input-group-btn">
					<button class="btn btn-default" type="submit"><i class="fas fa-filter"></i> Filter</button>
				</span>
			</div>
		</form>
	</header>
	<div class="panel-body">
		<div class="table-responsive">
			<table class="table table-bordered table-hover table-condensed table-export">
				<thead>
					<tr>
						<th>#</th>
						<th>Student</th>
						<th>Class</th>
						<th>Reg No</th>
						<th>Picked Up By</th>
						<th>Relationship</th>
						<th>Date</th>
						<th>Time</th>
					</tr>
				</thead>
				<tbody>
				<?php if (empty($records)): ?>
					<tr><td colspan="8" class="text-center text-muted">No pickup records for <?=html_escape($date)?>.</td></tr>
				<?php else: $i = 1; foreach ($records as $r): ?>
					<tr>
						<td><?=$i++?></td>
						<td><?=html_escape($r['student_name'])?></td>
						<td><?=html_escape($r['class_name'])?> / <?=html_escape($r['section_name'])?></td>
						<td><?=html_escape($r['register_no'])?></td>
						<td><?=html_escape($r['person_name'])?></td>
						<td><?=html_escape($r['relationship'])?></td>
						<td><?=_d($r['pickup_date'])?></td>
						<td><?=date('h:i A', strtotime($r['pickup_time']))?></td>
					</tr>
				<?php endforeach; endif; ?>
				</tbody>
			</table>
		</div>
	</div>
</section>
