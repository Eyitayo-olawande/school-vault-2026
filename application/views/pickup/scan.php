<script src="<?=base_url('assets/vendor/jsqr/jsQR.min.js')?>"></script>
<div class="row">
	<div class="col-md-8 col-md-offset-2">
		<section class="panel">
			<header class="panel-heading">
				<h4 class="panel-title"><i class="fas fa-qrcode"></i> Pickup QR Scanner</h4>
				<p class="text-muted" style="padding:0 15px 8px;font-size:13px;">Scan an authorized pickup person's QR code to verify and log the pickup.</p>
			</header>
			<div class="panel-body">

				<!-- Camera viewfinder -->
				<div id="camera_wrap" style="position:relative;background:#000;border-radius:6px;overflow:hidden;max-width:480px;margin:0 auto 16px;">
					<video id="qr_video" style="width:100%;display:block;" playsinline autoplay muted></video>
					<canvas id="qr_canvas" style="display:none;"></canvas>
					<!-- Aiming overlay -->
					<div style="position:absolute;top:50%;left:50%;transform:translate(-50%,-50%);width:200px;height:200px;pointer-events:none;">
						<div style="position:absolute;top:0;left:0;width:30px;height:30px;border-top:3px solid #f39c12;border-left:3px solid #f39c12;border-radius:4px 0 0 0;"></div>
						<div style="position:absolute;top:0;right:0;width:30px;height:30px;border-top:3px solid #f39c12;border-right:3px solid #f39c12;border-radius:0 4px 0 0;"></div>
						<div style="position:absolute;bottom:0;left:0;width:30px;height:30px;border-bottom:3px solid #f39c12;border-left:3px solid #f39c12;border-radius:0 0 0 4px;"></div>
						<div style="position:absolute;bottom:0;right:0;width:30px;height:30px;border-bottom:3px solid #f39c12;border-right:3px solid #f39c12;border-radius:0 0 4px 0;"></div>
					</div>
					<div id="cam_status" style="position:absolute;bottom:8px;left:0;right:0;text-align:center;color:#fff;font-size:13px;text-shadow:0 1px 3px #000;">
						<i class="fas fa-spinner fa-spin"></i> Starting camera...
					</div>
				</div>

				<!-- Camera error / no-camera fallback -->
				<div id="cam_error" style="display:none;" class="alert alert-warning text-center">
					<i class="fas fa-exclamation-triangle"></i> Camera not available.
					<br><small>Use a USB/Bluetooth QR scanner or type the token below.</small>
				</div>

				<!-- Manual fallback -->
				<div class="form-group mt-sm">
					<label class="control-label" style="font-size:12px;color:#aaa;">Or enter token manually</label>
					<div class="input-group">
						<input type="text" id="qr_input" class="form-control" placeholder="Paste or type QR token..." autocomplete="off" />
						<span class="input-group-btn">
							<button class="btn btn-primary" id="btnVerify" type="button">
								<i class="fas fa-search"></i> Verify
							</button>
						</span>
					</div>
				</div>

				<!-- Scan result -->
				<div id="scan_result" style="display:none;margin-top:12px;">
					<div id="result_success" class="alert alert-success" style="display:none;">
						<h4 style="margin-top:0;"><i class="fas fa-check-circle"></i> <span id="res_person"></span></h4>
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
(function () {
	var video   = document.getElementById('qr_video');
	var canvas  = document.getElementById('qr_canvas');
	var ctx     = canvas.getContext('2d');
	var camStat = document.getElementById('cam_status');
	var scanning = false;
	var cooldown = false;

	// ── Camera start ────────────────────────────────────────────────────────
	function startCamera() {
		if (!navigator.mediaDevices || !navigator.mediaDevices.getUserMedia) {
			document.getElementById('cam_error').style.display = 'block';
			document.getElementById('camera_wrap').style.display = 'none';
			return;
		}
		navigator.mediaDevices.getUserMedia({ video: { facingMode: 'environment' } })
			.then(function (stream) {
				video.srcObject = stream;
				video.setAttribute('playsinline', true);
				video.play();
				scanning = true;
				requestAnimationFrame(tick);
				camStat.innerHTML = '<i class="fas fa-circle" style="color:#e74c3c;font-size:8px;"></i> Scanning...';
			})
			.catch(function () {
				document.getElementById('cam_error').style.display = 'block';
				document.getElementById('camera_wrap').style.display = 'none';
			});
	}

	// ── Frame scan loop ──────────────────────────────────────────────────────
	function tick() {
		if (!scanning) return;
		if (video.readyState === video.HAVE_ENOUGH_DATA) {
			canvas.height = video.videoHeight;
			canvas.width  = video.videoWidth;
			ctx.drawImage(video, 0, 0, canvas.width, canvas.height);
			var img  = ctx.getImageData(0, 0, canvas.width, canvas.height);
			var code = jsQR(img.data, img.width, img.height, { inversionAttempts: 'dontInvert' });
			if (code && !cooldown) {
				verify(code.data);
				return;
			}
		}
		requestAnimationFrame(tick);
	}

	// ── Verify token ─────────────────────────────────────────────────────────
	function verify(token) {
		cooldown = true;
		camStat.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Verifying...';

		$.ajax({
			type: 'POST',
			url: base_url + 'pickup/verify_qr',
			data: { token: token },
			dataType: 'json',
			success: function (d) {
				var $result = $('#scan_result');
				var $ok     = $('#result_success');
				var $err    = $('#result_error');
				$result.show();

				if (d.status === 'success') {
					$('#res_person').text(d.person_name);
					$('#res_rel').text(d.relationship || '—');
					$('#res_student').text(d.student_name);
					$('#res_class').text(d.class_name + ' / ' + d.section_name);
					$('#res_regno').text(d.register_no);
					$('#res_time').text(d.time);
					$ok.show(); $err.hide();
					camStat.innerHTML = '<i class="fas fa-check-circle" style="color:#2ecc71;"></i> Verified — scanning next...';
				} else {
					$('#res_error_msg').text(d.message);
					$err.show(); $ok.hide();
					camStat.innerHTML = '<i class="fas fa-times-circle" style="color:#e74c3c;"></i> Not recognized — try again';
				}

				// Resume scanning after 2.5 s
				setTimeout(function () {
					cooldown = false;
					$('#qr_input').val('');
					requestAnimationFrame(tick);
					camStat.innerHTML = '<i class="fas fa-circle" style="color:#e74c3c;font-size:8px;"></i> Scanning...';
				}, 2500);
			},
			error: function () {
				$('#res_error_msg').text('Server error. Please try again.');
				$('#result_error').show(); $('#result_success').hide();
				$('#scan_result').show();
				cooldown = false;
				requestAnimationFrame(tick);
			}
		});
	}

	// ── Manual input ─────────────────────────────────────────────────────────
	document.getElementById('btnVerify').addEventListener('click', function () {
		var t = document.getElementById('qr_input').value.trim();
		if (t) verify(t);
	});
	document.getElementById('qr_input').addEventListener('keypress', function (e) {
		if (e.which === 13) {
			var t = this.value.trim();
			if (t) verify(t);
		}
	});

	// Start immediately
	startCamera();
})();
</script>
