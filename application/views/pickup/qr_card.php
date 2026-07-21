<?php
$p = $person;
?>
<div class="row">
    <div class="col-md-6 col-md-offset-3 col-sm-8 col-sm-offset-2">
        <section class="panel">
            <header class="panel-heading">
                <h4 class="panel-title">
                    <i class="fas fa-id-card"></i> Authorized Pickup QR Card
                    <span class="pull-right no-print">
                        <a href="<?=base_url('pickup')?>" class="btn btn-default btn-xs">
                            <i class="fas fa-arrow-left"></i> Back to List
                        </a>
                        &nbsp;
                        <button onclick="window.print()" class="btn btn-primary btn-xs">
                            <i class="fas fa-print"></i> Print
                        </button>
                    </span>
                </h4>
            </header>
            <div class="panel-body text-center" style="padding:30px 20px;">

                <!-- Authorized person details -->
                <div style="background:#f8f9fa;border-radius:8px;padding:16px;margin-bottom:20px;">
                    <h3 style="margin:0 0 4px;font-weight:700;"><?=html_escape($p['name'])?></h3>
                    <?php if (!empty($p['relationship'])): ?>
                    <p class="text-muted" style="margin:0 0 4px;font-size:14px;">
                        <?=html_escape($p['relationship'])?>
                    </p>
                    <?php endif; ?>
                    <?php if (!empty($p['phone'])): ?>
                    <p class="text-muted" style="margin:0 0 2px;font-size:13px;">
                        <i class="fas fa-phone fa-xs"></i> <?=html_escape($p['phone'])?>
                    </p>
                    <?php endif; ?>
                </div>

                <!-- Student they're authorized for -->
                <div style="margin-bottom:20px;">
                    <p style="font-size:13px;color:#666;margin-bottom:6px;">Authorized to pick up:</p>
                    <?php if (!empty($p['student_photo'])): ?>
                    <img src="<?=get_image_url('student', $p['student_photo'])?>"
                         style="width:60px;height:60px;border-radius:50%;object-fit:cover;border:2px solid #ddd;margin-bottom:8px;">
                    <?php endif; ?>
                    <div>
                        <strong style="font-size:15px;"><?=html_escape($p['first_name'] . ' ' . $p['last_name'])?></strong><br>
                        <small class="text-muted">
                            <?=html_escape($p['class_name'])?> &mdash; <?=html_escape($p['section_name'])?>
                            &middot; Reg: <?=html_escape($p['register_no'])?>
                        </small>
                    </div>
                </div>

                <!-- QR code -->
                <div style="display:inline-block;padding:12px;border:2px solid #ddd;border-radius:8px;background:#fff;margin-bottom:16px;">
                    <img src="<?=html_escape($qr_data_uri)?>"
                         alt="Pickup QR Code"
                         style="width:200px;height:200px;display:block;">
                </div>

                <p class="text-muted" style="font-size:12px;margin:0;">
                    Present this QR code at the pickup gate
                </p>
            </div>
        </section>
    </div>
</div>
<style>
@media print {
    .no-print, header.navbar, .sidebar, nav, footer, .breadcrumb { display: none !important; }
    .panel { border: none !important; box-shadow: none !important; }
    body { background: #fff !important; }
}
</style>
