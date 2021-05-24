<?php

/*
 * This file is part of the API Platform project.
 *
 * (c) Kévin Dunglas <dunglas@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace ApiPlatform\Core\Bridge\Doctrine\Orm\Filter;

use ApiPlatform\Core\Bridge\Doctrine\Common\Filter\DateFilterInterface;
use ApiPlatform\Core\Bridge\Doctrine\Common\Filter\DateFilterTrait;
use ApiPlatform\Core\Bridge\Doctrine\Orm\Util\QueryNameGeneratorInterface;
use ApiPlatform\Core\Exception\InvalidArgumentException;
use Doctrine\DBAL\Types\Type as DBALType;
use Doctrine\ORM\QueryBuilder;

/**
 * Filters the collection by date intervals.
 *
 * @author Kévin Dunglas <dunglas@gmail.com>
 * @author Théo FIDRY <theo.fidry@gmail.com>
 *
 * @final
 */
class DateFilter extends AbstractContextAwareFilter implements DateFilterInterface, QueryExpressionGeneratorInterface
{
    use DateFilterTrait;

    public const DOCTRINE_DATE_TYPES = [
        DBALType::DATE => true,
        DBALType::DATETIME => true,
        DBALType::DATETIMETZ => true,
        DBALType::TIME => true,
        DBALType::DATE_IMMUTABLE => true,
        DBALType::DATETIME_IMMUTABLE => true,
        DBALType::DATETIMETZ_IMMUTABLE => true,
        DBALType::TIME_IMMUTABLE => true,
    ];

    /**
     * {@inheritdoc}
     */
    protected function filterProperty(string $property, $values, QueryBuilder $queryBuilder, QueryNameGeneratorInterface $queryNameGenerator, string $resourceClass, string $operationName = null)
    {
        // Expect $values to be an array having the period as keys and the date value as values
        if (
            !\is_array($values) ||
            !$this->isPropertyEnabled($property, $resourceClass) ||
            !$this->isPropertyMapped($property, $resourceClass) ||
            !$this->isDateField($property, $resourceClass)
        ) {
            return null;
        }

        $alias = $queryBuilder->getRootAliases()[0];
        $field = $property;

        if ($this->isPropertyNested($property, $resourceClass)) {
            [$alias, $field] = $this->addJoinsForNestedProperty($property, $alias, $queryBuilder, $queryNameGenerator, $resourceClass);
        }

        $nullManagement = $this->properties[$property] ?? null;
        $type = (string) $this->getDoctrineFieldType($property, $resourceClass);

        $wheres = [];
        if (isset($values[self::PARAMETER_BEFORE])) {
            $this->addWhere(
                $queryBuilder,
                $queryNameGenerator,
                $alias,
                $field,
                self::PARAMETER_BEFORE,
                $values[self::PARAMETER_BEFORE],
                $nullManagement,
                $type,
                $wheres
            );
        }

        if (isset($values[self::PARAMETER_STRICTLY_BEFORE])) {
            $this->addWhere(
                $queryBuilder,
                $queryNameGenerator,
                $alias,
                $field,
                self::PARAMETER_STRICTLY_BEFORE,
                $values[self::PARAMETER_STRICTLY_BEFORE],
                $nullManagement,
                $type,
                $wheres
            );
        }

        if (isset($values[self::PARAMETER_AFTER])) {
            $this->addWhere(
                $queryBuilder,
                $queryNameGenerator,
                $alias,
                $field,
                self::PARAMETER_AFTER,
                $values[self::PARAMETER_AFTER],
                $nullManagement,
                $type,
                $wheres
            );
        }

        if (isset($values[self::PARAMETER_STRICTLY_AFTER])) {
            $this->addWhere(
                $queryBuilder,
                $queryNameGenerator,
                $alias,
                $field,
                self::PARAMETER_STRICTLY_AFTER,
                $values[self::PARAMETER_STRICTLY_AFTER],
                $nullManagement,
                $type,
                $wheres
            );
        }

        return $wheres;
    }

    /**
     * Adds a where clause according to the chosen null management.
     *
     * @param string|DBALType $type
     */
    protected function addWhere(QueryBuilder $queryBuilder, QueryNameGeneratorInterface $queryNameGenerator, string $alias, string $field, string $operator, $value, string $nullManagement = null, $type = null, &$wheres)
    {
        $type = (string) $type;
        $value = $this->normalizeValue($value, $operator);

        if (null === $value) {
            return;
        }

        try {
            $value = false === strpos($type, '_immutable') ? new \DateTime($value) : new \DateTimeImmutable($value);
        } catch (\Exception $e) {
            // Silently ignore this filter if it can not be transformed to a \DateTime
            $this->logger->notice('Invalid filter ignored', [
                'exception' => new InvalidArgumentException(sprintf('The field "%s" has a wrong date format. Use one accepted by the \DateTime constructor', $field)),
            ]);

            return;
        }

        $valueParameter = $queryNameGenerator->generateParameterName($field);
        $queryBuilder->setParameter($valueParameter, $value, $type);

        $operatorValue = [
            self::PARAMETER_BEFORE => '<=',
            self::PARAMETER_STRICTLY_BEFORE => '<',
            self::PARAMETER_AFTER => '>=',
            self::PARAMETER_STRICTLY_AFTER => '>',
        ];
        $baseWhere = sprintf('%s.%s %s :%s', $alias, $field, $operatorValue[$operator], $valueParameter);

        if (null === $nullManagement) {
            $wheres[] = $baseWhere;
        } elseif (self::EXCLUDE_NULL === $nullManagement) {
            $wheres[] = $queryBuilder->expr()->andX(
                $queryBuilder->expr()->isNotNull(sprintf('%s.%s', $alias, $field)),
                $baseWhere
            );
        } elseif (
            (self::INCLUDE_NULL_BEFORE === $nullManagement && \in_array($operator, [self::PARAMETER_BEFORE, self::PARAMETER_STRICTLY_BEFORE], true)) ||
            (self::INCLUDE_NULL_AFTER === $nullManagement && \in_array($operator, [self::PARAMETER_AFTER, self::PARAMETER_STRICTLY_AFTER], true)) ||
            (self::INCLUDE_NULL_BEFORE_AND_AFTER === $nullManagement && \in_array($operator, [self::PARAMETER_AFTER, self::PARAMETER_STRICTLY_AFTER, self::PARAMETER_BEFORE, self::PARAMETER_STRICTLY_BEFORE], true))
        ) {
            $wheres[] = $queryBuilder->expr()->orX(
                $baseWhere,
                $queryBuilder->expr()->isNull(sprintf('%s.%s', $alias, $field))
            );
        } else {
            $wheres[] = $queryBuilder->expr()->andX(
                $baseWhere,
                $queryBuilder->expr()->isNotNull(sprintf('%s.%s', $alias, $field))
            );
        }
    }
}
