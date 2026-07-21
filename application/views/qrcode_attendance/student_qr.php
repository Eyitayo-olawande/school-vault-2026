<?php
$s       = $student;
$qrUrl   = base_url('qrcode_attendance/qr_image/' . $s['qr_token']);
$scanUrl = site_url('qrcode_attendance/scan/' . $s['qr_token']);
?>
<div class="row">
    <div class="col-md-6 col-md-offset-3 col-sm-8 col-sm-offset-2">
        <section class="panel">
            <header class="panel-heading">
                <h4 class="panel-title">
                    <i class="fas fa-qrcode"></i> Student Attendance QR Card
                    <span class="pull-right no-print">
                        <a href="<?=base_url('qrcode_attendance/generate')?>" class="btn btn-default btn-xs">
                            <i class="fas fa-arrow-left"></i> Class Sheet
                        </a>
                        &nbsp;
                        <button onclick="window.print()" class="btn btn-primary btn-xs">
                            <i class="fas fa-print"></i> Print
                        </button>
                    </span>
                </h4>
            </header>
            <div class="panel-body text-center" style="padding:30px 20px;">
                <!-- Student photo -->
                <?php if (!empty($s['photo'])): ?>
                <img src="<?=get_image_url('student', $s['photo'])?>"
                     style="width:80px;height:80px;border-radius:50%;object-fit:cover;border:3px solid #ddd;margin-bottom:12px;">
                <?php endif; ?>

                <!-- Name & details -->
                <h3 style="margin:0 0 4px;font-weight:700;"><?=html_escape($s['first_name'] . ' ' . $s['last_name'])?></h3>
                <p class="text-muted" style="margin:0 0 2px;font-size:14px;">
                    <?=html_escape($s['class_name'])?> &mdash; <?=html_escape($s['section_name'])?>
                </p>
                <p class="text-muted" style="margin:0 0 20px;font-size:13px;">
                    Reg No: <strong><?=html_escape($s['register_no'])?></strong>
                    <?php if (!empty($s['roll'])): ?>
                    &middot; Roll: <strong><?=html_escape($s['roll'])?></strong>
                    <?php endif; ?>
                </p>

                <!-- QR code -->
                <div style="display:inline-block;padding:12px;border:2px solid #ddd;border-radius:8px;background:#fff;margin-bottom:16px;">
                    <img src="<?=html_escape($qrUrl)?>"
                         alt="QR Code"
                         style="width:200px;height:200px;display:block;">
                </div>

                <p class="text-muted" style="font-size:12px;margin:0;">
                    Scan to mark attendance &nbsp;&middot;&nbsp;
                    <small style="word-break:break-all;"><?=html_escape($scanUrl)?></small>
                </p>
            </div>
        </section>

        <!-- Back to student list -->
        <div class="text-center no-print mb-md">
            <a href="javascript:history.back()" class="btn btn-link btn-sm">
                <i class="fas fa-chevron-left"></i> Back
            </a>
        </div>
    </div>
</div>
<style>
@media print {
    .no-print, header.navbar, .sidebar, nav, footer, .breadcrumb { display: none !important; }
    .panel { border: none !important; box-shadow: none !important; }
    body { background: #fff !important; }
}
</style>
