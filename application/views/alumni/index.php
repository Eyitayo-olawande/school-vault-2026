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

                <div class="table-responsive">
                    <table class="table table-bordered table-hover table-condensed" id="alumniTable">
                        <thead>
                            <tr>
                                <th width="40">#</th>
                                <th><?= translate('name') ?></th>
                                <th><?= translate('register_no') ?></th>
                                <th><?= translate('guardian_name') ?></th>
                                <th><?= translate('class') ?> / <?= translate('section') ?></th>
                                <th><?= translate('session') ?></th>
                                <th><?= translate('mobileno') ?></th>
                                <th width="80"><?= translate('action') ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (!empty($alumni)): ?>
                            <?php $n = 1; foreach ($alumni as $row): ?>
                            <tr>
                                <td><?= $n++ ?></td>
                                <td>
                                    <?php
                                    $photo = !empty($row['photo'])
                                        ? base_url('uploads/student/' . $row['photo'])
                                        : base_url('assets/images/avatar/avatar.png');
                                    ?>
                                    <img src="<?= $photo ?>" class="img-circle mr-xs" style="width:28px;height:28px;object-fit:cover;" alt="">
                                    <?= html_escape($row['first_name'] . ' ' . $row['last_name']) ?>
                                </td>
                                <td><?= html_escape($row['register_no']) ?></td>
                                <td><?= html_escape($row['guardian_name'] ?: 'N/A') ?></td>
                                <td><?= html_escape($row['class_name'] . ' (' . $row['section_name'] . ')') ?></td>
                                <td><?= html_escape($row['school_year']) ?></td>
                                <td><?= html_escape($row['mobileno']) ?></td>
                                <td>
                                    <a href="<?= base_url('student/profile/' . $row['student_id']) ?>"
                                       class="btn btn-xs btn-default" title="<?= translate('view') ?>">
                                        <i class="fas fa-eye"></i>
                                    </a>
                                </td>
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
                <p class="text-muted mt-sm">
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
            order: [[5, 'desc'], [1, 'asc']],
            pageLength: 25,
            columnDefs: [{ orderable: false, targets: [7] }]
        });
    }
});
</script>
