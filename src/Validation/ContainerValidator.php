<?php

/*
 * Copyright (c) 2022-2026 Matthew Suhocki. All rights reserved.
 *
 * This software is licensed under the terms of the MIT License <https://opensource.org/licenses/MIT>.
 * The above copyright notice and this notice shall be included in all copies or substantial portions of this software.
 */

declare(strict_types=1);

namespace Suhock\DependencyInjection\Validation;

use ReflectionClass;
use Suhock\DependencyInjection\Builder\Descriptor;
use Suhock\DependencyInjection\DescriptorId;
use Suhock\DependencyInjection\Key;
use Suhock\DependencyInjection\Lifetime\ScopedStrategy;
use Suhock\DependencyInjection\Lifetime\SingletonStrategy;
use Suhock\DependencyInjection\Lifetime\TransientStrategy;
use Suhock\DependencyInjection\Resolver\ResolutionPlan;
use Suhock\DependencyInjection\Resolver\ResolutionPlanEdge;
use Suhock\DependencyInjection\Resolver\ResolutionPlanKind;

use function array_map;
use function array_pop;
use function array_search;
use function array_shift;
use function array_slice;
use function array_unshift;
use function class_exists;
use function count;
use function implode;
use function interface_exists;
use function is_a;
use function min;
use function sprintf;

/**
 * Runs the guaranteed-failure graph checks over a set of compiled {@see ResolutionPlan}s: unresolvable required
 * dependencies, invalid members, factory type mismatches, all-required-edge dependency cycles, and captive
 * dependencies (a singleton reaching a scoped service through required edges). Resolvability is descriptor-map
 * membership, for keyed and unkeyed dependencies alike. Soft (defaulted or nullable) edges self-heal at runtime and
 * are never issues. Everything found is aggregated into one {@see ContainerValidationException}.
 *
 * @internal
 */
final class ContainerValidator
{
    /**
     * @param array<string, Descriptor<object>> $descriptors The full descriptor map the plans were compiled from,
     *     keyed by descriptor id
     */
    public function __construct(
        private readonly array $descriptors,
    ) {}

    /**
     * @param array<string, ResolutionPlan<object>> $plans The compiled plans, keyed by descriptor id
     *
     * @throws ContainerValidationException If the configuration contains any guaranteed-failure defect
     */
    public function validate(array $plans): void
    {
        $issues = $this->collect($plans);

        if (count($issues) > 0) {
            throw new ContainerValidationException($issues);
        }
    }

    /**
     * @param array<string, ResolutionPlan<object>> $plans
     *
     * @return list<ValidationIssue<object>>
     */
    private function collect(array $plans): array
    {
        $issues = [];

        /** @var array<string, list<array{string, bool}>> $adjacency target id and required flag per chosen edge */
        $adjacency = [];

        foreach ($plans as $id => $plan) {
            $descriptor = $this->descriptors[$id] ?? null;

            if ($descriptor === null) {
                continue;
            }

            $issues = [
                ...$issues,
                ...$this->planIssues($id, $descriptor, $plan),
                ...$this->lazyIssues($id, $descriptor, $plan, $plans),
            ];
            $adjacency[$id] = $this->chosenEdges($plan);
        }

        return [
            ...$issues,
            ...$this->cycleIssues($adjacency),
            ...$this->captiveIssues($adjacency),
        ];
    }

    /**
     * Exports the configuration's dependency graph: every service and every satisfied, chosen edge, with the
     * injection point each edge flows through. Mirrors exactly what resolution would traverse; unsatisfiable
     * injection points produce no edge. Purely informational: a
     * defective configuration still exports.
     *
     * @param array<string, ResolutionPlan<object>> $plans The compiled plans, keyed by descriptor id
     */
    public function exportGraph(array $plans): DependencyGraph
    {
        $serviceIds = [];

        foreach ($this->descriptors as $id => $descriptor) {
            $serviceIds[] = DescriptorId::display($id);
        }

        $edges = [];

        foreach ($plans as $id => $plan) {
            if (!isset($this->descriptors[$id])) {
                continue;
            }

            $sourceId = DescriptorId::display($id);

            if ($plan->implementationTarget !== null && isset($this->descriptors[$plan->implementationTarget])) {
                $edges[] = new DependencyGraphEdge(
                    $sourceId,
                    DescriptorId::display($plan->implementationTarget),
                    required: true,
                    injectionPoint: 'the implementation class',
                );
            }

            foreach (self::describedEdges($plan) as [$description, $edge]) {
                $target = $this->chosenTarget($edge);

                if ($target !== null) {
                    $edges[] = new DependencyGraphEdge(
                        $sourceId,
                        DescriptorId::display($target),
                        required: !$edge->soft,
                        injectionPoint: $description,
                    );
                }
            }
        }

        return new DependencyGraph($serviceIds, $edges);
    }

    /**
     * Every edge of a plan paired with a description of its injection point, e.g.
     * <code>["parameter $x of __construct()", $edge]</code>.
     *
     * @template TClass of object
     *
     * @param ResolutionPlan<TClass> $plan
     *
     * @return iterable<array{string, ResolutionPlanEdge}>
     */
    private static function describedEdges(ResolutionPlan $plan): iterable
    {
        $argumentLocation = $plan->kind === ResolutionPlanKind::Factory ? 'the factory' : '__construct()';

        foreach ($plan->argumentEdges as $edge) {
            yield [sprintf('parameter $%s of %s', $edge->name, $argumentLocation), $edge];
        }

        foreach ($plan->mutatorEdges as $edge) {
            yield [sprintf('parameter $%s of the mutator', $edge->name), $edge];
        }
    }

    /**
     * The defects local to one service: non-instantiable classes, factory return-type mismatches, an unresolvable
     * implementation target, and unsatisfiable required edges.
     *
     * @template TClass of object
     *
     * @param Descriptor<TClass> $descriptor
     * @param ResolutionPlan<TClass> $plan
     *
     * @return list<ValidationIssue<TClass>>
     */
    private function planIssues(string $id, Descriptor $descriptor, ResolutionPlan $plan): array
    {
        $issues = [];
        $key = DescriptorId::keyOf($id);

        if ($plan->nonInstantiableMessage !== null) {
            $issues[] = new ValidationIssue(
                $descriptor->className,
                $key,
                ValidationIssueKind::NonInstantiableClass,
                $plan->nonInstantiableMessage,
            );
        }

        if (
            $plan->declaredFactoryReturnType !== null
            && self::returnTypeCanNeverSatisfy($plan->declaredFactoryReturnType, $descriptor->className)
        ) {
            $issues[] = new ValidationIssue(
                $descriptor->className,
                $key,
                ValidationIssueKind::FactoryReturnTypeMismatch,
                "the factory declares return type $plan->declaredFactoryReturnType, which can never be an"
                    . " instance of $descriptor->className",
            );
        }

        if ($plan->implementationTarget !== null && !isset($this->descriptors[$plan->implementationTarget])) {
            $issues[] = new ValidationIssue(
                $descriptor->className,
                $key,
                ValidationIssueKind::MissingImplementation,
                "the implementation class $plan->implementationTarget is not itself a resolvable service",
            );
        }

        foreach (self::describedEdges($plan) as [$description, $edge]) {
            if ($edge->soft || $this->edgeIsSatisfied($edge)) {
                continue;
            }

            $issues[] = $edge->dependency === null
                ? new ValidationIssue(
                    $descriptor->className,
                    $key,
                    ValidationIssueKind::UnresolvableParameter,
                    "required $description ($edge->declaredType) has a type the container is never consulted for"
                        . ' and no default value',
                )
                : new ValidationIssue(
                    $descriptor->className,
                    $key,
                    $edge->dependency->key !== null
                        ? ValidationIssueKind::MissingKeyedDependency
                        : ValidationIssueKind::MissingDependency,
                    "required $description (" . self::describeAlternatives($edge) . ') is not resolvable',
                );
        }

        return $issues;
    }

    /**
     * The lazy-injection defects of one service: a #[Lazy] parameter the container cannot construct lazily, because
     * it has no class type, or resolves to a factory-produced service whose concrete class is not statically known.
     * A lazy edge that is simply unresolvable is not a lazy defect: a required one is reported as a missing
     * dependency, a soft one self-heals, and neither ever builds a lazy object.
     *
     * @template TClass of object
     *
     * @param Descriptor<TClass> $descriptor
     * @param ResolutionPlan<TClass> $plan
     * @param array<string, ResolutionPlan<object>> $plans
     *
     * @return list<ValidationIssue<TClass>>
     */
    private function lazyIssues(string $id, Descriptor $descriptor, ResolutionPlan $plan, array $plans): array
    {
        $issues = [];
        $key = DescriptorId::keyOf($id);

        foreach (self::describedEdges($plan) as [$description, $edge]) {
            if (!$edge->lazy) {
                continue;
            }

            $message = $this->lazyDefect($edge, $description, $plans);

            if ($message !== null) {
                $issues[] = new ValidationIssue(
                    $descriptor->className,
                    $key,
                    ValidationIssueKind::UnbuildableLazyDependency,
                    $message,
                );
            }
        }

        return $issues;
    }

    /**
     * The reason a lazy edge cannot be built, or <code>null</code> if it can (or is unresolvable, handled elsewhere).
     *
     * @param array<string, ResolutionPlan<object>> $plans
     */
    private function lazyDefect(ResolutionPlanEdge $edge, string $description, array $plans): ?string
    {
        if ($edge->dependency === null) {
            return "lazy $description (" . ($edge->declaredType ?? 'untyped')
                . ') has no class type the container can construct lazily';
        }

        $target = $this->chosenTarget($edge);

        if ($target === null || $this->targetIsLazyBuildable($target, $plans, [])) {
            return null;
        }

        return "lazy $description resolves to " . DescriptorId::display($target)
            . ', which the container cannot construct as a lazy object: a factory-produced service with no'
            . ' statically-known concrete class, or a class with no properties to defer';
    }

    /**
     * Whether the container can build a native lazy object for a service: an autowired class (ghost) or a held
     * instance (already constructed) always can; a factory (proxy) can only when its concrete class is statically
     * known; an implementation defers to its target. Guards against an implementation cycle.
     *
     * @param array<string, ResolutionPlan<object>> $plans
     * @param array<string, true> $seen
     */
    private function targetIsLazyBuildable(string $targetId, array $plans, array $seen): bool
    {
        if (isset($seen[$targetId])) {
            return false;
        }

        $seen[$targetId] = true;
        $plan = $plans[$targetId] ?? null;

        if ($plan === null) {
            return false;
        }

        return match ($plan->kind) {
            // A held instance already exists, so a lazy edge to it is a harmless no-op rather than a defect.
            ResolutionPlanKind::Leaf => true,
            ResolutionPlanKind::AutowiredClass => self::classCanBeLazy($plan->className),
            ResolutionPlanKind::Factory => self::factoryTargetCanBeLazy($plan),
            ResolutionPlanKind::Implementation => $plan->implementationTarget !== null
                && isset($this->descriptors[$plan->implementationTarget])
                && $this->targetIsLazyBuildable($plan->implementationTarget, $plans, $seen),
        };
    }

    /**
     * Whether a factory-produced service can be proxied lazily: a concrete class must be statically known (the
     * declared return class if concrete, otherwise the service's own class) and that class must itself be lazy-able.
     *
     * @template TClass of object
     *
     * @param ResolutionPlan<TClass> $plan
     */
    private static function factoryTargetCanBeLazy(ResolutionPlan $plan): bool
    {
        $className = self::factoryLazyClass($plan);

        return $className !== null && self::classCanBeLazy($className);
    }

    /**
     * The statically known concrete, instantiable class a lazy proxy of a factory-produced service can reflect, or
     * <code>null</code> when neither the declared return type nor the service class is a concrete class.
     *
     * @template TClass of object
     *
     * @param ResolutionPlan<TClass> $plan
     *
     * @return class-string<TClass>|null
     */
    private static function factoryLazyClass(ResolutionPlan $plan): ?string
    {
        if (
            $plan->declaredFactoryReturnType !== null
            && class_exists($plan->declaredFactoryReturnType)
            && (new ReflectionClass($plan->declaredFactoryReturnType))->isInstantiable()
        ) {
            return $plan->declaredFactoryReturnType;
        }

        if (class_exists($plan->className) && (new ReflectionClass($plan->className))->isInstantiable()) {
            return $plan->className;
        }

        return null;
    }

    /**
     * Whether a class can be represented as a PHP native lazy object. It must declare at least one non-static
     * property: PHP has no state to defer for a property-less class, so it creates such an object eagerly and never
     * runs the ghost initializer or proxy factory, which would silently skip the constructor.
     *
     * @param class-string $className
     */
    private static function classCanBeLazy(string $className): bool
    {
        if (!class_exists($className)) {
            return false;
        }

        foreach ((new ReflectionClass($className))->getProperties() as $property) {
            if (!$property->isStatic()) {
                return true;
            }
        }

        return false;
    }

    /**
     * All-required-edge cycle detection over the chosen-edge graph, by depth-first search with a recursion stack. A
     * cycle containing a soft edge self-heals at runtime and is not reported. Each distinct cycle is reported once,
     * on its lexicographically smallest member.
     *
     * @param array<string, list<array{string, bool}>> $adjacency
     *
     * @return list<ValidationIssue<object>>
     */
    private function cycleIssues(array $adjacency): array
    {
        $issues = [];
        $reported = [];
        $visited = [];
        $stack = [];

        foreach ($adjacency as $id => $edges) {
            if (!isset($visited[$id])) {
                $this->visitForCycles($id, $adjacency, $visited, $stack, $reported, $issues);
            }
        }

        return $issues;
    }

    /**
     * @param array<string, list<array{string, bool}>> $adjacency
     * @param array<string, true> $visited
     * @param list<string> $stack
     * @param array<string, true> $reported
     * @param list<ValidationIssue<object>> $issues
     */
    private function visitForCycles(
        string $id,
        array $adjacency,
        array &$visited,
        array &$stack,
        array &$reported,
        array &$issues,
    ): void {
        $visited[$id] = true;
        $stack[] = $id;

        foreach ($adjacency[$id] ?? [] as [$target, $required]) {
            $stackIndex = array_search($target, $stack, true);

            if ($stackIndex !== false) {
                $cycle = array_slice($stack, $stackIndex);

                if (count($cycle) > 0) {
                    $issue = $this->cycleIssue($cycle, $adjacency, $reported);

                    if ($issue !== null) {
                        $issues[] = $issue;
                    }
                }

                continue;
            }

            if (!isset($visited[$target]) && isset($this->descriptors[$target])) {
                $this->visitForCycles($target, $adjacency, $visited, $stack, $reported, $issues);
            }
        }

        array_pop($stack);
    }

    /**
     * Builds the issue for one detected cycle, or returns <code>null</code> when the cycle contains a soft edge or
     * was already reported.
     *
     * @param non-empty-list<string> $cycle The descriptor ids on the cycle, in dependency order
     * @param array<string, list<array{string, bool}>> $adjacency
     * @param array<string, true> $reported Canonical cycle keys already reported, updated on report
     *
     * @return ValidationIssue<object>|null
     */
    private function cycleIssue(array $cycle, array $adjacency, array &$reported): ?ValidationIssue
    {
        $targets = [...array_slice($cycle, 1), $cycle[0]];

        foreach ($cycle as $index => $source) {
            $target = $targets[$index] ?? null;

            if ($target === null || !self::hasRequiredEdge($adjacency, $source, $target)) {
                return null;
            }
        }

        // Rotate so the cycle starts at its smallest id: the same cycle found from different entry points then
        // produces the same key and is reported once.
        $startIndex = (int) array_search(min($cycle), $cycle, true);
        $canonical = [...array_slice($cycle, $startIndex), ...array_slice($cycle, 0, $startIndex)];
        $canonicalKey = implode("\1", $canonical);

        if (isset($reported[$canonicalKey])) {
            return null;
        }

        $reported[$canonicalKey] = true;
        $first = $canonical[0] ?? null;
        $descriptor = $first === null ? null : $this->descriptors[$first] ?? null;

        if ($first === null || $descriptor === null) {
            return null;
        }

        $path = implode(' -> ', [...array_map(DescriptorId::display(...), $canonical), DescriptorId::display($first)]);

        return new ValidationIssue(
            $descriptor->className,
            DescriptorId::keyOf($first),
            ValidationIssueKind::CircularDependency,
            "every service on the dependency cycle $path requires the next, so none can ever be constructed",
        );
    }

    /**
     * Captive-dependency detection: a singleton constructs its entire required subgraph in the root context, so a
     * path of required edges from a singleton to a scoped service always throws. The search is context-transparent
     * through singleton and transient intermediaries.
     *
     * @param array<string, list<array{string, bool}>> $adjacency
     *
     * @return list<ValidationIssue<object>>
     */
    private function captiveIssues(array $adjacency): array
    {
        $issues = [];

        foreach ($this->descriptors as $id => $descriptor) {
            if (!$descriptor->lifetimeStrategy instanceof SingletonStrategy) {
                continue;
            }

            // Breadth-first over required edges, so the path reported per reached scoped service is shortest.
            $parents = [$id => null];
            $queue = [$id];
            $reportedTargets = [];

            while (count($queue) > 0) {
                $current = array_shift($queue);

                foreach ($adjacency[$current] ?? [] as [$target, $required]) {
                    if (!$required || isset($parents[$target])) {
                        continue;
                    }

                    $targetDescriptor = $this->descriptors[$target] ?? null;

                    if ($targetDescriptor === null) {
                        continue;
                    }

                    $parents[$target] = $current;

                    if ($targetDescriptor->lifetimeStrategy instanceof ScopedStrategy) {
                        if (!isset($reportedTargets[$target])) {
                            $reportedTargets[$target] = true;
                            $issues[] = $this->captiveIssue($id, $descriptor, $target, $parents);
                        }

                        continue;
                    }

                    if (
                        $targetDescriptor->lifetimeStrategy instanceof SingletonStrategy
                        || $targetDescriptor->lifetimeStrategy instanceof TransientStrategy
                    ) {
                        $queue[] = $target;
                    }
                }
            }
        }

        return $issues;
    }

    /**
     * @template TClass of object
     *
     * @param Descriptor<TClass> $descriptor The singleton's descriptor
     * @param array<string, string|null> $parents
     *
     * @return ValidationIssue<TClass>
     */
    private function captiveIssue(
        string $singletonId,
        Descriptor $descriptor,
        string $scopedId,
        array $parents,
    ): ValidationIssue {
        $path = [];

        for ($id = $scopedId; $id !== null; $id = $parents[$id] ?? null) {
            array_unshift($path, DescriptorId::display($id));
        }

        return new ValidationIssue(
            $descriptor->className,
            DescriptorId::keyOf($singletonId),
            ValidationIssueKind::CaptiveDependency,
            'singleton requires the scoped service ' . DescriptorId::display($scopedId) . ' via '
                . implode(' -> ', $path) . ', which always resolves outside of a scope',
        );
    }

    /**
     * The out-edges the runtime would choose against the frozen descriptor map: per satisfied dependency, the first
     * satisfiable alternative's first member present in the map; plus the implementation target, when present.
     * Unsatisfied and never-consulted edges produce no graph edge.
     *
     * @param ResolutionPlan<object> $plan
     *
     * @return list<array{string, bool}>
     */
    private function chosenEdges(ResolutionPlan $plan): array
    {
        $edges = [];

        if ($plan->implementationTarget !== null && isset($this->descriptors[$plan->implementationTarget])) {
            $edges[] = [$plan->implementationTarget, true];
        }

        foreach (self::describedEdges($plan) as [$description, $edge]) {
            $target = $this->chosenTarget($edge);

            if ($target !== null) {
                // A lazy edge does not construct its target while the service itself is built, so it can neither
                // form an all-required construction cycle nor drag a scoped service captive into a singleton.
                $edges[] = [$target, !$edge->soft && !$edge->lazy];
            }
        }

        return $edges;
    }

    private function chosenTarget(ResolutionPlanEdge $edge): ?string
    {
        if ($edge->dependency === null) {
            return null;
        }

        foreach ($edge->dependency->alternatives as $alternative) {
            foreach ($alternative as $className) {
                $targetId = DescriptorId::compute($className, $edge->dependency->key);

                if (isset($this->descriptors[$targetId])) {
                    return $targetId;
                }
            }
        }

        return null;
    }

    private function edgeIsSatisfied(ResolutionPlanEdge $edge): bool
    {
        return $edge->dependency !== null && $this->chosenTarget($edge) !== null;
    }

    private static function describeAlternatives(ResolutionPlanEdge $edge): string
    {
        if ($edge->dependency === null) {
            return '';
        }

        $types = implode(
            '|',
            array_map(static fn(array $alternative) => implode('&', $alternative), $edge->dependency->alternatives),
        );

        return $edge->dependency->key === null
            ? $types
            : "$types with key '" . Key::getKeyFromStringOrEnum($edge->dependency->key) . "'";
    }

    /**
     * Mirrors the conservative rule for factory return types: a mismatch is only guaranteed when the declared type
     * and the service class are unrelated in both directions and no unseen subtype could bridge them: the declared
     * type is final, or both are non-interface classes.
     */
    private static function returnTypeCanNeverSatisfy(string $returnType, string $className): bool
    {
        if (is_a($returnType, $className, true) || is_a($className, $returnType, true)) {
            return false;
        }

        if (class_exists($returnType) && (new ReflectionClass($returnType))->isFinal()) {
            return true;
        }

        return !interface_exists($returnType) && !interface_exists($className);
    }

    /**
     * @param array<string, list<array{string, bool}>> $adjacency
     */
    private static function hasRequiredEdge(array $adjacency, string $source, string $target): bool
    {
        foreach ($adjacency[$source] ?? [] as [$edgeTarget, $required]) {
            if ($edgeTarget === $target && $required) {
                return true;
            }
        }

        return false;
    }
}
