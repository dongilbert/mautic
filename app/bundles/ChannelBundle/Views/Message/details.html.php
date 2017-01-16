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
    $channelIdKey[$channel] = array_search($channel, array_column($channel_contents, 'channel'));
    $tab                    = [
        'active'        => $active,
        'id'            => 'channel_'.$channel,
        'name'          => $config['label'],
        'content'       => ($channelIdKey[$channel] === 0 or $channelIdKey[$channel] > 0) ? $view['actions']->render(new \Symfony\Component\HttpKernel\Controller\ControllerReference($config['detailView'],
            ['objectId' => $channel_contents[$channelIdKey[$channel]]['channel_id'], 'isEmbedded' => true])) : 'Not Active',

    ];

    $tabs[] = $tab;

    $active = false;
}

$view['slots']->set('formTabs', $tabs);
?>
<?php echo $view->render('MauticCoreBundle:Helper:tabs.html.php', ['tabs' => $tabs]);
$view['slots']->set('mauticContent', 'message');
?>
<?php
$view['slots']->start('rightFormContent');
$view['slots']->stop();
