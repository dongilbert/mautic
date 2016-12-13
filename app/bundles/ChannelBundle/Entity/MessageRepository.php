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

    public function getMessages($search = '', $limit = 10, $start = 0, $viewOther = false)
    {
        $q = $this->createQueryBuilder('mc');

        if (!empty($search)) {
            if (is_array($search)) {
                $search = array_map('intval', $search);
                $q->andWhere($q->expr()->in('mq.id', ':search'))
                    ->setParameter('search', $search);
            } else {
                $q->andWhere($q->expr()->like('mq.name', ':search'))
                    ->setParameter('search', "%{$search}%");
            }
        }

        if (!$viewOther) {
            $q->andWhere($q->expr()->eq('mq.createdBy', ':id'))
                ->setParameter('id', $this->currentUser->getId());
        }

        if (!empty($limit)) {
            $q->setFirstResult($start)
                ->setMaxResults($limit);
        }

        $results = $q->getQuery()->getResult();

        return $results;
    }
}
