<div class="row">
	<div class="col-md-8 col-md-offset-2">
		<section class="panel">
			<header class="panel-heading">
				<h4 class="panel-title"><i class="fas fa-qrcode"></i> Pickup QR Scanner</h4>
				<p class="panel-subtitle text-muted" style="padding:0 15px 8px;">Scan an authorized pickup person's QR code to verify and log the pickup.</p>
			</header>
			<div class="panel-body">
				<!-- Scan input -->
				<div class="form-group mb-md">
					<label class="control-label">Scan QR Code or Enter Token Manually</label>
					<div class="input-group">
						<input type="text" id="qr_input" class="form-control input-lg" placeholder="Scan or type QR token..." autofocus autocomplete="off" />
						<span class="input-group-btn">
							<button class="btn btn-primary btn-lg" id="btnVerify" type="button">
								<i class="fas fa-search"></i> Verify
							</button>
						</span>
					</div>
					<p class="help-block">Most USB/Bluetooth QR scanners auto-submit on scan.</p>
				</div>

				<!-- Result area -->
				<div id="scan_result" style="display:none;">
					<div id="result_success" class="alert alert-success" style="display:none;">
						<h4><i class="fas fa-check-circle"></i> <span id="res_person"></span></h4>
						<table class="table table-condensed mb-none mt-sm">
							<tr><th style="width:140px;">Relationship</th><td id="res_rel"></td></tr>
							<tr><th>Student</th><td id="res_student"></td></tr>
							<tr><th>Class</th><td id="res_class"></td></tr>
							<tr><th>Reg No</th><td id="res_regno"></td></tr>
							<tr><th>Pickup Time</th><td id="res_time"></td></tr>
						</table>
					</div>
					<div id="result_error" class="alert alert-danger" style="display:none;">
						<i class="fas fa-times-circle"></i> <span id="res_error_msg"></span>
					</div>
				</div>
			</div>
		</section>
	</div>
</div>
<script>
$(document).ready(function() {
	var $input  = $('#qr_input');
	var $result = $('#scan_result');
	var $ok     = $('#result_success');
	var $err    = $('#result_error');

	function doVerify() {
		var token = $input.val().trim();
		if (!token) return;
		$result.show();
		$ok.hide(); $err.hide();

		$.ajax({
			type: 'POST',
			url: base_url + 'pickup/verify_qr',
			data: { token: token },
			dataType: 'json',
			success: function(d) {
				if (d.status === 'success') {
					$('#res_person').text(d.person_name);
					$('#res_rel').text(d.relationship || '—');
					$('#res_student').text(d.student_name);
					$('#res_class').text(d.class_name + ' / ' + d.section_name);
					$('#res_regno').text(d.register_no);
					$('#res_time').text(d.time);
					$ok.show();
				} else {
					$('#res_error_msg').text(d.message);
					$err.show();
				}
				$input.val('').focus();
			},
			error: function() {
				$('#res_error_msg').text('Server error. Please try again.');
				$err.show();
				$input.val('').focus();
			}
		});
	}

	$('#btnVerify').on('click', doVerify);
	$input.on('keypress', function(e) { if (e.which === 13) doVerify(); });
});
</script>
