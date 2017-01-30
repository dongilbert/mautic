<?php

/*
 * @copyright   2016 Mautic Contributors. All rights reserved
 * @author      Mautic
 *
 * @link        http://mautic.org
 *
 * @license     GNU/GPLv3 http://www.gnu.org/licenses/gpl-3.0.html
 */

namespace Mautic\PageBundle\EventListener;

use Mautic\ChannelBundle\ChannelEvents;
use Mautic\ChannelBundle\Event\ChannelEvent;
use Mautic\CoreBundle\EventListener\CommonSubscriber;
use Mautic\ReportBundle\Builder\MauticReportBuilder;
use Mautic\ReportBundle\Model\ReportModel;

/**
 * Class ChannelSubscriber.
 */
class ChannelSubscriber extends CommonSubscriber
{
    /**
     * @return array
     */
    public static function getSubscribedEvents()
    {
        return [
            ChannelEvents::ADD_CHANNEL => ['onAddChannel', 0],
        ];
    }

    /**
     * @param ChannelEvent $event
     */
    public function onAddChannel(ChannelEvent $event)
    {
        $event->addChannel(
            'page',
            [
                ReportModel::CHANNEL_FEATURE => [
                    'table'  => 'pages',
                    'fields' => [
                        MauticReportBuilder::CHANNEL_COLUMN_NAME        => 'page.title',
                        MauticReportBuilder::CHANNEL_COLUMN_DESCRIPTION => 'page.meta_description',
                    ],
                ],
            ]
        );
    }
}
