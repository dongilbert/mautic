<?php

/*
 * @copyright   2016 Mautic Contributors. All rights reserved
 * @author      Mautic
 *
 * @link        http://mautic.org
 *
 * @license     GNU/GPLv3 http://www.gnu.org/licenses/gpl-3.0.html
 */

namespace Mautic\SmsBundle\EventListener;

use Mautic\ChannelBundle\ChannelEvents;
use Mautic\ChannelBundle\Event\ChannelEvent;
use Mautic\ChannelBundle\Model\MessageModel;
use Mautic\CoreBundle\EventListener\CommonSubscriber;
use Mautic\LeadBundle\Model\LeadModel;

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

    public function onAddChannel(ChannelEvent $event)
    {
        if (!empty($this->params['sms_enabled'])) {
            $event->addChannel(
                'sms',
                [
                    MessageModel::CHANNEL_FEATURE => [
                        'lookupFormType' => 'sms_list',
                        'detailView'     => 'MauticSmsBundle:Sms:view',
                    ],
                    LeadModel::CHANNEL_FEATURE => [],
                ]
            );
        }
    }
}
