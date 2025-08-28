<?php

declare(strict_types=1);

namespace MauticPlugin\MauticDoiBundle\Helper;

use Doctrine\DBAL\Schema\Table;
use Doctrine\DBAL\Types\BigIntType;

class MigrationHelper
{
    public static function getReferencedColumnType(Table $table, string $columnName = 'id'): string
    {
        if (!$table->hasColumn($columnName)) {
            throw new \InvalidArgumentException("Table '{$table->getName()}' does not have an '{$columnName}' column");
        }

        $idColumn   = $table->getColumn($columnName);
        $columnType = $idColumn->getType();
        $isUnsigned = $idColumn->getUnsigned();
        $baseType   = $columnType instanceof BigIntType ? 'BIGINT' : 'INT';

        return $isUnsigned ? "{$baseType} UNSIGNED" : $baseType;
    }
}
