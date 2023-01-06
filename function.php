<?php

namespace R\DB;

/**
 * @template T
 * @param class-string<T> $class
 * @return Q<T>
 */
function Q(string $class)
{
    return new Q($class);
}
