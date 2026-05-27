<?php
if (!defined('ABSPATH')) {
    die('Access denied.');
}
?>
<input type="hidden" <?php foreach ($this->tpl_args->get('field') as $field_key => $field_value) { ?><?php if ((($field_key === 'disabled' || $field_key === 'readonly') && $field_value != false) || ($field_key != 'disabled' && $field_key != 'readonly')) { ?><?php echo esc_attr($field_key); ?>="<?php echo esc_attr($field_value); ?>" <?php } ?><?php } ?> value="<?php echo esc_attr($this->tpl_args->get('value')); ?>">
