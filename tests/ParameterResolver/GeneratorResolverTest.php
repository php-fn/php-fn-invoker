<?php declare(strict_types=1);

namespace Invoker\Test\ParameterResolver;

use Invoker\ParameterResolver\AssociativeArrayResolver;
use Invoker\ParameterResolver\Container\ParameterNameContainerResolver;
use Invoker\ParameterResolver\Container\TypeHintContainerResolver;
use Invoker\ParameterResolver\DefaultValueResolver;
use Invoker\ParameterResolver\GeneratorResolver;
use Invoker\ParameterResolver\NumericArrayResolver;
use Invoker\ParameterResolver\ParameterResolver;
use Invoker\ParameterResolver\TypeHintResolver;
use Invoker\Reflection\CallableReflection;
use Invoker\Test\Mock\ArrayContainer;
use phpDocumentor\Reflection\DocBlock\Tags\Param;
use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface;
use ReflectionException;
use ReflectionFunction;
use ReflectionParameter;

/**
 * @coversDefaultClass GeneratorResolver
 */
class GeneratorResolverTest extends TestCase
{
    public function providerSameBehaviour(): array
    {
        $assoc = new AssociativeArrayResolver();
        $numeric = new NumericArrayResolver();
        return [
            'assoc' => [
                [0 => 'A1', 2 => 'A3'],
                $this->resolvers(AssociativeArrayResolver::class),
                function ($a1, $a2, $a3) {
                },
                ['a1' => 'A1', 'a3' => 'A3'],
            ],
            'assoc-container' => [
                [1 => 'A2', 2 => 'A3'],
                $this->resolvers(ParameterNameContainerResolver::class, ['a3' => 'A3', 'a2' => 'A2']),
                function ($a1, $a2, $a3) {
                },
            ],
            'numeric' => [
                [1 => 'A2'],
                $this->resolvers(NumericArrayResolver::class),
                function ($a1, $a2, $a3) {
                },
                ['a1' => 'A1', 'a3' => 'A3', 1 => 'A2'],
            ],
            'type' => [
                [1 => $assoc, 2 => $numeric],
                $this->resolvers(TypeHintResolver::class),
                function ($a1, AssociativeArrayResolver $a2, NumericArrayResolver $a3) {
                },
                [AssociativeArrayResolver::class => $assoc, NumericArrayResolver::class => $numeric],
            ],
            'type-container' => [
                [0 => $assoc, 2 => $numeric],
                $this->resolvers(
                    TypeHintContainerResolver::class,
                    [AssociativeArrayResolver::class => $assoc, NumericArrayResolver::class => $numeric]
                ),
                function (AssociativeArrayResolver $a1, $a2, NumericArrayResolver $a3) {
                },
            ],
            'default' => [
                [1 => ['array'], 2 => 'string'],
                $this->resolvers(DefaultValueResolver::class),
                function ($a1, array $a2 = ['array'], $a3 = 'string') {
                },
            ],
        ];
    }

    private function resolvers(string $class, array $entries = []): array
    {
        $container = new ArrayContainer($entries);
        return [
            'class' => $this->classes($container)[$class],
            'generator' => $this->generators($container)[$class],
        ];
    }

    private function classes(ContainerInterface $container): array
    {
        return [
            AssociativeArrayResolver::class => new AssociativeArrayResolver(),
            NumericArrayResolver::class => new NumericArrayResolver(),
            TypeHintResolver::class => new TypeHintResolver(),
            DefaultValueResolver::class => new DefaultValueResolver(),
            TypeHintContainerResolver::class => new TypeHintContainerResolver($container),
            ParameterNameContainerResolver::class => new ParameterNameContainerResolver($container),
        ];
    }

    /**
     * @param ContainerInterface $container
     *
     * @return ParameterResolver[]
     */
    private function generators(ContainerInterface $container): array
    {
        $generators = [

            AssociativeArrayResolver::class => function (
                ReflectionParameter $parameter,
                array $provided
            ) {
                if (array_key_exists($parameter->name, $provided)) {
                    yield $provided[$parameter->name];
                }
            },

            NumericArrayResolver::class => function (
                ReflectionParameter $parameter,
                array $provided
            ) {
                if (array_key_exists($parameter->getPosition(), $provided)) {
                    yield $provided[$parameter->getPosition()];
                }
            },

            TypeHintResolver::class => function (
                ReflectionParameter $parameter,
                array $provided
            ) {
                if (($class = $parameter->getClass()) && array_key_exists($class->name, $provided)) {
                    yield $provided[$class->name];
                }
            },

            DefaultValueResolver::class => function (
                ReflectionParameter $parameter
            ) {
                if ($parameter->isOptional()) {
                    try {
                        yield $parameter->getDefaultValue();
                    } catch (ReflectionException $e) {
                        // Can't get default values from PHP internal classes and functions
                    }
                }
            },

            TypeHintContainerResolver::class => function (
                ReflectionParameter $parameter
            ) use ($container) {
                if (($class = $parameter->getClass()) && $container->has($class->name)) {
                    yield $container->get($class->name);
                }
            },

            ParameterNameContainerResolver::class => function (
                ReflectionParameter $parameter
            ) use ($container) {
                if (($name = $parameter->name) && $container->has($name)) {
                    yield $container->get($name);
                }
            },

        ];

        return array_map(function (callable $generator) {
            return new GeneratorResolver($generator);
        }, $generators);
    }

    /**
     * @covers ::getParameters
     * @dataProvider providerSameBehaviour
     *
     * @param array $expected
     * @param ParameterResolver[] $resolvers
     * @param callable $callable
     * @param array $provided
     * @param array $resolved
     */
    public function testSameBehaviour(
        array $expected,
        array $resolvers,
        callable $callable,
        array $provided = [],
        array $resolved = []
    ): void {
        $reflection = CallableReflection::create($callable);
        foreach ($resolvers as $type => $resolver) {
            $actual = $resolver->getParameters($reflection, $provided, $resolved);
            self::assertSame($expected, $actual, $type);
        }
    }

    /**
     * @covers ::getParameters
     */
    public function testGetParametersWithDocBlockTags(): void
    {
        $actual = (new GeneratorResolver(function (ReflectionParameter $parameter, array $provided, Param $tag = null) {
            yield [
                $parameter->getName() => $tag ? [
                    'type' => (string)$tag->getType(),
                    'desc' => (string)$tag->getDescription(),
                ] : null
            ];
        }))->getParameters(new ReflectionFunction(
        /**
         * @param string $a1
         * @param bool $a3
         * @param mixed ...$a4 any description
         */
            function ($a1, $a2, $a3 = 'default', ...$a4) {
            }
        ), [], []);

        self::assertEquals([
            ['a1' => ['type' => 'string', 'desc' => '']],
            ['a2' => null],
            ['a3' => ['type' => 'bool', 'desc' => '']],
            ['a4' => ['type' => 'mixed', 'desc' => 'any description']],
        ], $actual);
    }
}
