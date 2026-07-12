<?php $widget = (is_superadmin_loggedin() ? 3 : 4); ?>
<section class="panel">
    <header class="panel-heading">
        <h4 class="panel-title">Period-wise Attendance Filter</h4>
    </header>
    <?php echo form_open($this->uri->uri_string()); ?>
    <div class="panel-body">
        <div class="row mb-sm">
        <?php if (is_superadmin_loggedin()): ?>
            <div class="col-md-3 mb-sm">
                <div class="form-group">
                    <label class="control-label"><?=translate('branch')?> <span class="required">*</span></label>
                    <?php
                    echo form_dropdown('branch_id', $this->app_lib->getSelectList('branch'), set_value('branch_id'),
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
                    echo form_dropdown('class_id', $this->app_lib->getClass($branch_id), set_value('class_id'),
                        "class='form-control' id='class_id' onchange='getSectionByClass(this.value,0)'
                        data-plugin-selectTwo data-width='100%' data-minimum-results-for-search='Infinity'");
                    ?>
                </div>
            </div>
            <div class="col-md-<?=$widget?> mb-sm">
                <div class="form-group">
                    <label class="control-label"><?=translate('section')?> <span class="required">*</span></label>
                    <?php
                    echo form_dropdown('section_id', $this->app_lib->getSections(set_value('class_id'), false), set_value('section_id'),
                        "class='form-control' id='section_id'
                        data-plugin-selectTwo data-width='100%' data-minimum-results-for-search='Infinity'");
                    ?>
                </div>
            </div>
            <div class="col-md-<?=$widget?> mb-sm">
                <div class="form-group">
                    <label class="control-label"><?=translate('month_year')?> <span class="required">*</span></label>
                    <div class="input-group">
                        <input type="text" class="form-control" name="timestamp"
                               id="monthpicker" value="<?=set_value('timestamp', date('Y-m'))?>"
                               autocomplete="off" placeholder="YYYY-MM">
                        <span class="input-group-addon"><i class="icon-event icons"></i></span>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <footer class="panel-footer">
        <div class="row">
            <div class="col-md-offset-10 col-md-2">
                <button type="submit" class="btn btn-default btn-block">
                    <i class="fas fa-filter"></i> <?=translate('filter')?>
                </button>
            </div>
        </div>
    </footer>
    <?php echo form_close(); ?>
</section>

<?php if (isset($report)): ?>

<?php
$students = $report['students'];
$subjects = $report['subjects'];
$map      = $report['map'];
$days     = $report['days'];
$year     = $year ?? date('Y');
$month    = $month ?? date('m');
?>

<?php if (empty($students) || empty($subjects)): ?>
<div class="alert alert-info">
    <i class="fas fa-info-circle"></i>
    No period-wise attendance records found for the selected class/section/month.
    Attendance must be entered with a specific period selected to appear here.
</div>

<?php else: ?>

<?php foreach ($subjects as $subject): ?>
<section class="panel appear-animation" data-appear-animation="fadeIn" data-appear-animation-delay="100">
    <header class="panel-heading">
        <h4 class="panel-title"><i class="fas fa-clock"></i> <?=html_escape($subject)?></h4>
    </header>
    <div class="panel-body">
        <div class="table-responsive">
            <table class="table table-bordered table-condensed mb-none" style="font-size:12px;">
                <thead>
                    <tr>
                        <th style="min-width:140px;">Student</th>
                        <?php for ($d = 1; $d <= $days; $d++):
                            $dayDate = sprintf('%04d-%02d-%02d', $year, $month, $d);
                            $dow = date('D', strtotime($dayDate));
                        ?>
                        <th class="text-center" style="min-width:28px;" title="<?=$dayDate?>">
                            <?=$d?><br><small style="font-weight:normal;"><?=$dow?></small>
                        </th>
                        <?php endfor; ?>
                        <th class="text-center" style="min-width:50px;">%</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($students as $enrollID => $stu): ?>
                    <?php
                    $subjectMap  = $map[$enrollID][$subject] ?? [];
                    $cntPresent  = 0;
                    $cntTotal    = 0;
                    ?>
                    <tr>
                        <td><?=html_escape($stu['name'])?><br><small class="text-muted">Roll: <?=html_escape($stu['roll'])?></small></td>
                        <?php for ($d = 1; $d <= $days; $d++):
                            $dayDate = sprintf('%04d-%02d-%02d', $year, $month, $d);
                            $status  = $subjectMap[$dayDate] ?? null;
                            $dow     = (int)date('w', strtotime($dayDate));
                            $isWeekend = ($dow === 0 || $dow === 6);

                            if ($status !== null) { $cntTotal++; }
                            if ($status === 'P' || $status === 'L') { $cntPresent++; }
                            if ($status === 'HD') { $cntPresent += 0.5; $cntTotal += 1; }

                            $bg    = '';
                            $label = '&nbsp;';
                            if ($isWeekend && $status === null) {
                                $bg = 'background:#f0f0f0;';
                            } elseif ($status === 'P') {
                                $bg = 'background:#dff0d8;'; $label = 'P';
                            } elseif ($status === 'A') {
                                $bg = 'background:#f2dede;'; $label = 'A';
                            } elseif ($status === 'L') {
                                $bg = 'background:#fcf8e3;'; $label = 'L';
                            } elseif ($status === 'HD') {
                                $bg = 'background:#d9edf7;'; $label = 'HD';
                            } elseif ($status === 'EA') {
                                $bg = 'background:#f0e6ff;'; $label = 'EA';
                            }
                        ?>
                        <td class="text-center" style="<?=$bg?>padding:3px 2px;"><?=$label?></td>
                        <?php endfor; ?>
                        <td class="text-center">
                            <?php if ($cntTotal > 0): ?>
                            <strong><?=round(($cntPresent / $cntTotal) * 100, 1)?>%</strong>
                            <?php else: ?><span class="text-muted">—</span><?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <small class="text-muted mt-xs">
            <span class="label label-success">P</span> Present &nbsp;
            <span class="label label-danger">A</span> Absent &nbsp;
            <span class="label label-warning">L</span> Late &nbsp;
            <span class="label label-info">HD</span> Half Day &nbsp;
            <span class="label" style="background:#b59fd1;">EA</span> Excused
        </small>
    </div>
</section>
<?php endforeach; ?>

<?php endif; ?>
<?php endif; ?>

<script>
$(document).ready(function () {
    $('#monthpicker').datepicker({
        format: 'yyyy-mm',
        viewMode: 'months',
        minViewMode: 'months',
        autoclose: true,
        orientation: 'bottom',
    });
});
</script>
