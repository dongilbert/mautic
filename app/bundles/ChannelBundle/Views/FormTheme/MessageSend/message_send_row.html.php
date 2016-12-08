<?php

/*
 * @copyright   2014 Mautic Contributors. All rights reserved
 * @author      Mautic
 *
 * @link        http://mautic.org
 *
 * @license     GNU/GPLv3 http://www.gnu.org/licenses/gpl-3.0.html
 */
?>

<div class="row">
    <div class="col-xs-12 ?>">
        <?php echo $view['form']->row($form['message']); ?>
    </div>
<div class="row">
    <div class="col-xs-12 mt-lg">
        <div class="mt-3">
            <?php echo $view['form']->row($form['newMessageButton']); ?>
            <?php echo $view['form']->row($form['editMessageButton']); ?>
            <?php echo $view['form']->row($form['previewMessageButton']); ?>
        </div>
    </div>
</div>