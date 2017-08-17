<?php

/*
 * @copyright   2017 Mautic Contributors. All rights reserved
 * @author      Mautic
 *
 * @link        http://mautic.org
 *
 * @license     GNU/GPLv3 http://www.gnu.org/licenses/gpl-3.0.html
 */

namespace Mautic\PageBundle\Test;

use Mautic\CoreBundle\Helper\TemplatingHelper;
use Mautic\PageBundle\Entity\Page;
use Mautic\PageBundle\Event\PageDisplayEvent;
use Mautic\PageBundle\EventListener\BuilderSubscriber;
use Mautic\PageBundle\Helper\TokenHelper;
use Mautic\PageBundle\Model\PageModel;
use Mautic\PluginBundle\Helper\IntegrationHelper;
use Symfony\Component\Templating\EngineInterface;

class BuilderSubscriberTest extends \PHPUnit_Framework_TestCase
{
    public function testOnPageDisplay()
    {
        $page = new Page();
        $page->setContent($defaultPageContent = <<<'PAGECONTENT'
<html>
<head>
<meta name="description" content="{pagemetadescription}" />
<title>{pagetitle}</title>
</head>
<body>
{langbar}
{sharebuttons}
{pagelink=1}
</body>
</html>
PAGECONTENT
);
        $page->setMetaDescription('Let\'s be honest, who reads this...');
        $page->setTitle('My Awesome Page Title!!');

        $integrationHelper = $this->getMockBuilder(IntegrationHelper::class)
            ->disableOriginalConstructor()
            ->setMethods(['getShareButtons'])
            ->getMock();

        $integrationHelper->expects($this->any())
            ->method('getShareButtons')
            ->willReturn(['facebook']);

        $pageModel = $this->getMockBuilder(PageModel::class)
            ->disableOriginalConstructor()
            ->getMock();

        $tokenHelper = $this->getMockBuilder(TokenHelper::class)
            ->disableOriginalConstructor()
            ->setMethods(['findPageTokens'])
            ->getMock();

        $tokenHelper->expects($this->once())
            ->method('findPageTokens')
            ->willReturn(['{pagelink=1}' => '/url-to-page-1']);

        $templating = $this->getMock(EngineInterface::class);

        $templating->expects($this->once())
            ->method('render')
            ->with('MauticPageBundle:SubscribedEvents\PageToken:sharebtn_css.html.php')
            ->willReturn(null);

        $templatingHelper = $this->getMockBuilder(TemplatingHelper::class)
            ->disableOriginalConstructor()
            ->setMethods(['getTemplating'])
            ->getMock();

        $templatingHelper->expects($this->once())
            ->method('getTemplating')
            ->willReturn($templating);

        $subscriber = $this->getMockBuilder(BuilderSubscriber::class)
            ->setConstructorArgs([
                $tokenHelper,
                $integrationHelper,
                $pageModel,
            ])
            ->setMethods(['renderLanguageBar'])
            ->getMock();

        $subscriber->setTemplating($templatingHelper);

        $subscriber->expects($this->once())
            ->method('renderLanguageBar')
            ->willReturn('<div>Language bar HTML</div>');

        $event = new PageDisplayEvent($page->getContent(), $page);

        $subscriber->onPageDisplay($event);

        $pageContent = $event->getContent();

        $this->assertContains(
            '<meta name="description" content="Let\'s be honest, who reads this..." />',
            $pageContent,
            'Meta description is replaced in page content.'
        );

        $this->assertNotContains(
            '{pagemetadescription}',
            $pageContent,
            'Meta description token should be removed from content.'
        );

        $this->assertContains(
            '<title>My Awesome Page Title!!</title>',
            $pageContent,
            'Page title should be found in the page content.'
        );

        $this->assertNotContains(
            '{pagetitle}',
            $pageContent,
            'Page title token should be removed from content.'
        );

        $this->assertContains(
            '<div>Language bar HTML</div>',
            $pageContent,
            'Language bar html should be found in the page content.'
        );

        $this->assertNotContains(
            '{langbar}',
            $pageContent,
            'Language bar token should be removed from content.'
        );

        $this->assertContains(
            "<div class='share-buttons'>\nfacebook</div>\n",
            $pageContent,
            'Share button html should be found in the page content.'
        );

        $this->assertNotContains(
            '{sharebuttons}',
            $pageContent,
            'Share buttons token should be removed from content.'
        );

        $this->assertContains(
            '/url-to-page-1',
            $pageContent,
            'Page link html should be found in the page content.'
        );

        $this->assertNotContains(
            '{pagelink=1}',
            $pageContent,
            'Page link token should be removed from content.'
        );
    }
}
