<?php

/*
 * @copyright   2016 Mautic Contributors. All rights reserved
 * @author      Mautic, Inc.
 *
 * @link        https://mautic.org
 *
 * @license     GNU/GPLv3 http://www.gnu.org/licenses/gpl-3.0.html
 */

namespace Mautic\ChannelBundle\Controller;

use Mautic\ChannelBundle\Model\MessageModel;
use Mautic\CoreBundle\Controller\AbstractStandardFormController;
use Mautic\CoreBundle\Helper\Chart\LineChart;
use Symfony\Component\Form\Form;

/**
 * Class MessageController.
 */
class MessageController extends AbstractStandardFormController
{
    /**
     * @param $args
     * @param $view
     *
     * @return mixed
     */
    protected function customizeViewArguments($args, $view)
    {
        /** @var MessageModel $model */
        $model          = $this->getModel($this->modelName);
        $viewParameters = [];
        switch ($view) {
            case 'index':
                $viewParameters = [
                    'headerTitle' => $this->get('translator')->trans('mautic.channel.messages'),
                    'listHeaders' => [
                        [
                            'text'  => 'mautic.core.channels',
                            'class' => 'visible-md visible-lg',
                        ],
                    ],
                    'listItemTemplate'  => 'MauticChannelBundle:Message:list_item.html.php',
                    'enableCloneButton' => true,
                ];

                break;
            case 'viewTwitter':
                $viewParameters = [
                    'contentTemplate' => $this->getStandardTemplate('details_twitter.html.php'),

                ];
                break;
            case 'view':
                $viewParameters = [
                    'channels'        => $model->getChannels(),
                    'channelContents' => $model->getMessageChannels($args['viewParameters']['item']->getId()),
                ];
                break;
            case 'new':
            case 'edit':
                // Check to see if this is a popup
                if (isset($form['updateSelect'])) {
                    $this->template = false;
                } else {
                    $this->template = true;
                }
                $viewParameters = [
                    'channels' => $model->getChannels(),
                ];

                break;
        }

        $args['viewParameters'] = array_merge($args['viewParameters'], $viewParameters);

        return $args;
    }

    /**
     * @param Form $form
     * @param      $view
     *
     * @return \Symfony\Component\Form\FormView
     */
    public function getStandardFormView(Form $form, $view)
    {
        $themes = ['MauticChannelBundle:FormTheme'];
        /** @var MessageModel $model */
        $model    = $this->getModel($this->modelName);
        $channels = $model->getChannels();
        foreach ($channels as $channel) {
            if (isset($channel['formTheme'])) {
                $themes[] = $channel['formTheme'];
            }
        }

        return $this->setFormTheme($form, 'MauticChannelBundle:Message:form.html.php', $themes);
    }

    /**
     * @param int $page
     *
     * @return \Mautic\CoreBundle\Controller\Response|\Symfony\Component\HttpFoundation\JsonResponse|\Symfony\Component\HttpFoundation\RedirectResponse
     */
    public function indexAction($page = 1)
    {
        return $this->indexStandard($page);
    }

    /**
     * @return \Mautic\CoreBundle\Controller\Response|\Symfony\Component\HttpFoundation\JsonResponse
     */
    public function newAction()
    {
        return $this->newStandard();
    }

    /**
     * @param      $objectId
     * @param bool $ignorePost
     *
     * @return \Mautic\CoreBundle\Controller\Response|\Symfony\Component\HttpFoundation\JsonResponse
     */
    public function editAction($objectId, $ignorePost = false)
    {
        return $this->editStandard($objectId, $ignorePost);
    }

    /**
     * @param $objectId
     *
     * @return array|\Symfony\Component\HttpFoundation\JsonResponse|\Symfony\Component\HttpFoundation\RedirectResponse|\Symfony\Component\HttpFoundation\Response
     */
    public function viewAction($objectId)
    {
        return $this->viewStandard($objectId);
    }

    /**
     * @param      $objectId
     * @param bool $ignorePost
     *
     * @return \Mautic\CoreBundle\Controller\Response|\Symfony\Component\HttpFoundation\JsonResponse
     */
    public function viewTwitterAction($objectId)
    {
        if (!$this->get('mautic.security')->isGranted('mauticChannel:message:view')) {
            return $this->accessDenied();
        }

        $session = $this->get('session');

        /** @var \MauticPlugin\MauticSocialBundle\Model\MonitoringModel $model */
        $model = $this->getModel('channel.message');

        /** @var \MauticPlugin\MauticSocialBundle\Entity\PostCountRepository $postCountRepo */
        $postCountRepo = $this->getModel('social.postcount')->getRepository();

        $security = $this->container->get('mautic.security');

        $tweet = $model->getChannelMessageByChannelId($objectId);
        $this->factory->getLogger()->addError(print_r($tweet, true));
        $entity = $model->getEntity($tweet['message_id']);
        //set the asset we came from
        $page = $session->get('mautic.message.twitter.page', 1);

        $tmpl      = $this->request->isXmlHttpRequest() ? $this->request->get('tmpl', 'details_twitter') : 'details_twitter';
        $routeVars = [
            'objectAction' => 'view',
            'objectId'     => $entity->getId(),
        ];
        if ($page !== null) {
            $routeVars['listPage'] = $page;
        }
        $route = $this->generateUrl($this->getActionRoute(), $routeVars);
        // Audit Log
        $logs = $this->getModel('core.auditLog')->getLogForObject('tweet', $objectId, $entity->getDateAdded());
        // Init the date range filter form
        $dateRangeValues = $this->request->get('daterange', []);
        $dateRangeForm   = $this->get('form.factory')->create('daterange', $dateRangeValues, ['action' => $route]);
        $dateFrom        = new \DateTime($dateRangeForm['date_from']->getData());
        $dateTo          = new \DateTime($dateRangeForm['date_to']->getData());

        $chart     = new LineChart(null, $dateFrom, $dateTo);
        $leadStats = $model->getLeadStatsPost(
            $dateFrom,
            $dateTo,
            ['message_id' => $tweet['message_id']]
        );
        $chart->setDataset($this->get('translator')->trans('mautic.social.twitter.tweet.count'), $leadStats);

        return $this->delegateView(
            [
                'returnUrl'      => $route,
                'viewParameters' => [
                    'item'          => $entity,
                    'logs'          => $logs,
                    'isEmbedded'    => $this->request->get('isEmbedded') ? $this->request->get('isEmbedded') : false,
                    'tmpl'          => $tmpl,
                    'security'      => $security,
                    'stats'         => $chart->render(),
                    'dateRangeForm' => $dateRangeForm->createView(),
                ],
                'contentTemplate' => 'MauticChannelBundle:Message:details_twitter.html.php',
                'passthroughVars' => [
                    'activeLink'    => '#mautic_message_index',
                    'mauticContent' => 'message',
                ],
            ]
        );
    }
    /**
     * @param $objectId
     *
     * @return \Mautic\CoreBundle\Controller\Response|\Symfony\Component\HttpFoundation\JsonResponse|\Symfony\Component\HttpFoundation\RedirectResponse
     */
    public function cloneAction($objectId)
    {
        return $this->cloneStandard($objectId);
    }

    /**
     * @param $objectId
     *
     * @return \Symfony\Component\HttpFoundation\JsonResponse|\Symfony\Component\HttpFoundation\RedirectResponse
     */
    protected function deleteAction($objectId)
    {
        return $this->deleteStandard($objectId);
    }

    /**
     * @return \Symfony\Component\HttpFoundation\JsonResponse|\Symfony\Component\HttpFoundation\RedirectResponse
     */
    public function batchDeleteAction()
    {
        return $this->batchDeleteStandard();
    }

    /**
     * {@inheritdoc}
     */
    protected function setStandardTemplateBases()
    {
        $this->controllerBase = 'MauticChannelBundle:Message';
    }

    /**
     * {@inheritdoc}
     */
    protected function setStandardSessionBase()
    {
        $this->sessionBase = 'mautic.message';
    }

    /**
     * {@inheritdoc}
     */
    protected function setStandardRoutes()
    {
        $this->routeBase = 'message';
    }

    /**
     * {@inheritdoc}
     */
    protected function setStandardFrontendVariables()
    {
        $this->mauticContent = 'messages';
    }

    /**
     * {@inheritdoc}
     */
    protected function setStandardModelName()
    {
        $this->modelName = 'channel.message';
    }

    /**
     * {@inheritdoc}
     */
    protected function setStandardTranslationBase()
    {
        $this->translationBase = 'mautic.channel.message';
    }
}
