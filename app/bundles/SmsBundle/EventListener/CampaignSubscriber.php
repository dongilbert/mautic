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

use Mautic\CampaignBundle\CampaignEvents;
use Mautic\CampaignBundle\Event\CampaignBuilderEvent;
use Mautic\CampaignBundle\Event\CampaignExecutionEvent;
use Mautic\ChannelBundle\Model\MessageQueueModel;
use Mautic\CoreBundle\Event\TokenReplacementEvent;
use Mautic\CoreBundle\EventListener\CommonSubscriber;
use Mautic\CoreBundle\Helper\CoreParametersHelper;
use Mautic\LeadBundle\Entity\DoNotContact;
use Mautic\LeadBundle\Model\LeadModel;
use Mautic\SmsBundle\Api\AbstractSmsApi;
use Mautic\SmsBundle\Event\SmsSendEvent;
use Mautic\SmsBundle\Helper\SmsHelper;
use Mautic\SmsBundle\Model\SmsModel;
use Mautic\SmsBundle\SmsEvents;

/**
 * Class CampaignSubscriber.
 */
class CampaignSubscriber extends CommonSubscriber
{
    /**
     * @var CoreParametersHelper
     */
    protected $coreParametersHelper;

    /**
     * @var LeadModel
     */
    protected $leadModel;

    /**
     * @var SmsModel
     */
    protected $smsModel;

    /**
     * @var AbstractSmsApi
     */
    protected $smsApi;

    /**
     * @var smsHelper
     */
    protected $smsHelper;

    /**
     * @var MessageQueueModel
     */
    protected $messageQueueModel;

    /**
     * CampaignSubscriber constructor.
     *
     * @param CoreParametersHelper $coreParametersHelper
     * @param LeadModel            $leadModel
     * @param SmsModel             $smsModel
     * @param AbstractSmsApi       $smsApi
     * @param SmsHelper            $smsHelper
     * @param MessageQueueModel    $messageQueueModel
     */
    public function __construct(
        CoreParametersHelper $coreParametersHelper,
        LeadModel $leadModel,
        SmsModel $smsModel,
        AbstractSmsApi $smsApi,
        SmsHelper $smsHelper,
        MessageQueueModel $messageQueueModel
    ) {
        $this->coreParametersHelper = $coreParametersHelper;
        $this->leadModel            = $leadModel;
        $this->smsModel             = $smsModel;
        $this->smsApi               = $smsApi;
        $this->smsHelper            = $smsHelper;
        $this->messageQueueModel    = $messageQueueModel;
    }

    /**
     * {@inheritdoc}
     */
    public static function getSubscribedEvents()
    {
        return [
            CampaignEvents::CAMPAIGN_ON_BUILD     => ['onCampaignBuild', 0],
            SmsEvents::ON_CAMPAIGN_TRIGGER_ACTION => ['onCampaignTriggerAction', 0],
        ];
    }

    /**
     * @param CampaignBuilderEvent $event
     */
    public function onCampaignBuild(CampaignBuilderEvent $event)
    {
        if ($this->coreParametersHelper->getParameter('sms_enabled')) {
            $event->addAction(
                'sms.send_text_sms',
                [
                    'label'            => 'mautic.campaign.sms.send_text_sms',
                    'description'      => 'mautic.campaign.sms.send_text_sms.tooltip',
                    'eventName'        => SmsEvents::ON_CAMPAIGN_TRIGGER_ACTION,
                    'formType'         => 'smssend_list',
                    'formTypeOptions'  => ['update_select' => 'campaignevent_properties_sms'],
                    'formTheme'        => 'MauticSmsBundle:FormTheme\SmsSendList',
                    'timelineTemplate' => 'MauticSmsBundle:SubscribedEvents\Timeline:index.html.php',
                    'channel'          => 'sms',
                    'channelIdField'   => 'sms',
                ]
            );
        }
    }

    /**
     * @param CampaignExecutionEvent $event
     */
    public function onCampaignTriggerAction(CampaignExecutionEvent $event)
    {
        $lead = $event->getLead();

        if ($this->leadModel->isContactable($lead, 'sms') !== DoNotContact::IS_CONTACTABLE) {
            return $event->setFailed('mautic.sms.campaign.failed.not_contactable');
        }

        $leadPhoneNumber = $lead->getFieldValue('mobile');

        if (empty($leadPhoneNumber)) {
            $leadPhoneNumber = $lead->getFieldValue('phone');
        }

        if (empty($leadPhoneNumber)) {
            return $event->setFailed('mautic.sms.campaign.failed.missing_number');
        }

        $smsId = (int) $event->getConfig()['sms'];
        $sms   = $this->smsModel->getEntity($smsId);

        if ($sms->getId() !== $smsId) {
            return $event->setFailed('mautic.sms.campaign.failed.missing_entity');
        }

        $smsEvent = new SmsSendEvent($sms->getMessage(), $lead);
        $smsEvent->setSmsId($smsId);
        $this->dispatcher->dispatch(SmsEvents::SMS_ON_SEND, $smsEvent);

        $tokenEvent = $this->dispatcher->dispatch(
            SmsEvents::TOKEN_REPLACEMENT,
            new TokenReplacementEvent(
                $smsEvent->getContent(),
                $lead,
                ['channel' => ['sms', $sms->getId()]]
            )
        );

        $defaultFrequencyNumber = $this->coreParametersHelper->getParameter('sms_frequency_number');
        $defaultFrequencyTime   = $this->coreParametersHelper->getParameter('sms_frequency_time');

        /** @var \Mautic\LeadBundle\Entity\FrequencyRuleRepository $frequencyRulesRepo */
        $frequencyRulesRepo = $this->leadModel->getFrequencyRuleRepository();

        $leadIds    = $lead->getId();
        $dontSendTo = $frequencyRulesRepo->getAppliedFrequencyRules('sms', $leadIds, $defaultFrequencyNumber, $defaultFrequencyTime);

        $metadata = 'mautic.sms.campaign.failed.not_contactable';
        if (empty($dontSendTo) || (!empty($dontSendTo) && $dontSendTo[0]['lead_id'] != $lead->getId())) {
            $metadata = $this->smsApi->sendSms($leadPhoneNumber, $tokenEvent->getContent());
        }

        if (true !== $metadata) {
            return $event->setFailed($metadata);
        }

        $this->smsModel->createStatEntry($sms, $lead);
        $this->smsModel->getRepository()->upCount($smsId);
        $event->setChannel('sms', $sms->getId());
        $event->setResult($result);
    }
}
