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

/**
 * Query Expression Generator
 *
 * Filters that do not return their expressions but
 * themselves add their expressions to $queryBuilder
 * should NOT declare to implement this interface.
 */
interface QueryExpressionGeneratorInterface
{
    /**
     * @return array of Doctrine\ORM\Query\Expr\* and/or string (DQL),
     * each of which must be self-contained in the sense that the intended
     * logic is not compromised if it is combined with the others and other
     * self-contained expressions by
     * Doctrine\ORM\Query\Expr\Andx or Doctrine\ORM\Query\Expr\Orx
     *
     * Adds parameters and joins to $queryBuilder.
     * Caller of this function is responsable for adding the generated
     * expressions to $queryBuilder so that the parameters in the query will
     * correspond 1 to 1 with the parameters that where added by this function.
     * In practice this comes down to adding EACH expression to $queryBuilder
     * once and only once.
     */
    public function generateExpressions(QueryBuilder $queryBuilder, QueryNameGeneratorInterface $queryNameGenerator, string $resourceClass, string $operationName = null, array $context = []);
}
