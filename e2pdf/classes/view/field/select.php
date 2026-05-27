<?php
if (!defined('ABSPATH')) {
    die('Access denied.');
}
?>
<select <?php foreach ($this->tpl_args->get('field') as $field_key => $field_value) { ?><?php if (($field_key === 'disabled' && $field_value != false) || $field_key != 'disabled') { ?><?php echo esc_attr($field_key); ?>="<?php echo esc_attr($field_value); ?>" <?php } ?><?php } ?>>
    <?php if ($this->tpl_args->get('empty')) { ?>
        <option <?php if ($this->tpl_args->get('value') == '') { ?>selected="selected"<?php } ?> value=""><?php echo esc_html($this->tpl_args->get('empty')) ?></option>
    <?php } ?>
    <?php foreach ($this->tpl_args->get('options') as $option_key => $option_value) { ?>
        <?php if (is_array($option_value)) { ?>
            <option <?php
            if (isset($option_value['subfield'])) {
                foreach ($option_value['subfield'] as $sub_key => $sub_value) {
                    ?><?php echo esc_attr($sub_key); ?>="<?php echo esc_attr($sub_value); ?>" <?php
                    }
                }
                ?><?php if ($this->tpl_args->get('value') == $option_value['key']) { ?>selected="selected"<?php } ?> value="<?php echo esc_attr($option_value['key']); ?>"><?php echo esc_html($option_value['value']); ?></option>
            <?php } else { ?>
            <option <?php if ($this->tpl_args->get('value') == $option_key) { ?>selected="selected"<?php } ?> value="<?php echo esc_attr($option_key); ?>"><?php echo esc_html($option_value); ?></option>
        <?php } ?>
    <?php } ?>
</select>
