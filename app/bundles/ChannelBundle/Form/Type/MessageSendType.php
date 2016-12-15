<?php

/*
 * @copyright   2014 Mautic Contributors. All rights reserved
 * @author      Mautic
 *
 * @link        http://mautic.org
 *
 * @license     GNU/GPLv3 http://www.gnu.org/licenses/gpl-3.0.html
 */

namespace Mautic\ChannelBundle\Form\Type;

use Mautic\CoreBundle\Factory\MauticFactory;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Routing\RouterInterface;

/**
 * Class MessageSendType.
 */
class MessageSendType extends AbstractType
{
    protected $router;

    /**
     * @param MauticFactory $factory
     */
    public function __construct(RouterInterface $router)
    {
        $this->router = $router;
    }

    /**
     * @param FormBuilderInterface $builder
     * @param array                $options
     */
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        if (!empty($options['message_id'])) {
            // create button edit email
            $windowUrlEdit = $this->router->generate(
                'mautic_message_action',
                [
                    'objectAction' => 'edit',
                    'objectId'     => $options['message_id'],
                    'contentOnly'  => 1,
                    'updateSelect' => true,
                ]
            );

            $builder->add(
                'editEmailButton',
                'button',
                [
                    'attr' => [
                        'class'   => 'btn btn-primary btn-nospin',
                        'onclick' => 'Mautic.loadNewWindow(Mautic.standardEmailUrl({"windowUrl": "'.$windowUrlEdit.'"}))',
                        'icon'    => 'fa fa-edit',
                    ],
                    'label' => 'mautic.email.send.edit.message',
                ]
            );
        }
    }

    /**
     * @param OptionsResolver $resolver
     */
    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver->setDefined(['message_id']);
    }

    /**
     * @return string
     */
    public function getName()
    {
        return 'message_send';
    }
}
