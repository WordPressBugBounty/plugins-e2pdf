<?php
if (!defined('ABSPATH')) {
    die('Access denied.');
}
?>
<textarea <?php foreach ($this->tpl_args->get('field') as $field_key => $field_value) { ?><?php if (($field_key === 'disabled' && $field_value != false) || $field_key != 'disabled') { ?><?php echo esc_attr($field_key); ?>="<?php echo esc_attr($field_value); ?>" <?php } ?><?php } ?>><?php echo esc_textarea($this->tpl_args->get('value')); ?></textarea>
