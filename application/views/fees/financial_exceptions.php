<div class="row">
    <div class="col-md-12">
        <div class="row mb-md">
            <div class="col-md-12">
                <button id="run-detection" class="btn btn-primary">
                    <i class="fas fa-search"></i> Run Exception Detection
                </button>
                <small class="text-muted ml-sm">Scans for overpayments, orphaned records, and stranded wallet balances.</small>
            </div>
        </div>

        <!-- Open Exceptions -->
        <section class="panel">
            <header class="panel-heading">
                <h4 class="panel-title">
                    <i class="fas fa-exclamation-circle text-danger"></i> Open Exceptions
                    <span class="badge badge-danger"><?=count($exceptions)?></span>
                </h4>
            </header>
            <div class="panel-body">
                <?php if (empty($exceptions)): ?>
                <p class="text-success text-center"><i class="fas fa-check-circle"></i> No open exceptions.</p>
                <?php else: ?>
                <table class="table table-bordered table-condensed table-hover mb-none" id="exceptions-table">
                    <thead>
                        <tr>
                            <th>Severity</th>
                            <th>Type</th>
                            <th>Student</th>
                            <th>Reg No</th>
                            <th class="text-right">Amount</th>
                            <th>Description</th>
                            <th>Detected</th>
                            <th class="no-sort">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($exceptions as $ex): ?>
                        <tr data-id="<?=$ex['id']?>">
                            <td>
                                <?php if ($ex['severity'] === 'critical'): ?>
                                <span class="label label-danger">Critical</span>
                                <?php elseif ($ex['severity'] === 'warning'): ?>
                                <span class="label label-warning">Warning</span>
                                <?php else: ?>
                                <span class="label label-info">Info</span>
                                <?php endif; ?>
                            </td>
                            <td><?=htmlspecialchars(str_replace('_', ' ', ucfirst($ex['exception_type'])))?></td>
                            <td><?=htmlspecialchars($ex['student_name'] ?: '—')?></td>
                            <td><?=htmlspecialchars($ex['register_no'] ?: '—')?></td>
                            <td class="text-right"><?=!empty($ex['amount']) ? currencyFormat($ex['amount']) : '—'?></td>
                            <td><?=htmlspecialchars($ex['description'])?></td>
                            <td><?=_d($ex['detected_at'])?></td>
                            <td>
                                <button class="btn btn-xs btn-success resolve-btn" data-id="<?=$ex['id']?>">
                                    <i class="fas fa-check"></i> Resolve
                                </button>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                <?php endif; ?>
            </div>
        </section>

        <!-- Resolved Exceptions -->
        <?php if (!empty($resolved)): ?>
        <section class="panel appear-animation" data-appear-animation="<?=$global_config['animations']?>" data-appear-animation-delay="100">
            <header class="panel-heading">
                <h4 class="panel-title">
                    <i class="fas fa-check-circle text-success"></i> Resolved Exceptions
                </h4>
            </header>
            <div class="panel-body">
                <table class="table table-bordered table-condensed table-hover mb-none" id="resolved-table">
                    <thead>
                        <tr>
                            <th>Severity</th>
                            <th>Type</th>
                            <th>Student</th>
                            <th class="text-right">Amount</th>
                            <th>Description</th>
                            <th>Detected</th>
                            <th>Resolved</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($resolved as $ex): ?>
                        <tr>
                            <td>
                                <span class="label label-<?=($ex['severity'] === 'critical' ? 'danger' : ($ex['severity'] === 'warning' ? 'warning' : 'info'))?>">
                                    <?=ucfirst($ex['severity'])?>
                                </span>
                            </td>
                            <td><?=htmlspecialchars(str_replace('_', ' ', ucfirst($ex['exception_type'])))?></td>
                            <td><?=htmlspecialchars($ex['student_name'] ?: '—')?></td>
                            <td class="text-right"><?=!empty($ex['amount']) ? currencyFormat($ex['amount']) : '—'?></td>
                            <td><?=htmlspecialchars($ex['description'])?></td>
                            <td><?=_d($ex['detected_at'])?></td>
                            <td><?=_d($ex['resolved_at'])?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </section>
        <?php endif; ?>
    </div>
</div>

<script>
$(document).ready(function() {
    $('#run-detection').on('click', function() {
        var btn = $(this).button('loading');
        $.post(base_url + 'fees/run_exception_detection', {}, function(data) {
            var res = JSON.parse(data);
            if (res.status === 'success') {
                set_alert('success', res.new_exceptions + ' new exception(s) detected.');
                setTimeout(function(){ location.reload(); }, 1200);
            }
        }).always(function(){ btn.button('reset'); });
    });

    $(document).on('click', '.resolve-btn', function() {
        var id = $(this).data('id');
        var row = $(this).closest('tr');
        $.post(base_url + 'fees/resolve_exception/' + id, {}, function(data) {
            var res = JSON.parse(data);
            if (res.status === 'success') {
                row.fadeOut(400, function(){ $(this).remove(); });
            }
        });
    });
});
</script>
