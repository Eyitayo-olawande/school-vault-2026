<div class="row">
    <div class="col-md-12">
        <section class="panel">
            <header class="panel-heading">
                <div class="panel-actions">
                    <a href="#" class="panel-action panel-action-toggle" data-panel-toggle></a>
                </div>
                <h2 class="panel-title"><?= translate('manage_alumni') ?></h2>
            </header>
            <div class="panel-body">

                <!-- Session filter -->
                <form method="get" action="<?= base_url('alumni/index') ?>" class="form-inline mb-md">
                    <div class="form-group mr-sm">
                        <label class="mr-xs"><?= translate('session') ?>:</label>
                        <select name="session_id" class="form-control input-sm" data-plugin-selectTwo data-width="180px">
                            <option value=""><?= translate('all') ?></option>
                            <?php foreach ($sessions as $sy): ?>
                            <option value="<?= $sy['id'] ?>" <?= ($selected_session == $sy['id']) ? 'selected' : '' ?>>
                                <?= html_escape($sy['school_year']) ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <button type="submit" class="btn btn-default btn-sm">
                        <i class="fas fa-filter"></i> <?= translate('filter') ?>
                    </button>
                    <?php if ($selected_session): ?>
                    <a href="<?= base_url('alumni/index') ?>" class="btn btn-link btn-sm"><?= translate('clear') ?></a>
                    <?php endif; ?>
                </form>

                <!-- Legend -->
                <div class="mb-sm" style="font-size:12px;">
                    <span class="label label-danger mr-xs">Outstanding</span> Fees unpaid — cannot issue certificate &nbsp;
                    <span class="label label-warning mr-xs">Fee Cleared</span> Balance settled — certificate can be issued &nbsp;
                    <span class="label label-success mr-xs">Issued</span> Certificate collected
                </div>

                <div class="table-responsive">
                    <table class="table table-bordered table-hover table-condensed" id="alumniTable">
                        <thead>
                            <tr>
                                <th width="40">#</th>
                                <th><?= translate('name') ?></th>
                                <th><?= translate('register_no') ?></th>
                                <th><?= translate('class') ?> / <?= translate('section') ?></th>
                                <th><?= translate('session') ?></th>
                                <th>Outstanding</th>
                                <th>Certificate Status</th>
                                <?php if (get_permission('manage_alumni', 'is_add')): ?>
                                <th width="160">Action</th>
                                <?php endif; ?>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (!empty($alumni)): ?>
                            <?php $n = 1; foreach ($alumni as $row):
                                $outstanding   = (float)$row['outstanding'];
                                $isCleared     = !empty($row['cleared_at']);
                                $isIssued      = !empty($row['issued_at']);
                                $photo = !empty($row['photo'])
                                    ? base_url('uploads/student/' . $row['photo'])
                                    : base_url('assets/images/avatar/avatar.png');
                            ?>
                            <tr>
                                <td><?= $n++ ?></td>
                                <td>
                                    <img src="<?= $photo ?>" class="img-circle mr-xs" style="width:28px;height:28px;object-fit:cover;" alt="">
                                    <a href="<?= base_url('student/profile/' . $row['student_id']) ?>">
                                        <?= html_escape($row['first_name'] . ' ' . $row['last_name']) ?>
                                    </a>
                                </td>
                                <td><?= html_escape($row['register_no']) ?></td>
                                <td><?= html_escape($row['class_name'] . ' (' . $row['section_name'] . ')') ?></td>
                                <td><?= html_escape($row['school_year']) ?></td>
                                <td class="text-right" style="font-variant-numeric:tabular-nums;">
                                    <?php if ($outstanding > 0): ?>
                                        <strong class="text-danger"><?= number_format($outstanding, 2) ?></strong>
                                    <?php else: ?>
                                        <span class="text-success"><i class="fas fa-check-circle"></i> Nil</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if ($isIssued): ?>
                                        <span class="label label-success">
                                            <i class="fas fa-certificate"></i> Issued
                                        </span>
                                        <br><small class="text-muted"><?= _d($row['issued_at']) ?></small>
                                    <?php elseif ($isCleared): ?>
                                        <span class="label label-warning">
                                            <i class="fas fa-check"></i> Fee Cleared
                                        </span>
                                        <br><small class="text-muted"><?= _d($row['cleared_at']) ?></small>
                                    <?php else: ?>
                                        <span class="label label-danger">
                                            <i class="fas fa-lock"></i> Outstanding
                                        </span>
                                    <?php endif; ?>
                                </td>
                                <?php if (get_permission('manage_alumni', 'is_add')): ?>
                                <td>
                                    <?php if ($isIssued): ?>
                                        <span class="text-muted" style="font-size:11px;"><i class="fas fa-check-double"></i> Done</span>
                                    <?php elseif ($isCleared): ?>
                                        <button class="btn btn-xs btn-success btn-mark-issued"
                                            data-student-id="<?= $row['student_id'] ?>"
                                            data-name="<?= html_escape($row['first_name'] . ' ' . $row['last_name']) ?>">
                                            <i class="fas fa-certificate"></i> Issue Certificate
                                        </button>
                                    <?php elseif ($outstanding <= 0): ?>
                                        <button class="btn btn-xs btn-warning btn-mark-cleared"
                                            data-student-id="<?= $row['student_id'] ?>"
                                            data-name="<?= html_escape($row['first_name'] . ' ' . $row['last_name']) ?>">
                                            <i class="fas fa-check"></i> Mark Fee Cleared
                                        </button>
                                    <?php else: ?>
                                        <span class="text-danger" style="font-size:11px;" title="Collect outstanding fees first">
                                            <i class="fas fa-lock"></i> Fees pending
                                        </span>
                                    <?php endif; ?>
                                </td>
                                <?php endif; ?>
                            </tr>
                            <?php endforeach; ?>
                            <?php else: ?>
                            <tr>
                                <td colspan="8">
                                    <h5 class="text-danger text-center"><?= translate('no_information_available') ?></h5>
                                </td>
                            </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>

                <?php if (!empty($alumni)): ?>
                <p class="text-muted mt-sm" style="font-size:12px;">
                    <?= count($alumni) ?> alumni record<?= count($alumni) !== 1 ? 's' : '' ?>
                    <?= $selected_session ? 'in selected session' : 'across all sessions' ?>
                </p>
                <?php endif; ?>
            </div>
        </section>
    </div>
</div>

<script>
$(document).ready(function () {
    if ($.fn.DataTable) {
        $('#alumniTable').DataTable({
            order: [[4, 'desc'], [1, 'asc']],
            pageLength: 25,
            columnDefs: [{ orderable: false, targets: [-1] }]
        });
    }

    function alumniAjax(url, studentId, name, confirmMsg, btn, successMsg) {
        if (!confirm(confirmMsg)) return;
        btn.prop('disabled', true).prepend('<i class="fas fa-spinner fa-spin"></i> ');
        $.post(base_url + url, { student_id: studentId }, function (resp) {
            if (resp.status === 'success') {
                set_alert('success', successMsg);
                setTimeout(function () { location.reload(); }, 1200);
            } else {
                set_alert('error', resp.error || 'Action failed.');
                btn.prop('disabled', false).find('.fa-spinner').remove();
            }
        }, 'json').fail(function () {
            set_alert('error', 'Server error — please try again.');
            btn.prop('disabled', false).find('.fa-spinner').remove();
        });
    }

    $(document).on('click', '.btn-mark-cleared', function () {
        var btn  = $(this);
        var id   = btn.data('student-id');
        var name = btn.data('name');
        alumniAjax(
            'alumni/mark_cleared', id, name,
            'Confirm that all outstanding fees for ' + name + ' have been collected and settled?',
            btn,
            'Fee clearance recorded for ' + name + '.'
        );
    });

    $(document).on('click', '.btn-mark-issued', function () {
        var btn  = $(this);
        var id   = btn.data('student-id');
        var name = btn.data('name');
        alumniAjax(
            'alumni/mark_issued', id, name,
            'Confirm that the certificate has been physically handed to ' + name + '?',
            btn,
            'Certificate issued for ' + name + '.'
        );
    });
});
</script>
