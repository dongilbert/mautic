<?php

/*
 * @copyright   2014 Mautic Contributors. All rights reserved
 * @author      Mautic
 *
 * @link        http://mautic.org
 *
 * @license     GNU/GPLv3 http://www.gnu.org/licenses/gpl-3.0.html
 */

namespace Mautic\CoreBundle\Controller;

/**
 * Class FormController.
 *
 * @deprecated 2.3 - to be removed in 3.0; use AbstractFormController instead
 */
class FormController extends AbstractStandardFormController
{
    /**
     * @deprecated 2.3 - to be removed in 3.0; extend AbstractStandardFormController instead
     *
     * @param string $modelName      The model for this controller
     * @param string $permissionBase Permission base for the model (i.e. form.forms or addon.yourAddon.items)
     * @param string $routeBase      Route base for the controller routes (i.e. mautic_form or custom_addon)
     * @param string $sessionBase    Session name base for items saved to session such as filters, page, etc
     * @param string $langStringBase Language string base for the shared strings
     * @param string $templateBase   Template base (i.e. YourController:Default) for the view/controller
     * @param string $activeLink     Link ID to return via ajax response
     * @param string $mauticContent  Mautic content string to return via ajax response for onLoad functions
     * @param string $controllerBase Controller base in case $templateBase is something different for post action redirect
     */
    protected function setStandardParameters(
        $modelName,
        $permissionBase,
        $routeBase,
        $sessionBase,
        $translationBase,
        $templateBase = null,
        $activeLink = null,
        $mauticContent = null
    ) {
        $this->modelName      = $modelName;
        $this->permissionBase = $permissionBase;
        if (strpos($sessionBase, 'mautic.') !== 0) {
            $sessionBase = 'mautic.'.$sessionBase;
        }
        $this->sessionBase     = $sessionBase;
        $this->routeBase       = $routeBase;
        $this->translationBase = $translationBase;
        $this->activeLink      = $activeLink;
        $this->mauticContent   = $mauticContent;

        if (null !== $templateBase) {
            $this->templateBase = $templateBase;
        }

        if (null === $this->controllerBase) {
            $this->controllerBase = $this->templateBase;
        }
    }

    protected function setStandardModelName()
    {
        // ignore - for BC only
    }

    protected function setStandardFrontendVariables()
    {
        // ignore - for BC only
    }

    protected function setStandardRoutes()
    {
        // ignore - for BC only
    }

    protected function setStandardSessionBase()
    {
        // ignore - for BC only
    }

    protected function setStandardTemplateBases()
    {
        // ignore - for BC only
    }

    protected function setStandardTranslationBase()
    {
        // ignore - for BC only
    }

    /**
     * Deletes a group of entities.
     *
     * @return \Symfony\Component\HttpFoundation\JsonResponse|\Symfony\Component\HttpFoundation\RedirectResponse
     */
    protected function batchDeleteStandard(array $viewParameters = [])
    {
        $page      = $this->get('session')->get($this->sessionBase.'.page', 1);
        $returnUrl = $this->generateUrl($this->routeBase.'_index', ['page' => $page]);
        $flashes   = [];

        $postActionVars = [
            'returnUrl'       => $returnUrl,
            'viewParameters'  => array_merge(['page' => $page], $viewParameters),
            'contentTemplate' => $this->templateBase.':index',
            'passthroughVars' => [
                'activeLink'    => $this->activeLink,
                'mauticContent' => $this->mauticContent,
            ],
        ];

        if ($this->request->getMethod() == 'POST') {
            $model     = $this->getModel($this->modelName);
            $ids       = json_decode($this->request->query->get('ids', ''));
            $deleteIds = [];

            // Loop over the IDs to perform access checks pre-delete
            foreach ($ids as $objectId) {
                $entity = $model->getEntity($objectId);

                if ($entity === null) {
                    $flashes[] = [
                        'type'    => 'error',
                        'msg'     => $this->langStringBase.'.error.notfound',
                        'msgVars' => ['%id%' => $objectId],
                    ];
                } elseif (!$this->get('mautic.security')->hasEntityAccess(
                    $this->permissionBase.':deleteown',
                    $this->permissionBase.':deleteother',
                    $entity->getCreatedBy()
                )
                ) {
                    $flashes[] = $this->accessDenied(true);
                } elseif ($model->isLocked($entity)) {
                    $flashes[] = $this->isLocked($postActionVars, $entity, $this->modelName, true);
                } else {
                    $deleteIds[] = $objectId;
                }
            }

            // Delete everything we are able to
            if (!empty($deleteIds)) {
                $entities = $model->deleteEntities($deleteIds);

                $flashes[] = [
                    'type'    => 'notice',
                    'msg'     => $this->langStringBase.'.notice.batch_deleted',
                    'msgVars' => [
                        '%count%' => count($entities),
                    ],
                ];
            }
        } //else don't do anything

        return $this->postActionRedirect(
            array_merge(
                $postActionVars,
                [
                    'flashes' => $flashes,
                ]
            )
        );
    }
}
