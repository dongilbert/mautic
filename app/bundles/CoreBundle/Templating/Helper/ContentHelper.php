<?php

/*
 * @copyright   2016 Mautic Contributors. All rights reserved
 * @author      Mautic, Inc.
 *
 * @link        https://mautic.org
 *
 * @license     GNU/GPLv3 http://www.gnu.org/licenses/gpl-3.0.html
 */

namespace Mautic\CoreBundle\Templating\Helper;

use Mautic\CoreBundle\CoreEvents;
use Mautic\CoreBundle\Event\CustomContentEvent;
use Symfony\Bundle\FrameworkBundle\Templating\DelegatingEngine;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\Templating\Helper\Helper;

class ContentHelper extends Helper
{
    /**
     * @var DelegatingEngine
     */
    protected $templating;

    /**
     * @var EventDispatcherInterface
     */
    protected $dispatcher;

    /**
     * UIHelper constructor.
     *
     * @param DelegatingEngine         $templating
     * @param EventDispatcherInterface $dispatcher
     */
    public function __construct(DelegatingEngine $templating, EventDispatcherInterface $dispatcher)
    {
        $this->templating = $templating;
        $this->dispatcher = $dispatcher;
    }

    /**
     * Dispatch an event to collect custom content.
     *
     * @param       $viewName The main identifier for the content requested
     * @param       $context  Context of the content requested for the viewName
     * @param array $vars     Templating vars
     *
     * @return string
     */
    public function getCustomContent($viewName, $context = null, array $vars = [])
    {
        // unset $vars that are not allowed by the template
        unset($vars['this'], $vars['view']);

        /** @var ContentEvent $event */
        $event = $this->dispatcher->dispatch(
            CoreEvents::VIEW_INJECT_CUSTOM_CONTENT,
            new CustomContentEvent($viewName, $context, $vars)
        );

        $content = $event->getContent();

        if ($templates = $event->getTemplates()) {
            foreach ($templates as $template => $templateVars) {
                $content[] = $this->templating->render($template, array_merge($vars, $templateVars));
            }
        }

        return implode("\n\n", $content);
    }

    /**
     * @return string
     */
    public function getName()
    {
        return 'content';
    }
}
