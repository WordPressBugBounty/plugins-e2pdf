<?php
if (!defined('ABSPATH')) {
    die('Access denied.');
}
?>
<div id="e2pdf-builder" class="categorydiv e2pdf-tabs-panel">
    <ul id="e2pdf-tabs" class="category-tabs e2pdf-tabs">
        <li class="active"><a data-tab="build-pdf-fields" href="javascript:void(0);"><?php _e('Fields', 'e2pdf'); ?></a></li>
        <li><a data-tab="build-pdf-objects" href="javascript:void(0);"><?php _e('Objects', 'e2pdf'); ?></a></li>
    </ul>
    <div id="build-pdf-fields" class="tabs-panel build-pdf-fields" >
        <div class="e2pdf-build-panel">
            <p class="post-attributes-label-wrapper">
                <label><?php _e('Fields', 'e2pdf'); ?>:</label>
            </p>

            <ul class="e2pdf-build-pdf-elements-list">
                <li>
                    <a data-type="e2pdf-input" href="javascript:void(0);" class="e2pdf-be e2pdf-clone"><?php _e('Input', 'e2pdf'); ?></a>
                </li><li>
                    <a data-type="e2pdf-textarea" href="javascript:void(0);" class="e2pdf-be e2pdf-clone"><?php _e('Textarea', 'e2pdf'); ?></a>
                </li><li>
                    <a data-type="e2pdf-checkbox" href="javascript:void(0);" class="e2pdf-be e2pdf-clone"><?php _e('Checkbox', 'e2pdf'); ?></a>
                </li><li>
                    <a data-type="e2pdf-radio" href="javascript:void(0);" class="e2pdf-be e2pdf-clone"><?php _e('Radio', 'e2pdf'); ?></a>
                </li><li>
                    <a data-type="e2pdf-select" href="javascript:void(0);" class="e2pdf-be e2pdf-clone"><?php _e('Select', 'e2pdf'); ?></a>
                </li><li>
                    <a data-type="e2pdf-signature" href="javascript:void(0);" class="e2pdf-be e2pdf-clone"><?php _e('Signature', 'e2pdf'); ?></a>
                </li>
            </ul>
            <div class="clear"></div>
        </div>
    </div>
    <div id="build-pdf-objects" class="tabs-panel" style="display: none;">
        <div class="e2pdf-build-panel">
            <p class="post-attributes-label-wrapper">
                <label><?php _e('Objects', 'e2pdf'); ?>:</label>
            </p>
            <ul class="e2pdf-build-pdf-elements-list">
                <li><a data-type="e2pdf-html" href="javascript:void(0);" class="e2pdf-be e2pdf-clone"><?php _e('Html', 'e2pdf'); ?></a>
                </li><li>
                    <a data-type="e2pdf-image" href="javascript:void(0);" class="e2pdf-be e2pdf-clone"><?php _e('Image', 'e2pdf'); ?></a>
                </li><li>
                    <a data-type="e2pdf-rectangle" href="javascript:void(0);" class="e2pdf-be e2pdf-clone"><?php _e('Rectangle', 'e2pdf'); ?></a>
                </li><li>
                    <a data-type="e2pdf-link" href="javascript:void(0);" class="e2pdf-be e2pdf-clone"><?php _e('Link', 'e2pdf'); ?></a>
                </li><li>
                    <a data-type="e2pdf-qrcode" href="javascript:void(0);" class="e2pdf-be e2pdf-clone"><?php _e('QR Code', 'e2pdf'); ?></a>
                </li><li>
                    <a data-type="e2pdf-barcode" href="javascript:void(0);" class="e2pdf-be e2pdf-clone"><?php _e('Barcode', 'e2pdf'); ?></a>
                </li><li>
                    <a data-type="e2pdf-graph" href="javascript:void(0);" class="e2pdf-be e2pdf-clone"><?php _e('Graph', 'e2pdf'); ?></a>
                </li><li>
                    <a data-type="e2pdf-page-number" href="javascript:void(0);" class="e2pdf-be e2pdf-clone"><?php _e('Page Number', 'e2pdf'); ?></a>
                </li>
            </ul>
            <div class="clear"></div>
        </div>
    </div>
</div>



