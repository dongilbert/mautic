<?php

/*
 * @copyright   2016 Mautic Contributors. All rights reserved
 * @author      Mautic, Inc.
 *
 * @link        https://mautic.org
 *
 * @license     GNU/GPLv3 http://www.gnu.org/licenses/gpl-3.0.html
 */

namespace Mautic\ChannelBundle\Model;

use Mautic\CampaignBundle\Model\CampaignModel;
use Mautic\ChannelBundle\Entity\Message;
use Mautic\ChannelBundle\Form\Type\MessageType;
use Mautic\ChannelBundle\Helper\ChannelListHelper;
use Mautic\CoreBundle\Model\AjaxLookupModelInterface;
use Mautic\CoreBundle\Model\FormModel;
use Symfony\Component\Form\FormFactory;

/**
 * Class MessageModel.
 */
class MessageModel extends FormModel implements AjaxLookupModelInterface
{
    const CHANNEL_FEATURE = 'marketing_messages';

    /**
     * @var ChannelListHelper
     */
    protected $channelListHelper;

    /**
     * @var CampaignModel
     */
    protected $campaignModel;

    /**
     * MessageModel constructor.
     *
     * @param ChannelListHelper $channelListHelper
     * @param CampaignModel     $campaignModel
     */
    public function __construct(ChannelListHelper $channelListHelper, CampaignModel $campaignModel)
    {
        $this->channelListHelper = $channelListHelper;
        $this->campaignModel     = $campaignModel;
    }

    /**
     * @param Message $entity
     * @param bool    $unlock
     */
    public function saveEntity($entity, $unlock = true)
    {
        parent::saveEntity($entity, $unlock);

        // Persist channels to update changes
        $channels = $entity->getChannels();
        if ($channels->count()) {
            foreach ($channels as $channel) {
                $this->em->persist($channel);
            }
            $this->em->flush();
        }
    }

    /**
     * @return string
     */
    public function getPermissionBase()
    {
        return 'channel:messages';
    }

    /**
     * @return \Doctrine\ORM\EntityRepository|\Mautic\ChannelBundle\Entity\MessageRepository
     */
    public function getRepository()
    {
        return $this->em->getRepository('MauticChannelBundle:Message');
    }

    /**
     * @param null $id
     *
     * @return Form
     */
    public function getEntity($id = null)
    {
        if ($id === null) {
            return new Message();
        }

        return parent::getEntity($id);
    }

    /**
     * @param object      $entity
     * @param FormFactory $formFactory
     * @param null        $action
     * @param array       $options
     *
     * @return \Symfony\Component\Form\FormInterface
     */
    public function createForm($entity, $formFactory, $action = null, $options = [])
    {
        if (!empty($action)) {
            $options['action'] = $action;
        }

        return $formFactory->create(MessageType::class, $entity, $options);
    }

    /**
     * @return array
     */
    public function getChannels()
    {
        $channels = $this->channelListHelper->getFeatureChannels(self::CHANNEL_FEATURE);

        // Validate channel configs
        foreach ($channels as $channel => $config) {
            if (!isset($config['lookupFormType']) && !isset($config['propertiesFormType'])) {
                throw new \InvalidArgumentException('lookupFormType and/or propertiesFormType are required for channel '.$channel);
            }

            switch (true) {
                case $this->translator->hasId('mautic.channel.'.$channel):
                    $label = $this->translator->trans('mautic.channel.'.$channel);
                    break;
                case $this->translator->hasId('mautic.'.$channel):
                    $label = $this->translator->trans('mautic.'.$channel);
                    break;
                case $this->translator->hasId('mautic.'.$channel.'.'.$channel):
                    $label = $this->translator->trans('mautic.'.$channel.'.'.$channel);
                    break;
                default:
                    $label = ucfirst($channel);
            }
            $config['label'] = $label;

            $channels[$channel] = $config;
        }

        return $channels;
    }

    /**
     * @param        $type
     * @param string $filter
     * @param int    $limit
     * @param int    $start
     * @param array  $options
     *
     * @return array
     */
    public function getLookupResults($type, $filter = '', $limit = 10, $start = 0, $options = [])
    {
        $results = [];
        switch ($type) {
            case 'channel.message':
                $entities = $this->getRepository()->getMessageList(
                    $filter,
                    $limit,
                    $start,
                    $this->security->isGranted($this->getPermissionBase().':viewother')
                );

                foreach ($entities as $entity) {
                    $results[] = [
                        'label' => $entity['name'],
                        'value' => $entity['id'],
                    ];
                }

                break;
        }

        return $results;
    }

    /**
     * @param $messageId
     *
     * @return array
     */
    public function getMessageChannels($messageId)
    {
        return $this->getRepository()->getMessageChannels($messageId);
    }

    public function getChannelMessageByChannelId($channelId)
    {
        return $this->getRepository()->getChannelMessageByChannelId($channelId);
    }

    /**
     * @param $dateFrom
     * @param $dateTo
     * @param $options
     *
     * @return array
     */
    public function getLeadStatsPost($dateFrom, $dateTo, $options)
    {
        //todo get stats for channel messages
        return [0, time()];
    }
}
