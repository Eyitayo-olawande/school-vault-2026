<?php
$widget = (is_superadmin_loggedin() ? 2 : 3);
$ratingLabels = [0 => '— Select —', 1 => 'Poor', 2 => 'Fair', 3 => 'Good', 4 => 'Excellent'];
?>
<div class="row">
    <div class="col-md-12">
        <section class="panel">
            <?php echo form_open($this->uri->uri_string(), ['class' => 'validate']); ?>
            <header class="panel-heading">
                <h4 class="panel-title">Psychomotor Domain — Select Class</h4>
            </header>
            <div class="panel-body">
                <div class="row mb-sm">
                    <?php if (is_superadmin_loggedin()): ?>
                    <div class="col-md-2 mb-sm">
                        <div class="form-group">
                            <label class="control-label"><?=translate('branch')?> <span class="required">*</span></label>
                            <?php echo form_dropdown('branch_id', $this->app_lib->getSelectList('branch'), set_value('branch_id'), "class='form-control' id='branch_id' data-plugin-selectTwo data-width='100%' data-minimum-results-for-search='Infinity'"); ?>
                        </div>
                    </div>
                    <?php endif; ?>
                    <div class="col-md-<?=$widget?> mb-sm">
                        <div class="form-group">
                            <label class="control-label"><?=translate('exam')?> <span class="required">*</span></label>
                            <?php
                            if (!empty($branch_id)) {
                                $arrayExam = ['' => translate('select')];
                                foreach ($this->db->get_where('exam', ['branch_id' => $branch_id, 'session_id' => get_session_id()])->result() as $ex) {
                                    $arrayExam[$ex->id] = $this->application_model->exam_name_by_id($ex->id);
                                }
                            } else {
                                $arrayExam = ['' => translate('select_branch_first')];
                            }
                            echo form_dropdown('exam_id', $arrayExam, set_value('exam_id'), "class='form-control' id='exam_id' required data-plugin-selectTwo data-width='100%' data-minimum-results-for-search='Infinity'");
                            ?>
                        </div>
                    </div>
                    <div class="col-md-3 mb-sm">
                        <div class="form-group">
                            <label class="control-label"><?=translate('class')?> <span class="required">*</span></label>
                            <?php echo form_dropdown('class_id', $this->app_lib->getClass($branch_id), set_value('class_id'), "class='form-control' id='class_id' onchange='getSectionByClass(this.value,0)' required data-plugin-selectTwo data-width='100%' data-minimum-results-for-search='Infinity'"); ?>
                        </div>
                    </div>
                    <div class="col-md-<?=$widget?>">
                        <div class="form-group">
                            <label class="control-label"><?=translate('section')?> <span class="required">*</span></label>
                            <?php echo form_dropdown('section_id', $this->app_lib->getSections(set_value('class_id'), false), set_value('section_id'), "class='form-control' id='section_id' required data-plugin-selectTwo data-width='100%' data-minimum-results-for-search='Infinity'"); ?>
                        </div>
                    </div>
                </div>
            </div>
            <footer class="panel-footer">
                <div class="row">
                    <div class="col-md-offset-10 col-md-2">
                        <button type="submit" name="search" value="1" class="btn btn-default btn-block"><i class="fas fa-filter"></i> <?=translate('filter')?></button>
                    </div>
                </div>
            </footer>
            <?php echo form_close(); ?>
        </section>

        <?php if (!empty($students) && !empty($domain_types)): ?>
        <section class="panel appear-animation" data-appear-animation="<?=$global_config['animations']?>" data-appear-animation-delay="100">
            <?php echo form_open('exam/psychomotor_save', ['class' => 'frm-submit-msg']); ?>
            <?php
            echo form_hidden(['exam_id' => $exam_id, 'class_id' => $class_id, 'section_id' => $section_id, 'branch_id' => $branch_id]);
            ?>
            <header class="panel-heading">
                <h4 class="panel-title"><i class="fas fa-users"></i> Psychomotor Domain Ratings</h4>
            </header>
            <div class="panel-body">
                <div class="table-responsive mt-md mb-lg">
                    <table class="table table-bordered table-condensed">
                        <thead>
                            <tr>
                                <th>#</th>
                                <th>Student</th>
                                <th>Reg No</th>
                                <?php foreach ($domain_types as $dt): ?>
                                <th style="min-width:130px"><?=html_escape($dt['name'])?></th>
                                <?php endforeach; ?>
                            </tr>
                        </thead>
                        <tbody>
                            <?php $i = 1; foreach ($students as $st): ?>
                            <tr>
                                <td><?=$i++?></td>
                                <td><?=html_escape($st->fullname)?></td>
                                <td><?=html_escape($st->register_no)?></td>
                                <?php foreach ($domain_types as $dt): ?>
                                <td>
                                    <?php
                                    $current = isset($ratings[$st->id][$dt['id']]) ? $ratings[$st->id][$dt['id']] : 0;
                                    echo form_dropdown("rating[{$st->id}][{$dt['id']}]", $ratingLabels, $current, "class='form-control input-sm'");
                                    ?>
                                </td>
                                <?php endforeach; ?>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
            <div class="panel-footer">
                <div class="row">
                    <div class="col-md-offset-10 col-md-2">
                        <button type="submit" class="btn btn-default btn-block" data-loading-text="<i class='fas fa-spinner fa-spin'></i> Saving">
                            <i class="fas fa-save"></i> <?=translate('save')?>
                        </button>
                    </div>
                </div>
            </div>
            <?php echo form_close(); ?>
        </section>
        <?php elseif (isset($_POST['search'])): ?>
        <div class="alert alert-info"><i class="fas fa-info-circle"></i> No students found or no psychomotor domain types configured for this branch.</div>
        <?php endif; ?>
    </div>
</div>
<script>
$(document).ready(function() {
    <?php if (is_superadmin_loggedin()): ?>
    $('#branch_id').on('change', function() {
        var b = $(this).val();
        getClassByBranch(b);
        getExamByBranch(b);
        $('#section_id').html('<option value=""><?=translate("select")?></option>');
    });
    <?php endif; ?>
    $('#section_id').on('change', function() {
        $.post(base_url + 'subject/getByClassSection', {classID: $('#class_id').val(), sectionID: $(this).val()}, function(d){ $('#subject_id').html(d); });
    });
});
</script>
