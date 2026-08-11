<?php defined('BASEPATH') or exit('No direct script access allowed'); ?>
<?php $widget = (is_superadmin_loggedin() ? 3 : 4); ?>
<div class="row">
    <div class="col-md-12">
        <section class="panel">
            <header class="panel-heading">
                <h4 class="panel-title"><?= translate('select_ground') ?></h4>
            </header>
            <?php echo form_open($this->uri->uri_string(), ['class' => 'validate']); ?>
            <div class="panel-body">
                <div class="row mb-sm">
                    <?php if (is_superadmin_loggedin()): ?>
                    <div class="col-md-3">
                        <div class="form-group">
                            <label class="control-label"><?= translate('branch') ?> <span class="required">*</span></label>
                            <?php
                                $arrayBranch = $this->app_lib->getSelectList('branch');
                                echo form_dropdown('branch_id', $arrayBranch, set_value('branch_id'),
                                    "class='form-control' onchange='getClassByBranch(this.value)'
                                     data-plugin-selectTwo data-width='100%' data-minimum-results-for-search='Infinity'");
                            ?>
                        </div>
                    </div>
                    <?php endif; ?>

                    <div class="col-md-<?= $widget ?> mb-sm">
                        <div class="form-group">
                            <label class="control-label"><?= translate('class') ?></label>
                            <?php
                                $arrayClass = array_merge(['all' => 'All Classes'], $this->app_lib->getClass($branch_id));
                                echo form_dropdown('class_id', $arrayClass, set_value('class_id'),
                                    "class='form-control' id='class_id' onchange='getSectionByClass(this.value,1)'
                                     data-plugin-selectTwo data-width='100%' data-minimum-results-for-search='Infinity'");
                            ?>
                        </div>
                    </div>

                    <div class="col-md-<?= $widget ?> mb-sm">
                        <div class="form-group">
                            <label class="control-label"><?= translate('section') ?></label>
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
                            <i class="fas fa-filter"></i> <?= translate('filter') ?>
                        </button>
                    </div>
                </div>
            </footer>
            <?php echo form_close(); ?>
        </section>

        <?php if (isset($students)): ?>
        <section class="panel appear-animation" data-appear-animation="<?= $global_config['animations'] ?>" data-appear-animation-delay="100">
            <header class="panel-heading">
                <h4 class="panel-title">
                    <i class="fas fa-user-plus"></i> New Intake Report
                    <span class="badge badge-default ml-sm"><?= count($students) ?></span>
                </h4>
            </header>
            <div class="panel-body mb-md">
                <?php if (empty($students)): ?>
                    <div class="alert alert-info"><i class="fas fa-info-circle"></i> No students found for the selected filters.</div>
                <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-bordered table-condensed table-hover table-export">
                        <thead>
                            <tr>
                                <th>#</th>
                                <th><?= translate('name') ?></th>
                                <th><?= translate('register_no') ?></th>
                                <th><?= translate('class') ?></th>
                                <th><?= translate('section') ?></th>
                                <th><?= translate('student') . ' ' . translate('username') ?></th>
                                <th><?= translate('parent') . ' ' . translate('username') ?></th>
                                <th>DVA Account No</th>
                                <th>DVA Bank</th>
                                <th>Admission Date</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($students as $i => $row): ?>
                            <tr>
                                <td><?= $i + 1 ?></td>
                                <td><?= html_escape($row['fullname']) ?></td>
                                <td><?= html_escape($row['register_no']) ?></td>
                                <td><?= html_escape($row['class_name']) ?></td>
                                <td><?= html_escape($row['section_name']) ?></td>
                                <td><?= html_escape($row['student_login'] ?? '—') ?></td>
                                <td><?= html_escape($row['parent_login'] ?? '—') ?></td>
                                <td><?= !empty($row['dva_account']) ? html_escape($row['dva_account']) : '—' ?></td>
                                <td><?= !empty($row['dva_bank'])    ? html_escape($row['dva_bank'])    : '—' ?></td>
                                <td><?= !empty($row['admission_date']) ? _d($row['admission_date']) : '—' ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <?php endif; ?>
            </div>
        </section>
        <?php endif; ?>
    </div>
</div>
