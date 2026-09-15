<?php defined('BASEPATH') or exit('No direct script access allowed'); ?>
<div class="row">
    <div class="col-md-12">
        <section class="panel">
            <header class="panel-heading">
                <h4 class="panel-title">
                    <i class="fas fa-users"></i>
                    <?= html_escape($exam->title) ?> &mdash; Student Status
                    <span class="badge badge-default ml-sm"><?= count($students) ?></span>
                </h4>
                <div class="panel-btn">
                    <a href="<?= base_url('onlineexam/question_list/' . $exam->id) ?>" class="btn btn-default btn-circle">
                        <i class="fas fa-list"></i> Question List
                    </a>
                    <a href="<?= base_url('onlineexam') ?>" class="btn btn-default btn-circle">
                        <i class="fas fa-display"></i> Exam List
                    </a>
                </div>
            </header>
            <div class="panel-body">
                <?php if (empty($students)): ?>
                    <div class="alert alert-info"><i class="fas fa-info-circle"></i> No students enrolled for this exam.</div>
                <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-bordered table-condensed table-hover table-export">
                        <thead>
                            <tr>
                                <th>#</th>
                                <th>Name</th>
                                <th>Reg No</th>
                                <th>Section</th>
                                <th>Attempts Used</th>
                                <th>Status</th>
                                <th>Extra Minutes Granted</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($students as $i => $s): ?>
                            <tr>
                                <td><?= $i + 1 ?></td>
                                <td><?= html_escape($s['fullname']) ?></td>
                                <td><?= html_escape($s['register_no']) ?></td>
                                <td><?= html_escape($s['section_name'] ?? '—') ?></td>
                                <td><?= (int)$s['attempt_count'] ?> / <?= (int)$exam->limits_participation ?></td>
                                <td>
                                    <?php if ($s['submitted_id']): ?>
                                        <span class="label label-success">Submitted</span>
                                    <?php elseif ($s['attempt_count'] > 0): ?>
                                        <span class="label label-warning">In Progress</span>
                                    <?php else: ?>
                                        <span class="label label-default">Not Started</span>
                                    <?php endif; ?>
                                </td>
                                <td><?= $s['extra_minutes'] > 0 ? $s['extra_minutes'] . ' min' : '—' ?></td>
                                <td>
                                    <button class="btn btn-xs btn-default btn-restore"
                                        data-student-id="<?= $s['student_id'] ?>"
                                        data-student-name="<?= html_escape($s['fullname']) ?>"
                                        data-extra="<?= (int)$s['extra_minutes'] ?>">
                                        <i class="fas fa-rotate-left"></i> Restore
                                    </button>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <?php endif; ?>
            </div>
        </section>
    </div>
</div>

<!-- Restore modal -->
<div class="zoom-anim-dialog modal-block mfp-hide" id="modal-restore">
    <section class="panel">
        <header class="panel-heading">
            <h4 class="panel-title"><i class="fas fa-rotate-left"></i> Restore Student</h4>
        </header>
        <div class="panel-body">
            <p>Restoring <strong id="restore-name"></strong> will:</p>
            <ul>
                <li>Clear their submitted record so they can retake</li>
                <li>Refund one attempt so it doesn't count against them</li>
                <li>Add any extra time you specify below to their next session</li>
            </ul>
            <div class="form-group">
                <label class="control-label">Extra Minutes to Grant <small class="text-muted">(0 = no extra time)</small></label>
                <input type="number" id="restore-extra-minutes" class="form-control" min="0" max="120" value="0" style="max-width:140px;">
            </div>
        </div>
        <footer class="panel-footer">
            <div class="row">
                <div class="col-md-12 text-right">
                    <button class="btn btn-default modal-dismiss mr-sm">Cancel</button>
                    <button id="btn-confirm-restore" class="btn btn-primary" data-loading-text="<i class='fas fa-spinner fa-spin'></i> Restoring...">
                        <i class="fas fa-rotate-left"></i> Confirm Restore
                    </button>
                </div>
            </div>
        </footer>
    </section>
</div>

<script type="text/javascript">
$(document).ready(function() {
    var currentStudentID = null;
    var examID = <?= (int)$exam->id ?>;

    $('.btn-restore').on('click', function() {
        currentStudentID = $(this).data('student-id');
        $('#restore-name').text($(this).data('student-name'));
        $('#restore-extra-minutes').val($(this).data('extra') || 0);
        mfp_modal('#modal-restore');
    });

    $('#btn-confirm-restore').on('click', function() {
        if (!currentStudentID) return;
        var btn = $(this);
        btn.button('loading');
        $.ajax({
            url: base_url + 'onlineexam/restore_student',
            type: 'POST',
            data: {
                student_id: currentStudentID,
                exam_id: examID,
                extra_minutes: $('#restore-extra-minutes').val()
            },
            dataType: 'json',
            success: function(data) {
                $.magnificPopup.close();
                if (data.status === 'success') {
                    swal({
                        toast: true,
                        position: 'top-end',
                        type: 'success',
                        title: data.message,
                        timer: 4000,
                        showConfirmButton: false
                    }).then(function() { location.reload(); });
                    setTimeout(function() { location.reload(); }, 1500);
                } else {
                    alertMsg(data.message, 'error');
                }
            },
            complete: function() { btn.button('reset'); }
        });
    });
});
</script>
