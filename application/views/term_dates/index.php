<div class="row">
	<div class="col-md-12">
		<section class="panel">
			<header class="panel-heading">
				<h4 class="panel-title"><i class="far fa-calendar-alt"></i> Term Dates</h4>
				<?php if (get_permission('term_dates', 'is_add')): ?>
				<div class="panel-actions">
					<button class="btn btn-default btn-sm" data-toggle="modal" data-target="#addTermDateModal">
						<i class="fas fa-plus"></i> Add Term Date
					</button>
				</div>
				<?php endif; ?>
			</header>
			<div class="panel-body">
				<div class="alert alert-info">
					<i class="fas fa-info-circle"></i>
					Set resumption, mid-term, and exam dates per term. The DVA payment cron uses these dates to schedule payment reminders automatically.
				</div>
				<div class="table-responsive">
					<table class="table table-bordered table-hover table-condensed">
						<thead>
							<tr>
								<th>#</th>
								<?php if (is_superadmin_loggedin()): ?><th>Branch</th><?php endif; ?>
								<th>Session</th>
								<th>Term</th>
								<th>Resumption Date</th>
								<th>Mid-Term Date</th>
								<th>Exam Start Date</th>
								<th>Action</th>
							</tr>
						</thead>
						<tbody>
							<?php if (!empty($term_dates)): $i = 1; foreach ($term_dates as $row): ?>
							<tr>
								<td><?= $i++ ?></td>
								<?php if (is_superadmin_loggedin()): ?><td><?= html_escape($row['branch_name'] ?? '') ?></td><?php endif; ?>
								<td><?= html_escape($row['school_year']) ?></td>
								<td><span class="label label-info"><?= html_escape($row['term']) ?></span></td>
								<td><?= html_escape($row['resumption_date']) ?></td>
								<td><?= $row['midterm_date'] ? html_escape($row['midterm_date']) : '<span class="text-muted">—</span>' ?></td>
								<td><?= $row['exam_start_date'] ? html_escape($row['exam_start_date']) : '<span class="text-muted">—</span>' ?></td>
								<td>
									<?php if (get_permission('term_dates', 'is_add')): ?>
									<button class="btn btn-xs btn-default btn-edit"
										data-id="<?= $row['id'] ?>"
										data-session="<?= $row['session_id'] ?>"
										data-term="<?= $row['term'] ?>"
										data-resumption="<?= $row['resumption_date'] ?>"
										data-midterm="<?= $row['midterm_date'] ?>"
										data-exam="<?= $row['exam_start_date'] ?>">
										<i class="fas fa-edit"></i>
									</button>
									<?php endif; ?>
									<?php if (get_permission('term_dates', 'is_delete')): ?>
									<a href="<?= base_url('term_dates/delete/' . $row['id']) ?>" class="btn btn-xs btn-danger"
										onclick="return confirm('Delete this term date?')">
										<i class="fas fa-trash"></i>
									</a>
									<?php endif; ?>
								</td>
							</tr>
							<?php endforeach; else: ?>
							<tr><td colspan="7" class="text-center text-muted">No term dates configured yet.</td></tr>
							<?php endif; ?>
						</tbody>
					</table>
				</div>
			</div>
		</section>
	</div>
</div>

<!-- Add/Edit Modal -->
<div class="modal fade" id="addTermDateModal" tabindex="-1">
	<div class="modal-dialog">
		<div class="modal-content">
			<div class="modal-header">
				<button type="button" class="close" data-dismiss="modal">&times;</button>
				<h4 class="modal-title" id="modalTitle">Add Term Date</h4>
			</div>
			<?php echo form_open('term_dates/save', ['class' => 'frm-submit', 'id' => 'termDateForm']); ?>
			<div class="modal-body">
				<?php if (is_superadmin_loggedin()): ?>
				<div class="form-group">
					<label class="control-label">Branch <span class="required">*</span></label>
					<?php
						$arrayBranch = $this->app_lib->getSelectList('branch');
						echo form_dropdown("branch_id", $arrayBranch, '', "class='form-control' required");
					?>
				</div>
				<?php endif; ?>
				<div class="form-group">
					<label class="control-label">Session <span class="required">*</span></label>
					<select name="session_id" class="form-control" required>
						<option value="">— Select Session —</option>
						<?php foreach ($sessions as $sy): ?>
						<option value="<?= $sy['id'] ?>"><?= html_escape($sy['school_year']) ?></option>
						<?php endforeach; ?>
					</select>
				</div>
				<div class="form-group">
					<label class="control-label">Term <span class="required">*</span></label>
					<select name="term" class="form-control" required>
						<option value="">— Select Term —</option>
						<option value="1ST">1ST Term</option>
						<option value="2ND">2ND Term</option>
						<option value="3RD">3RD Term</option>
					</select>
				</div>
				<div class="form-group">
					<label class="control-label">Resumption Date <span class="required">*</span></label>
					<input type="date" name="resumption_date" class="form-control" required>
				</div>
				<div class="form-group">
					<label class="control-label">Mid-Term Date</label>
					<input type="date" name="midterm_date" class="form-control">
					<span class="help-block">Leave blank if not applicable.</span>
				</div>
				<div class="form-group">
					<label class="control-label">Exam Start Date</label>
					<input type="date" name="exam_start_date" class="form-control">
				</div>
			</div>
			<div class="modal-footer">
				<button type="button" class="btn btn-default" data-dismiss="modal">Cancel</button>
				<button type="submit" class="btn btn-primary" data-loading-text="<i class='fas fa-spinner fa-spin'></i> Saving">
					<i class="fas fa-save"></i> Save
				</button>
			</div>
			<?php echo form_close(); ?>
		</div>
	</div>
</div>

<script>
$(document).ready(function () {
	$(document).on('click', '.btn-edit', function () {
		var btn = $(this);
		$('#modalTitle').text('Edit Term Date');
		$('[name="session_id"]').val(btn.data('session'));
		$('[name="term"]').val(btn.data('term'));
		$('[name="resumption_date"]').val(btn.data('resumption'));
		$('[name="midterm_date"]').val(btn.data('midterm') || '');
		$('[name="exam_start_date"]').val(btn.data('exam') || '');
		$('#addTermDateModal').modal('show');
	});

	$('#addTermDateModal').on('hidden.bs.modal', function () {
		$('#modalTitle').text('Add Term Date');
		$('#termDateForm')[0].reset();
	});
});
</script>
