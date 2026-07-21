<script src="<?=base_url('assets/vendor/jsqr/jsQR.min.js')?>"></script>
<div class="row">
	<div class="col-md-8 col-md-offset-2">
		<section class="panel">
			<header class="panel-heading">
				<h4 class="panel-title">
					<i class="fas fa-camera"></i> QR Attendance — Camera Scanner
					<a href="<?=base_url('qrcode_attendance/generate')?>" class="btn btn-default btn-xs pull-right">
						<i class="fas fa-qrcode"></i> Print QR Sheets
					</a>
				</h4>
				<p class="text-muted" style="padding:0 15px 8px;font-size:13px;">
					Point the camera at a student's QR card. Attendance is marked automatically on detection.
				</p>
			</header>
			<div class="panel-body">

				<!-- Camera viewfinder -->
				<div id="camera_wrap" style="position:relative;background:#000;border-radius:6px;overflow:hidden;max-width:480px;margin:0 auto 16px;">
					<video id="qr_video" style="width:100%;display:block;" playsinline autoplay muted></video>
					<canvas id="qr_canvas" style="display:none;"></canvas>
					<div style="position:absolute;top:50%;left:50%;transform:translate(-50%,-50%);width:200px;height:200px;pointer-events:none;">
						<div style="position:absolute;top:0;left:0;width:30px;height:30px;border-top:3px solid #27ae60;border-left:3px solid #27ae60;border-radius:4px 0 0 0;"></div>
						<div style="position:absolute;top:0;right:0;width:30px;height:30px;border-top:3px solid #27ae60;border-right:3px solid #27ae60;border-radius:0 4px 0 0;"></div>
						<div style="position:absolute;bottom:0;left:0;width:30px;height:30px;border-bottom:3px solid #27ae60;border-left:3px solid #27ae60;border-radius:0 0 0 4px;"></div>
						<div style="position:absolute;bottom:0;right:0;width:30px;height:30px;border-bottom:3px solid #27ae60;border-right:3px solid #27ae60;border-radius:0 0 4px 0;"></div>
					</div>
					<div id="cam_status" style="position:absolute;bottom:8px;left:0;right:0;text-align:center;color:#fff;font-size:13px;text-shadow:0 1px 3px #000;">
						<i class="fas fa-spinner fa-spin"></i> Starting camera...
					</div>
				</div>

				<div id="cam_error" style="display:none;" class="alert alert-warning text-center">
					<i class="fas fa-exclamation-triangle"></i> Camera not available.
					<br><small>Use a USB QR scanner or type the token below.</small>
				</div>

				<div class="form-group mt-sm">
					<label class="control-label" style="font-size:12px;color:#aaa;">Or enter token manually</label>
					<input type="text" id="qr_input" class="form-control" placeholder="Scan or type QR token..." autocomplete="off" />
				</div>

				<!-- Scan log (last 10 scans this session) -->
				<div id="scan_log" style="max-height:300px;overflow-y:auto;margin-top:12px;"></div>

			</div>
		</section>
	</div>
</div>
<script>
(function () {
	var video    = document.getElementById('qr_video');
	var canvas   = document.getElementById('qr_canvas');
	var ctx      = canvas.getContext('2d');
	var camStat  = document.getElementById('cam_status');
	var log      = document.getElementById('scan_log');
	var scanning = false;
	var cooldown = false;
	var scanned  = {}; // deduplicate within session

	function startCamera() {
		if (!navigator.mediaDevices || !navigator.mediaDevices.getUserMedia) {
			document.getElementById('cam_error').style.display = 'block';
			document.getElementById('camera_wrap').style.display = 'none';
			return;
		}
		navigator.mediaDevices.getUserMedia({ video: { facingMode: 'environment' } })
			.then(function (stream) {
				video.srcObject = stream;
				video.play();
				scanning = true;
				requestAnimationFrame(tick);
				camStat.innerHTML = '<i class="fas fa-circle" style="color:#2ecc71;font-size:8px;"></i> Scanning...';
			})
			.catch(function () {
				document.getElementById('cam_error').style.display = 'block';
				document.getElementById('camera_wrap').style.display = 'none';
			});
	}

	function tick() {
		if (!scanning) return;
		if (video.readyState === video.HAVE_ENOUGH_DATA) {
			canvas.height = video.videoHeight;
			canvas.width  = video.videoWidth;
			ctx.drawImage(video, 0, 0, canvas.width, canvas.height);
			var img  = ctx.getImageData(0, 0, canvas.width, canvas.height);
			var code = jsQR(img.data, img.width, img.height, { inversionAttempts: 'dontInvert' });
			if (code && !cooldown) {
				processQR(code.data);
				return;
			}
		}
		requestAnimationFrame(tick);
	}

	function processQR(raw) {
		// The attendance QR code contains the full scan URL. Extract the token.
		var token = raw;
		var match = raw.match(/qrcode_attendance\/scan\/([a-f0-9]+)/i);
		if (match) token = match[1];

		// Deduplicate: same token within 5 seconds
		if (scanned[token]) return;
		scanned[token] = true;
		setTimeout(function () { delete scanned[token]; }, 5000);

		cooldown = true;
		camStat.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Marking attendance...';

		// Navigate to the scan URL — the server marks attendance and redirects to scan_result
		// We do this in a hidden iframe so the camera stays active
		var iframe = document.createElement('iframe');
		iframe.style.display = 'none';
		iframe.src = base_url + 'qrcode_attendance/scan/' + encodeURIComponent(token);
		document.body.appendChild(iframe);

		iframe.onload = function () {
			try {
				var body = iframe.contentDocument.body.innerText || '';
				// Parse JSON if the endpoint returns it, else assume success
				addLogEntry(token, body);
			} catch (e) {
				addLogEntry(token, '');
			}
			document.body.removeChild(iframe);
			cooldown = false;
			requestAnimationFrame(tick);
			camStat.innerHTML = '<i class="fas fa-circle" style="color:#2ecc71;font-size:8px;"></i> Scanning...';
		};
	}

	function addLogEntry(token, body) {
		var time = new Date().toLocaleTimeString();
		var html = '<div class="alert alert-success alert-sm mb-xs" style="padding:8px 12px;">' +
			'<i class="fas fa-check-circle"></i> ' +
			'<strong>' + time + '</strong> — QR scanned (<code>' + token.substring(0, 8) + '…</code>)' +
			'</div>';
		log.insertAdjacentHTML('afterbegin', html);
		// Keep only 10 entries
		var entries = log.querySelectorAll('.alert');
		if (entries.length > 10) entries[entries.length - 1].remove();
	}

	// Manual input: on Enter or blur
	var input = document.getElementById('qr_input');
	input.addEventListener('keypress', function (e) {
		if (e.which === 13 && this.value.trim()) {
			processQR(this.value.trim());
			this.value = '';
		}
	});

	startCamera();
})();
</script>
