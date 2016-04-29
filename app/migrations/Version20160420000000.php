<?php
/**
 * @package     Mautic
 * @copyright   2016 Mautic Contributors. All rights reserved.
 * @author      Mautic
 * @link        http://mautic.org
 * @license     GNU/GPLv3 http://www.gnu.org/licenses/gpl-3.0.html
 */

namespace Mautic\Migrations;

use Doctrine\DBAL\Migrations\SkipMigrationException;
use Doctrine\DBAL\Schema\Schema;
use Mautic\CoreBundle\Doctrine\AbstractMauticMigration;

/**
 * SMS Channel Migration
 */
class Version20160420000000 extends AbstractMauticMigration
{
    /**
     * @param Schema $schema
     *
     * @throws SkipMigrationException
     * @throws \Doctrine\DBAL\Schema\SchemaException
     */
    public function preUp(Schema $schema)
    {
        if ($schema->hasTable($this->prefix . 'sms_messages')) {
            throw new SkipMigrationException('Schema includes this migration');
        }
    }

    /**
     * @param Schema $schema
     */
    public function mysqlUp(Schema $schema)
    {
        $categoryIdIdx = $this->generatePropertyName('sms_messages', 'idx', array('category_id'));
        $categoryIdFk  = $this->generatePropertyName('sms_messages', 'fk', array('category_id'));

        $mainTableSql = <<<SQL
CREATE TABLE `{$this->prefix}sms_messages` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `category_id` int(11) DEFAULT NULL,
  `is_published` tinyint(1) NOT NULL,
  `date_added` datetime DEFAULT NULL,
  `created_by` int(11) DEFAULT NULL,
  `created_by_user` varchar(255) COLLATE utf8_unicode_ci DEFAULT NULL,
  `date_modified` datetime DEFAULT NULL,
  `modified_by` int(11) DEFAULT NULL,
  `modified_by_user` varchar(255) COLLATE utf8_unicode_ci DEFAULT NULL,
  `checked_out` datetime DEFAULT NULL,
  `checked_out_by` int(11) DEFAULT NULL,
  `checked_out_by_user` varchar(255) COLLATE utf8_unicode_ci DEFAULT NULL,
  `name` varchar(255) COLLATE utf8_unicode_ci NOT NULL,
  `description` longtext COLLATE utf8_unicode_ci,
  `lang` varchar(255) COLLATE utf8_unicode_ci NOT NULL,
  `message` longtext COLLATE utf8_unicode_ci NOT NULL,
  `sms_type` longtext COLLATE utf8_unicode_ci DEFAULT NULL,
  `publish_up` datetime DEFAULT NULL,
  `publish_down` datetime DEFAULT NULL,
  `sent_count` int(11) NOT NULL,
  PRIMARY KEY (`id`),
  KEY `{$categoryIdIdx}` (`category_id`),
  CONSTRAINT `{$categoryIdFk}` FOREIGN KEY (`category_id`) REFERENCES `{$this->prefix}categories` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;
SQL;

        $this->addSql($mainTableSql);

        $smsIdIdx = $this->generatePropertyName('sms_message_stats', 'idx', array('sms_id'));
        $leadIdIdx = $this->generatePropertyName('sms_message_stats', 'idx', array('lead_id'));
        $listIdIdx = $this->generatePropertyName('sms_message_stats', 'idx', array('list_id'));
        $ipIdIdx = $this->generatePropertyName('sms_message_stats', 'idx', array('ip_id'));
        $smsIdFk = $this->generatePropertyName('sms_message_stats', 'fk', array('sms_id'));
        $leadIdFk = $this->generatePropertyName('sms_message_stats', 'fk', array('lead_id'));
        $listIdFk = $this->generatePropertyName('sms_message_stats', 'fk', array('list_id'));
        $ipIdFk = $this->generatePropertyName('sms_message_stats', 'fk', array('ip_id'));

        $statsSql = <<<SQL
CREATE TABLE `{$this->prefix}sms_message_stats` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `sms_id` int(11) DEFAULT NULL,
  `lead_id` int(11) DEFAULT NULL,
  `list_id` int(11) DEFAULT NULL,
  `ip_id` int(11) DEFAULT NULL,
  `date_sent` datetime NOT NULL,
  `tracking_hash` varchar(255) COLLATE utf8_unicode_ci DEFAULT NULL,
  `source` varchar(255) COLLATE utf8_unicode_ci DEFAULT NULL,
  `source_id` int(11) DEFAULT NULL,
  `tokens` longtext COLLATE utf8_unicode_ci COMMENT '(DC2Type:array)',
  PRIMARY KEY (`id`),
  KEY `{$smsIdIdx}` (`sms_id`),
  KEY `{$leadIdIdx}` (`lead_id`),
  KEY `{$listIdIdx}` (`list_id`),
  KEY `{$ipIdIdx}` (`ip_id`),
  KEY `mtc_stat_sms_search` (`sms_id`,`lead_id`),
  KEY `mtc_stat_sms_hash_search` (`tracking_hash`),
  KEY `mtc_stat_sms_source_search` (`source`,`source_id`),
  CONSTRAINT `{$listIdFk}` FOREIGN KEY (`list_id`) REFERENCES `{$this->prefix}lead_lists` (`id`) ON DELETE SET NULL,
  CONSTRAINT `{$leadIdFk}` FOREIGN KEY (`lead_id`) REFERENCES `{$this->prefix}leads` (`id`) ON DELETE SET NULL,
  CONSTRAINT `{$ipIdFk}` FOREIGN KEY (`ip_id`) REFERENCES `{$this->prefix}ip_addresses` (`id`),
  CONSTRAINT `{$smsIdFk}` FOREIGN KEY (`sms_id`) REFERENCES `{$this->prefix}sms_messages` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;
SQL;
        $this->addSql($statsSql);

        $smsIdIdx = $this->generatePropertyName('sms_message_list_xref', 'idx', array('sms_id'));
        $leadlistIdIdx = $this->generatePropertyName('sms_message_list_xref', 'idx', array('leadlist_id'));
        $smsIdFk = $this->generatePropertyName('sms_message_list_xref', 'fk', array('sms_id'));
        $leadlistIdFk = $this->generatePropertyName('sms_message_list_xref', 'fk', array('leadlist_id'));

        $listXrefSql = <<<SQL
CREATE TABLE `{$this->prefix}sms_message_list_xref` (
  `sms_id` int(11) NOT NULL,
  `leadlist_id` int(11) NOT NULL,
  PRIMARY KEY (`sms_id`,`leadlist_id`),
  KEY `{$smsIdIdx}` (`sms_id`),
  KEY `{$leadlistIdIdx}` (`leadlist_id`),
  CONSTRAINT `{$smsIdFk}` FOREIGN KEY (`sms_id`) REFERENCES `{$this->prefix}sms_messages` (`id`) ON DELETE CASCADE,
  CONSTRAINT `{$leadlistIdFk}` FOREIGN KEY (`leadlist_id`) REFERENCES `{$this->prefix}lead_lists` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;
SQL;
        $this->addSql($listXrefSql);

        $smsIdIdx = $this->generatePropertyName('page_redirects', 'idx', array('sms_id'));
        $smsIdFk  = $this->generatePropertyName('page_redirects', 'fk', array('sms_id'));

        $this->addSql('ALTER TABLE ' . $this->prefix . 'page_redirects ADD sms_id INT(11) DEFAULT NULL AFTER `email_id`');
        $this->addSql('ALTER TABLE ' . $this->prefix . 'page_redirects ADD CONSTRAINT ' . $smsIdFk . ' FOREIGN KEY (sms_id) REFERENCES ' . $this->prefix . 'sms_messages (id) ON DELETE SET NULL');
        $this->addSql('CREATE INDEX ' . $smsIdIdx . ' ON ' . $this->prefix . 'page_redirects (sms_id)');
    }

    /**
     * @param Schema $schema
     */
    public function postgresqlUp(Schema $schema)
    {
        $this->addSql('CREATE TABLE '.$this->prefix.'sms_messages (id INT NOT NULL, category_id INT DEFAULT NULL, is_published BOOLEAN NOT NULL, date_added TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, created_by INT DEFAULT NULL, created_by_user VARCHAR(255) DEFAULT NULL, date_modified TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, modified_by INT DEFAULT NULL, modified_by_user VARCHAR(255) DEFAULT NULL, checked_out TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, checked_out_by INT DEFAULT NULL, checked_out_by_user VARCHAR(255) DEFAULT NULL, name VARCHAR(255) NOT NULL, description TEXT DEFAULT NULL, lang VARCHAR(255) NOT NULL, message TEXT NOT NULL, sms_type TEXT DEFAULT NULL, publish_up TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, publish_down TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, sent_count INT NOT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE TABLE '.$this->prefix.'sms_message_list_xref (sms_id INT NOT NULL, leadlist_id INT NOT NULL, PRIMARY KEY(sms_id, leadlist_id))');
        $this->addSql('CREATE TABLE '.$this->prefix.'sms_message_stats (id INT NOT NULL, sms_id INT DEFAULT NULL, lead_id INT DEFAULT NULL, list_id INT DEFAULT NULL, ip_id INT DEFAULT NULL, date_sent TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, is_read BOOLEAN NOT NULL, date_read TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, tracking_hash VARCHAR(255) DEFAULT NULL, retry_count INT DEFAULT NULL, source VARCHAR(255) DEFAULT NULL, source_id INT DEFAULT NULL, tokens TEXT DEFAULT NULL, click_count INT DEFAULT NULL, last_clicked TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, click_details TEXT DEFAULT NULL, PRIMARY KEY(id))');
        
        $this->addSql('CREATE SEQUENCE ' . $this->prefix . 'sms_messages_id_seq INCREMENT BY 1 MINVALUE 1 START 1');
        $this->addSql('CREATE SEQUENCE ' . $this->prefix . 'sms_message_stats_id_seq INCREMENT BY 1 MINVALUE 1 START 1');

        $this->addSql('CREATE INDEX '.$this->generatePropertyName('sms_messages', 'idx', array('category_id')).' ON '.$this->prefix.'sms_messages (category_id)');
        $this->addSql('CREATE INDEX '.$this->generatePropertyName('sms_message_list_xref', 'idx', array('sms_id')).' ON '.$this->prefix.'sms_message_list_xref (sms_id)');
        $this->addSql('CREATE INDEX '.$this->generatePropertyName('sms_message_list_xref', 'idx', array('leadlist_id')).' ON '.$this->prefix.'sms_message_list_xref (leadlist_id)');
        $this->addSql('CREATE INDEX '.$this->generatePropertyName('sms_message_stats', 'idx', array('sms_id')).' ON '.$this->prefix.'sms_message_stats (sms_id)');
        $this->addSql('CREATE INDEX '.$this->generatePropertyName('sms_message_stats', 'idx', array('lead_id')).' ON '.$this->prefix.'sms_message_stats (lead_id)');
        $this->addSql('CREATE INDEX '.$this->generatePropertyName('sms_message_stats', 'idx', array('ip_id')).' ON '.$this->prefix.'sms_message_stats (ip_id)');
        $this->addSql('CREATE INDEX '.$this->prefix.'stat_sms_search ON '.$this->prefix.'sms_message_stats (sms_id, lead_id)');
        $this->addSql('CREATE INDEX '.$this->prefix.'stat_sms_clicked_search ON '.$this->prefix.'sms_message_stats (is_read)');
        $this->addSql('CREATE INDEX '.$this->prefix.'stat_sms_hash_search ON '.$this->prefix.'sms_message_stats (tracking_hash)');
        $this->addSql('CREATE INDEX '.$this->prefix.'stat_sms_source_search ON '.$this->prefix.'sms_message_stats (source, source_id)');
        
        $this->addSql('ALTER TABLE '.$this->prefix.'sms_messages ADD CONSTRAINT '.$this->generatePropertyName('sms_messages', 'fk', array('category_id')).' FOREIGN KEY (category_id) REFERENCES '.$this->prefix.'categories (id) ON DELETE SET NULL NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE '.$this->prefix.'sms_message_list_xref ADD CONSTRAINT '.$this->generatePropertyName('sms_message_list_xref', 'fk', array('sms_id')).' FOREIGN KEY (sms_id) REFERENCES '.$this->prefix.'sms_messages (id) ON DELETE SET NULL NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE '.$this->prefix.'sms_message_list_xref ADD CONSTRAINT '.$this->generatePropertyName('sms_message_list_xref', 'fk', array('leadlist_id')).' FOREIGN KEY (leadlist_id) REFERENCES '.$this->prefix.'lead_lists (id) ON DELETE SET NULL NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE '.$this->prefix.'sms_message_stats ADD CONSTRAINT '.$this->generatePropertyName('sms_message_stats', 'fk', array('sms_id')).' FOREIGN KEY (sms_id) REFERENCES '.$this->prefix.'sms_messages (id) ON DELETE SET NULL NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE '.$this->prefix.'sms_message_stats ADD CONSTRAINT '.$this->generatePropertyName('sms_message_stats', 'fk', array('lead_id')).' FOREIGN KEY (lead_id) REFERENCES '.$this->prefix.'leads (id) ON DELETE SET NULL NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE '.$this->prefix.'sms_message_stats ADD CONSTRAINT '.$this->generatePropertyName('sms_message_stats', 'fk', array('list_id')).' FOREIGN KEY (list_id) REFERENCES '.$this->prefix.'lead_lists (id) ON DELETE SET NULL NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE '.$this->prefix.'sms_message_stats ADD CONSTRAINT '.$this->generatePropertyName('sms_message_stats', 'fk', array('ip_id')).' FOREIGN KEY (ip_id) REFERENCES '.$this->prefix.'ip_addresses (id) ON DELETE SET NULL NOT DEFERRABLE INITIALLY IMMEDIATE');
        
        $this->addSql('COMMENT ON COLUMN '.$this->prefix.'sms_messages.date_added IS \'(DC2Type:datetime)\'');
        $this->addSql('COMMENT ON COLUMN '.$this->prefix.'sms_messages.date_modified IS \'(DC2Type:datetime)\'');
        $this->addSql('COMMENT ON COLUMN '.$this->prefix.'sms_messages.checked_out IS \'(DC2Type:datetime)\'');
        $this->addSql('COMMENT ON COLUMN '.$this->prefix.'sms_messages.publish_up IS \'(DC2Type:datetime)\'');
        $this->addSql('COMMENT ON COLUMN '.$this->prefix.'sms_messages.publish_down IS \'(DC2Type:datetime)\'');
        $this->addSql('COMMENT ON COLUMN '.$this->prefix.'sms_message_stats.date_sent IS \'(DC2Type:datetime)\'');
        $this->addSql('COMMENT ON COLUMN '.$this->prefix.'sms_message_stats.date_read IS \'(DC2Type:datetime)\'');
        $this->addSql('COMMENT ON COLUMN '.$this->prefix.'sms_message_stats.tokens IS \'(DC2Type:array)\'');
        $this->addSql('COMMENT ON COLUMN '.$this->prefix.'sms_message_stats.last_clicked IS \'(DC2Type:datetime)\'');
        $this->addSql('COMMENT ON COLUMN '.$this->prefix.'sms_message_stats.click_details IS \'(DC2Type:array)\'');
    }
}
