<?php
if (!defined('ABSPATH')) {
    die('Access denied.');
}
?>
<?php if ($this->tpl_args->get('prefield')) { ?><div class="e2pdf-prefield"><?php echo esc_html($this->tpl_args->get('prefield')); ?></div><span class="e2pdf-prefield-field"><?php } ?><input type="password" autocomplete="new-password" <?php foreach ($this->tpl_args->get('field') as $field_key => $field_value) { ?><?php if ((($field_key === 'disabled' || $field_key === 'readonly') && $field_value != false) || ($field_key != 'disabled' && $field_key != 'readonly')) { ?><?php echo esc_attr($field_key); ?>="<?php echo esc_attr($field_value); ?>" <?php } ?><?php } ?> value="<?php echo esc_attr($this->tpl_args->get('value')); ?>"><?php if ($this->tpl_args->get('prefield')) { ?></span><?php } ?>
