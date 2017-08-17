<?php

/*
 * @copyright   2017 Mautic Contributors. All rights reserved
 * @author      Mautic
 *
 * @link        http://mautic.org
 *
 * @license     GNU/GPLv3 http://www.gnu.org/licenses/gpl-3.0.html
 */

namespace MauticPlugin\MauticFocusBundle\Tests\EventListener;

use Mautic\CoreBundle\Security\Permissions\CorePermissions;
use Mautic\PageBundle\Entity\Page;
use Mautic\PageBundle\Event\PageDisplayEvent;
use MauticPlugin\MauticFocusBundle\Entity\Focus;
use MauticPlugin\MauticFocusBundle\EventListener\PageSubscriber;
use MauticPlugin\MauticFocusBundle\Model\FocusModel;
use Symfony\Component\Routing\RouterInterface;

class PageSubscriberTest extends \PHPUnit_Framework_TestCase
{
    public function testOnPageDisplayWithPublished()
    {
        $page = new Page();
        $page->setContent('Some content and now the focus item token. {focus=1}');

        $focus = new Focus();
        $focus->setIsPublished(true);

        $focusModel = $this->getMockBuilder(FocusModel::class)
            ->disableOriginalConstructor()
            ->setMethods(['getEntity'])
            ->getMock();

        $focusModel->expects($this->once())
            ->method('getEntity')
            ->with(1)
            ->willReturn($focus);

        $security = $this->getMockBuilder(CorePermissions::class)
            ->disableOriginalConstructor()
            ->setMethods(['hasEntityAccess'])
            ->getMock();

        $security->expects($this->any())
            ->method('hasEntityAccess')
            ->willReturn(true);

        $router = $this->getMock(RouterInterface::class);

        $router->expects($this->once())
            ->method('generate')
            ->willReturnArgument(0);

        $subscriber = new PageSubscriber($focusModel, $router);
        $subscriber->setSecurity($security);

        $event = new PageDisplayEvent($page->getContent(), $page);

        $subscriber->onPageDisplay($event);

        $pageContent = $event->getContent();

        $this->assertContains(
            '<script src="mautic_focus_generate" type="text/javascript" charset="utf-8" async="async"></script>',
            $pageContent,
            'Focus script tag should be found in the page content.'
        );

        $this->assertNotContains(
            '{focus=1}',
            $pageContent,
            'Focus token should be replaced out of the page content.'
        );
    }
    public function testOnPageDisplayWithUnPublished()
    {
        $page = new Page();
        $page->setContent('Some content and now the focus item token. {focus=1}');

        $focus = new Focus();
        $focus->setIsPublished(false);

        $focusModel = $this->getMockBuilder(FocusModel::class)
            ->disableOriginalConstructor()
            ->setMethods(['getEntity'])
            ->getMock();

        $focusModel->expects($this->once())
            ->method('getEntity')
            ->with(1)
            ->willReturn($focus);

        $security = $this->getMockBuilder(CorePermissions::class)
            ->disableOriginalConstructor()
            ->setMethods(['hasEntityAccess'])
            ->getMock();

        $security->expects($this->any())
            ->method('hasEntityAccess')
            ->willReturn(false);

        $router = $this->getMock(RouterInterface::class);

        $router->expects($this->never())
            ->method('generate')
            ->willReturnArgument(0);

        $subscriber = new PageSubscriber($focusModel, $router);
        $subscriber->setSecurity($security);

        $event = new PageDisplayEvent($page->getContent(), $page);

        $subscriber->onPageDisplay($event);

        $pageContent = $event->getContent();

        $this->assertNotContains(
            '<script src="mautic_focus_generate" type="text/javascript" charset="utf-8" async="async"></script>',
            $pageContent,
            'Focus script tag should not be found in the page content when the focus item is unpublished and it is not being viewed by an admin user.'
        );

        $this->assertNotContains(
            '{focus=1}',
            $pageContent,
            'Focus token should be replaced out of the page content when unpublished and it is not being viewed by an admin user.'
        );
    }
}
