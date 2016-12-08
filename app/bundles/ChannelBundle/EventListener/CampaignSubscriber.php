<?php

/*
 * @copyright   2014 Mautic Contributors. All rights reserved
 * @author      Mautic
 *
 * @link        http://mautic.org
 *
 * @license     GNU/GPLv3 http://www.gnu.org/licenses/gpl-3.0.html
 */

namespace Mautic\ChannelBundle\EventListener;

use Mautic\CampaignBundle\CampaignEvents;
use Mautic\CampaignBundle\Event\CampaignBuilderEvent;
use Mautic\ChannelBundle\ChannelEvents;
use Mautic\CoreBundle\EventListener\CommonSubscriber;

/**
 * Class CampaignSubscriber.
 */
class CampaignSubscriber extends CommonSubscriber
{
    /**
     * {@inheritdoc}
     */
    public static function getSubscribedEvents()
    {
        return [
            CampaignEvents::CAMPAIGN_ON_BUILD => ['onCampaignBuild', 0],
        ];
    }

    /**
     * @param CampaignBuilderEvent $event
     */
    public function onCampaignBuild(CampaignBuilderEvent $event)
    {
        $action = [
            'label'           => 'mautic.channel.campaign.event.send',
            'description'     => 'mautic.channel.campaign.event.send_descr',
            'eventName'       => ChannelEvents::ON_CAMPAIGN_TRIGGER_ACTION,
            'formType'        => 'message_send',
            'formTypeOptions' => ['update_select' => 'campaignevent_properties_message'],
            'formTheme'       => 'MauticEmailBundle:FormTheme\MessageSend',
        ];
        $event->addAction('message.send', $action);
    }
}
