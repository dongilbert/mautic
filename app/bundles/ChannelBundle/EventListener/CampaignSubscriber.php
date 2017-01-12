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
use Mautic\CampaignBundle\Event\CampaignExecutionEvent;
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
            CampaignEvents::CAMPAIGN_ON_BUILD         => ['onCampaignBuild', 0],
            ChannelEvents::ON_CAMPAIGN_TRIGGER_ACTION => ['onCampaignTriggerAction', 0],
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
                'messageId'       => $entity['id'],
                'formTypeOptions' => ['message_id' => $entity['id']],
            ];
            $event->addMessage('message.send_'.$entity['name'], $action);
        }
    }

    /**
     * @param CampaignExecutionEvent $event
     */
    public function onCampaignTriggerAction(CampaignExecutionEvent $event)
    {
        $messageSent     = false;
        $channelMessages = $this->messageModel->getChannelMessages((int) $event->getConfig()['messageId']);
        $messageEvent    = $event->getEvent();
        $lead            = $event->getLead();

        $args = [
            'lead' => $lead,

            'eventDetails'    => '',
            'systemTriggered' => $event->getSystemTriggered(),
            'eventSettings'   => $event->getEventSettings(),
        ];
        $eventType = [
            'campaign'            => $messageEvent['campaign'],
            'name'                => $messageEvent['name'],
            'id'                  => $messageEvent['id'],
            'eventType'           => 'action',
            'triggerDate'         => $messageEvent['triggerDate'],
            'triggerInterval'     => $messageEvent['triggerInterval'],
            'triggerIntervalUnit' => $messageEvent['triggerIntervalUnit'],
            'triggerMode'         => $messageEvent['triggerMode'],
            'decisionPath'        => $messageEvent['decisionPath'],

        ];
        $args['event'] = $eventType;
        foreach ($channelMessages as $message) {
            $messageId = $message['channel_id'];
            switch ($message['channel']) {
                case 'email':
                    $eventName                   = \Mautic\EmailBundle\EmailEvents::ON_CAMPAIGN_TRIGGER_ACTION;
                    $args['event']['type']       = 'email.send';
                    $args['event']['properties'] = [
                        'email'      => $messageId,
                        'email_type' => 'marketing',
                    ];
                    break;
                case 'sms':
                    $eventName                   = \Mautic\SmsBundle\SmsEvents::ON_CAMPAIGN_TRIGGER_ACTION;
                    $args['event']['type']       = 'sms.send_text_sms';
                    $args['event']['properties'] = [
                        'sms' => $messageId,
                    ];
                    break;
                case 'dynamicContent':
                    $eventName                   = \Mautic\DynamicContentBundle\DynamicContentEvents::ON_CAMPAIGN_TRIGGER_ACTION;
                    $args['event']['type']       = 'dwc.push_content';
                    $args['event']['properties'] = [
                        'dynamicContent' => $messageId,
                    ];
                    break;
                case 'notification':
                    $eventName                   = \Mautic\NotificationBundle\NotificationEvents::ON_CAMPAIGN_TRIGGER_ACTION;
                    $args['event']['type']       = 'notification.send_notification';
                    $args['event']['properties'] = [
                        'notification' => $messageId,
                    ];
                    break;
                case 'tweet':
                    $eventName             = \MauticPlugin\MauticSocialBundle\SocialEvents::ON_CAMPAIGN_TRIGGER_ACTION;
                    $args['event']['type'] = 'twitter.tweet';
                    break;
            }
            $result       = [];
            $channelEvent = new CampaignExecutionEvent($args, $result);
            if ($this->dispatcher->hasListeners($eventName)) {
                $channelEvent = $this->dispatcher->dispatch($eventName, $channelEvent);
                $messageSent  = $channelEvent->getResult();
            }
        }

        return $event->setResult($messageSent);
    }
}
