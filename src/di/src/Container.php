<?php

declare(strict_types=1);
/**
 * This file is part of Hyperf.
 *
 * @link     https://www.hyperf.io
 * @document https://doc.hyperf.io
 * @contact  group@hyperf.io
 * @license  https://github.com/hyperf/hyperf/blob/master/LICENSE
 */

namespace Hyperf\Di;

use Hyperf\Di\Definition\DefinitionInterface;
use Hyperf\Di\Definition\ObjectDefinition;
use Hyperf\Di\Exception\NotFoundException;
use Hyperf\Di\Resolver\ResolverDispatcher;
use Hyperf\Dispatcher\Exceptions\InvalidArgumentException;
use Psr\Container\ContainerInterface;
use Psr\Container\NotFoundExceptionInterface;

class Container implements ContainerInterface
{
    /**
     * Map of entries that are already resolved.
     *
     * @var array
     */
    private $resolvedEntries = [];

    /**
     * Map of definitions that are already fetched (local cache).
     *
     * @var (DefinitionInterface|null)[]
     */
    private $fetchedDefinitions = [];

    /**
     * @var Definition\DefinitionSourceInterface
     */
    private $definitionSource;

    /**
     * @var Resolver\ResolverInterface
     */
    private $definitionResolver;

    /**
     * @TODO Extract ProxyFactory to a Interface.
     * @var ProxyFactory
     */
    private $proxyFactory;

    /**
     * Container constructor.
     * @param Definition\DefinitionSourceInterface $definitionSource
     */
    public function __construct(Definition\DefinitionSourceInterface $definitionSource)
    {
        $this->definitionSource = $definitionSource;
        $this->definitionResolver = new ResolverDispatcher($this);
        $this->proxyFactory = new ProxyFactory();
        // Auto-register the container.
        $this->resolvedEntries = [
            self::class => $this,
            ContainerInterface::class => $this,
            ProxyFactory::class => $this->proxyFactory,
        ];
    }

    /**
     * Build an entry of the container by its name.
     * This method behave like get() except resolves the entry again every time.
     * For example if the entry is a class then a new instance will be created each time.
     * This method makes the container behave like a factory.
     *
     * @param string $name entry name or a class name
     * @param array $parameters Optional parameters to use to build the entry. Use this to force specific parameters
     *                          to specific values. Parameters not defined in this array will be resolved using
     *                          the container.
     * @throws InvalidArgumentException the name parameter must be of type string
     * @throws NotFoundException no entry found for the given name
     */
    public function make(string $name, array $parameters = [])
    {
        $definition = $this->getDefinition($name);

        if (! $definition) {
            throw new NotFoundException("No entry or class found for '{$name}'");
        }

        return $this->resolveDefinition($definition, $parameters);
    }

    /**
     * Finds an entry of the container by its identifier and returns it.
     *
     * @param string $name identifier of the entry to look for
     * @return mixed entry
     */
    public function get($name)
    {
        // If the entry is already resolved we return it
        if (isset($this->resolvedEntries[$name]) || array_key_exists($name, $this->resolvedEntries)) {
            return $this->resolvedEntries[$name];
        }
        $this->resolvedEntries[$name] = $value = $this->make($name);
        return $value;
    }

    /**
     * Returns true if the container can return an entry for the given identifier.
     * Returns false otherwise.
     * `has($name)` returning true does not mean that `get($name)` will not throw an exception.
     * It does however mean that `get($name)` will not throw a `NotFoundExceptionInterface`.
     *
     * @param string $name identifier of the entry to look for
     */
    public function has($name): bool
    {
        if (! is_string($name)) {
            throw new InvalidArgumentException(sprintf('The name parameter must be of type string, %s given', is_object($name) ? get_class($name) : gettype($name)));
        }

        if (array_key_exists($name, $this->resolvedEntries)) {
            return true;
        }

        $definition = $this->getDefinition($name);
        if ($definition === null) {
            return false;
        }

        if ($definition instanceof ObjectDefinition) {
            return $definition->isInstantiable();
        }

        return true;
    }

    public function getProxyFactory(): ProxyFactory
    {
        return $this->proxyFactory;
    }

    public function getDefinitionSource(): Definition\DefinitionSourceInterface
    {
        return $this->definitionSource;
    }

    protected function setDefinition(string $name, DefinitionInterface $definition): void
    {
        // Clear existing entry if it exists
        if (array_key_exists($name, $this->resolvedEntries)) {
            unset($this->resolvedEntries[$name]);
        }
        $this->fetchedDefinitions = []; // Completely clear this local cache

        $this->definitionSource->addDefinition($name, $definition);
    }

    private function getDefinition(string $name): ?DefinitionInterface
    {
        // Local cache that avoids fetching the same definition twice
        if (! array_key_exists($name, $this->fetchedDefinitions)) {
            $this->fetchedDefinitions[$name] = $this->definitionSource->getDefinition($name);
        }

        return $this->fetchedDefinitions[$name];
    }

    /**
     * Resolves a definition.
     */
    private function resolveDefinition(DefinitionInterface $definition, array $parameters = [])
    {
        return $this->definitionResolver->resolve($definition, $parameters);
    }
}
