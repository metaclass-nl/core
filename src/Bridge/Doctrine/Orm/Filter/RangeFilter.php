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

use ApiPlatform\Core\Bridge\Doctrine\Common\Filter\RangeFilterInterface;
use ApiPlatform\Core\Bridge\Doctrine\Common\Filter\RangeFilterTrait;
use ApiPlatform\Core\Bridge\Doctrine\Orm\Util\QueryNameGeneratorInterface;
use Doctrine\ORM\QueryBuilder;

/**
 * Filters the collection by range.
 *
 * @author Lee Siong Chan <ahlee2326@me.com>
 *
 * @final
 */
class RangeFilter extends AbstractContextAwareFilter implements RangeFilterInterface, QueryExpressionGeneratorInterface
{
    use RangeFilterTrait;

    /**
     * {@inheritdoc}
     */
    protected function filterProperty(string $property, $values, QueryBuilder $queryBuilder, QueryNameGeneratorInterface $queryNameGenerator, string $resourceClass, string $operationName = null)
    {
        if (
            !\is_array($values) ||
            !$this->isPropertyEnabled($property, $resourceClass) ||
            !$this->isPropertyMapped($property, $resourceClass)
        ) {
            return null;
        }

        $values = $this->normalizeValues($values, $property);
        if (null === $values) {
            return null;
        }

        $alias = $queryBuilder->getRootAliases()[0];
        $field = $property;

        if ($this->isPropertyNested($property, $resourceClass)) {
            [$alias, $field] = $this->addJoinsForNestedProperty($property, $alias, $queryBuilder, $queryNameGenerator, $resourceClass);
        }

        $wheres = [];
        foreach ($values as $operator => $value) {
            $this->addWhere(
                $queryBuilder,
                $queryNameGenerator,
                $alias,
                $field,
                $operator,
                $value,
                $wheres
            );
        }
        return $wheres;
    }

    /**
     * Adds the where clause according to the operator.
     *
     * @param string $alias
     * @param string $field
     * @param string $operator
     * @param string $value
     */
    protected function addWhere(QueryBuilder $queryBuilder, QueryNameGeneratorInterface $queryNameGenerator, $alias, $field, $operator, $value, &$wheres)
    {
        $valueParameter = $queryNameGenerator->generateParameterName($field);

        switch ($operator) {
            case self::PARAMETER_BETWEEN:
                $rangeValue = explode('..', $value);

                $rangeValue = $this->normalizeBetweenValues($rangeValue);
                if (null === $rangeValue) {
                    return;
                }

                if ($rangeValue[0] === $rangeValue[1]) {
                    $wheres[] = sprintf('%s.%s = :%s', $alias, $field, $valueParameter);
                    $queryBuilder->setParameter($valueParameter, $rangeValue[0]);

                    return;
                }

                $wheres[] = sprintf('%1$s.%2$s BETWEEN :%3$s_1 AND :%3$s_2', $alias, $field, $valueParameter);
                $queryBuilder
                    ->setParameter(sprintf('%s_1', $valueParameter), $rangeValue[0])
                    ->setParameter(sprintf('%s_2', $valueParameter), $rangeValue[1]);

                break;
            case self::PARAMETER_GREATER_THAN:
                $value = $this->normalizeValue($value, $operator);
                if (null === $value) {
                    return;
                }

                $wheres[] = sprintf('%s.%s > :%s', $alias, $field, $valueParameter);
                $queryBuilder->setParameter($valueParameter, $value);

                break;
            case self::PARAMETER_GREATER_THAN_OR_EQUAL:
                $value = $this->normalizeValue($value, $operator);
                if (null === $value) {
                    return;
                }

                $wheres[] = sprintf('%s.%s >= :%s', $alias, $field, $valueParameter);
                $queryBuilder->setParameter($valueParameter, $value);

                break;
            case self::PARAMETER_LESS_THAN:
                $value = $this->normalizeValue($value, $operator);
                if (null === $value) {
                    return;
                }

                $wheres[] = sprintf('%s.%s < :%s', $alias, $field, $valueParameter);
                $queryBuilder->setParameter($valueParameter, $value);

                break;
            case self::PARAMETER_LESS_THAN_OR_EQUAL:
                $value = $this->normalizeValue($value, $operator);
                if (null === $value) {
                    return;
                }

                $wheres[] = sprintf('%s.%s <= :%s', $alias, $field, $valueParameter);
                $queryBuilder->setParameter($valueParameter, $value);

                break;
        }
    }
}
