<?php
if (!defined('ABSPATH')) {
    die('Access denied.');
}
?>
<fieldset <?php foreach ($this->tpl_args->get('field') as $field_key => $field_value) { ?><?php if (($field_key === 'disabled' && $field_value != false) || $field_key != 'disabled') { ?><?php echo esc_attr($field_key); ?>="<?php echo esc_attr($field_value); ?>" <?php } ?><?php } ?>> 
    <?php foreach ($this->tpl_args->get('options') as $option_key => $option_value) { ?>
        <?php if (is_array($option_value)) { ?>
            <div class="e2pdf-ib e2pdf-w100">
                <label>
                    <input type="checkbox" <?php
                    if (isset($option_value['subfield'])) {
                        foreach ($option_value['subfield'] as $sub_key => $sub_value) {
                            ?><?php echo esc_attr($sub_key); ?>="<?php echo esc_attr($sub_value); ?>" <?php
                               }
                           }
                           ?><?php if (is_array($this->tpl_args->get('value')) && in_array($option_value['key'], $this->tpl_args->get('value'))) { ?>checked="checked"<?php } ?> value="<?php echo esc_attr($option_value['key']); ?>"><?php echo esc_html($option_value['value']); ?>
                </label>
            </div>  
        <?php } else { ?>
            <div class="e2pdf-ib e2pdf-w100">
                <label>
                    <input type="checkbox" <?php foreach ($this->tpl_args->get('field') as $field_key => $field_value) { ?><?php if ($field_key === 'name') { ?>name="<?php echo esc_attr($field_value); ?>[]" <?php } ?><?php } ?> value="<?php echo esc_attr($option_key); ?>" <?php if (is_array($this->tpl_args->get('value')) && in_array($option_key, $this->tpl_args->get('value'))) { ?>checked="checked"<?php } ?>><?php echo esc_html($option_value); ?>
                </label>
            </div>
        <?php } ?>
    <?php } ?>
</fieldset>
