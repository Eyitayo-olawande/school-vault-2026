<?php
$widget     = (is_superadmin_loggedin() ? 2 : 3);
$selSession = isset($session_id) ? $session_id : '';
$selTerm    = isset($term) ? $term : '';
?>
<div class="row">
    <div class="col-md-12">
        <section class="panel">
            <header class="panel-heading">
                <h4 class="panel-title"><?=translate('select_ground')?></h4>
            </header>
            <?php echo form_open($this->uri->uri_string(), ['class' => 'validate']); ?>
            <div class="panel-body">
                <div class="row mb-sm">

                <?php if (is_superadmin_loggedin()): ?>
                    <div class="col-md-2 mb-sm">
                        <div class="form-group">
                            <label class="control-label"><?=translate('branch')?> <span class="required">*</span></label>
                            <?php
                                $arrayBranch = $this->app_lib->getSelectList('branch');
                                echo form_dropdown('branch_id', $arrayBranch, set_value('branch_id'),
                                    "class='form-control' onchange='getClassByBranch(this.value)'
                                     data-plugin-selectTwo data-width='100%' data-minimum-results-for-search='Infinity'");
                            ?>
                        </div>
                    </div>
                <?php endif; ?>

                    <div class="col-md-<?=$widget?> mb-sm">
                        <div class="form-group">
                            <label class="control-label">Academic Session <span class="required">*</span></label>
                            <?php
                                echo form_dropdown('session_id', $session_list, $selSession,
                                    "class='form-control' required
                                     data-plugin-selectTwo data-width='100%' data-minimum-results-for-search='Infinity'");
                            ?>
                        </div>
                    </div>

                    <div class="col-md-<?=$widget?> mb-sm">
                        <div class="form-group">
                            <label class="control-label">Term <small class="text-muted">(optional)</small></label>
                            <select name="term" class="form-control"
                                data-plugin-selectTwo data-width="100%" data-minimum-results-for-search="Infinity">
                                <option value="" <?= empty($selTerm) ? 'selected' : '' ?>>All Terms (no allocation at all)</option>
                                <option value="1ST TERM" <?= ($selTerm === '1ST TERM') ? 'selected' : '' ?>>1st Term only</option>
                                <option value="2ND TERM" <?= ($selTerm === '2ND TERM') ? 'selected' : '' ?>>2nd Term only</option>
                                <option value="3RD TERM" <?= ($selTerm === '3RD TERM') ? 'selected' : '' ?>>3rd Term only</option>
                            </select>
                        </div>
                    </div>

                    <div class="col-md-<?=$widget?> mb-sm">
                        <div class="form-group">
                            <label class="control-label"><?=translate('class')?> <span class="required">*</span></label>
                            <?php
                                $arrayClass = $this->app_lib->getClass($branch_id);
                                echo form_dropdown('class_id', $arrayClass, set_value('class_id'),
                                    "class='form-control' id='class_id' onchange='getSectionByClass(this.value,1)' required
                                     data-plugin-selectTwo data-width='100%' data-minimum-results-for-search='Infinity'");
                            ?>
                        </div>
                    </div>

                    <div class="col-md-<?=$widget?> mb-sm">
                        <div class="form-group">
                            <label class="control-label"><?=translate('section')?></label>
                            <?php
                                $arraySection = $this->app_lib->getSections(set_value('class_id'), true);
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
                        <button type="submit" name="search" value="1" class="btn btn-default btn-block">
                            <i class="fas fa-filter"></i> <?=translate('filter')?>
                        </button>
                    </div>
                </div>
            </footer>
            <?php echo form_close(); ?>
        </section>

        <?php if (isset($invoicelist)): ?>
        <section class="panel appear-animation" data-appear-animation="<?=$global_config['animations']?>" data-appear-animation-delay="100">
            <?php echo form_open('fees/invoicePrint', ['class' => 'printIn']); ?>
            <header class="panel-heading">
                <h4 class="panel-title">
                    <i class="fas fa-list-ol"></i> <?=translate('student_list')?>
                    <?php if (!empty($selTerm)): ?>
                        <span class="text-muted" style="font-size:0.85em"> &mdash; No <?=htmlspecialchars($selTerm)?> allocation</span>
                    <?php else: ?>
                        <span class="text-muted" style="font-size:0.85em"> &mdash; No fees allocated</span>
                    <?php endif; ?>
                    <span class="badge badge-danger ml-sm"><?=count($invoicelist)?></span>
                    <div class="panel-btn">
                        <button type="submit" class="btn btn-default btn-circle"
                            data-loading-text="<i class='fas fa-spinner fa-spin'></i> Processing">
                            <i class="fas fa-print"></i> <?=translate('generate')?>
                        </button>
                    </div>
                </h4>
            </header>
            <div class="panel-body">
                <div class="mb-md mt-md">
                    <div class="export_title"><?=translate('invoice') . ' ' . translate('list')?></div>

                    <?php if (empty($invoicelist)): ?>
                    <div class="alert alert-success">
                        <i class="fas fa-check-circle"></i>
                        All students in this class<?= !empty($selTerm) ? ' have a ' . htmlspecialchars($selTerm) . ' fee allocation' : ' have fee allocations'?>.
                    </div>
                    <?php else: ?>
                    <table class="table table-bordered table-condensed table-hover mb-none tbr-top table-export">
                        <thead>
                            <tr>
                                <th class="hidden-print">
                                    <div class="checkbox-replace">
                                        <label class="i-checks" data-toggle="tooltip" data-original-title="Print Show / Hidden">
                                            <input type="checkbox" name="select-all" id="selectAllchkbox"> <i></i>
                                        </label>
                                    </div>
                                </th>
                                <th><?=translate('student')?></th>
                                <th><?=translate('class')?></th>
                                <th><?=translate('section')?></th>
                                <th><?=translate('register_no')?></th>
                                <th><?=translate('roll')?></th>
                                <th><?=translate('mobile_no')?></th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php foreach ($invoicelist as $row): ?>
                            <tr>
                                <td class="hidden-print checked-area">
                                    <div class="checkbox-replace">
                                        <label class="i-checks"><input type="checkbox" name="student_id[]" value="<?=$row['student_id']?>"><i></i></label>
                                    </div>
                                </td>
                                <td><?=htmlspecialchars($row['first_name'] . ' ' . $row['last_name'])?></td>
                                <td><?=htmlspecialchars($row['class_name'])?></td>
                                <td><?=htmlspecialchars($row['section_name'])?></td>
                                <td><?=htmlspecialchars($row['register_no'])?></td>
                                <td><?=htmlspecialchars($row['roll'])?></td>
                                <td><?=htmlspecialchars($row['mobileno'])?></td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                    <?php endif; ?>
                </div>
            </div>
            <?php echo form_close(); ?>
        </section>
        <?php endif; ?>
    </div>
</div>

<script>
$(document).ready(function () {
    $('form.printIn').on('submit', function (e) {
        e.preventDefault();
        var btn = $(this).find('[type="submit"]');
        $.ajax({
            url: $(this).attr('action'),
            type: 'POST',
            data: $(this).serialize(),
            dataType: 'html',
            cache: false,
            beforeSend: function () { btn.button('loading'); },
            success: function (data) { fn_printElem(data, true); },
            error: function () { btn.button('reset'); alert('An error occurred, please try again'); },
            complete: function () { btn.button('reset'); }
        });
    });
});
</script>
