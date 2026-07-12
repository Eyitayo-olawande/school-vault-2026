<!-- QR Attendance Scan Result -->
<div class="row">
    <div class="col-md-6 col-md-offset-3">
        <section class="panel">
            <header class="panel-heading">
                <h4 class="panel-title"><i class="fas fa-qrcode"></i> QR Attendance</h4>
            </header>
            <div class="panel-body text-center" style="padding: 32px;">

            <?php if (!empty($error)): ?>
                <div class="text-danger mb-md" style="font-size:48px;"><i class="fas fa-times-circle"></i></div>
                <h4 class="text-danger"><?=html_escape($error)?></h4>
                <?php if (!empty($student)): ?>
                <p class="text-muted mt-sm"><?=html_escape($student['first_name'] . ' ' . $student['last_name'])?></p>
                <?php endif; ?>

            <?php elseif (!empty($info)): ?>
                <div class="text-info mb-md" style="font-size:48px;"><i class="fas fa-info-circle"></i></div>
                <h4 class="text-info"><?=html_escape($info)?></h4>
                <?php if (!empty($student)): ?>
                <p class="text-muted mt-sm">
                    <strong><?=html_escape($student['first_name'] . ' ' . $student['last_name'])?></strong><br>
                    Reg: <?=html_escape($student['register_no'])?>
                </p>
                <?php endif; ?>

            <?php elseif (!empty($success)): ?>
                <div class="text-success mb-md" style="font-size:64px;"><i class="fas fa-check-circle"></i></div>
                <h3 class="text-success">Attendance Marked</h3>
                <p class="mt-sm" style="font-size:18px;">
                    <strong><?=html_escape($student['first_name'] . ' ' . $student['last_name'])?></strong>
                </p>
                <p class="text-muted">
                    Reg: <?=html_escape($student['register_no'])?><br>
                    Date: <?=html_escape($date)?><br>
                    Status: <span class="label label-success">Present (QR)</span>
                </p>
            <?php endif; ?>

            </div>
            <footer class="panel-footer text-right">
                <a href="javascript:history.back()" class="btn btn-default">
                    <i class="fas fa-arrow-left"></i> Back
                </a>
            </footer>
        </section>
    </div>
</div>
