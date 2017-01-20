<?php
/**
 * @copyright   2017 Mautic Contributors. All rights reserved
 * @author      Mautic
 *
 * @link        http://mautic.org
 *
 * @license     GNU/GPLv3 http://www.gnu.org/licenses/gpl-3.0.html
 */

namespace Mautic\Migrations;

use Doctrine\DBAL\Migrations\SkipMigrationException;
use Doctrine\DBAL\Schema\Schema;
use Mautic\CampaignBundle\Model\CampaignModel;
use Mautic\CoreBundle\Doctrine\AbstractMauticMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
class Version20170106102310 extends AbstractMauticMigration
{
    /**
     * @param Schema $schema
     *
     * @throws SkipMigrationException
     * @throws \Doctrine\DBAL\Schema\SchemaException
     */
    public function preUp(Schema $schema)
    {
        $table = $schema->getTable($this->prefix.'campaign_events');
        if ($table->hasColumn('channel')) {
            throw new SkipMigrationException('Schema includes this migration');
        }
    }

    /**
     * @param Schema $schema
     */
    public function up(Schema $schema)
    {
        $this->addSql("ALTER TABLE {$this->prefix}campaign_events ADD channel VARCHAR(60) DEFAULT NULL");
        $this->addSql("ALTER TABLE {$this->prefix}campaign_events ADD channel_id INTEGER DEFAULT NULL");
        $this->addSql("CREATE INDEX {$this->prefix}channel ON {$this->prefix}campaign_events (channel, channel_id)");
        $this->addSql("CREATE INDEX {$this->prefix}channel ON {$this->prefix}campaign_lead_event_log (channel, channel_id)");
    }

    /**
     * Update all the channel events with their assigned channels.
     *
     * @param Schema $schema
     */
    public function postUp(Schema $schema)
    {
        /** @var CampaignModel $campaignModel */
        $campaignModel = $this->container->get('mautic.campaign.model.campaign');
        $eventSettings = $campaignModel->getEvents();

        $eventsWithChannels = [];
        foreach ($eventSettings as $type => $events) {
            foreach ($events as $eventType => $settings) {
                if (!empty($settings['channel'])) {
                    $eventsWithChannels[$eventType] = [
                        'channel'        => $settings['channel'],
                        'channelIdField' => null,
                    ];

                    if (!empty($settings['channelIdField'])) {
                        $eventsWithChannels[$eventType]['channelIdField'] = $settings['channelIdField'];
                    }
                }
            }
        }

        // Let's update
        $logger = $this->container->get('monolog.logger.mautic');
        $qb     = $this->connection->createQueryBuilder();

        $qb->select('e.id, e.type, e.properties')
           ->from($this->prefix.'campaign_events', 'e')
           ->where(
               $qb->expr()->in('e.type', array_map([$qb->expr(), 'literal'], array_keys($eventsWithChannels)))
           )
           ->setMaxResults(500);

        $start = 0;
        while ($results = $qb->execute()->fetchAll()) {
            $eventChannels = [];

            // Start a transaction
            $this->connection->beginTransaction();

            foreach ($results as $row) {
                $channelId  = null;
                $eventType  = $row['type'];
                $properties = unserialize($row['properties']);
                $field      = !empty($eventsWithChannels[$eventType]['channelIdField']) ? $eventsWithChannels[$eventType]['channelIdField'] : null;
                if ($field && isset($properties[$field])) {
                    if (is_array($properties[$field])) {
                        if (count($properties[$field]) === 1) {
                            $channelId = $properties[$field][0];
                        }
                    } elseif (!empty($properties[$field])) {
                        $channelId = $properties[$field];
                    }
                }

                $eventChannels[$row['id']] = [
                    'channel'    => $eventsWithChannels[$eventType]['channel'],
                    'channel_id' => $channelId,
                ];

                $this->connection->update(
                    MAUTIC_TABLE_PREFIX.'campaign_events',
                    $eventChannels[$row['id']],
                    [
                        'id' => $row['id'],
                    ]
                );
            }

            try {
                $this->connection->commit();

                // Update logs
                $this->connection->beginTransaction();
                foreach ($eventChannels as $id => $channel) {
                    $lqb = $this->connection->createQueryBuilder()
                                            ->update($this->prefix.'campaign_lead_event_log')
                                            ->set('channel', ':channel')
                                            ->set('channel_id', ':channel_id')
                                            ->setParameters($channel);
                    $lqb->where(
                        $lqb->expr()->andX(
                            $lqb->expr()->eq('event_id', $id),
                            $lqb->expr()->isNull('channel')
                        )
                    )->execute();
                }
                $this->connection->commit();
            } catch (\Exception $e) {
                $this->connection->rollBack();

                $logger->addError($e->getMessage(), ['exception' => $e]);
            }

            // Increase the start
            $start += 500;
            $qb->setFirstResult($start);
        }
    }
}
