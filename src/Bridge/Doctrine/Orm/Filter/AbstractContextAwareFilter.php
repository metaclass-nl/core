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

use ApiPlatform\Core\Bridge\Doctrine\Orm\Util\QueryNameGeneratorInterface;
use Doctrine\ORM\QueryBuilder;

abstract class AbstractContextAwareFilter extends AbstractFilter implements ContextAwareFilterInterface
{
    /**
     * {@inheritdoc}
     */
    public function apply(QueryBuilder $queryBuilder, QueryNameGeneratorInterface $queryNameGenerator, string $resourceClass, string $operationName = null, array $context = [])
    {
        if (!isset($context['filters']) || !\is_array($context['filters'])) {
            parent::apply($queryBuilder, $queryNameGenerator, $resourceClass, $operationName, $context);

            return;
        }

        foreach ($this->generateExpressions($queryBuilder, $queryNameGenerator, $resourceClass, $operationName, $context) as $exp) {
            $queryBuilder->andWhere($exp);
        }
    }

    /** {@inheritdoc} */
    public function generateExpressions(QueryBuilder $queryBuilder, QueryNameGeneratorInterface $queryNameGenerator, string $resourceClass, string $operationName = null, array $context = [])
    {
        if (!isset($context['filters']) || !\is_array($context['filters'])) {
            return parent::generateExpressions($queryBuilder, $queryNameGenerator, $resourceClass, $operationName, $context);
        }

        $result = [];
        foreach ($context['filters'] as $property => $value) {
            $expressions = $this->filterProperty($this->denormalizePropertyName($property), $value, $queryBuilder, $queryNameGenerator, $resourceClass, $operationName, $context);
            if ($expressions !== null && $this instanceof QueryExpressionGeneratorInterface) {
                $result = array_merge($result, $expressions);
            }
        }
        return $result;
    }

}
