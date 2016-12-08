<?php

/*
 * @copyright   2016 Mautic Contributors. All rights reserved
 * @author      Mautic, Inc.
 *
 * @link        https://mautic.org
 *
 * @license     GNU/GPLv3 http://www.gnu.org/licenses/gpl-3.0.html
 */

namespace Mautic\ChannelBundle\Entity;

use Mautic\CoreBundle\Entity\CommonRepository;

class MessageRepository extends CommonRepository
{
    /**
     * @return string
     */
    public function getTableAlias()
    {
        return 'mc';
    }

    public function getMessages()
    {
        $q = $this->createQueryBuilder('mc');

        $q->where($q->expr()->eq('mq.isPublished', ':published'))
            ->setParameter('published', true, 'boolean')
            ->indexBy('mc', 'mc.id');

        $results = $q->getQuery()->getResult();

        return $results;
    }
}
