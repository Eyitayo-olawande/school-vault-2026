<div class="row">
    <div class="col-md-12">
        <section class="panel">
            <?php echo form_open_multipart($this->uri->uri_string(), array( 'class' => 'form-horizontal form-bordered validate'));?>
            <header class="panel-heading">
                <h4 class="panel-title">
                    <i class="fas fa-file-archive"></i> <?=translate('dva_multiple_import')?>
                </h4>
            </header>
            <div class="panel-body">
                <?php if ($this->session->flashdata('csvimport')): ?>
                <div class="alert-danger p-sm"><?php echo $this->session->flashdata('csvimport'); ?></div>
                <?php endif; ?>
                <div class="form-group mt-md">
                    <div class="col-md-12 mb-md">
                        <a class="btn btn-default pull-right" href="<?=base_url('student/csv_SampleDVAdownloader')?>">
                            <i class='fas fa-file-download'></i> Download Sample Import File
                        </a>
                    </div>
                    <div class="col-md-12">
                        <div class="alert alert-subl">
                            <strong>Instructions :</strong><br />
                            Pending...<br />
                        </div>
                    </div>
                </div>
                <?php if (is_superadmin_loggedin()): ?>
                <div class="form-group">
                    <label class="control-label col-md-3"><?php echo translate('branch');?> <span
                            class="required">*</span></label>
                    <div class="col-md-6">
                        <?php
							$arrayBranch = $this->app_lib->getSelectList('branch');
							echo form_dropdown("branch_id", $arrayBranch, set_value('branch_id'), "class='form-control' onchange='getClassByBranch(this.value)'
							data-plugin-selectTwo data-width='100%' data-minimum-results-for-search='Infinity'");
						?>
                        <span class="error"><?=form_error('branch_id')?></span>
                    </div>
                </div>
                <?php endif; ?>
                <div class="form-group">
                    <label class="control-label col-md-3"><?=translate('class')?> <span
                            class="required">*</span></label>
                    <div class="col-md-6">
                        <?php
							$arrayClass = $this->app_lib->getClass($branch_id);
							echo form_dropdown("class_id", $arrayClass, set_value('class_id'), "class='form-control' id='class_id' onchange='getSectionByClass(this.value,0)'
							data-plugin-selectTwo data-width='100%' data-minimum-results-for-search='Infinity' ");
						?>
                        <span class="error"><?=form_error('class_id')?></span>
                    </div>
                </div>
                <div class="form-group">
                    <label class="control-label col-md-3"><?=translate('section')?> <span
                            class="required">*</span></label>
                    <div class="col-md-6">
                        <?php
							$arraySection = $this->app_lib->getSections(set_value('class_id'));
							echo form_dropdown("section_id", $arraySection, set_value('section_id'), "class='form-control' id='section_id' 
							data-plugin-selectTwo data-width='100%' data-minimum-results-for-search='Infinity' ");
						?>
                        <span class="error"><?=form_error('section_id')?></span>
                    </div>
                </div>
                <div class="form-group">
                    <label class="control-label col-md-3">Select CSV File <span class="required">*</span></label>
                    <div class="col-md-6 mb-lg">
                        <input type="file" name="dvafile" class="dropify" data-height="140"
                            data-allowed-file-extensions="csv" />
                        <?php echo form_error('dvafile', '<label class="error">', '</label>'); ?>
                    </div>
                </div>
            </div>
            <footer class="panel-footer">
                <div class="row">
                    <div class="col-md-offset-3 col-md-2">
                        <button type="submit" name="save" value="1" class="btn btn btn-default btn-block">
                            <i class="fas fa-plus-circle"></i> <?=translate('import')?>
                        </button>
                    </div>
                </div>
            </footer>
            <?php echo form_close();?>
        </section>
    </div>
</div>