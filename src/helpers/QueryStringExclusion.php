<?php

namespace lameco\blitz\helpers;

/**
 * Decides whether a request's query string makes it uncacheable.
 *
 * The plugin forces Blitz to cache every URL with a query string as the same page, so a listed
 * parameter must exclude the request whenever it is present, whatever its value: a check on the
 * value would let `?q=0` or `?q=` render into the cache under the plain URL.
 */
final class QueryStringExclusion
{
    /**
     * @return string[]
     */
    public static function parseParamList(string $list): array
    {
        $params = [];

        foreach (explode(',', $list) as $param) {
            // PHP parses `?filters[]=1` into the key `filters`, so `filters[]` has to match that key.
            $param = preg_replace('/(\[\])+$/', '', trim($param)) ?? '';

            if ($param !== '') {
                $params[] = $param;
            }
        }

        return array_values(array_unique($params));
    }

    /**
     * @param array<array-key, mixed> $queryParams
     * @param array<array-key, mixed> $sectionRows Rows of `section` ("sectionHandle:entryTypeHandle") and `queryStringParams`
     */
    public static function isExcluded(
        array $queryParams,
        string $globalParams,
        array $sectionRows,
        ?string $sectionHandle,
        ?string $entryTypeHandle,
    ): bool {
        if (self::hasAnyParam($queryParams, self::parseParamList($globalParams))) {
            return true;
        }

        if ($sectionHandle === null || $entryTypeHandle === null) {
            return false;
        }

        $match = $sectionHandle . ':' . $entryTypeHandle;

        foreach ($sectionRows as $row) {
            if (!is_array($row) || ($row['section'] ?? null) !== $match) {
                continue;
            }

            if (self::hasAnyParam($queryParams, self::parseParamList((string)($row['queryStringParams'] ?? '')))) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param array<array-key, mixed> $queryParams
     * @param string[] $params
     */
    private static function hasAnyParam(array $queryParams, array $params): bool
    {
        foreach ($params as $param) {
            if (array_key_exists($param, $queryParams)) {
                return true;
            }
        }

        return false;
    }
}
