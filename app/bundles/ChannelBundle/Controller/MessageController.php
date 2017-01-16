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
use Symfony\Component\Form\Form;

/**
 * Class MessageController.
 */
class MessageController extends AbstractStandardFormController
{
    /**
     * @param        $objectAction
     * @param int    $objectId
     * @param string $objectModel
     *
     * @return JsonResponse|\Symfony\Component\HttpFoundation\RedirectResponse
     */
    public function executeAction($objectAction, $objectId = 0, $objectSubId = 0, $objectModel = '')
    {
        if (method_exists($this, "{$objectAction}Action")) {
            return $this->{"{$objectAction}Action"}($objectId);
        } else {
            return $this->accessDenied();
        }
    }
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
            case 'view':
                $viewParameters = [
                    'channels'         => $model->getChannels(),
                    'channel_contents' => $model->getChannelMessages($args['viewParameters']['item']->getId()),
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
     * @param      $objectId
     * @param bool $ignorePost
     *
     * @return \Mautic\CoreBundle\Controller\Response|\Symfony\Component\HttpFoundation\JsonResponse
     */
    public function viewAction($objectId)
    {
        return $this->viewStandard($objectId);
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
