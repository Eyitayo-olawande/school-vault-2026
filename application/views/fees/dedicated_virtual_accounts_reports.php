<?php  $widget = (is_superadmin_loggedin() ? 4 : 6); ?>
<div class="row">
    <div class="col-md-12">
        <section class="panel">
            <header class="panel-heading">
                <h4 class="panel-title"><?=translate('select_ground')?></h4>
            </header>
            <?php echo form_open($this->uri->uri_string(), array('class' => 'validate'));?>
            <div class="panel-body">
                <div class="row mb-sm">
                    <?php if (is_superadmin_loggedin() ): ?>
                    <div class="col-md-4">
                        <div class="form-group">
                            <label class="control-label"><?=translate('branch')?> <span
                                    class="required">*</span></label>
                            <?php
								$arrayBranch = $this->app_lib->getSelectList('branch');
								echo form_dropdown("branch_id", $arrayBranch, set_value('branch_id'), "class='form-control' onchange='getClassByBranch(this.value)'
								data-plugin-selectTwo data-width='100%' data-minimum-results-for-search='Infinity'");
							?>
                        </div>
                    </div>
                    <?php endif; ?>
                    <div class="col-md-<?php echo $widget; ?> mb-sm">
                        <div class="form-group">
                            <label class="control-label"><?=translate('class')?> <span class="required">*</span></label>
                            <?php
								$arrayClass = $this->app_lib->getClass($branch_id);
								echo form_dropdown("class_id", $arrayClass, set_value('class_id'), "class='form-control' id='class_id' onchange='getSectionByClass(this.value,1)'
								required data-plugin-selectTwo data-width='100%' data-minimum-results-for-search='Infinity' ");
							?>
                        </div>
                    </div>
                    <div class="col-md-<?php echo $widget; ?> mb-sm">
                        <div class="form-group">
                            <label class="control-label"><?=translate('section')?> <span
                                    class="required">*</span></label>
                            <?php
								$arraySection = $this->app_lib->getSections(set_value('class_id'), true);
								echo form_dropdown("section_id", $arraySection, set_value('section_id'), "class='form-control' id='section_id' required
								data-plugin-selectTwo data-width='100%' data-minimum-results-for-search='Infinity' ");
							?>
                        </div>
                    </div>
                </div>
            </div>
            <footer class="panel-footer">
                <div class="row">
                    <div class="col-md-offset-10 col-md-2">
                        <button type="submit" name="search" value="1" class="btn btn-default btn-block"> <i
                                class="fas fa-filter"></i> <?=translate('filter')?></button>
                    </div>
                </div>
            </footer>
            <?php echo form_close();?>
        </section>

        <?php if (isset($students)):?>
        <section class="panel appear-animation" data-appear-animation="<?=$global_config['animations'] ?>"
            data-appear-animation-delay="100">
            <header class="panel-heading">
                <h4 class="panel-title"><i class="fas fa-user-graduate"></i>
                    <?php echo translate('dedicated_virtual_accounts') . " " . translate('reports');?></h4>
            </header>
            <div class="panel-body mb-md">
                <table class="table table-bordered table-condensed table-hover table-export">
                    <thead>
                        <tr>
                            <th class="no-sort"><?=translate('photo')?></th>
                            <th><?=translate('name')?></th>
                            <th><?=translate('class')?></th>
                            <th><?=translate('section')?></th>
                            <th><?=translate('register_no')?></th>
                            <th width="80"><?=translate('roll')?></th>
                            <th><?=translate('guardian_name')?></th>
                            <th><?=translate('dva_account_number')?></th>
                            <th><?=translate('dva_customer_id')?></th>
                            <th><?=translate('dva_account_bank')?></th>
                            <th><?=translate('total_received')?></th>
                            <th><?=translate('last_payment_date')?></th>
                            <th><?=translate('parent')?></th>
                            <th><?=translate('student')?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
						foreach($students as $row):
						?>
                        <tr>
                            <td class="center"><img src="<?php echo get_image_url('student', $row['photo']); ?>"
                                    height="50"></td>
                            <td class="<?=($row['active'] == 0 ? 'text-danger' : '')?>"><?php echo $row['fullname'];?>
                            </td>
                            <td><?php echo $row['class_name'];?></td>
                            <td><?php echo $row['section_name'];?></td>
                            <td><?php echo $row['register_no'];?></td>
                            <td><?php echo $row['roll'];?></td>
                            <td><?php echo (!empty($row['parent_id']) ? get_type_name_by_id('parent', $row['parent_id']) : 'N/A');?>
                            </td>
                            <td><?php echo $row['account_number'];?></td>
                            <td><?php echo $row['customer_id'];?></td>
                            <td><?php echo $row['dedicated_account_bank'];?></td>
                            <td><?php echo currencyFormat($row['total_received']);?></td>
                            <td><?php echo !empty($row['last_payment_date']) ? _d($row['last_payment_date']) : '—';?></td>
                            <td><?php echo $row['parent_login'];?></td>
                            <td><?php echo $row['student_login'];?></td>
                        </tr>
                        <?php endforeach;?>
                    </tbody>
                </table>
            </div>
        </section>
        <?php endif;?>
    </div>
</div>
