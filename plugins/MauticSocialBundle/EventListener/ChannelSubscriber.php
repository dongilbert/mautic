<?php

/*
 * @copyright   2016 Mautic Contributors. All rights reserved
 * @author      Mautic
 *
 * @link        http://mautic.org
 *
 * @license     GNU/GPLv3 http://www.gnu.org/licenses/gpl-3.0.html
 */

namespace MauticPlugin\MauticSocialBundle\EventListener;

use Mautic\ChannelBundle\ChannelEvents;
use Mautic\ChannelBundle\Event\ChannelEvent;
use Mautic\ChannelBundle\Model\MessageModel;
use Mautic\CoreBundle\EventListener\CommonSubscriber;

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
            'tweet',
            [
                MessageModel::CHANNEL_FEATURE => [
                    'propertiesFormType' => 'twitter_tweet',
                    'channelTemplate'    => 'MauticSocialBundle:SubscribedEvents\Channel:message.html.php',
                    'formTheme'          => 'MauticSocialBundle:FormTheme',
                    'detailView'         => 'MauticSocialBundle:Monitoring:view',
                ],
            ]
        );
    }
}
