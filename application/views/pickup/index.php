<?php $today = date('Y-m-d'); ?>
<!-- Stat tiles -->
<div class="row mb-md">
	<div class="col-md-3 col-sm-6 mb-sm">
		<div class="panel panel-default text-center" style="padding:16px 10px;">
			<div style="font-size:13px;color:#888;">Authorized Persons</div>
			<div style="font-size:28px;font-weight:700;"><?=$total?></div>
			<div style="font-size:12px;color:#aaa;">Total authorized</div>
		</div>
	</div>
	<div class="col-md-3 col-sm-6 mb-sm">
		<div class="panel panel-default text-center" style="padding:16px 10px;">
			<div style="font-size:13px;color:#888;">Active</div>
			<div style="font-size:28px;font-weight:700;color:#27ae60;"><?=$active?></div>
			<div style="font-size:12px;color:#aaa;">Currently active</div>
		</div>
	</div>
	<div class="col-md-3 col-sm-6 mb-sm">
		<div class="panel panel-default text-center" style="padding:16px 10px;">
			<div style="font-size:13px;color:#888;">Today's Pickups</div>
			<div style="font-size:28px;font-weight:700;color:#3498db;"><?=$today_pk?></div>
			<div style="font-size:12px;color:#aaa;">Completed today</div>
		</div>
	</div>
	<div class="col-md-3 col-sm-6 mb-sm">
		<div class="panel panel-default text-center" style="padding:16px 10px;">
			<div style="font-size:13px;color:#888;">Quick Links</div>
			<div style="margin-top:6px;">
				<a href="<?=base_url('pickup/scan')?>" class="btn btn-primary btn-sm btn-block mb-xs">
					<i class="fas fa-qrcode"></i> QR Scanner
				</a>
				<a href="<?=base_url('pickup/records')?>" class="btn btn-default btn-sm btn-block">
					<i class="fas fa-list"></i> View Records
				</a>
			</div>
		</div>
	</div>
</div>

<div class="row">
	<!-- Add Person form -->
	<div class="col-md-4">
		<section class="panel">
			<header class="panel-heading">
				<h4 class="panel-title"><i class="fas fa-user-plus"></i> Add Authorized Person</h4>
			</header>
			<?php echo form_open('pickup'); ?>
			<div class="panel-body">
				<input type="hidden" name="person_id" value="" />
				<?php if (is_superadmin_loggedin()): ?>
				<div class="form-group">
					<label class="control-label"><?=translate('branch')?> <span class="required">*</span></label>
					<?php echo form_dropdown('branch_id_sel', $this->app_lib->getSelectList('branch'), set_value('branch_id_sel'),
						"class='form-control' id='pickup_branch_id' onchange='loadPickupStudents(this.value)' data-plugin-selectTwo data-width='100%' data-minimum-results-for-search='Infinity'"); ?>
				</div>
				<?php endif; ?>
				<div class="form-group">
					<label class="control-label">Student <span class="required">*</span></label>
					<select name="student_id" id="pickup_student_id" class="form-control" required
						data-plugin-selectTwo data-width="100%" data-minimum-results-for-search="Infinity">
						<option value="">— Select Student —</option>
						<?php
						$students = $this->db->select('s.id, CONCAT(s.first_name," ",s.last_name," (",s.register_no,")") as label')
							->from('student s')
							->join('enroll e', 'e.student_id = s.id', 'inner')
							->where('e.branch_id', $branch_id)
							->order_by('s.first_name')
							->get()->result_array();
						foreach ($students as $st):
						?>
						<option value="<?=$st['id']?>"><?=html_escape($st['label'])?></option>
						<?php endforeach; ?>
					</select>
				</div>
				<div class="form-group">
					<label class="control-label">Full Name <span class="required">*</span></label>
					<input type="text" class="form-control" name="name" value="<?=set_value('name')?>" required placeholder="e.g. Mrs. Jane Doe" />
				</div>
				<div class="form-group">
					<label class="control-label">Relationship</label>
					<?php echo form_dropdown('relationship', [
						'' => '— Select —', 'Mother' => 'Mother', 'Father' => 'Father',
						'Guardian' => 'Guardian', 'Sibling' => 'Sibling', 'Uncle' => 'Uncle',
						'Aunt' => 'Aunt', 'Grandparent' => 'Grandparent', 'Driver' => 'Driver', 'Other' => 'Other',
					], set_value('relationship'), "class='form-control' data-plugin-selectTwo data-width='100%' data-minimum-results-for-search='Infinity'"); ?>
				</div>
				<div class="form-group">
					<label class="control-label">Phone Number</label>
					<input type="text" class="form-control" name="phone" value="<?=set_value('phone')?>" placeholder="+234..." />
				</div>
				<div class="form-group">
					<label class="control-label">ID Number (optional)</label>
					<input type="text" class="form-control" name="id_number" value="<?=set_value('id_number')?>" placeholder="NIN / Passport / Driver's licence" />
				</div>
				<div class="form-group mb-none">
					<label><input type="checkbox" name="is_active" value="1" <?=set_checkbox('is_active','1',true)?> /> Active</label>
				</div>
			</div>
			<div class="panel-footer">
				<button type="submit" class="btn btn-default btn-block pull-right">
					<i class="fas fa-plus-circle"></i> Save Person
				</button>
			</div>
			<?php echo form_close(); ?>
		</section>
	</div>

	<!-- Persons list -->
	<div class="col-md-8">
		<section class="panel">
			<header class="panel-heading">
				<h4 class="panel-title"><i class="fas fa-users"></i> Authorized Persons List</h4>
			</header>
			<div class="panel-body">
				<div class="table-responsive">
					<table class="table table-bordered table-hover table-condensed">
						<thead>
							<tr>
								<th>#</th>
								<th>Name</th>
								<th>Student</th>
								<th>Class</th>
								<th>Relationship</th>
								<th>Phone</th>
								<th>Status</th>
								<th>QR</th>
								<th><?=translate('action')?></th>
							</tr>
						</thead>
						<tbody>
						<?php if (empty($persons)): ?>
							<tr><td colspan="9" class="text-center text-muted">No authorized persons added yet.</td></tr>
						<?php else: $i = 1; foreach ($persons as $p): ?>
							<tr>
								<td><?=$i++?></td>
								<td><?=html_escape($p['name'])?></td>
								<td><?=html_escape($p['student_name'])?><br><small class="text-muted"><?=html_escape($p['register_no'])?></small></td>
								<td><?=html_escape($p['class_name'])?> / <?=html_escape($p['section_name'])?></td>
								<td><?=html_escape($p['relationship'])?></td>
								<td><?=html_escape($p['phone'])?></td>
								<td>
									<?php if ($p['is_active']): ?>
									<span class="label label-success">Active</span>
									<?php else: ?>
									<span class="label label-default">Inactive</span>
									<?php endif; ?>
								</td>
								<td>
									<a href="<?=base_url('pickup/qr_card/' . $p['id'])?>" class="btn btn-default btn-xs" title="View QR Card">
										<i class="fas fa-qrcode"></i>
									</a>
								</td>
								<td>
									<?php echo btn_delete('pickup/delete/' . $p['id']); ?>
								</td>
							</tr>
						<?php endforeach; endif; ?>
						</tbody>
					</table>
				</div>
			</div>
		</section>
	</div>
</div>
<script>
function loadPickupStudents(branchID) {
	$.post(base_url + 'pickup/get_students', {branch_id: branchID}, function(html) {
		$('#pickup_student_id').html(html).trigger('change');
	});
}
</script>
