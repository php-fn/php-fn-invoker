<?php declare(strict_types=1);
/** @noinspection PhpUnhandledExceptionInspection */

namespace Invoker\Test\ParameterResolver;

use Invoker\Invoker;
use Invoker\ParameterResolver\AssociativeArrayResolver;
use Invoker\ParameterResolver\AssociativeVariadicResolver;
use Invoker\ParameterResolver\ResolverChain;
use PHPUnit\Framework\TestCase;
use ReflectionFunction;

class AssociativeVariadicResolverTest extends TestCase
{
    /**
     * @covers AssociativeVariadicResolver::__invoke
     */
    public function testInvoke(): void
    {
        $callable = function($p1, ...$p2) {
            return \func_get_args();
        };
        $ref = new ReflectionFunction($callable);

        $resolverCast = new ResolverChain([new AssociativeVariadicResolver(), new AssociativeArrayResolver()]);
        $invokerCast  = new Invoker($resolverCast);
        $resolver     = new ResolverChain([new AssociativeVariadicResolver(false), new AssociativeArrayResolver()]);
        $invoker      = new Invoker($resolver);

        // parameter provided as array
        $provided = ['p2' => ['P21', 'P22'], 'p1' => 'P1'];
        self::assertSame([1 => 'P21', 2 => 'P22', 0 => 'P1'], $resolverCast->getParameters($ref, $provided, []));
        self::assertSame(['P1', 'P21', 'P22'], $invokerCast->call($callable, $provided));
        self::assertSame([1 => ['P21', 'P22'], 0 => 'P1'], $resolver->getParameters($ref, $provided, []));
        self::assertSame(['P1', ['P21', 'P22']], $invoker->call($callable, $provided));

        // parameter provided as non-array => cast
        $provided = ['p1' => 'P1', 'p2' => 'P2'];
        self::assertSame([1 => 'P2', 0 => 'P1'], $resolverCast->getParameters($ref, $provided, []));
        self::assertSame(['P1', 'P2'], $invokerCast->call($callable, $provided));
        self::assertSame([1 => 'P2', 0 => 'P1'], $resolver->getParameters($ref, $provided, []));
        self::assertSame(['P1', 'P2'], $invoker->call($callable, $provided));

        // parameter is not provided => no exception
        $provided = ['p1' => 'P1'];
        self::assertSame(['P1'], $resolverCast->getParameters($ref, $provided, []));
        self::assertSame(['P1'], $invokerCast->call($callable, $provided));
        self::assertSame(['P1'], $resolver->getParameters($ref, $provided, []));
        self::assertSame(['P1'], $invoker->call($callable, $provided));
    }
}
