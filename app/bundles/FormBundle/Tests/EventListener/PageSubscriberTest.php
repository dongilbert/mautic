<?php

/*
 * @copyright   2017 Mautic Contributors. All rights reserved
 * @author      Mautic
 *
 * @link        http://mautic.org
 *
 * @license     GNU/GPLv3 http://www.gnu.org/licenses/gpl-3.0.html
 */

namespace Mautic\FormBundle\Test;

use Mautic\CoreBundle\Security\Permissions\CorePermissions;
use Mautic\CoreBundle\Translation\Translator;
use Mautic\FormBundle\Entity\Form;
use Mautic\FormBundle\EventListener\PageSubscriber;
use Mautic\FormBundle\Model\FormModel;
use Mautic\PageBundle\Entity\Page;
use Mautic\PageBundle\Event\PageDisplayEvent;

class PageSubscriberTest extends \PHPUnit_Framework_TestCase
{
    public function testOnPageDisplay()
    {
        $page = new Page();
        $page->setContent('{form=1}');

        $form = new Form();
        $form->setInKioskMode(true);
        $form->setIsPublished(true);

        $formModel = $this->getMockBuilder(FormModel::class)
            ->disableOriginalConstructor()
            ->setMethods(['getEntity', 'getContent', 'populateValuesWithGetParameters'])
            ->getMock();

        $formModel->expects($this->once())
            ->method('getEntity')
            ->with(1)
            ->willReturn($form);

        $formModel->expects($this->once())
            ->method('getContent')
            ->willReturn('<form>Form 1 content</form>');

        $security = $this->getMockBuilder(CorePermissions::class)
            ->disableOriginalConstructor()
            ->setMethods(['hasEntityAccess'])
            ->getMock();

        $security->expects($this->any())
            ->method('hasEntityAccess')
            ->willReturn(true);

        $translator = $this->getMockBuilder(Translator::class)
            ->disableOriginalConstructor()
            ->setMethods(['trans'])
            ->getMock();

        $translator->expects($this->any())
            ->method('trans')
            ->willReturnArgument(0);

        $subscriber = new PageSubscriber($formModel);
        $subscriber->setSecurity($security);
        $subscriber->setTranslator($translator);

        $event = new PageDisplayEvent($page->getContent(), $page);

        $subscriber->onPageDisplay($event);

        $pageContent = $event->getContent();

        $this->assertContains(
            '<form>Form 1 content',
            $pageContent,
            'Form content should be found in the page content.'
        );

        $this->assertNotContains(
            '{form=1}',
            $pageContent,
            'Form token should be replaced out of the page content.'
        );
    }
}
