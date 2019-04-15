<script>
    var page = '<?php echo $this->get('page'); ?>';
    var siteurl = '<?php echo $this->get('siteurl'); ?>';
    var adminurl = '<?php echo $this->get('adminurl'); ?>';
    var nonce = '<?php echo $this->get('nonce'); ?>';
    var uploadmarker = '<?php echo $this->get('upload-marker'); ?>';
    var section = '<?php echo $this->get('section'); ?>';
    var form_id = '#settings-form';
    var update_button_id = '#update_settings';
    var reset_button_id = '#reset_settings';
    var reload_button_id = '#reload_settings';
</script>
<div class="wrap" style="position:relative;">
    <?php $this->page_title('Settings') ?>
    <?php if (strpos(admin_url('options-general.php'), $_SERVER['PHP_SELF']) === FALSE) {
        settings_errors();
    } ?>
    <span id="settings-panel" style="display:none">
        <div class="settings-header"><?php $this->get('settings')->header(); ?></div>
		<form enctype="multipart/form-data" method="post" action="options.php" id="settings-form">
			<input type="hidden" name="page" value="<?php echo $this->get('page'); ?>"/>
            <?php settings_fields($this->get('group')); ?>
            <?php do_settings_sections($this->get('page')); ?>
            <hr/>
            <?php
            if ($this->get('hasrequired')) {
                echo '<span style="font-weight: bold">*</span> = Setting is required.';
            }
            ?>
            <div class="sticky-footer">
            <?php
            submit_button('Update', 'primary', 'update_settings', FALSE, array('disabled' => ''));
            echo '&nbsp;';
            submit_button('Reset', 'primary', 'reset_settings', FALSE, array('disabled' => ''));
            echo '&nbsp;';
            submit_button('Reload', 'primary', 'reload_settings', FALSE, array('disabled' => ''));
            echo '&nbsp;';
            if (current_user_can('administrator')) {
                if ($this->get('canbackup') === TRUE) {
                    submit_button('Backup', 'primary', 'export_settings', FALSE);
                    echo '&nbsp;';
                }
                submit_button('Import', 'primary', 'import_settings', FALSE);
                if ($this->get('backupdate') !== FALSE) {
                    echo '&nbsp;';
                    submit_button('Restore as at '.$this->get('backupdate'), 'primary', 'restore_settings', FALSE);
                }
            } ?>
            </div>
	    </form>
        <div class="settings-footer"><?php $this->get('settings')->footer(); ?></div>
    </span>
</div>
