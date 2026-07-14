<?php $widget = (is_superadmin_loggedin() ? 3 : 4); ?>
<section class="panel">
    <header class="panel-heading">
        <h4 class="panel-title"><?=translate('select_ground')?></h4>
    </header>
    <?php echo form_open($this->uri->uri_string()); ?>
    <div class="panel-body">
        <div class="row mb-sm">
        <?php if (is_superadmin_loggedin()): ?>
            <div class="col-md-3 mb-sm">
                <div class="form-group">
                    <label class="control-label"><?=translate('branch')?> <span class="required">*</span></label>
                    <?php
                    $arrayBranch = $this->app_lib->getSelectList('branch');
                    echo form_dropdown('branch_id', $arrayBranch, set_value('branch_id'),
                        "class='form-control' onchange='getClassByBranch(this.value)' id='branchID'
                        data-plugin-selectTwo data-width='100%' data-minimum-results-for-search='Infinity'");
                    ?>
                </div>
            </div>
        <?php endif; ?>
            <div class="col-md-<?=$widget?> mb-sm">
                <div class="form-group">
                    <label class="control-label"><?=translate('class')?> <span class="required">*</span></label>
                    <?php
                    $arrayClass = $this->app_lib->getClass($branch_id);
                    echo form_dropdown('class_id', $arrayClass, set_value('class_id'),
                        "class='form-control' id='class_id' onchange='getSectionByClass(this.value,0)'
                        data-plugin-selectTwo data-width='100%' data-minimum-results-for-search='Infinity'");
                    ?>
                </div>
            </div>
            <div class="col-md-<?=$widget?> mb-sm">
                <div class="form-group">
                    <label class="control-label"><?=translate('section')?> <span class="required">*</span></label>
                    <?php
                    $arraySection = $this->app_lib->getSections(set_value('class_id'), false);
                    echo form_dropdown('section_id', $arraySection, set_value('section_id'),
                        "class='form-control' id='section_id'
                        data-plugin-selectTwo data-width='100%' data-minimum-results-for-search='Infinity'");
                    ?>
                </div>
            </div>
        </div>
    </div>
    <footer class="panel-footer">
        <div class="row">
            <div class="col-md-offset-10 col-md-2">
                <button type="submit" class="btn btn-default btn-block">
                    <i class="fas fa-qrcode"></i> Generate QR Sheet
                </button>
            </div>
        </div>
    </footer>
    <?php echo form_close(); ?>
</section>

<?php if (isset($students)): ?>
<section class="panel appear-animation" data-appear-animation="fadeIn" data-appear-animation-delay="100">
    <header class="panel-heading">
        <h4 class="panel-title">
            <i class="fas fa-qrcode"></i> QR Code Sheet
            <button onclick="window.print()" class="btn btn-default btn-sm pull-right">
                <i class="fas fa-print"></i> Print
            </button>
        </h4>
    </header>
    <div class="panel-body">
        <?php if (empty($students)): ?>
        <div class="alert alert-info">No students found in this class/section.</div>
        <?php else: ?>

        <!-- Open session panel -->
        <div class="alert alert-info mb-md" id="sessionStatus">
            <i class="fas fa-info-circle"></i>
            Open a scan session before scanning, so QR codes are only accepted within the time window.
        </div>
        <div class="row mb-md no-print">
            <div class="col-md-3">
                <input type="date" id="sessionDate" class="form-control" value="<?=date('Y-m-d')?>">
            </div>
            <div class="col-md-3">
                <select id="windowMin" class="form-control">
                    <option value="10">10-minute window</option>
                    <option value="15" selected>15-minute window</option>
                    <option value="20">20-minute window</option>
                    <option value="30">30-minute window</option>
                </select>
            </div>
            <div class="col-md-3">
                <button id="btnOpenSession" class="btn btn-success btn-block">
                    <i class="fas fa-lock-open"></i> Open Scan Session
                </button>
            </div>
        </div>

        <!-- QR code grid (printable) -->
        <div class="row">
        <?php foreach ($students as $s): ?>
            <div class="col-md-3 col-sm-4 col-xs-6 text-center mb-lg">
                <div class="panel panel-default" style="padding:12px;">
                    <img src="<?=base_url('qrcode_attendance/qr_image/' . $s['qr_token'])?>"
                         alt="QR for <?=html_escape($s['first_name'])?>"
                         style="width:120px;height:120px;display:block;margin:0 auto 8px;">
                    <strong style="font-size:13px;"><?=html_escape($s['first_name'] . ' ' . $s['last_name'])?></strong><br>
                    <small class="text-muted">Roll: <?=html_escape($s['roll'])?> &middot; <?=html_escape($s['register_no'])?></small>
                </div>
            </div>
        <?php endforeach; ?>
        </div>

        <?php endif; ?>
    </div>
</section>

<script>
document.getElementById('btnOpenSession').addEventListener('click', function () {
    var btn = this;
    btn.disabled = true;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Opening...';

    fetch(base_url + 'qrcode_attendance/open_session', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: new URLSearchParams({
            <?php echo $this->security->get_csrf_token_name(); ?>: document.cookie.match(/<?=addslashes($this->config->item('csrf_cookie_name'))?>=([^;]+)/)?.[1] || '',
            class_id:       '<?=(int)$class_id?>',
            section_id:     '<?=(int)$section_id?>',
            date:           document.getElementById('sessionDate').value,
            window_minutes: document.getElementById('windowMin').value,
        })
    })
    .then(r => r.json())
    .then(data => {
        if (data.status === 'ok') {
            var closesAt = new Date(data.closes_at.replace(' ', 'T'));
            document.getElementById('sessionStatus').className = 'alert alert-success mb-md';
            document.getElementById('sessionStatus').innerHTML =
                '<i class="fas fa-check-circle"></i> Scan session open — accepts QR scans until <strong>' +
                closesAt.toLocaleTimeString() + '</strong>. You may now scan student QR codes.';
        }
        btn.disabled = false;
        btn.innerHTML = '<i class="fas fa-lock-open"></i> Open Scan Session';
    })
    .catch(function () {
        btn.disabled = false;
        btn.innerHTML = '<i class="fas fa-lock-open"></i> Open Scan Session';
        alert('Failed to open session. Please try again.');
    });
});
</script>

<style>
@media print {
    .no-print, header, footer, .sidebar, nav, .panel-heading button { display: none !important; }
    .panel { border: none !important; box-shadow: none !important; }
}
</style>
<?php endif; ?>
