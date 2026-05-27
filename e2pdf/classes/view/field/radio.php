<?php
if (!defined('ABSPATH')) {
    die('Access denied.');
}
?>
<?php foreach ($this->tpl_args->get('options') as $field_key => $field_value) { ?>
    <?php if (is_array($field_value)) { ?>
        <div>
            <input type="radio" <?php foreach ($this->tpl_args->get('field') as $sub_key => $sub_value) { ?><?php if (($sub_key === 'disabled' && $sub_value != false) || $sub_key != 'disabled') { ?><?php echo esc_attr($sub_key); ?>="<?php echo esc_attr($sub_value); ?>" <?php } ?><?php } ?> <?php
            if (isset($field_value['subfield'])) {
                foreach ($field_value['subfield'] as $sub_key => $sub_value) {
                    ?><?php echo esc_attr($sub_key); ?>="<?php echo esc_attr($sub_value); ?>" <?php
                       }
                   }
                   ?><?php if ($this->tpl_args->get('value') == $field_value['key']) { ?>checked="checked"<?php } ?> value="<?php echo esc_attr($field_value['key']); ?>"> <?php echo esc_html($field_value['value']); ?>
        </div>
    <?php } else { ?>
        <div>
            <input type="radio" <?php foreach ($this->tpl_args->get('field') as $sub_key => $sub_value) { ?><?php if (($sub_key === 'disabled' && $sub_value != false) || $sub_key != 'disabled') { ?><?php echo esc_attr($sub_key); ?>="<?php echo esc_attr($sub_value); ?>" <?php } ?><?php } ?> <?php if ($this->tpl_args->get('value') == $field_key) { ?>checked="checked"<?php } ?> value="<?php echo esc_attr($field_key); ?>"> <?php echo esc_html($field_value); ?>
        </div>
    <?php } ?>
<?php } ?>
