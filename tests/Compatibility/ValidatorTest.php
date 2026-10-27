<?php

namespace R\DB\Tests\Compatibility;

use PHPUnit\Framework\TestCase;
use R\DB\Schema;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Validator\ValidatorInterface;
use Symfony\Component\Validator\ValidatorBuilder;

final class AttributeValidatedRecord
{
    #[Assert\NotBlank]
    public string $name = '';
}

final class AnnotationValidatedRecord
{
    /** @Assert\NotBlank */
    public string $name = '';
}

final class ValidatorTest extends TestCase
{
    private function schema(): Schema
    {
        // Validator construction must not require a database connection.
        return new Schema('unused', '127.0.0.1', 'unused');
    }

    public function testAttributeValidationAndCachedValidator(): void
    {
        $schema = $this->schema();
        $validator = $schema->getValidator();
        self::assertSame($validator, $schema->getValidator());
        $record = new AttributeValidatedRecord();
        $violations = $validator->validate($record);
        self::assertCount(1, $violations);
        self::assertSame('name', $violations->get(0)->getPropertyPath());
        $record->name = '客戶';
        self::assertCount(0, $validator->validate($record));
    }

    public function testLegacyAnnotationsRemainSupported(): void
    {
        $record = new AnnotationValidatedRecord();
        $validator = $this->schema()->getValidator();
        // Symfony 6.4 deliberately warns on legacy annotations. Assert this
        // specific warning without masking other issues or failing the suite for it.
        $deprecations = [];
        $previous = null;
        $previous = set_error_handler(static function ($level, $message, $file, $line) use (&$deprecations, &$previous) {
            if ($level === E_USER_DEPRECATED && str_contains($message, 'AnnotationValidatedRecord::$name" uses Doctrine Annotations')) {
                $deprecations[] = $message;
                return true;
            }
            return $previous ? $previous($level, $message, $file, $line) : false;
        });
        try {
            $violations = $validator->validate($record);
        } finally {
            restore_error_handler();
        }
        self::assertCount(method_exists(ValidatorBuilder::class, 'enableAttributeMapping') ? 1 : 0, $deprecations);
        self::assertCount(1, $violations);
        self::assertSame('name', $violations->get(0)->getPropertyPath());
        $record->name = 'valid';
        self::assertCount(0, $validator->validate($record));
    }

    public function testSuppliedValidatorIsPreserved(): void
    {
        $schema = $this->schema();
        $validator = $this->createStub(ValidatorInterface::class);
        $schema->setDefaultValidator($validator);
        self::assertSame($validator, $schema->getValidator());
    }
}
