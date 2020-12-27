<?php declare(strict_types=1);
/** @noinspection PhpUnhandledExceptionInspection */

namespace Invoker\Test\ParameterResolver;

use ArrayIterator;
use EmptyIterator;
use Invoker\ParameterResolver\GeneratorResolver;
use Invoker\ParameterResolver\ResolverChain;
use Invoker\ParameterResolver\TypeHintVariadicResolver;
use Iterator;
use PHPUnit\Framework\TestCase;
use ReflectionFunction;
use function func_get_args;

class TypeHintVariadicResolverTest extends TestCase
{
    /**
     * @covers TypeHintVariadicResolver::__invoke
     */
    public function testInvokeNoHint(): void
    {
        $fn = function ($a, ...$b) {
            return func_get_args();
        };
        $this->assertParams([], ['b' => 'B', 'a' => 'A'], $fn, new TypeHintVariadicResolver());
        $this->assertParams([], ['b' => 'B', 'a' => 'A'], $fn, new TypeHintVariadicResolver(true));
    }

    private function assertParams(
        array $expected,
        array $provided,
        callable $callable,
        GeneratorResolver $resolver
    ): void {
        $resolver = new ResolverChain([$resolver]);
        self::assertSame($expected, $resolver->getParameters(new ReflectionFunction($callable), $provided, []));
    }

    /**
     * @covers TypeHintVariadicResolver::__invoke
     */
    public function testInvokeArray(): void
    {
        $fn = function ($a, array ...$b) {
            return func_get_args();
        };
        $provided = ['b' => ['B'], 'a' => $this, ['C']];
        $this->assertParams([1 => ['B'], ['C']], $provided, $fn, new TypeHintVariadicResolver());
        $this->assertParams([1 => ['C']], $provided, $fn, new TypeHintVariadicResolver(true));
    }

    /**
     * @covers TypeHintVariadicResolver::__invoke
     */
    public function testInvokeCallable(): void
    {
        $fn = function ($a, callable ...$b) {
            return func_get_args();
        };
        $provided = [
            'b' => $b = function () {
            },
            'a' => $this,
            $c = [$this, 'testInvokeCallable']
        ];
        $this->assertParams([1 => $b, $c], $provided, $fn, new TypeHintVariadicResolver());
        $this->assertParams([1 => $c], $provided, $fn, new TypeHintVariadicResolver(true));
    }

    /**
     * @covers TypeHintVariadicResolver::__invoke
     */
    public function testInvokeClass(): void
    {
        $fn = function ($a, Iterator ...$b) {
            return func_get_args();
        };
        $provided = ['b' => $b = new ArrayIterator, 'a' => $this, $c = new EmptyIterator()];
        $this->assertParams([1 => $b, $c], $provided, $fn, new TypeHintVariadicResolver());
        $this->assertParams([1 => $c], $provided, $fn, new TypeHintVariadicResolver(true));
    }
}
