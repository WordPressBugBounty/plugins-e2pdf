<?php
if (!defined('ABSPATH')) {
    die('Access denied.');
}
?>
<div class="wrap">
    <h1><?php _e('Settings', 'e2pdf'); ?></h1>
    <hr class="wp-header-end">
    <?php $this->render('blocks', 'notifications'); ?>
    <h3 class="nav-tab-wrapper wp-clearfix">
        <?php foreach ($this->view->groups as $group_key => $group) { ?>
            <?php if (isset($group['action']) && isset($group['group'])) { ?>
                <a href="<?php echo $this->helper->get_url(array('page' => 'e2pdf-settings', 'action' => $group['action'], 'group' => $group['group'])); ?>" class="nav-tab <?php if ($this->get->get('action') === $group['action'] && $this->get->get('group') === $group['group']) { ?>nav-tab-active<?php } ?>"><?php echo $group['name']; ?></a>
            <?php } elseif (isset($group['action'])) { ?>
                <a href="<?php echo $this->helper->get_url(array('page' => 'e2pdf-settings', 'action' => $group['action'])); ?>" class="nav-tab <?php if ($this->get->get('action') === $group['action']) { ?>nav-tab-active<?php } ?>"><?php echo $group['name']; ?></a>
            <?php } else { ?>
                <a href="<?php echo $this->helper->get_url(array('page' => 'e2pdf-settings')); ?>" class="nav-tab <?php if (!$this->get->get('action')) { ?>nav-tab-active<?php } ?>"><?php echo $group['name']; ?></a>
            <?php } ?>
        <?php } ?>
    </h3>
    <div class="wrap">
        <?php if ($this->get->get('action') == 'fonts') { ?>
            <div class="e2pdf-view-area">
                <form enctype="multipart/form-data" method="post">
                    <input type="hidden" name="_wpnonce" value="<?php echo wp_create_nonce('e2pdf_settings'); ?>">
                    <ul class="e2pdf-fonts-upload">
                        <li><h4><?php _e('Upload Font', 'e2pdf'); ?>:</h4></li>
                        <li>
                            <div class="e2pdf-name"><?php _e('Font', 'e2pdf'); ?>:
                            </div><div class="e2pdf-value">
                                <input name="font" type="file">
                                <div class="e2pdf-note"><?php _e('Allowed filetypes', 'e2pdf'); ?>: <strong><?php echo implode(', ', $this->view->allowed_extensions); ?></strong></div>
                                <div class="e2pdf-note"><?php _e('Max upload filesize', 'e2pdf'); ?>: <strong><?php echo $this->view->upload_max_filesize; ?></strong></div>
                            </div>
                        </li>
                        <li>
                            <div class="e2pdf-name"></div><div class="e2pdf-value">
                                <input class="button-primary button-large" type="submit" value="<?php _e('Upload', 'e2pdf'); ?>"> 
                            </div>
                        </li>
                    </ul>
                </form>
                <div class="e2pdf-rel">
                    <form>
                        <div class="e2pdf-form-loader"><span class="spinner"></span></div>
                        <ul class="e2pdf-fonts-list">
                            <li></li>
                            <?php foreach ($this->view->fonts as $key => $value) { ?>
                                <li>
                                    <div class="e2pdf-name">
                                        <strong><?php echo $value; ?></strong> (<?php echo $key; ?>)
                                    </div><div class="e2pdf-value">
                                        <?php echo in_array(md5_file($this->helper->get('fonts_dir') . $key), $this->view->cached_fonts) ? '<i class="dashicons dashicons-cloud-saved"></i>' : ''; ?>
                                        <a class="e2pdf-link" href="<?php echo $this->helper->get_upload_url('fonts/' . $key); ?>" target="_blank"><i class="dashicons dashicons-download"></i></a>
                                        <?php if ($key === 'NotoSans-Regular.ttf') { ?>
                                            <a class="e2pdf-link" disabled="disabled" data-font="<?php echo $key; ?>" href="javascript:void(0);"><i class="dashicons dashicons-no"></i></a>
                                        <?php } else { ?>
                                            <a class="e2pdf-link e2pdf-delete-font" data-font="<?php echo $key; ?>" href="javascript:void(0);" _wpnonce="<?php echo wp_create_nonce('e2pdf_settings'); ?>"><i class="dashicons dashicons-no"></i></a>
                                        <?php } ?>
                                    </div>

                                </li>
                            <?php } ?>
                        </ul>
                    </form>
                </div>
            </div>
        <?php } elseif ($this->get->get('action') == 'permissions') { ?>
            <div class="e2pdf-view-area">
                <form enctype="multipart/form-data" method="post">
                    <input type="hidden" name="_wpnonce" value="<?php echo wp_create_nonce('e2pdf_settings'); ?>">
                    <ul class="e2pdf-permissions-list">
                        <?php foreach ($this->view->roles as $role_key => $role) { ?>
                            <li>
                                <div class="e2pdf-name"><?php echo $role['name']; ?>:
                                </div><div class="e2pdf-value">
                                    <?php
                                    foreach ($this->view->caps as $cap_key => $cap) {
                                        $this->render('field', 'checkbox', array(
                                            'field' => array(
                                                'name' => 'permissions[' . $role_key . '][' . $cap_key . ']',
                                                'placeholder' => $cap['name']
                                            ),
                                            'value' => isset($role['capabilities'][$cap_key]) && $role['capabilities'][$cap_key] ? true : false,
                                            'checkbox_value' => true,
                                            'default_value' => false,
                                        ));
                                    }
                                    ?>
                                </div>
                            </li>
                        <?php } ?>
                    </ul>
                    <?php submit_button(); ?>
                </form>

            </div>
        <?php } elseif ($this->get->get('action') == 'maintenance') { ?>
            <div class="e2pdf-view-area">
                <ul class="e2pdf-options-list">
                    <li><h2><?php _e('Maintenance', 'e2pdf') ?></h2></li>
                    <li>
                        <div class="e2pdf-name">
                            <?php _e('Optimize Database', 'e2pdf'); ?>:
                        </div><div class="e2pdf-value">
                            <form onsubmit="return confirm('<?php _e('Are you sure want to continue?', 'e2pdf') ?>');" method="post" action="<?php echo $this->helper->get_url(array('page' => 'e2pdf-settings', 'action' => 'maintenance')); ?>">
                                <input type="hidden" name="_wpnonce" value="<?php echo wp_create_nonce('e2pdf_settings'); ?>">
                                <input type="hidden" name="e2pdf_db_optimize" value="1">
                                <input type="submit" name="submit" id="submit" class="button button-primary" value="»">
                            </form>
                        </div>
                    </li>
                    <li>
                        <div class="e2pdf-name">
                            <?php _e('Clear Recovery Mode Limit', 'e2pdf'); ?>:
                        </div><div class="e2pdf-value">
                            <form onsubmit="return confirm('<?php _e('Are you sure want to continue?', 'e2pdf') ?>');" method="post" action="<?php echo $this->helper->get_url(array('page' => 'e2pdf-settings', 'action' => 'maintenance')); ?>">
                                <input type="hidden" name="_wpnonce" value="<?php echo wp_create_nonce('e2pdf_settings'); ?>">
                                <input type="hidden" name="e2pdf_recovery_mode_limit" value="1">
                                <input type="submit" name="submit" id="submit" class="button button-primary" value="»">
                            </form>
                        </div>
                    </li>
                    <li><h2><?php _e('Cache', 'e2pdf') ?></h2></li>
                    <li>
                        <div class="e2pdf-name">
                            <?php _e('Purge Objects Cache', 'e2pdf'); ?>:
                        </div><div class="e2pdf-value">
                            <form onsubmit="return confirm('<?php _e('Are you sure want to continue?', 'e2pdf') ?>');" method="post" action="<?php echo $this->helper->get_url(array('page' => 'e2pdf-settings', 'action' => 'maintenance')); ?>">
                                <input type="hidden" name="_wpnonce" value="<?php echo wp_create_nonce('e2pdf_settings'); ?>">
                                <input type="hidden" name="e2pdf_purge_objects_cache" value="1">
                                <input type="submit" name="submit" id="submit" class="button button-primary" value="»">
                            </form>
                        </div>
                    </li>
                    <li>
                        <div class="e2pdf-name">
                            <?php _e('Purge Fonts Cache', 'e2pdf'); ?>:
                        </div><div class="e2pdf-value">
                            <form onsubmit="return confirm('<?php _e('Are you sure want to continue?', 'e2pdf') ?>');" method="post" action="<?php echo $this->helper->get_url(array('page' => 'e2pdf-settings', 'action' => 'maintenance')); ?>">
                                <input type="hidden" name="_wpnonce" value="<?php echo wp_create_nonce('e2pdf_settings'); ?>">
                                <input type="hidden" name="e2pdf_purge_fonts_cache" value="1">
                                <input type="submit" name="submit" id="submit" class="button button-primary" value="»">
                            </form>
                        </div>
                    </li>
                    <li>
                        <div class="e2pdf-name">
                            <?php _e('Purge PDFs Cache', 'e2pdf'); ?>:
                        </div><div class="e2pdf-value">
                            <form onsubmit="return confirm('<?php _e('Are you sure want to continue?', 'e2pdf') ?>');" method="post" action="<?php echo $this->helper->get_url(array('page' => 'e2pdf-settings', 'action' => 'maintenance')); ?>">
                                <input type="hidden" name="_wpnonce" value="<?php echo wp_create_nonce('e2pdf_settings'); ?>">
                                <input type="hidden" name="e2pdf_purge_pdfs_cache" value="1">
                                <input type="submit" name="submit" id="submit" class="button button-primary" value="»">
                            </form>
                        </div>
                    </li>
                    <li><hr></li>
                    <li>
                        <div class="e2pdf-name">
                            <?php _e('Purge Full Cache', 'e2pdf'); ?>:
                        </div><div class="e2pdf-value">
                            <form onsubmit="return confirm('<?php _e('Are you sure want to continue?', 'e2pdf') ?>');" method="post" action="<?php echo $this->helper->get_url(array('page' => 'e2pdf-settings', 'action' => 'maintenance')); ?>">
                                <input type="hidden" name="_wpnonce" value="<?php echo wp_create_nonce('e2pdf_settings'); ?>">
                                <input type="hidden" name="e2pdf_purge_cache" value="1">
                                <input type="submit" name="submit" id="submit" class="button button-primary" value="»">
                            </form>
                        </div>
                    </li>
                </ul>
            </div>
        <?php } else { ?>
            <div class="e2pdf-view-area">
                <?php if (isset($group['action']) && isset($group['group'])) { ?>
                    <form method="post" action="<?php echo $this->helper->get_url(array('page' => 'e2pdf-settings', 'action' => $this->get->get('action'), 'group' => $this->get->get('group'))); ?>">
                    <?php } elseif (isset($group['action'])) { ?>
                        <form method="post" action="<?php echo $this->helper->get_url(array('page' => 'e2pdf-settings', 'action' => $this->get->get('action'))); ?>">
                        <?php } else { ?>
                            <form method="post" action="<?php echo $this->helper->get_url(array('page' => 'e2pdf-settings')); ?>">
                            <?php } ?>
                            <input type="hidden" name="_wpnonce" value="<?php echo wp_create_nonce('e2pdf_settings'); ?>">
                            <ul class="e2pdf-options-list">
                                <?php foreach ($this->view->options as $group_key => $group) { ?>
                                    <?php foreach ($group['options'] as $option_key => $option_value) { ?>
                                        <?php if (isset($option_value['header'])) { ?>
                                            <li><h4><?php echo $option_value['header']; ?></h4></li>
                                        <?php } ?>
                                        <?php if ($option_value['type'] != 'hidden') { ?>
                                            <li>
                                                <div class="e2pdf-name">
                                                    <?php
                                                    if ($option_value['name']) {
                                                        echo $option_value['name'] . ":";
                                                    }
                                                    ?>
                                                </div><div class="e2pdf-value <?php echo $option_value['type'] == 'checkbox_list' ? 'checkbox_list' : '' ?>">
                                                    <?php
                                                    if ($option_value['type'] == 'checkbox') {
                                                        $this->render('field', 'checkbox', array(
                                                            'field' => array(
                                                                'name' => $option_value['key'],
                                                                'placeholder' => isset($option_value['placeholder']) ? $option_value['placeholder'] : ''
                                                            ),
                                                            'value' => isset($option_value['value']) ? $option_value['value'] : '',
                                                            'checkbox_value' => isset($option_value['checkbox_value']) ? $option_value['checkbox_value'] : '',
                                                            'default_value' => isset($option_value['default_value']) ? $option_value['default_value'] : '',
                                                        ));
                                                    } elseif ($option_value['type'] == 'text') {
                                                        $this->render('field', 'text', array(
                                                            'field' => array(
                                                                'name' => isset($option_value['key']) ? $option_value['key'] : '',
                                                                'placeholder' => isset($option_value['placeholder']) ? $option_value['placeholder'] : '',
                                                                'class' => isset($option_value['class']) ? 'e2pdf-w100 ' . $option_value['class'] : 'e2pdf-w100',
                                                                'readonly' => isset($option_value['readonly']) ? $option_value['readonly'] : false,
                                                            ),
                                                            'value' => $option_value['value'],
                                                            'prefield' => isset($option_value['prefield']) ? $option_value['prefield'] : '',
                                                        ));
                                                    } elseif ($option_value['type'] == 'textarea') {
                                                        $this->render('field', 'textarea', array(
                                                            'field' => array(
                                                                'name' => isset($option_value['key']) ? $option_value['key'] : '',
                                                                'style' => 'height: 100px;',
                                                                'class' => 'e2pdf-w100',
                                                                'placeholder' => isset($option_value['placeholder']) ? $option_value['placeholder'] : '',
                                                            ),
                                                            'value' => isset($option_value['value']) ? $option_value['value'] : '',
                                                        ));
                                                    } elseif ($option_value['type'] == 'select') {
                                                        $this->render('field', 'select', array(
                                                            'field' => array(
                                                                'name' => isset($option_value['key']) ? $option_value['key'] : '',
                                                                'class' => 'e2pdf-w100'
                                                            ),
                                                            'value' => isset($option_value['value']) ? $option_value['value'] : '',
                                                            'options' => isset($option_value['options']) ? $option_value['options'] : ''
                                                        ));
                                                    } elseif ($option_value['type'] == 'checkbox_list') {
                                                        $this->render('field', 'hidden', array(
                                                            'field' => array(
                                                                'name' => $option_value['key'],
                                                            ),
                                                            'value' => '',
                                                        ));

                                                        foreach ($option_value['options'] as $sub_option_key => $sub_option_value) {
                                                            if ($sub_option_value['type'] == 'checkbox') {
                                                                $this->render('field', 'checkbox', array(
                                                                    'field' => array(
                                                                        'name' => $sub_option_value['key'],
                                                                        'placeholder' => isset($sub_option_value['placeholder']) ? $sub_option_value['placeholder'] : ''
                                                                    ),
                                                                    'value' => isset($sub_option_value['value']) ? $sub_option_value['value'] : '',
                                                                    'checkbox_value' => isset($sub_option_value['checkbox_value']) ? $sub_option_value['checkbox_value'] : '',
                                                                    'default_value' => isset($sub_option_value['default_value']) ? $sub_option_value['default_value'] : '',
                                                                ));
                                                            }
                                                        }
                                                    }
                                                    ?>
                                                    <?php if (isset($option_value['description'])) { ?>
                                                        <div class="e2pdf-small">* <?php echo $option_value['description']; ?></div>
                                                    <?php } ?>
                                                </div>
                                            </li>
                                        <?php } ?>
                                    <?php } ?>
                                <?php } ?>
                            </ul>
                            <?php submit_button(); ?>
                        </form>
                        </div>
                    <?php } ?>

                    </div>
                    </div>
                    <?php $this->render('blocks', 'debug-panel'); ?>