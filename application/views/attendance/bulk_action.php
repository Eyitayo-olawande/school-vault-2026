<?php $widget = (is_superadmin_loggedin() ? 2 : 3); ?>
<section class="panel">
	<header class="panel-heading">
		<h4 class="panel-title"><i class="fas fa-tasks"></i> Bulk Attendance — Mark Entire Class</h4>
	</header>
	<?php echo form_open($this->uri->uri_string()); ?>
	<div class="panel-body">
		<div class="row mb-sm">
			<?php if (is_superadmin_loggedin()): ?>
			<div class="col-md-2 mb-sm">
				<div class="form-group">
					<label class="control-label"><?=translate('branch')?> <span class="required">*</span></label>
					<?php echo form_dropdown('branch_id', $this->app_lib->getSelectList('branch'), set_value('branch_id'),
						"class='form-control' id='branchID' onchange='getClassByBranch(this.value)' data-plugin-selectTwo data-width='100%' data-minimum-results-for-search='Infinity'"); ?>
				</div>
			</div>
			<?php endif; ?>
			<div class="col-md-<?=$widget?> mb-sm">
				<div class="form-group">
					<label class="control-label"><?=translate('class')?> <span class="required">*</span></label>
					<?php echo form_dropdown('class_id', $this->app_lib->getClass($branch_id), set_value('class_id'),
						"class='form-control' id='class_id' onchange='getSectionByClass(this.value,0)' data-plugin-selectTwo data-width='100%' data-minimum-results-for-search='Infinity'"); ?>
				</div>
			</div>
			<div class="col-md-<?=$widget?> mb-sm">
				<div class="form-group">
					<label class="control-label"><?=translate('section')?> <span class="required">*</span></label>
					<?php echo form_dropdown('section_id', $this->app_lib->getSections(set_value('class_id'), false), set_value('section_id'),
						"class='form-control' id='section_id' data-plugin-selectTwo data-width='100%' data-minimum-results-for-search='Infinity'"); ?>
				</div>
			</div>
			<div class="col-md-<?=$widget?> mb-sm">
				<div class="form-group">
					<label class="control-label"><?=translate('date')?> <span class="required">*</span></label>
					<input type="date" class="form-control" name="date" value="<?=set_value('date', date('Y-m-d'))?>" max="<?=date('Y-m-d')?>" required />
				</div>
			</div>
		</div>
		<div class="row mb-sm">
			<div class="col-md-3 mb-sm">
				<div class="form-group">
					<label class="control-label">Mark All As <span class="required">*</span></label>
					<?php echo form_dropdown('bulk_status', [
						'P'  => 'Present',
						'A'  => 'Absent',
						'L'  => 'Late',
						'EA' => 'Excused Absent',
					], set_value('bulk_status', 'P'), "class='form-control' data-plugin-selectTwo data-width='100%' data-minimum-results-for-search='Infinity'"); ?>
				</div>
			</div>
			<div class="col-md-4 mb-sm">
				<div class="form-group">
					<label class="control-label">Remark</label>
					<input type="text" class="form-control" name="remark" value="<?=set_value('remark')?>" placeholder="Optional note" />
				</div>
			</div>
			<div class="col-md-3 mb-sm">
				<div class="form-group">
					<label class="control-label">&nbsp;</label>
					<div class="checkbox" style="margin-top:6px;">
						<label>
							<input type="checkbox" name="skip_existing" value="1" <?=set_checkbox('skip_existing', '1', true)?> />
							Skip already-marked students
						</label>
					</div>
				</div>
			</div>
		</div>
		<div class="alert alert-warning mb-none">
			<i class="fas fa-info-circle"></i>
			This will mark <strong>all enrolled students</strong> in the selected class/section with the chosen status.
			Uncheck "Skip already-marked" to overwrite existing records.
		</div>
	</div>
	<div class="panel-footer">
		<div class="row">
			<div class="col-md-offset-9 col-md-3">
				<button type="submit" name="apply" value="1" class="btn btn-primary btn-block">
					<i class="fas fa-check-circle"></i> Apply to All Students
				</button>
			</div>
		</div>
	</div>
	<?php echo form_close(); ?>
</section>
