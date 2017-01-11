<?php

/*
 * @copyright   2016 Mautic Contributors. All rights reserved
 * @author      Mautic, Inc.
 *
 * @link        https://mautic.org
 *
 * @license     GNU/GPLv3 http://www.gnu.org/licenses/gpl-3.0.html
 */
$view->extend('MauticCoreBundle:Default:content.html.php');
$view['slots']->set('mauticContent', 'marketingMessage');
$view['slots']->set('headerTitle', $item->getName());

$view['slots']->set('actions', $view->render('MauticCoreBundle:Helper:page_actions.html.php', [
    'item'            => $item,
    'templateButtons' => [
        'edit'   => $view['security']->hasEntityAccess($permissions['channel:messages:editown'], $permissions['channel:messages:editother'], $item->getCreatedBy()),
        'clone'  => $permissions['channel:messages:create'],
        'delete' => $view['security']->hasEntityAccess($permissions['channel:messages:deleteown'], $permissions['channel:messages:deleteown'], $item->getCreatedBy()),
    ],
    'routeBase' => 'message',
]));
// active, id, name, content
$tabs   = [];
$active = true;
foreach ($channels as $channel => $config) {
    $tab = [
        'active'  => $active,
        'id'      => 'channel_'.$channel,
        'name'    => $config['label'],
        'content' => $channel,
    ];

    $tabs[] = $tab;

    $active = false;
}

$view['slots']->set('formTabs', $tabs);
$view['slots']->start('rightFormContent');
$view['slots']->stop();
