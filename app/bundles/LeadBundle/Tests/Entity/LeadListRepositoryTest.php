<?php

/*
 * @copyright   2016 Mautic Contributors. All rights reserved
 * @author      Mautic, Inc.
 *
 * @link        https://mautic.org
 *
 * @license     GNU/GPLv3 http://www.gnu.org/licenses/gpl-3.0.html
 */

namespace Mautic\LeadBundle\Tests\Entity;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Driver\Statement;
use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Query\Expression\ExpressionBuilder;
use Doctrine\DBAL\Query\QueryBuilder;
use Doctrine\DBAL\Schema\Column;
use Doctrine\DBAL\Schema\MySqlSchemaManager;
use Doctrine\DBAL\Types\DateTimeType;
use Doctrine\DBAL\Types\TextType;
use Doctrine\ORM\EntityManager;
use Mautic\CoreBundle\Helper\DateTimeHelper;
use Mautic\CoreBundle\Translation\Translator;
use Mautic\LeadBundle\Entity\LeadListRepository;

class LeadListRepositoryTest extends \PHPUnit_Framework_TestCase
{
    public function testIncludeSegmentFilterWithFiltersAppendInOrGroups()
    {
        list($mockRepository, $reflectedMethod, $connection) = $this->getReflectedGenerateSegmentExpressionMethod();

        $parameters = [];
        $qb         = $connection->createQueryBuilder();
        $filters    =
            [
                [
                    'glue'     => 'and',
                    'operator' => 'in',
                    'field'    => 'leadlist',
                    'object'   => 'lead',
                    'type'     => 'leadlist',
                    'display'  => null,
                    'filter'   => [1, 2],
                ],
            ];

        // array $filters, array &$parameters, QueryBuilder $q, QueryBuilder $parameterQ = null, $listId = null, $not = false
        $expr   = $reflectedMethod->invokeArgs($mockRepository, [$filters, &$parameters, $qb]);
        $string = (string) $expr;

        $found = preg_match_all('/EXISTS \(SELECT null FROM '.MAUTIC_TABLE_PREFIX.'leads .*? LEFT JOIN '.MAUTIC_TABLE_PREFIX.'lead_lists_leads/', $string, $matches);
        $this->assertEquals(2, $found, $string);

        // Segment filters combined by OR to keep consistent behavior with the use of leadlist_id IN (1,2,3)
        $found = preg_match_all('/OR \(EXISTS \(SELECT null FROM '.MAUTIC_TABLE_PREFIX.'leads .*? LEFT JOIN '.MAUTIC_TABLE_PREFIX.'lead_lists_leads/', $string, $matches);
        $this->assertEquals(1, $found, $string);

        $found = preg_match_all('/\(l.email = :(.*?)\)/', $string, $matches);
        $this->assertEquals(2, $found, $string);

        $this->assertTrue(isset($parameters[$matches[1][0]]) && $parameters[$matches[1][0]] = 'blah@blah.com', $string);
        $this->assertTrue(isset($parameters[$matches[1][1]]) && $parameters[$matches[1][1]] = 'blah2@blah.com', $string);
    }

    public function testIncludeSegmentFilterWithOutFiltersAppendMembershipSubquery()
    {
        list($mockRepository, $reflectedMethod, $connection) = $this->getReflectedGenerateSegmentExpressionMethod(true);

        $parameters = [];
        $qb         = $connection->createQueryBuilder();
        $filters    =
            [
                [
                    'glue'     => 'and',
                    'operator' => 'in',
                    'field'    => 'leadlist',
                    'object'   => 'lead',
                    'type'     => 'leadlist',
                    'display'  => null,
                    'filter'   => [1, 2],
                ],
            ];

        // array $filters, array &$parameters, QueryBuilder $q, QueryBuilder $parameterQ = null, $listId = null, $not = false
        $expr = $reflectedMethod->invokeArgs($mockRepository, [$filters, &$parameters, $qb]);

        $string = (string) $expr;

        // Two segments included
        $found = preg_match_all('/EXISTS \(SELECT null FROM '.MAUTIC_TABLE_PREFIX.'lead_lists_leads/', $string, $matches);
        $this->assertEquals(2, $found, $string);

        // Segment filters combined by OR to keep consistent behavior with the use of leadlist_id IN (1,2,3)
        $found = preg_match_all('/OR \(EXISTS \(SELECT null FROM '.MAUTIC_TABLE_PREFIX.'lead_lists_leads/', $string, $matches);
        $this->assertEquals(1, $found, $string);
    }

    public function testExcludeSegmentFilterWithFiltersAppendNotExistsSubQuery()
    {
        list($mockRepository, $reflectedMethod, $connection) = $this->getReflectedGenerateSegmentExpressionMethod();

        $parameters = [];
        $qb         = $connection->createQueryBuilder();
        $filters    =
            [
                [
                    'glue'     => 'and',
                    'operator' => '!in',
                    'field'    => 'leadlist',
                    'object'   => 'lead',
                    'type'     => 'leadlist',
                    'display'  => null,
                    'filter'   => [1, 2],
                ],
            ];

        // array $filters, array &$parameters, QueryBuilder $q, QueryBuilder $parameterQ = null, $listId = null, $not = false
        $expr   = $reflectedMethod->invokeArgs($mockRepository, [$filters, &$parameters, $qb]);
        $string = (string) $expr;

        $found = preg_match_all('/NOT EXISTS \(SELECT null FROM '.MAUTIC_TABLE_PREFIX.'leads .*? LEFT JOIN '.MAUTIC_TABLE_PREFIX.'lead_lists_leads/', $string, $matches);
        $this->assertEquals(2, $found, $string);

        // Segment filters combined by AND to keep consistent behavior with the use of leadlist_id IN (1,2,3)
        $found = preg_match_all('/AND \(NOT EXISTS \(SELECT null FROM '.MAUTIC_TABLE_PREFIX.'leads .*? LEFT JOIN '.MAUTIC_TABLE_PREFIX.'lead_lists_leads/', $string, $matches);
        $this->assertEquals(1, $found, $string);

        $found = preg_match_all('/\(l.email = :(.*?)\)/', $string, $matches);
        $this->assertEquals(2, $found, $string);

        $this->assertTrue(isset($parameters[$matches[1][0]]) && $parameters[$matches[1][0]] = 'blah@blah.com', $string);
        $this->assertTrue(isset($parameters[$matches[1][1]]) && $parameters[$matches[1][1]] = 'blah2@blah.com', $string);
    }

    public function testExcludeSegmentFilterWithOutFiltersAppendMembershipSubquery()
    {
        list($mockRepository, $reflectedMethod, $connection) = $this->getReflectedGenerateSegmentExpressionMethod(true);

        $parameters = [];
        $qb         = $connection->createQueryBuilder();
        $filters    =
            [
                [
                    'glue'     => 'and',
                    'operator' => '!in',
                    'field'    => 'leadlist',
                    'object'   => 'lead',
                    'type'     => 'leadlist',
                    'display'  => null,
                    'filter'   => [1, 2],
                ],
            ];

        // array $filters, array &$parameters, QueryBuilder $q, QueryBuilder $parameterQ = null, $listId = null, $not = false
        $expr = $reflectedMethod->invokeArgs($mockRepository, [$filters, &$parameters, $qb]);

        $string = (string) $expr;

        // Two segments included
        $found = preg_match_all('/NOT EXISTS \(SELECT null FROM '.MAUTIC_TABLE_PREFIX.'lead_lists_leads/', $string, $matches);
        $this->assertEquals(2, $found, $string);

        // Segment filters combined by AND to keep consistent behavior with the use of leadlist_id NOT IN (1,2,3)
        $found = preg_match_all('/AND \(NOT EXISTS \(SELECT null FROM '.MAUTIC_TABLE_PREFIX.'lead_lists_leads/', $string, $matches);
        $this->assertEquals(1, $found, $string);
    }

    public function testLikeFilterAppendsAmperstandIfNotIncluded()
    {
        list($mockRepository, $reflectedMethod, $connection) = $this->getReflectedGenerateSegmentExpressionMethod(true);

        $parameters = [];
        $qb         = $connection->createQueryBuilder();
        $filters    =
            [
                [
                    'glue'     => 'and',
                    'operator' => 'like',
                    'field'    => 'email',
                    'object'   => 'lead',
                    'type'     => 'text',
                    'display'  => null,
                    'filter'   => 'blah.com',
                ],
            ];

        // array $filters, array &$parameters, QueryBuilder $q, QueryBuilder $parameterQ = null, $listId = null, $not = false
        $expr = $reflectedMethod->invokeArgs($mockRepository, [$filters, &$parameters, $qb]);

        $string = (string) $expr;
        $found  = preg_match('/^l.email LIKE :(.*?)$/', $string, $match);
        $this->assertEquals(1, $found, $string);

        $this->assertTrue(isset($parameters[$match[1]]) && $parameters[$match[1]] == '%blah.com%', $string);
    }

    public function testLikeFilterDoesNotAppendsAmperstandIfAlreadyIncluded()
    {
        list($mockRepository, $reflectedMethod, $connection) = $this->getReflectedGenerateSegmentExpressionMethod(true);

        $parameters = [];
        $qb         = $connection->createQueryBuilder();
        $filters    =
            [
                [
                    'glue'     => 'and',
                    'operator' => 'like',
                    'field'    => 'email',
                    'object'   => 'lead',
                    'type'     => 'text',
                    'display'  => null,
                    'filter'   => 'blah.com%',
                ],
            ];

        // array $filters, array &$parameters, QueryBuilder $q, QueryBuilder $parameterQ = null, $listId = null, $not = false
        $expr = $reflectedMethod->invokeArgs($mockRepository, [$filters, &$parameters, $qb]);

        $string = (string) $expr;
        $found  = preg_match('/^l.email LIKE :(.*?)$/', $string, $match);
        $this->assertEquals(1, $found, $string);

        $this->assertTrue(isset($parameters[$match[1]]) && $parameters[$match[1]] == 'blah.com%', $string);
    }

    public function testContainsFilterAppendsAmperstandOnBothEnds()
    {
        list($mockRepository, $reflectedMethod, $connection) = $this->getReflectedGenerateSegmentExpressionMethod(true);

        $parameters = [];
        $qb         = $connection->createQueryBuilder();
        $filters    =
            [
                [
                    'glue'     => 'and',
                    'operator' => 'contains',
                    'field'    => 'email',
                    'object'   => 'lead',
                    'type'     => 'text',
                    'display'  => null,
                    'filter'   => 'blah.com',
                ],
            ];

        // array $filters, array &$parameters, QueryBuilder $q, QueryBuilder $parameterQ = null, $listId = null, $not = false
        $expr = $reflectedMethod->invokeArgs($mockRepository, [$filters, &$parameters, $qb]);

        $string = (string) $expr;
        $found  = preg_match('/^l.email LIKE :(.*?)$/', $string, $match);
        $this->assertEquals(1, $found, $string);

        $this->assertTrue(isset($parameters[$match[1]]) && $parameters[$match[1]] == '%blah.com%', $string);
    }

    public function testStartsWithFilterAppendsAmperstandAtEnd()
    {
        list($mockRepository, $reflectedMethod, $connection) = $this->getReflectedGenerateSegmentExpressionMethod(true);

        $parameters = [];
        $qb         = $connection->createQueryBuilder();
        $filters    =
            [
                [
                    'glue'     => 'and',
                    'operator' => 'startsWith',
                    'field'    => 'email',
                    'object'   => 'lead',
                    'type'     => 'text',
                    'display'  => null,
                    'filter'   => 'blah.com',
                ],
            ];

        // array $filters, array &$parameters, QueryBuilder $q, QueryBuilder $parameterQ = null, $listId = null, $not = false
        $expr = $reflectedMethod->invokeArgs($mockRepository, [$filters, &$parameters, $qb]);

        $string = (string) $expr;
        $found  = preg_match('/^l.email LIKE :(.*?)$/', $string, $match);
        $this->assertEquals(1, $found, $string);

        $this->assertTrue(isset($parameters[$match[1]]) && $parameters[$match[1]] == 'blah.com%', $string);
    }

    public function testEndsWithFilterAppendsAmperstandAtBeginning()
    {
        list($mockRepository, $reflectedMethod, $connection) = $this->getReflectedGenerateSegmentExpressionMethod(true);

        $parameters = [];
        $qb         = $connection->createQueryBuilder();
        $filters    =
            [
                [
                    'glue'     => 'and',
                    'operator' => 'endsWith',
                    'field'    => 'email',
                    'object'   => 'lead',
                    'type'     => 'text',
                    'display'  => null,
                    'filter'   => 'blah.com',
                ],
            ];

        // array $filters, array &$parameters, QueryBuilder $q, QueryBuilder $parameterQ = null, $listId = null, $not = false
        $expr = $reflectedMethod->invokeArgs($mockRepository, [$filters, &$parameters, $qb]);

        $string = (string) $expr;
        $found  = preg_match('/^l.email LIKE :(.*?)$/', $string, $match);
        $this->assertEquals(1, $found, $string);

        $this->assertTrue(isset($parameters[$match[1]]) && $parameters[$match[1]] == '%blah.com', $string);
    }

    public function testDateTimeFiltersYear()
    {
        list($mockRepository, $reflectedMethod, $connection) = $this->getReflectedGenerateSegmentExpressionMethod(true);

        $parameters = [];
        $qb         = $connection->createQueryBuilder();
        $filters    = [
            [
                'glue'     => 'and',
                'operator' => '=',
                'field'    => 'last_active',
                'object'   => 'lead',
                'type'     => 'datetime',
                'display'  => null,
                'filter'   => 'mautic.lead.list.year_this',
            ],
        ];

        $expr = $reflectedMethod->invokeArgs($mockRepository, [$filters, &$parameters, $qb]);

        $today    = new DateTimeHelper('midnight first day of this year', null, 'local');
        $nextYear = $today->modify('+1 year', true);

        $string = (string) $expr;
        $found  = preg_match('/^\(STR_TO_DATE\(l.last_active, \'%Y-%m-%d %k:%i:%s\'\) >= :([a-zA-Z]*)\)/', $string, $match);
        $this->assertEquals(1, $found, $string);
        $this->assertArrayHasKey($match[1], $parameters);
        $this->assertEquals($today->toUtcString('Y-m-d 00:00:00'), $parameters[$match[1]], $string);

        $found = preg_match('/\(STR_TO_DATE\(l.last_active, \'%Y-%m-%d %k:%i:%s\'\) < :([a-zA-Z]*)\)$/', $string, $match);
        $this->assertEquals(1, $found, $string);
        $this->assertArrayHasKey($match[1], $parameters);
        $this->assertEquals($nextYear->format('Y-m-d 00:00:00'), $parameters[$match[1]], $string);
    }

    public function testDateTimeFiltersMonth()
    {
        list($mockRepository, $reflectedMethod, $connection) = $this->getReflectedGenerateSegmentExpressionMethod(true);

        $parameters = [];
        $qb         = $connection->createQueryBuilder();
        $filters    = [
            [
                'glue'     => 'and',
                'operator' => '=',
                'field'    => 'last_active',
                'object'   => 'lead',
                'type'     => 'datetime',
                'display'  => null,
                'filter'   => 'mautic.lead.list.month_this',
            ],
        ];

        $expr = $reflectedMethod->invokeArgs($mockRepository, [$filters, &$parameters, $qb]);

        $today     = new DateTimeHelper('midnight first day of this month', null, 'local');
        $nextMonth = $today->modify('+1 month', true);

        $string = (string) $expr;
        $found  = preg_match('/^\(STR_TO_DATE\(l.last_active, \'%Y-%m-%d %k:%i:%s\'\) >= :([a-zA-Z]*)\)/', $string, $match);
        $this->assertEquals(1, $found, $string);
        $this->assertArrayHasKey($match[1], $parameters);
        $this->assertEquals($today->toUtcString('Y-m-d 00:00:00'), $parameters[$match[1]], $string);

        $found = preg_match('/\(STR_TO_DATE\(l.last_active, \'%Y-%m-%d %k:%i:%s\'\) < :([a-zA-Z]*)\)$/', $string, $match);
        $this->assertEquals(1, $found, $string);
        $this->assertArrayHasKey($match[1], $parameters);
        $this->assertEquals($nextMonth->format('Y-m-d 00:00:00'), $parameters[$match[1]], $string);
    }

    public function testDateTimeFiltersWeek()
    {
        list($mockRepository, $reflectedMethod, $connection) = $this->getReflectedGenerateSegmentExpressionMethod(true);

        $parameters = [];
        $qb         = $connection->createQueryBuilder();
        $filters    = [
            [
                'glue'     => 'and',
                'operator' => '=',
                'field'    => 'last_active',
                'object'   => 'lead',
                'type'     => 'datetime',
                'display'  => null,
                'filter'   => 'mautic.lead.list.week_this',
            ],
        ];

        $expr = $reflectedMethod->invokeArgs($mockRepository, [$filters, &$parameters, $qb]);

        $today    = new DateTimeHelper('midnight monday this week', null, 'local');
        $nextYear = $today->modify('+1 week', true);

        $string = (string) $expr;
        $found  = preg_match('/^\(STR_TO_DATE\(l.last_active, \'%Y-%m-%d %k:%i:%s\'\) >= :([a-zA-Z]*)\)/', $string, $match);
        $this->assertEquals(1, $found, $string);
        $this->assertArrayHasKey($match[1], $parameters);
        $this->assertEquals($today->toUtcString('Y-m-d 00:00:00'), $parameters[$match[1]], $string);

        $found = preg_match('/\(STR_TO_DATE\(l.last_active, \'%Y-%m-%d %k:%i:%s\'\) < :([a-zA-Z]*)\)$/', $string, $match);
        $this->assertEquals(1, $found, $string);
        $this->assertArrayHasKey($match[1], $parameters);
        $this->assertEquals($nextYear->format('Y-m-d 00:00:00'), $parameters[$match[1]], $string);
    }

    public function testDateTimeFiltersDay()
    {
        list($mockRepository, $reflectedMethod, $connection) = $this->getReflectedGenerateSegmentExpressionMethod(true);

        $parameters = [];
        $qb         = $connection->createQueryBuilder();
        $filters    = [
            [
                'glue'     => 'and',
                'operator' => '=',
                'field'    => 'last_active',
                'object'   => 'lead',
                'type'     => 'datetime',
                'display'  => null,
                'filter'   => 'mautic.lead.list.yesterday',
            ],
        ];

        $expr = $reflectedMethod->invokeArgs($mockRepository, [$filters, &$parameters, $qb]);

        $today = new DateTimeHelper('today', null, 'local');
        $today->modify('-1 day');
        $tomorrow = $today->modify('+1 day', true);

        $string = (string) $expr;
        $found  = preg_match('/^\(STR_TO_DATE\(l.last_active, \'%Y-%m-%d %k:%i:%s\'\) >= :([a-zA-Z]*)\)/', $string, $match);
        $this->assertEquals(1, $found, $string);
        $this->assertArrayHasKey($match[1], $parameters);
        $this->assertEquals($today->toUtcString('Y-m-d 00:00:00'), $parameters[$match[1]], $string);

        $found = preg_match('/\(STR_TO_DATE\(l.last_active, \'%Y-%m-%d %k:%i:%s\'\) < :([a-zA-Z]*)\)$/', $string, $match);
        $this->assertEquals(1, $found, $string);
        $this->assertArrayHasKey($match[1], $parameters);
        $this->assertEquals($tomorrow->format('Y-m-d 00:00:00'), $parameters[$match[1]], $string);
    }

    public function testUrlTitleEq()
    {
        list($mockRepository, $reflectedMethod, $connection) = $this->getReflectedGenerateSegmentExpressionMethod(true);

        $parameters = [];
        $qb         = $connection->createQueryBuilder();
        $filters    = [
            [
                'glue'     => 'and',
                'operator' => '=',
                'field'    => 'url_title',
                'object'   => 'lead',
                'type'     => 'text',
                'display'  => null,
                'filter'   => 'Mautic Page Title',
            ],
        ];

        $expr   = $reflectedMethod->invokeArgs($mockRepository, [$filters, &$parameters, $qb]);
        $string = (string) $expr;
        $found  = preg_match('/page_hits ([a-zA-Z]*) WHERE \(\1.url_title = :([a-zA-Z]*)\)/', $string, $match);
        $this->assertEquals(1, $found, $string);
        $this->assertArrayHasKey($match[2], $parameters);
        $this->assertEquals('Mautic Page Title', $parameters[$match[2]]);
    }

    public function testUrlTitleRegexp()
    {
        list($mockRepository, $reflectedMethod, $connection) = $this->getReflectedGenerateSegmentExpressionMethod(true);

        $parameters = [];
        $qb         = $connection->createQueryBuilder();
        $filters    = [
            [
                'glue'     => 'and',
                'operator' => 'regexp',
                'field'    => 'url_title',
                'object'   => 'lead',
                'type'     => 'text',
                'display'  => null,
                'filter'   => 'Mautic Page Title',
            ],
        ];

        $expr   = $reflectedMethod->invokeArgs($mockRepository, [$filters, &$parameters, $qb]);
        $string = (string) $expr;
        $found  = preg_match('/page_hits ([a-zA-Z]*) WHERE \(\1.lead_id = l.id\) AND \(\1.url_title REGEXP :([a-zA-Z]*)\)/', $string, $match);
        $this->assertEquals(1, $found, $string);
        $this->assertArrayHasKey($match[2], $parameters);
        $this->assertEquals('Mautic Page Title', $parameters[$match[2]]);
    }

    public function testUrlTitleContains()
    {
        list($mockRepository, $reflectedMethod, $connection) = $this->getReflectedGenerateSegmentExpressionMethod(true);

        $parameters = [];
        $qb         = $connection->createQueryBuilder();
        $filters    = [
            [
                'glue'     => 'and',
                'operator' => 'contains',
                'field'    => 'url_title',
                'object'   => 'lead',
                'type'     => 'text',
                'display'  => null,
                'filter'   => 'Mautic Page Title',
            ],
        ];

        $expr   = $reflectedMethod->invokeArgs($mockRepository, [$filters, &$parameters, $qb]);
        $string = (string) $expr;
        $found  = preg_match('/page_hits ([a-zA-Z]*) WHERE \(\1.url_title LIKE :([a-zA-Z]*)\)/', $string, $match);
        $this->assertEquals(1, $found, $string);
        $this->assertArrayHasKey($match[2], $parameters);
        $this->assertEquals('%Mautic Page Title%', $parameters[$match[2]]);
    }

    public function testUrlTitleStartsWith()
    {
        list($mockRepository, $reflectedMethod, $connection) = $this->getReflectedGenerateSegmentExpressionMethod(true);

        $parameters = [];
        $qb         = $connection->createQueryBuilder();
        $filters    = [
            [
                'glue'     => 'and',
                'operator' => 'startsWith',
                'field'    => 'url_title',
                'object'   => 'lead',
                'type'     => 'text',
                'display'  => null,
                'filter'   => 'Mautic Page Title',
            ],
        ];

        $expr   = $reflectedMethod->invokeArgs($mockRepository, [$filters, &$parameters, $qb]);
        $string = (string) $expr;
        $found  = preg_match('/page_hits ([a-zA-Z]*) WHERE \(\1.url_title LIKE :([a-zA-Z]*)\)/', $string, $match);
        $this->assertEquals(1, $found, $string);
        $this->assertArrayHasKey($match[2], $parameters);
        $this->assertEquals('Mautic Page Title%', $parameters[$match[2]]);
    }

    public function testUrlTitleEndsWith()
    {
        list($mockRepository, $reflectedMethod, $connection) = $this->getReflectedGenerateSegmentExpressionMethod(true);

        $parameters = [];
        $qb         = $connection->createQueryBuilder();
        $filters    = [
            [
                'glue'     => 'and',
                'operator' => 'endsWith',
                'field'    => 'url_title',
                'object'   => 'lead',
                'type'     => 'text',
                'display'  => null,
                'filter'   => 'Mautic Page Title',
            ],
        ];

        $expr   = $reflectedMethod->invokeArgs($mockRepository, [$filters, &$parameters, $qb]);
        $string = (string) $expr;
        $found  = preg_match('/page_hits ([a-zA-Z]*) WHERE \(\1.url_title LIKE :([a-zA-Z]*)\)/', $string, $match);
        $this->assertEquals(1, $found, $string);
        $this->assertArrayHasKey($match[2], $parameters);
        $this->assertEquals('%Mautic Page Title', $parameters[$match[2]]);
    }

    public function testDeviceModelEq()
    {
        list($mockRepository, $reflectedMethod, $connection) = $this->getReflectedGenerateSegmentExpressionMethod(true);

        $parameters = [];
        $qb         = $connection->createQueryBuilder();
        $filters    = [
            [
                'glue'     => 'and',
                'operator' => '=',
                'field'    => 'device_model',
                'object'   => 'lead',
                'type'     => 'text',
                'display'  => null,
                'filter'   => 'iPhone',
            ],
        ];

        $expr   = $reflectedMethod->invokeArgs($mockRepository, [$filters, &$parameters, $qb]);
        $string = (string) $expr;
        $found  = preg_match('/lead_devices ([a-zA-Z]*) WHERE \(\1.device_model = :([a-zA-Z]*)\)/', $string, $match);
        $this->assertEquals(1, $found, $string);
        $this->assertArrayHasKey($match[2], $parameters);
        $this->assertEquals('iPhone', $parameters[$match[2]]);
    }

    public function testDeviceModelNotLike()
    {
        list($mockRepository, $reflectedMethod, $connection) = $this->getReflectedGenerateSegmentExpressionMethod(true);

        $parameters = [];
        $qb         = $connection->createQueryBuilder();
        $filters    = [
            [
                'glue'     => 'and',
                'operator' => '!like',
                'field'    => 'device_model',
                'object'   => 'lead',
                'type'     => 'text',
                'display'  => null,
                'filter'   => 'iPhone',
            ],
        ];

        $expr   = $reflectedMethod->invokeArgs($mockRepository, [$filters, &$parameters, $qb]);
        $string = (string) $expr;
        $found  = preg_match('/lead_devices ([a-zA-Z]*) WHERE \(\1.device_model LIKE :([a-zA-Z]*)\)/', $string, $match);
        $this->assertEquals(1, $found, $string);
        $this->assertArrayHasKey($match[2], $parameters);
        $this->assertEquals('%iPhone%', $parameters[$match[2]]);
    }

    public function testDeviceModelNotRegexp()
    {
        list($mockRepository, $reflectedMethod, $connection) = $this->getReflectedGenerateSegmentExpressionMethod(true);

        $parameters = [];
        $qb         = $connection->createQueryBuilder();
        $filters    = [
            [
                'glue'     => 'and',
                'operator' => '!regexp',
                'field'    => 'device_model',
                'object'   => 'lead',
                'type'     => 'text',
                'display'  => null,
                'filter'   => 'iPhone',
            ],
        ];

        $expr   = $reflectedMethod->invokeArgs($mockRepository, [$filters, &$parameters, $qb]);
        $string = (string) $expr;
        $found  = preg_match('/lead_devices ([a-zA-Z]*) WHERE \(\1.lead_id = l.id\) AND \(\1.device_model NOT REGEXP :([a-zA-Z]*)\)/', $string, $match);
        $this->assertEquals(1, $found, $string);
        $this->assertArrayHasKey($match[2], $parameters);
        $this->assertEquals('iPhone', $parameters[$match[2]]);
    }

    public function testHitUrlDateEq()
    {
        list($mockRepository, $reflectedMethod, $connection) = $this->getReflectedGenerateSegmentExpressionMethod(true);

        $parameters = [];
        $qb         = $connection->createQueryBuilder();
        $filters    = [
            [
                'glue'     => 'and',
                'operator' => '=',
                'field'    => 'hit_url_date',
                'object'   => 'lead',
                'type'     => 'text',
                'display'  => null,
                'filter'   => 'today',
            ],
        ];

        $expr   = $reflectedMethod->invokeArgs($mockRepository, [$filters, &$parameters, $qb]);
        $string = (string) $expr;
        $found  = preg_match('/page_hits ([a-zA-Z]*) WHERE \(\1.date_hit = :([a-zA-Z]*)\)/', $string, $match);
        $this->assertEquals(1, $found, $string);
        $this->assertArrayHasKey($match[2], $parameters);
        $this->assertEquals('today', $parameters[$match[2]]);
    }

    public function testHitUrlDateBetween()
    {
        list($mockRepository, $reflectedMethod, $connection) = $this->getReflectedGenerateSegmentExpressionMethod(true);

        $parameters = [];
        $qb         = $connection->createQueryBuilder();
        $filters    = [
            [
                'glue'     => 'and',
                'operator' => 'between',
                'field'    => 'hit_url_date',
                'object'   => 'lead',
                'type'     => 'text',
                'display'  => null,
                'filter'   => [
                    'yesterday',
                    'today',
                ],
            ],
        ];

        $expr   = $reflectedMethod->invokeArgs($mockRepository, [$filters, &$parameters, $qb]);
        $string = (string) $expr;
        $found  = preg_match('/page_hits ([a-zA-Z]*) WHERE \(\1.date_hit >= :([a-zA-Z]*)\)/', $string, $match);
        $this->assertEquals(1, $found, $string);
        $this->assertArrayHasKey($match[2], $parameters);
        $this->assertEquals('yesterday', $parameters[$match[2]]);

        $found = preg_match('/date_hit < :([a-zA-Z]*)\)/', $string, $match);
        $this->assertEquals(1, $found, $string);
        $this->assertArrayHasKey($match[1], $parameters);
        $this->assertEquals('today', $parameters[$match[1]]);
    }

    public function testHitUrlDateNotBetween()
    {
        list($mockRepository, $reflectedMethod, $connection) = $this->getReflectedGenerateSegmentExpressionMethod(true);

        $parameters = [];
        $qb         = $connection->createQueryBuilder();
        $filters    = [
            [
                'glue'     => 'and',
                'operator' => '!between',
                'field'    => 'hit_url_date',
                'object'   => 'lead',
                'type'     => 'text',
                'display'  => null,
                'filter'   => [
                    'yesterday',
                    'today',
                ],
            ],
        ];

        $expr   = $reflectedMethod->invokeArgs($mockRepository, [$filters, &$parameters, $qb]);
        $string = (string) $expr;
        $found  = preg_match('/page_hits ([a-zA-Z]*) WHERE \(\1.date_hit < :([a-zA-Z]*)\)/', $string, $match);
        $this->assertEquals(1, $found, $string);
        $this->assertArrayHasKey($match[2], $parameters);
        $this->assertEquals('yesterday', $parameters[$match[2]]);

        $found = preg_match('/date_hit >= :([a-zA-Z]*)\)/', $string, $match);
        $this->assertEquals(1, $found, $string);
        $this->assertArrayHasKey($match[1], $parameters);
        $this->assertEquals('today', $parameters[$match[1]]);
    }

    public function testLeadEmailReadDateLike()
    {
        list($mockRepository, $reflectedMethod, $connection) = $this->getReflectedGenerateSegmentExpressionMethod(true);

        $parameters = [];
        $qb         = $connection->createQueryBuilder();
        $filters    = [
            [
                'glue'     => 'and',
                'operator' => 'like',
                'field'    => 'lead_email_read_date',
                'object'   => 'lead',
                'type'     => 'text',
                'display'  => null,
                'filter'   => 'today',
            ],
        ];

        $expr   = $reflectedMethod->invokeArgs($mockRepository, [$filters, &$parameters, $qb]);
        $string = (string) $expr;
        $found  = preg_match('/email_stats ([a-zA-Z]*) WHERE \(\1.date_read LIKE :([a-zA-Z]*)\)/', $string, $match);
        $this->assertEquals(1, $found, $string);
        $this->assertArrayHasKey($match[2], $parameters);
        $this->assertEquals('today', $parameters[$match[2]]);
    }

    public function testNotificationEmpty()
    {
        list($mockRepository, $reflectedMethod, $connection) = $this->getReflectedGenerateSegmentExpressionMethod(true);

        $parameters = [];
        $qb         = $connection->createQueryBuilder();
        $filters    = [
            [
                'glue'     => 'and',
                'operator' => 'empty',
                'field'    => 'notification',
                'object'   => 'lead',
                'type'     => 'text',
                'display'  => null,
                'filter'   => 0,
            ],
        ];

        $expr   = $reflectedMethod->invokeArgs($mockRepository, [$filters, &$parameters, $qb]);
        $string = (string) $expr;
        $found  = preg_match('/push_ids ([a-zA-Z]*) WHERE \(\1.id IS NULL\)/', $string, $match);
        $this->assertEquals(1, $found, $string);
    }

    public function testRedirectIdNotEmpty()
    {
        list($mockRepository, $reflectedMethod, $connection) = $this->getReflectedGenerateSegmentExpressionMethod(true);

        $parameters = [];
        $qb         = $connection->createQueryBuilder();
        $filters    = [
            [
                'glue'     => 'and',
                'operator' => 'like',
                'field'    => 'redirect_id',
                'object'   => 'lead',
                'type'     => 'text',
                'display'  => null,
                'filter'   => 1,
            ],
        ];

        $expr   = $reflectedMethod->invokeArgs($mockRepository, [$filters, &$parameters, $qb]);
        $string = (string) $expr;
        $found  = preg_match('/page_hits ([a-zA-Z]*) WHERE \(\1.redirect_id IS NOT NULL\)/', $string, $match);
        $this->assertEquals(1, $found, $string);
    }

    public function testSessionsEq()
    {
        list($mockRepository, $reflectedMethod, $connection) = $this->getReflectedGenerateSegmentExpressionMethod(true);

        $parameters = [];
        $qb         = $connection->createQueryBuilder();
        $filters    = [
            [
                'glue'     => 'and',
                'operator' => 'like',
                'field'    => 'sessions',
                'object'   => 'lead',
                'type'     => 'text',
                'display'  => null,
                'filter'   => 'today',
            ],
        ];

        $expr   = $reflectedMethod->invokeArgs($mockRepository, [$filters, &$parameters, $qb]);
        $string = (string) $expr;
        $found  = preg_match('/page_hits ([a-zA-Z]*) WHERE \(\1.lead_id = l.id\) AND \(\1.date_hit > \([a-zA-Z]*.date_hit - INTERVAL 30 MINUTE\)\)/', $string, $match);
        $this->assertEquals(1, $found, $string);
    }

    private function getReflectedGenerateSegmentExpressionMethod($noFilters = false)
    {
        defined('MAUTIC_TABLE_PREFIX') or define('MAUTIC_TABLE_PREFIX', '');
        $mockRepository = $this->getMockBuilder(LeadListRepository::class)
            ->disableOriginalConstructor()
            ->setMethods(['getEntityManager'])
            ->getMock();

        $mockConnection = $this->getMockBuilder(Connection::class)
            ->disableOriginalConstructor()
            ->getMock();
        $mockConnection->method('getExpressionBuilder')
            ->willReturnCallback(
                function () use ($mockConnection) {
                    return new ExpressionBuilder($mockConnection);
                }
            );
        $mockConnection->method('quote')
            ->willReturnCallback(
                function ($value) {
                    return "'$value'";
                }
            );

        $mockSchemaManager = $this->getMockBuilder(MySqlSchemaManager::class)
            ->disableOriginalConstructor()
            ->getMock();
        $mockSchemaManager->method('listTableColumns')
            ->willReturnCallback(
                function ($table) {
                    $mockTextType = $this->getMockBuilder(TextType::class)
                        ->disableOriginalConstructor()
                        ->getMock();
                    $mockDateTimeType = $this->getMockBuilder(DateTimeType::class)
                        ->disableOriginalConstructor()
                        ->getMock();

                    switch ($table) {
                        case 'leads':
                            return [
                                'email'       => new Column('email', $mockTextType),
                                'last_active' => new Column('last_active', $mockDateTimeType),
                            ];
                            break;
                        case 'companies':
                            return [
                                'company_email' => new Column('company_email', $mockTextType),
                            ];
                            break;
                    }

                    return [];
                }
            );

        $mockConnection->method('getSchemaManager')
            ->willReturn($mockSchemaManager);

        $mockPlatform = $this->getMockBuilder(AbstractPlatform::class)
            ->disableOriginalConstructor()
            ->getMock();
        $mockPlatform->method('getName')
            ->willReturn('mysql');
        $mockConnection->method('getDatabasePlatform')
            ->willReturn($mockPlatform);

        $mockEntityManager = $this->getMockBuilder(EntityManager::class)
            ->disableOriginalConstructor()
            ->getMock();
        $mockEntityManager->method('getConnection')
            ->willReturn($mockConnection);

        $mockRepository->method('getEntityManager')
            ->willReturn($mockEntityManager);

        $mockStatement = $this->getMockBuilder(Statement::class)
            ->disableOriginalConstructor()
            ->getMock();

        $subFilters1 = [
            [
                'glue'     => 'and',
                'operator' => '=',
                'field'    => 'email',
                'object'   => 'lead',
                'type'     => 'text',
                'display'  => null,
                'filter'   => 'blah@blah.com',
            ],
        ];

        $subFilters2 = [
            [
                'glue'     => 'and',
                'operator' => '=',
                'field'    => 'email',
                'object'   => 'lead',
                'type'     => 'text',
                'display'  => null,
                'filter'   => 'blah2@blah.com',
            ],
        ];

        $filters = [
            [
                'id'      => 1,
                'filters' => serialize($noFilters ? [] : $subFilters1),
            ],
            [
                'id'      => 2,
                'filters' => serialize($noFilters ? [] : $subFilters2),
            ],
        ];
        $mockStatement->method('fetchAll')
            ->willReturn($filters);

        $mockConnection->method('createQueryBuilder')
            ->willReturnCallback(
                function () use ($mockConnection, $mockStatement) {
                    $qb = $this->getMockBuilder(QueryBuilder::class)
                        ->setConstructorArgs([$mockConnection])
                        ->setMethods(['execute'])
                        ->getMock();

                    $qb->method('execute')
                        ->willReturn($mockStatement);

                    return $qb;
                }
            );

        $mockTranslator = $this->getMockBuilder(Translator::class)
            ->disableOriginalConstructor()
            ->setMethods(['trans'])
            ->getMock();

        $mockTranslator->expects($this->any())
            ->method('trans')
            ->willReturnArgument(0);

        $mockRepository->setTranslator($mockTranslator);

        $reflectedMockRepository = new \ReflectionObject($mockRepository);
        $method                  = $reflectedMockRepository->getMethod('generateSegmentExpression');
        $method->setAccessible(true);

        return [$mockRepository, $method, $mockConnection];
    }
}
