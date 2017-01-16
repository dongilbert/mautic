<?php

/*
 * @copyright   2016 Mautic Contributors. All rights reserved
 * @author      Mautic, Inc.
 *
 * @link        https://mautic.org
 *
 * @license     GNU/GPLv3 http://www.gnu.org/licenses/gpl-3.0.html
 */

namespace Mautic\CoreBundle\Controller;

use Mautic\CoreBundle\Model\FormModel;

/**
 * abstract StandardFormControllerInterface.
 */
abstract class AbstractStandardFormController extends AbstractFormController
{
    protected $modelName;
    protected $routeBase;
    protected $actionRoute;
    protected $indexRoute;
    protected $sessionBase;
    protected $translationBase;
    protected $mauticContent;
    protected $controllerBase;
    protected $templateBase = 'MauticCoreBundle:Standard';
    protected $template;

    /**
     * AbstractStandardFormController constructor.
     *
     * Force setup of required variables for the standard methods
     */
    public function __construct()
    {
        $this->setStandardModelName();
        $this->setStandardRoutes();
        $this->setStandardSessionBase();
        $this->setStandardTranslationBase();
        $this->setStandardTemplateBases();
        $this->setStandardFrontendVariables();
    }

    /**
     * Set $this->modelName to this controller's model.
     */
    abstract protected function setStandardModelName();

    /**
     * Set $this->routeBase if it meets the $this->getIndexRoute() and $this->getActionRoute() standard
     * Or set $this->actionRoute and/or $this->indexRoute if not standard.
     */
    abstract protected function setStandardRoutes();

    /**
     * Set $this->sessionBase to be used in filters, pagination, etc.
     */
    abstract protected function setStandardSessionBase();

    /**
     * Set $this->translationBase to be used in views.
     */
    abstract protected function setStandardTranslationBase();

    /**
     * Set $this->templateBase if different than MauticCoreBundle:Standard and/or set $this->controllerBase if using MauticCoreBundle:Standard views.
     */
    abstract protected function setStandardTemplateBases();

    /**
     * Set $this->mauticContent to be used in passthroughVars for ajax responses.
     */
    abstract protected function setStandardFrontendVariables();

    /**
     * @param int    $id
     * @param string $modelName
     * @param int    $page
     *
     * @return \Symfony\Component\HttpFoundation\JsonResponse|\Symfony\Component\HttpFoundation\RedirectResponse|Response
     */
    protected function indexStandard($page = 1)
    {
        //set some permissions
        $permissions = $this->get('mautic.security')->isGranted(
            [
                $this->getPermissionBase().':view',
                $this->getPermissionBase().':viewown',
                $this->getPermissionBase().':viewother',
                $this->getPermissionBase().':create',
                $this->getPermissionBase().':edit',
                $this->getPermissionBase().':editown',
                $this->getPermissionBase().':editother',
                $this->getPermissionBase().':delete',
                $this->getPermissionBase().':deleteown',
                $this->getPermissionBase().':deleteother',
                $this->getPermissionBase().':publish',
                $this->getPermissionBase().':publishown',
                $this->getPermissionBase().':publishother',
            ],
            'RETURN_ARRAY',
            null,
            true
        );

        if (!$permissions[$this->getPermissionBase().':viewown'] && !$permissions[$this->getPermissionBase().':viewother']) {
            return $this->accessDenied();
        }

        if ($this->request->getMethod() == 'POST') {
            $this->setListFilters();
        }

        $session = $this->get('session');

        //set limits
        $limit = $session->get($this->sessionBase.'.limit', $this->coreParametersHelper->getParameter('default_pagelimit'));
        $start = ($page === 1) ? 0 : (($page - 1) * $limit);
        if ($start < 0) {
            $start = 0;
        }

        $search = $this->request->get('search', $session->get($this->sessionBase.'.filter', ''));
        $session->set($this->sessionBase.'.filter', $search);

        $filter = ['string' => $search, 'force' => []];

        $model = $this->getModel($this->modelName);
        $repo  = $model->getRepository();

        if (!$permissions[$this->getPermissionBase().':viewother']) {
            $filter['force'] = ['column' => $repo->getTableAlias().'.createdBy', 'expr' => 'eq', 'value' => $this->user->getId()];
        }

        $orderBy    = $session->get($this->sessionBase.'.orderby', $repo->getTableAlias().'.name');
        $orderByDir = $session->get($this->sessionBase.'.orderbydir', 'ASC');

        $items = $model->getEntities(
            [
                'start'      => $start,
                'limit'      => $limit,
                'filter'     => $filter,
                'orderBy'    => $orderBy,
                'orderByDir' => $orderByDir,
            ]
        );

        $count = count($items);

        if ($count && $count < ($start + 1)) {
            //the number of entities are now less then the current page so redirect to the last page
            $lastPage = ($count === 1) ? 1 : ((ceil($count / $limit)) ?: 1) ?: 1;

            $session->set($this->sessionBase.'.page', $lastPage);
            $returnUrl = $this->generateUrl($this->getIndexRoute(), ['page' => $lastPage]);

            return $this->postActionRedirect(
                [
                    'returnUrl'       => $returnUrl,
                    'viewParameters'  => ['page' => $lastPage],
                    'contentTemplate' => $this->controllerBase.':index',
                    'passthroughVars' => [
                        'mauticContent' => $this->mauticContent,
                    ],
                ]
            );
        }

        //set what page currently on so that we can return here after form submission/cancellation
        $session->set($this->sessionBase.'.page', $page);

        $viewParameters = [
            'permissionBase'  => $this->getPermissionBase(),
            'mauticContent'   => $this->mauticContent,
            'actionRoute'     => $this->getActionRoute(),
            'indexRoute'      => $this->getIndexRoute(),
            'tablePrefix'     => $model->getRepository()->getTableAlias(),
            'modelName'       => $this->modelName,
            'translationBase' => $this->translationBase,
            'searchValue'     => $search,
            'items'           => $items,
            'totalItems'      => $count,
            'page'            => $page,
            'limit'           => $limit,
            'permissions'     => $permissions,
            'security'        => $this->get('mautic.security'),
            'tmpl'            => $this->request->get('tmpl', 'index'),
        ];

        $delegateArgs = [
            'viewParameters'  => $viewParameters,
            'contentTemplate' => $this->getStandardTemplate('list.html.php'),
            'passthroughVars' => [
                'mauticContent' => $this->mauticContent,
                'route'         => $this->generateUrl($this->getIndexRoute(), ['page' => $page]),
            ],
        ];

        // Allow inherited class to adjust
        if (method_exists($this, 'customizeViewArguments')) {
            $delegateArgs = $this->customizeViewArguments($delegateArgs, 'index');
        }

        return $this->delegateView($delegateArgs);
    }

    /**
     * Individual item's details page.
     *
     * @param      $objectId
     * @param null $logObject
     * @param null $logBundle
     * @param null $listPage
     *
     * @return array|\Symfony\Component\HttpFoundation\JsonResponse|\Symfony\Component\HttpFoundation\RedirectResponse|\Symfony\Component\HttpFoundation\Response
     */
    protected function viewStandard($objectId, $logObject = null, $logBundle = null, $listPage = null)
    {
        $model    = $this->getModel($this->modelName);
        $entity   = $model->getEntity($objectId);
        $security = $this->get('mautic.security');

        if ($entity === null) {
            $page = $this->get('session')->get($this->sessionBase.'.page', 1);

            return $this->postActionRedirect(
                [
                    'returnUrl'       => $this->generateUrl($this->getIndexRoute(), ['page' => $page]),
                    'viewParameters'  => ['page' => $page],
                    'contentTemplate' => $this->controllerBase.':index',
                    'passthroughVars' => [
                        'mauticContent' => $this->mauticContent,
                    ],
                    'flashes' => [
                        [
                            'type'    => 'error',
                            'msg'     => $this->getTranslatedString('error.notfound'),
                            'msgVars' => ['%id%' => $objectId],
                        ],
                    ],
                ]
            );
        } elseif (!$security->hasEntityAccess(
            $this->getPermissionBase().':viewown',
            $this->getPermissionBase().':viewother',
            $entity->getCreatedBy()
        )
        ) {
            return $this->accessDenied();
        }

        // Set filters
        if ($this->request->getMethod() == 'POST') {
            $this->setListFilters();
        }

        // Audit log entries
        $logs = ($logObject) ? $this->getModel('core.auditLog')->getLogForObject($logObject, $objectId, $entity->getDateAdded(), 10, $logBundle) : [];

        // Generate route
        $routeVars = [
            'objectAction' => 'view',
            'objectId'     => $entity->getId(),
        ];
        if ($listPage !== null) {
            $routeVars['listPage'] = $listPage;
        }
        $route = $this->generateUrl($this->getActionRoute(), $routeVars);

        $delegateArgs = [
            'viewParameters' => [
                'item'        => $entity,
                'logs'        => $logs,
                'tmpl'        => $this->request->isXmlHttpRequest() ? $this->request->get('tmpl', 'index') : 'index',
                'permissions' => $security->isGranted(
                    [
                        $this->getPermissionBase().':view',
                        $this->getPermissionBase().':viewown',
                        $this->getPermissionBase().':viewother',
                        $this->getPermissionBase().':create',
                        $this->getPermissionBase().':edit',
                        $this->getPermissionBase().':editown',
                        $this->getPermissionBase().':editother',
                        $this->getPermissionBase().':delete',
                        $this->getPermissionBase().':deleteown',
                        $this->getPermissionBase().':deleteother',
                        $this->getPermissionBase().':publish',
                        $this->getPermissionBase().':publishown',
                        $this->getPermissionBase().':publishother',
                    ],
                    'RETURN_ARRAY',
                    null,
                    true
                ),
            ],
            'contentTemplate' => $this->getStandardTemplate('details.html.php'),
            'passthroughVars' => [
                'mauticContent' => $this->mauticContent,
                'route'         => $route,
            ],
        ];

        // Allow inherited class to adjust
        if (method_exists($this, 'customizeViewArguments')) {
            $delegateArgs = $this->customizeViewArguments($delegateArgs, 'view');
        }

        return $this->delegateView($delegateArgs);
    }

    /**
     * Generates new form and processes post data.
     *
     * @return \Symfony\Component\HttpFoundation\JsonResponse|Response
     */
    protected function newStandard()
    {
        $model  = $this->getModel($this->modelName);
        $entity = $model->getEntity();

        if (!$this->get('mautic.security')->isGranted($this->getPermissionBase().':create')) {
            return $this->accessDenied();
        }

        $model = $this->getModel($this->modelName);
        if (!$model instanceof FormModel) {
            throw new \Exception(get_class($model).' must extend '.FormModel::class);
        }

        //set the page we came from
        $page = $this->get('session')->get($this->sessionBase.'.page', 1);

        $action = $this->generateUrl($this->getActionRoute(), ['objectAction' => 'new']);
        $form   = $model->createForm($entity, $this->get('form.factory'), $action);

        ///Check for a submitted form and process it
        if ($this->request->getMethod() == 'POST') {
            $valid = false;
            if (!$cancelled = $this->isFormCancelled($form)) {
                if ($valid = $this->isFormValid($form)) {
                    // Allow inherited class to adjust
                    if (method_exists($this, 'beforeSaveEntity')) {
                        $valid = $this->beforeSaveEntity($entity, $form, 'new');
                    }

                    if ($valid) {
                        $model->saveEntity($entity);

                        // Allow inherited class to adjust
                        if (method_exists($this, 'afterSaveEntity')) {
                            $this->afterSaveEntity($entity, $form, 'new');
                        }

                        if (method_exists($this, 'viewAction')) {
                            $viewParameters = ['objectId' => $entity->getId(), 'objectAction' => 'view'];
                            $returnUrl      = $this->generateUrl($this->getActionRoute(), $viewParameters);
                            $template       = $this->controllerBase.':view';
                        } else {
                            $viewParameters = ['page' => $page];
                            $returnUrl      = $this->generateUrl($this->getIndexRoute(), $viewParameters);
                            $template       = $this->controllerBase.':index';
                        }
                    }
                }
            } else {
                $viewParameters = ['page' => $page];
                $returnUrl      = $this->generateUrl($this->getIndexRoute(), $viewParameters);
                $template       = $this->controllerBase.':index';
            }

            if ($cancelled || ($valid && !$this->isFormApplied($form))) {
                return $this->postActionRedirect(
                    [
                        'returnUrl'       => $returnUrl,
                        'viewParameters'  => $viewParameters,
                        'contentTemplate' => $template,
                        'passthroughVars' => [
                            'mauticContent' => $this->mauticContent,
                        ],
                    ]
                );
            } elseif ($valid && $this->isFormApplied($form)) {
                return $this->editAction($entity->getId(), true);
            }
        }

        $delegateArgs = [
            'viewParameters' => [
                'permissionBase'  => $this->getPermissionBase(),
                'mauticContent'   => $this->mauticContent,
                'actionRoute'     => $this->getActionRoute(),
                'indexRoute'      => $this->getIndexRoute(),
                'tablePrefix'     => $model->getRepository()->getTableAlias(),
                'modelName'       => $this->modelName,
                'translationBase' => $this->translationBase,
                'tmpl'            => $this->request->isXmlHttpRequest() ? $this->request->get('tmpl', 'index') : 'index',
                'entity'          => $entity,
                'form'            => (method_exists($this, 'getStandardFormView')) ?
                    $this->getStandardFormView($form, 'new') :
                    $form->createView(),
            ],
            'contentTemplate' => $this->getStandardTemplate('form.html.php'),
            'passthroughVars' => [
                'mauticContent' => $this->mauticContent,
                'route'         => $this->generateUrl(
                    $this->getActionRoute(),
                    [
                        'objectAction' => (!empty($valid) ? 'edit' : 'new'), //valid means a new form was applied
                        'objectId'     => ($entity) ? $entity->getId() : 0,
                    ]
                ),
            ],
        ];

        // Allow inherited class to adjust
        if (method_exists($this, 'customizeViewArguments')) {
            $delegateArgs = $this->customizeViewArguments($delegateArgs, 'new');
        }

        return $this->delegateView($delegateArgs);
    }

    /**
     * Generates edit form and processes post data.
     *
     * @param int  $objectId
     * @param bool $ignorePost
     *
     * @return \Symfony\Component\HttpFoundation\JsonResponse|Response
     */
    protected function editStandard($objectId, $ignorePost = false)
    {
        $isClone = false;
        $model   = $this->getModel($this->modelName);
        if (!$model instanceof FormModel) {
            throw new \Exception(get_class($model).' must extend '.FormModel::class);
        }

        if (is_object($objectId)) {
            $entity   = $objectId;
            $isClone  = true;
            $objectId = 'mautic_'.sha1(uniqid(mt_rand(), true));
        } elseif (strpos($objectId, 'mautic_') !== false) {
            $isClone = true;
            $entity  = $model->getEntity();
        } else {
            $entity = $model->getEntity($objectId);
        }

        //set the page we came from
        $page = $this->get('session')->get($this->sessionBase.'.page', 1);

        //set the return URL
        $returnUrl = $this->generateUrl($this->getIndexRoute(), ['page' => $page]);

        $viewParameters = ['page' => $page];

        $this->template = $this->controllerBase.':index';

        $postActionVars = [
            'returnUrl'       => $returnUrl,
            'viewParameters'  => $viewParameters,
            'contentTemplate' => $this->template,
            'passthroughVars' => [
                'mauticContent' => $this->mauticContent,
            ],
        ];

        //form not found
        if ($entity === null) {
            return $this->postActionRedirect(
                array_merge(
                    $postActionVars,
                    [
                        'flashes' => [
                            [
                                'type'    => 'error',
                                'msg'     => $this->getTranslatedString('error.notfound'),
                                'msgVars' => ['%id%' => $objectId],
                            ],
                        ],
                    ]
                )
            );
        } elseif (!$this->get('mautic.security')->hasEntityAccess(
            $this->getPermissionBase().':editown',
            $this->getPermissionBase().':editother',
            $entity->getCreatedBy()
        )
        ) {
            return $this->accessDenied();
        } elseif (!$isClone && $model->isLocked($entity)) {
            //deny access if the entity is locked
            return $this->isLocked($postActionVars, $entity, $this->modelName);
        }

        $action = $this->generateUrl($this->getActionRoute(), ['objectAction' => 'edit', 'objectId' => $objectId]);
        $form   = $model->createForm($entity, $this->get('form.factory'), $action);

        ///Check for a submitted form and process it
        if (!$ignorePost && $this->request->getMethod() == 'POST') {
            $valid = false;
            if (!$cancelled = $this->isFormCancelled($form)) {
                if ($valid = $this->isFormValid($form)) {
                    // Allow inherited class to adjust
                    if (method_exists($this, 'beforeSaveEntity')) {
                        $valid = $this->beforeSaveEntity($entity, $form, 'new');
                    }

                    if ($valid) {
                        $model->saveEntity($entity, $form->get('buttons')->get('save')->isClicked());

                        // Allow inherited class to adjust
                        if (method_exists($this, 'afterSaveEntity')) {
                            $this->afterSaveEntity($entity, $form, 'new');
                        }

                        $this->addFlash(
                            'mautic.core.notice.updated',
                            [
                                '%name%'      => $entity->getName(),
                                '%menu_link%' => $this->getIndexRoute(),
                                '%url%'       => $this->generateUrl(
                                    $this->getActionRoute(),
                                    [
                                        'objectAction' => 'edit',
                                        'objectId'     => $entity->getId(),
                                    ]
                                ),
                            ]
                        );

                        if (!$this->isFormApplied($form) && method_exists($this, 'viewAction')) {
                            $viewParameters = ['objectId' => $entity->getId(), 'objectAction' => 'view'];
                            $returnUrl      = $this->generateUrl($this->getActionRoute(), $viewParameters);
                            $template       = $this->controllerBase.':view';
                        }
                    }
                }
            } else {
                if (!$isClone) {
                    //unlock the entity
                    $model->unlockEntity($entity);
                }

                $viewParameters = ['page' => $page];
                $returnUrl      = $this->generateUrl($this->getIndexRoute(), $viewParameters);
                $template       = $this->controllerBase.':index';
            }

            if ($cancelled || ($valid && !$this->isFormApplied($form))) {
                return $this->postActionRedirect(
                    array_merge(
                        $postActionVars,
                        [
                            'returnUrl'       => $returnUrl,
                            'viewParameters'  => $viewParameters,
                            'contentTemplate' => $this->template,
                        ]
                    )
                );
            } elseif ($valid) {
                // Rebuild the form with new action so that apply doesn't keep creating a clone
                $action = $this->generateUrl($this->getActionRoute(), ['objectAction' => 'edit', 'objectId' => $entity->getId()]);
                $form   = $model->createForm($entity, $this->get('form.factory'), $action);
            }
        } elseif (!$isClone) {
            $model->lockEntity($entity);
        }

        $delegateArgs = [
            'viewParameters' => [
                'permissionBase'  => $this->getPermissionBase(),
                'mauticContent'   => $this->mauticContent,
                'actionRoute'     => $this->getActionRoute(),
                'indexRoute'      => $this->getIndexRoute(),
                'tablePrefix'     => $model->getRepository()->getTableAlias(),
                'modelName'       => $this->modelName,
                'translationBase' => $this->translationBase,
                'tmpl'            => $this->request->isXmlHttpRequest() ? $this->request->get('tmpl', 'index') : 'index',
                'entity'          => $entity,
                'form'            => (method_exists($this, 'getStandardFormView')) ?
                    $this->getStandardFormView($form, 'edit') :
                    $form->createView(),
            ],
            'contentTemplate' => $this->getStandardTemplate('form.html.php'),
            'passthroughVars' => [
                'mauticContent' => $this->mauticContent,
                'route'         => $this->generateUrl(
                    $this->getActionRoute(),
                    [
                        'objectAction' => 'edit',
                        'objectId'     => $entity->getId(),
                    ]
                ),
            ],
        ];

        // Allow inherited class to adjust
        if (method_exists($this, 'customizeViewArguments')) {
            $delegateArgs = $this->customizeViewArguments($delegateArgs, 'edit');
        }

        return $this->delegateView($delegateArgs);
    }

    /**
     * Clone an entity.
     *
     * @param int $objectId
     *
     * @return \Symfony\Component\HttpFoundation\JsonResponse|\Symfony\Component\HttpFoundation\RedirectResponse|Response
     */
    protected function cloneStandard($objectId)
    {
        $model  = $this->getModel($this->modelName);
        $entity = $model->getEntity($objectId);

        if ($entity != null) {
            if (!$this->get('mautic.security')->isGranted($this->getPermissionBase().':create')
                || !$this->get('mautic.security')->hasEntityAccess(
                    $this->getPermissionBase().':viewown',
                    $this->getPermissionBase().':viewother',
                    $entity->getCreatedBy()
                )
            ) {
                return $this->accessDenied();
            }

            $newEntity = clone $entity;
            if (method_exists($newEntity, 'setIsPublished')) {
                $newEntity->setIsPublished(false);
            }

            // Allow inherited class to adjust
            if (method_exists($this, 'afterCloneEntity')) {
                $this->afterCloneEntity($newEntity, $entity);
            }

            return $this->editAction($newEntity, true, true);
        }

        return $this->editAction($objectId, true, true);
    }

    /**
     * Deletes the entity.
     *
     * @param int $objectId
     *
     * @return \Symfony\Component\HttpFoundation\JsonResponse|\Symfony\Component\HttpFoundation\RedirectResponse
     */
    protected function deleteStandard($objectId)
    {
        $page      = $this->get('session')->get($this->sessionBase.'.page', 1);
        $returnUrl = $this->generateUrl($this->getIndexRoute(), ['page' => $page]);
        $flashes   = [];

        $postActionVars = [
            'returnUrl'       => $returnUrl,
            'viewParameters'  => ['page' => $page],
            'contentTemplate' => $this->controllerBase.':index',
            'passthroughVars' => [
                'mauticContent' => $this->mauticContent,
            ],
        ];

        if ($this->request->getMethod() == 'POST') {
            $model  = $this->getModel($this->modelName);
            $entity = $model->getEntity($objectId);

            if ($entity === null) {
                $flashes[] = [
                    'type'    => 'error',
                    'msg'     => $this->getTranslatedString('error.notfound'),
                    'msgVars' => ['%id%' => $objectId],
                ];
            } elseif (!$this->get('mautic.security')->hasEntityAccess(
                $this->getPermissionBase().':deleteown',
                $this->getPermissionBase().':deleteother',
                $entity->getCreatedBy()
            )
            ) {
                return $this->accessDenied();
            } elseif ($model->isLocked($entity)) {
                return $this->isLocked($postActionVars, $entity, $this->modelName);
            }

            $model->deleteEntity($entity);

            $identifier = $this->get('translator')->trans($entity->getName());
            $flashes[]  = [
                'type'    => 'notice',
                'msg'     => 'mautic.core.notice.deleted',
                'msgVars' => [
                    '%name%' => $identifier,
                    '%id%'   => $objectId,
                ],
            ];
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

    /**
     * Deletes a group of entities.
     *
     * @return \Symfony\Component\HttpFoundation\JsonResponse|\Symfony\Component\HttpFoundation\RedirectResponse
     */
    protected function batchDeleteStandard()
    {
        $page      = $this->get('session')->get($this->sessionBase.'.page', 1);
        $returnUrl = $this->generateUrl($this->getIndexRoute(), ['page' => $page]);
        $flashes   = [];

        $postActionVars = [
            'returnUrl'       => $returnUrl,
            'viewParameters'  => ['page' => $page],
            'contentTemplate' => $this->controllerBase.':index',
            'passthroughVars' => [
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
                        'msg'     => $this->getTranslatedString('error.notfound'),
                        'msgVars' => ['%id%' => $objectId],
                    ];
                } elseif (!$this->get('mautic.security')->hasEntityAccess(
                    $this->getPermissionBase().':deleteown',
                    $this->getPermissionBase().':deleteother',
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
                    'msg'     => $this->getTranslatedString('notice.batch_deleted'),
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

    /**
     * Get the template file.
     *
     * @param $file
     *
     * @return string
     */
    protected function getStandardTemplate($file)
    {
        if ($this->get('templating')->exists($this->controllerBase.':'.$file)) {
            return $this->controllerBase.':'.$file;
        } elseif ($this->get('templating')->exists($this->templateBase.':'.$file)) {
            return $this->templateBase.':'.$file;
        } else {
            return 'MauticCoreBundle:Standard:'.$file;
        }
    }

    /**
     * Get the permission base from the model.
     *
     * @return string
     */
    protected function getPermissionBase()
    {
        return (!empty($this->permissionBase)) ? $this->permissionBase : $this->getModel($this->modelName)->getPermissionBase();
    }

    /**
     * Get index route.
     *
     * @return string
     */
    protected function getIndexRoute()
    {
        return (!empty($this->indexRoute)) ? $this->indexRoute : 'mautic_'.str_replace('mautic_', '', $this->routeBase).'_index';
    }

    /**
     * Get action route.
     *
     * @return string
     */
    protected function getActionRoute()
    {
        return (!empty($this->actionRoute)) ? $this->actionRoute : 'mautic_'.str_replace('mautic_', '', $this->routeBase).'_action';
    }

    /**
     * Get custom or core translation.
     *
     * @param $string
     *
     * @return string
     */
    protected function getTranslatedString($string)
    {
        return $this->get('translator')->hasId($this->translationBase.'.'.$string) ? $this->translationBase
            .'.'.$string : 'mautic.core.'.$string;
    }
}
