<?php

namespace R\DB\Tests\Compatibility;

use GraphQL\Type\Definition\ObjectType;
use GraphQL\Type\Definition\Type;
use PHPUnit\Framework\TestCase;
use R\DB\GraphQLite\Mappers\MissingParameterException;
use R\DB\GraphQLite\Mappers\TypeMapper;
use R\DB\GraphQLite\Mappers\TypeMapperFactory;
use R\DB\Query;
use TheCodingMachine\GraphQLite\FactoryContext;
use TheCodingMachine\GraphQLite\Mappers\TypeMapperInterface;

final class GraphQLMapperTest extends TestCase
{
    private function mapper(): TypeMapper
    {
        // These mappings do not resolve anything through FactoryContext.
        $context = (new \ReflectionClass(FactoryContext::class))->newInstanceWithoutConstructor();
        return (new TypeMapperFactory())->create($context);
    }

    public function testMapperLoadsAndBuildsTheExistingQueryWrapper(): void
    {
        $mapper = $this->mapper();
        self::assertInstanceOf(TypeMapperInterface::class, $mapper);
        self::assertTrue($mapper->canMapClassToType(Query::class));
        self::assertFalse($mapper->canMapClassToType(\stdClass::class));
        $item = new ObjectType(['name' => 'Item', 'fields' => ['name' => Type::string()]]);
        $wrapper = $mapper->mapClassToType(Query::class, $item);
        self::assertSame('DB_QUERY_Item', $wrapper->name);
        self::assertSame($wrapper, $mapper->mapClassToType(Query::class, $item));
        $wrapper->freeze();
        self::assertSame(['data', 'meta'], array_keys($wrapper->getFields()));
        self::assertSame('Int', (string) $wrapper->getField('data')->args[0]->getType());
    }

    public function testMetadataTypeIsCachedAndHasTheExistingFields(): void
    {
        $mapper = $this->mapper();
        $meta = $mapper->mapNameToType('DB_META');
        self::assertSame('DB_META', $meta->name);
        self::assertSame(['total', 'class', 'key'], array_keys($meta->getFields()));
        self::assertSame($meta, $mapper->mapNameToType('DB_META'));
    }

    public function testMissingSubtypeStillRaisesTheOriginalException(): void
    {
        $this->expectException(MissingParameterException::class);
        $this->mapper()->mapClassToType(Query::class, null);
    }
}
