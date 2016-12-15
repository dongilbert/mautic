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
use Mautic\ChannelBundle\Model\MessageModel;
use Mautic\CoreBundle\EventListener\CommonSubscriber;

/**
 * Class CampaignSubscriber.
 */
class CampaignSubscriber extends CommonSubscriber
{
    private $messageModel;

    /**
     * CampaignSubscriber constructor.
     *
     * @param MessageModel $messageModel
     */
    public function __construct(MessageModel $messageModel)
    {
        $this->messageModel = $messageModel;
    }
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
        $entities = $this->messageModel->getRepository()->getMessageList();

        foreach ($entities as $entity) {
            $action = [
                'label'           => $entity['name'],
                'description'     => $entity['description'],
                'eventName'       => ChannelEvents::ON_CAMPAIGN_TRIGGER_ACTION,
                'formType'        => 'message_send',
                'formTypeOptions' => ['message_id' => $entity['id']],
            ];
            $event->addMessage('message.send_'.$entity['name'], $action);
        }
    }
}
