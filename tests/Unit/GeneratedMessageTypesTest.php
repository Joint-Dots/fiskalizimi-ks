<?php

namespace Jointdots\FiskalizimiKs\Tests\Unit;

use Google\Protobuf\RepeatedField;
use Jointdots\FiskalizimiKs\Generated\CitizenCoupon;
use Jointdots\FiskalizimiKs\Generated\CouponItem;
use Jointdots\FiskalizimiKs\Generated\Payment;
use Jointdots\FiskalizimiKs\Generated\PosCoupon;
use Jointdots\FiskalizimiKs\Generated\TaxGroup;
use Jointdots\FiskalizimiKs\Tests\TestCase;
use ReflectionClass;
use ReflectionNamedType;
use ReflectionUnionType;

/**
 * The generated messages must name types by a name that can be autoloaded.
 *
 * They once did not. Every repeated setter declared
 * `Google\Protobuf\Internal\RepeatedField`, which is where the class lived on
 * protobuf 3.x. On the 4.x and 5.x runtimes this package supports it is
 * `Google\Protobuf\RepeatedField`, and the old name survives only as a
 * `class_alias` written at the foot of the new class's own file.
 *
 * An alias is not a file, so Composer cannot load the old name: `class_exists`
 * on it is false from a cold start and only turns true once something unrelated
 * has pulled in `RepeatedField.php`. Nothing broke at runtime -- by the time a
 * coupon is built the real class is loaded and the alias exists -- but every
 * tool that resolves a type without running the code sees a signature naming a
 * class that is not there.
 *
 * These assertions look at the declared names rather than calling
 * `class_exists`, precisely because the alias would make `class_exists` answer
 * differently depending on what ran first.
 */
class GeneratedMessageTypesTest extends TestCase
{
    /** @var list<class-string> */
    private const MESSAGES = [
        CitizenCoupon::class,
        CouponItem::class,
        Payment::class,
        PosCoupon::class,
        TaxGroup::class,
    ];

    /**
     * No generated signature names a class Composer cannot autoload.
     *
     * `Google\Protobuf\Internal\` is a real namespace with real files in it --
     * `GPBUtil`, `GPBType`, `Message` all still live there -- so this checks the
     * mapping from name to file rather than banning the namespace.
     */
    public function test_generated_signatures_name_only_autoloadable_classes(): void
    {
        $unloadable = [];

        foreach (self::MESSAGES as $message) {
            foreach ($this->declaredTypeNames($message) as $method => $names) {
                foreach ($names as $name) {
                    if ($this->fileFor($name) === null) {
                        $unloadable[] = "{$message}::{$method}(): {$name}";
                    }
                }
            }
        }

        $this->assertSame([], $unloadable);
    }

    /** The repeated setters name the class protobuf 4.x and 5.x actually ship. */
    public function test_repeated_setters_name_the_canonical_repeated_field(): void
    {
        $seen = [];

        foreach (self::MESSAGES as $message) {
            foreach ($this->declaredTypeNames($message) as $names) {
                foreach ($names as $name) {
                    if (str_ends_with($name, 'RepeatedField')) {
                        $seen[] = $name;
                    }
                }
            }
        }

        $this->assertNotEmpty($seen, 'No repeated setter was found; this test is watching nothing.');
        $this->assertSame([RepeatedField::class], array_values(array_unique($seen)));
    }

    /**
     * A repeated field survives being read off one message and set on another.
     *
     * Round-tripping a coupon does exactly this, and it is the path that reaches
     * the repeated setters with something other than an `array`.
     */
    public function test_a_repeated_field_can_be_set_from_another_message(): void
    {
        $source = new PosCoupon;
        $source->setItems([
            (new CouponItem)->setName('Kafe')->setPrice(150)->setUnit('cope')->setQuantity(1.0)->setTotal(1500000),
        ]);

        $items = $source->getItems();
        $this->assertInstanceOf(RepeatedField::class, $items);

        $target = new PosCoupon;
        $target->setItems($items);

        $this->assertCount(1, $target->getItems());
        $this->assertSame('Kafe', $target->getItems()[0]->getName());
    }

    /**
     * Where a class name resolves to on disk, or null if Composer cannot map it.
     *
     * Asking the autoloader rather than `class_exists` is what makes an aliased
     * name -- one with no file of its own -- fail here.
     */
    private function fileFor(string $class): ?string
    {
        foreach (spl_autoload_functions() ?: [] as $autoloader) {
            if (is_array($autoloader) && $autoloader[0] instanceof \Composer\Autoload\ClassLoader) {
                $file = $autoloader[0]->findFile($class);

                return $file === false ? null : $file;
            }
        }

        $this->fail('No Composer autoloader is registered; this test cannot check anything.');
    }

    /**
     * The non-builtin class names each generated method declares, by method name.
     *
     * @param  class-string  $message
     * @return array<string, list<string>>
     */
    private function declaredTypeNames(string $message): array
    {
        $names = [];

        foreach ((new ReflectionClass($message))->getMethods() as $method) {
            if ($method->getDeclaringClass()->getName() !== $message) {
                continue;
            }

            $types = array_map(fn ($parameter) => $parameter->getType(), $method->getParameters());
            $types[] = $method->getReturnType();

            $declared = [];

            foreach ($types as $type) {
                $declared = array_merge($declared, $this->namedTypes($type));
            }

            if ($declared !== []) {
                $names[$method->getName()] = array_values(array_unique($declared));
            }
        }

        return $names;
    }

    /**
     * The class names a type expression mentions, flattening unions.
     *
     * @return list<string>
     */
    private function namedTypes(?\ReflectionType $type): array
    {
        if ($type instanceof ReflectionUnionType) {
            $names = [];

            foreach ($type->getTypes() as $inner) {
                $names = array_merge($names, $this->namedTypes($inner));
            }

            return $names;
        }

        if ($type instanceof ReflectionNamedType && ! $type->isBuiltin()) {
            return [$type->getName()];
        }

        return [];
    }
}
