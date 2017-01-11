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
        return 'm';
    }

    /**
     * @param string $search
     * @param int    $limit
     * @param int    $start
     * @param bool   $viewOther
     *
     * @return array
     */
    public function getMessageList($search = '', $limit = 10, $start = 0)
    {
        $alias = $this->getTableAlias();
        $q     = $this->createQueryBuilder($this->getTableAlias());
        $q->select('partial '.$alias.'.{id, name, description}');

        if (!empty($search)) {
            if (is_array($search)) {
                $search = array_map('intval', $search);
                $q->andWhere($q->expr()->in($alias.'.id', ':search'))
                    ->setParameter('search', $search);
            } else {
                $q->andWhere($q->expr()->like($alias.'.name', ':search'))
                    ->setParameter('search', "%{$search}%");
            }
        }
        $q->andWhere($q->expr()->eq($alias.'.isPublished', true));

        if (!empty($limit)) {
            $q->setFirstResult($start)
                ->setMaxResults($limit);
        }

        $results = $q->getQuery()->getArrayResult();

        return $results;
    }

    /**
     * @param $messageId
     *
     * @return array
     */
    public function getMessageGoalsPerChannel($messageId)
    {
        $q = $this->_em->getConnection()->createQueryBuilder();
        $q->from(MAUTIC_TABLE_PREFIX.'message_goals', 'mg')
            ->select('mg.name, mg.properties, mg.goal_type, mg.goal_order, mc.channel, mc.channel_id')
            ->join('mg', MAUTIC_TABLE_PREFIX.'message_channels', 'mc', 'mc.id = mg.channel_id')
            ->where($q->expr()->eq('mc.message_id', ':messageId'))
            ->setParameter('messageId', $messageId)
        ->orderBy('mc.channel, mg.goal_order');

        $result = $q->execute()->fetchAll();

        return $result;
    }

    /**
     * @param $messageId
     *
     * @return array
     */
    public function getChannelMessages($messageId)
    {
        $q = $this->_em->getConnection()->createQueryBuilder();
        $q->from(MAUTIC_TABLE_PREFIX.'message_channels', 'mc')
            ->select('id, channel, channel_id')
            ->where($q->expr()->eq('id', ':messageId'))
            ->setParameter('messageId', $messageId);

        $result = $q->execute()->fetchAll();

        return $result;
    }
}
