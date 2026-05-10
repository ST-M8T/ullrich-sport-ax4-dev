<?php

declare(strict_types=1);

use PhpParser\Node;
use PhpParser\Node\Arg;
use PhpParser\Node\Expr;
use PhpParser\Node\Expr\ArrayItem;
use PhpParser\Node\Expr\ClassConstFetch;
use PhpParser\Node\Expr\Closure;
use PhpParser\Node\Expr\MethodCall;
use PhpParser\Node\Expr\StaticCall;
use PhpParser\Node\Name;
use PhpParser\Node\Stmt\Expression;
use PhpParser\Node\Stmt\Namespace_;
use PhpParser\Node\Stmt\Use_;
use PhpParser\ParserFactory;

require_once __DIR__.'/../vendor/autoload.php';

final class SystemKartographieGenerator
{
    private const DEFAULT_OPTIONS = [
        'project-root' => '.',
        'output-dir' => 'docs',
        'route-name-for-coverage' => '—',
        'audit-report-name' => 'SYSTEM_AUDIT_REPORT.md',
        'menu-role-matrix-name' => 'SYSTEM_MENU_ROLE_MATRIX.md',
        'route-visibility-matrix-name' => 'SYSTEM_ROUTE_VISIBILITY_MATRIX.md',
        'reorganisation-roadmap-name' => 'SYSTEM_REORGANISATION_ROADMAP.md',
    ];

    /**
     * Fachliche Rollen-Zuordnung für das Management-Dashboard.
     * Falls die Produktrolle nicht abgebildet ist, wird die jeweilige Persona
     * im Audit als unbesetzt markiert.
     */
    private const PERSONA_ROLE_MAP = [
        'Mitarbeiter' => ['operations'],
        'Leiter' => ['leiter'],
        'Admin' => ['admin'],
    ];

    /**
     * @param  array<string, mixed>  $options
     */
    public function run(array $options): void
    {
        $projectRoot = realpath((string) ($options['project-root'] ?? self::DEFAULT_OPTIONS['project-root']));
        if ($projectRoot === false) {
            throw new RuntimeException('Projektpfad ist ungueltig.');
        }

        $outputDir = (string) ($options['output-dir'] ?? self::DEFAULT_OPTIONS['output-dir']);
        if (! is_dir($outputDir)) {
            $outputDir = $projectRoot.DIRECTORY_SEPARATOR.ltrim($outputDir, DIRECTORY_SEPARATOR);
        }
        $outputDir = rtrim($outputDir, DIRECTORY_SEPARATOR);

        $projectRoot = rtrim($projectRoot, DIRECTORY_SEPARATOR);
        $viewsPath = $projectRoot.'/resources/views';
        $routesPathWeb = $projectRoot.'/routes/web.php';
        $routesPathApi = $projectRoot.'/routes/api.php';
        $controllersPath = $projectRoot.'/app/Http/Controllers';
        $appServicePath = $projectRoot.'/app/Providers/AppServiceProvider.php';
        $navigationServicePath = $projectRoot.'/app/Support/UI/NavigationService.php';
        $settingsComposerPath = $projectRoot.'/app/View/Composers/Configuration/ConfigurationSettingsComposer.php';
        $bootstrapPath = $projectRoot.'/bootstrap/app.php';
        $identityPath = $projectRoot.'/config/identity.php';
        $includeUnguardedRoutes = (bool) ($options['include-unguarded-routes'] ?? false);

        $routes = [];
        $controllerViews = $this->buildControllerViewMap($controllersPath);
        $globalGroups = $this->parseBootstrapMiddlewareGroups($bootstrapPath);
        $routes = array_merge($routes, $this->parseRoutesFile($routesPathWeb, 'web', $controllerViews, $globalGroups));
        $routes = array_merge($routes, $this->parseRoutesFile($routesPathApi, 'api', $controllerViews, $globalGroups));

        usort($routes, static function (array $left, array $right): int {
            return [$left['surface'], $left['scope'], $left['uri'], $left['methods'], $left['name']] <=> [$right['surface'], $right['scope'], $right['uri'], $right['methods'], $right['name']];
        });

        $views = $this->buildViewList($viewsPath);
        $routeViews = [];
        $routeViewPaths = [];
        foreach ($routes as $route) {
            if ($route['view'] !== null) {
                $routeViews[$route['view']] = $route;
                $routeViewPaths[$route['view']] = $viewsPath.DIRECTORY_SEPARATOR.str_replace('.', DIRECTORY_SEPARATOR, $route['view']).'.blade.php';
            }
        }

        $composerBindings = $this->collectComposerBindings($appServicePath);
        $currentSections = $this->collectCurrentSectionUsages($viewsPath);
        $menuItems = $this->collectMenuItems($navigationServicePath, $routes);
        $settingsNav = $this->collectSettingsTabs($settingsComposerPath, $routes);
        $menuItems = $this->normalizeMenuItems(
            array_merge(
                $menuItems,
                $this->collectSettingsTabsAsMenuItems($settingsNav, $routes)
            )
        );
        $identity = require $identityPath;
        $permissionByRoute = $this->mapPermissionsByRoute($routes);
        $roleCoverage = $this->mapRoleCoverage($identity['roles'] ?? [], $permissionByRoute, $includeUnguardedRoutes);
        $routeDuplicates = $this->collectRouteDuplicates($routes);
        $permissionDrift = $this->collectPermissionDrift($permissionByRoute, $identity['permissions'] ?? []);
        $menuHealth = $this->collectMenuHealth($menuItems, array_fill_keys(array_map(static fn (array $route): string => (string) $route['name'], $routes), true));
        $viewReferenceMap = $this->collectViewReferenceMap($views);
        $reachableViews = $this->collectReachableViews($views, array_keys($routeViews), $viewReferenceMap['forward']);
        $multiRouteViews = $this->collectMultiRouteViews($routeViews);
        $duplicateViewSignatures = $this->collectDuplicateViewsBySignature($views);
        $personaCoverage = $this->collectPersonaCoverage($roleCoverage, $menuItems);
        $orphanViews = $this->collectOrphanViews($views, $routeViews, $reachableViews);
        $moduleSurface = $this->collectModuleSurfaceSummary($routes);

        $generatedAt = date('Y-m-d H:i:s');
        $routeDoc = $this->renderRouteDoc($routes, $composerBindings, $menuItems, $generatedAt);
        $viewDoc = $this->renderViewDoc($routeViews, $routeViewPaths, $views, $composerBindings, $currentSections, $generatedAt);
        $permissionDoc = $this->renderPermissionDoc($permissionByRoute, $roleCoverage, $identity, $menuItems, $routes, $generatedAt);
        $routeVisibilityDoc = $this->renderRouteVisibilityMatrix($permissionByRoute, $roleCoverage, $menuItems, $generatedAt);
        $auditDoc = $this->renderAuditReport(
            $routes,
            $routeDuplicates,
            $permissionDrift,
            $menuHealth,
            $orphanViews,
            $viewReferenceMap['reverse'],
            $multiRouteViews,
            $duplicateViewSignatures,
            $personaCoverage,
            $moduleSurface,
            $generatedAt
        );
        $reorgRoadmapDoc = $this->renderReorganisationRoadmap(
            $permissionByRoute,
            $routes,
            $routeDuplicates,
            $permissionDrift,
            $menuHealth,
            $roleCoverage,
            $menuItems,
            $orphanViews,
            $personaCoverage,
            $identity,
            $generatedAt
        );

        if (! is_dir($outputDir)) {
            if (! mkdir($outputDir, 0o775, true) && ! is_dir($outputDir)) {
                throw new RuntimeException(sprintf('Output-Verzeichnis "%s" kann nicht erstellt werden.', $outputDir));
            }
        }

        file_put_contents($outputDir.'/SYSTEM_ROUTE_KARTOGRAPHIE.md', $routeDoc);
        file_put_contents($outputDir.'/SYSTEM_VIEW_KARTOGRAPHIE.md', $viewDoc);
        file_put_contents($outputDir.'/SYSTEM_PERMISSION_MATRIX.md', $permissionDoc);
        file_put_contents($outputDir.'/'.(string) self::DEFAULT_OPTIONS['menu-role-matrix-name'], $this->renderMenuRoleMatrix($menuItems, $roleCoverage));
        file_put_contents($outputDir.'/'.(string) self::DEFAULT_OPTIONS['route-visibility-matrix-name'], $routeVisibilityDoc);
        file_put_contents($outputDir.'/'.(string) self::DEFAULT_OPTIONS['audit-report-name'], $auditDoc);
        file_put_contents($outputDir.'/'.(string) self::DEFAULT_OPTIONS['reorganisation-roadmap-name'], $reorgRoadmapDoc);

        $this->reportSummary($routes, $routeViews, $views);
    }

    /**
     * @param  array<string, mixed>  $options
     */
    public static function bootstrapOptions(array $argv): array
    {
        $parsed = getopt('', [
            'help',
            'project-root::',
            'output-dir::',
            'include-unguarded-routes',
        ]);

        if (isset($parsed['help'])) {
            $help = <<<'HELP'
System-Kartographie Generator

Usage:
php scripts/system-kartographie-gen.php [--project-root=...] [--output-dir=...] [--include-unguarded-routes]

--project-root
  Projektwurzel (Standard: aktuelles Verzeichnis).

--output-dir
  Ausgabeverzeichnis (Standard: docs).

--include-unguarded-routes
  Zeigt Routen ohne can:-Middleware ebenfalls in der Rollenreichweite an.
HELP;
            echo $help.PHP_EOL;
            exit(0);
        }

        return [
            'project-root' => $parsed['project-root'] ?? self::DEFAULT_OPTIONS['project-root'],
            'output-dir' => $parsed['output-dir'] ?? self::DEFAULT_OPTIONS['output-dir'],
            'include-unguarded-routes' => isset($parsed['include-unguarded-routes']),
        ];
    }

    /**
     * @param  array<string, list<string>>  $globalGroups  Group-Middleware, ausgelesen aus bootstrap/app.php
     */
    private function parseRoutesFile(string $path, string $surface, array $controllerViews, array $globalGroups = []): array
    {
        $parser = (new ParserFactory)->createForNewestSupportedVersion();
        $contents = (string) file_get_contents($path);
        $statements = $parser->parse($contents);
        if ($statements === null) {
            return [];
        }

        $imports = $this->collectImports($statements);
        // Globale Middleware-Group aus bootstrap/app.php abbilden, damit das Audit
        // die globalen Append-/Prepend-Schritte kennt und API-Routen nicht
        // fälschlich als ungeschützt erscheinen.
        $defaultGroup = $globalGroups[$surface] ?? ($surface === 'api'
            ? ['throttle:secure-api', 'auth.api', 'metrics', 'security-headers']
            : ['web', 'metrics', 'security-headers']);
        $context = [
            'uri' => '',
            'name' => '',
            'middleware' => $defaultGroup,
        ];

        $routes = [];
        $this->parseStatements($statements, $context, $surface, $controllerViews, $imports, $routes);

        return $routes;
    }

    /**
     * Liest die globale Middleware-Group-Konfiguration aus `bootstrap/app.php`.
     *
     * Erfasst werden `prependToGroup('web|api', X)` und `appendToGroup('web|api', X)`.
     * X kann eine Klassen-Konstante (z.B. `SomeMiddleware::class`) oder ein String-Alias sein.
     *
     * @return array{web: list<string>, api: list<string>}
     */
    private function parseBootstrapMiddlewareGroups(string $bootstrapPath): array
    {
        $groups = ['web' => [], 'api' => []];

        if (! is_file($bootstrapPath)) {
            return $groups;
        }

        $parser = (new ParserFactory)->createForNewestSupportedVersion();
        $contents = (string) file_get_contents($bootstrapPath);
        $statements = $parser->parse($contents);
        if ($statements === null) {
            return $groups;
        }

        // Group hat Default-Surface-Defaults (Laravel-Standard)
        $groups['web'] = ['web'];
        $groups['api'] = []; // Laravel 11/12 hat keine 'api' Group default
        $globalAliases = [];

        $traverser = new \PhpParser\NodeTraverser;
        $visitor = new class($groups, $globalAliases) extends \PhpParser\NodeVisitorAbstract
        {
            /** @var array<string, list<string>> */
            public array $groups;

            /** @var array<string, string> */
            public array $aliases;

            public function __construct(array &$groups, array &$aliases)
            {
                $this->groups = &$groups;
                $this->aliases = &$aliases;
            }

            public function enterNode(\PhpParser\Node $node): null
            {
                if ($node instanceof \PhpParser\Node\Expr\MethodCall && $node->name instanceof \PhpParser\Node\Identifier) {
                    $method = $node->name->toString();

                    // alias([...]) — Sammlung der Mappings für Hinweis-Texte
                    if ($method === 'alias' && isset($node->args[0])
                        && $node->args[0]->value instanceof \PhpParser\Node\Expr\Array_) {
                        foreach ($node->args[0]->value->items as $item) {
                            if (! $item instanceof \PhpParser\Node\Expr\ArrayItem) {
                                continue;
                            }
                            $key = $this->literal($item->key);
                            if (is_string($key)) {
                                $this->aliases[$key] = is_string($this->literal($item->value)) ? $this->literal($item->value) : $key;
                            }
                        }
                    }

                    if ($method === 'prependToGroup' || $method === 'appendToGroup') {
                        $surface = isset($node->args[0]) ? $this->literal($node->args[0]->value) : null;
                        $value = isset($node->args[1]) ? $this->literal($node->args[1]->value) : null;
                        if (! is_string($surface) || ! is_string($value) || ! isset($this->groups[$surface])) {
                            return null;
                        }
                        $alias = $this->shortName($value);
                        if ($method === 'prependToGroup') {
                            array_unshift($this->groups[$surface], $alias);
                        } else {
                            $this->groups[$surface][] = $alias;
                        }
                    }
                }

                return null;
            }

            private function literal(?\PhpParser\Node $node)
            {
                if ($node instanceof \PhpParser\Node\Scalar\String_) {
                    return $node->value;
                }
                if ($node instanceof \PhpParser\Node\Expr\ClassConstFetch
                    && $node->class instanceof \PhpParser\Node\Name
                    && $node->name instanceof \PhpParser\Node\Identifier
                    && $node->name->toString() === 'class') {
                    return $node->class->toString();
                }

                return null;
            }

            private function shortName(string $value): string
            {
                // String-Aliasse wie 'throttle:secure-api' bleiben wie sie sind.
                if (! str_contains($value, '\\')) {
                    return $value;
                }
                // Klassen-FQN auf einen Kebab-Alias reduzieren.
                $base = ltrim(substr($value, (int) strrpos($value, '\\')), '\\');

                return strtolower(preg_replace('/(?<!^)([A-Z])/', '-$1', $base) ?? $base);
            }
        };

        $traverser->addVisitor($visitor);
        $traverser->traverse($statements);

        // Eindeutigkeit
        foreach ($groups as $surface => $list) {
            $groups[$surface] = array_values(array_unique($list));
        }

        return $groups;
    }

    /**
     * @param  array<int, array<string, mixed>>  $statements
     * @param  array<string, mixed>  $context
     * @param  array<string, string>  $imports
     * @param  array<int, array<string, mixed>>  $routes
     */
    private function parseStatements(array $statements, array $context, string $surface, array $controllerViews, array $imports, array &$routes): void
    {
        foreach ($statements as $statement) {
            if (! $statement instanceof Expression) {
                continue;
            }

            $chain = $this->routeChain($statement->expr);
            if ($chain === null) {
                continue;
            }

            if ($this->isGroup($chain)) {
                $groupContext = $this->applyRouteContext($context, $chain);
                $closure = $this->findClosure($chain);
                if ($closure instanceof Closure) {
                    $this->parseStatements($closure->stmts, $groupContext, $surface, $controllerViews, $imports, $routes);
                }

                continue;
            }

            if (! $this->isRouteDefinition($chain)) {
                continue;
            }

            $expanded = $this->expandRoute($chain, $context, $surface, $controllerViews, $imports);
            foreach ($expanded as $route) {
                $routes[] = $route;
            }
        }
    }

    /**
     * @param  array<int, array<string, mixed>>  $chain
     */
    private function isGroup(array $chain): bool
    {
        foreach ($chain as $step) {
            if ($step['method'] === 'group') {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  array<int, array<string, mixed>>  $chain
     */
    private function findClosure(array $chain): ?Closure
    {
        foreach ($chain as $step) {
            if ($step['method'] !== 'group') {
                continue;
            }
            foreach ($step['args'] as $arg) {
                if ($arg instanceof Closure) {
                    return $arg;
                }
            }
        }

        return null;
    }

    /**
     * @param  array<int, array<string, mixed>>  $chain
     */
    private function isRouteDefinition(array $chain): bool
    {
        if ($chain === []) {
            return false;
        }

        $method = $chain[0]['method'];

        return in_array($method, ['get', 'post', 'put', 'patch', 'delete', 'match', 'any', 'resource'], true);
    }

    /**
     * @param  array<string, mixed>  $context
     * @param  array<int, array<string, mixed>>  $chain
     * @return array<string, mixed>
     */
    private function applyRouteContext(array $context, array $chain): array
    {
        $next = $context;

        foreach ($chain as $step) {
            if ($step['method'] === 'prefix' && isset($step['args'][0]) && is_string($step['args'][0])) {
                $next['uri'] = $this->combineUri($next['uri'], $step['args'][0]);

                continue;
            }

            if ($step['method'] === 'name' && isset($step['args'][0]) && is_string($step['args'][0])) {
                $next['name'] = $this->combineName($next['name'], $step['args'][0]);

                continue;
            }

            if ($step['method'] === 'middleware' && isset($step['args'][0])) {
                $collected = [];
                foreach ($step['args'] as $arg) {
                    if (is_array($arg)) {
                        foreach ($arg as $item) {
                            if (is_string($item)) {
                                $collected[] = $item;
                            }
                        }

                        continue;
                    }
                    if (is_string($arg)) {
                        $collected[] = $arg;
                    }
                }

                $next['middleware'] = array_values(array_unique(array_merge((array) $next['middleware'], $collected)));
            }
        }

        return $next;
    }

    /**
     * @param  array<int, array<string, mixed>>  $chain
     * @param  array<string, mixed>  $context
     * @param  array<string, string>  $controllerViews
     * @param  array<string, string>  $imports
     * @return array<int, array<string, mixed>>
     */
    private function expandRoute(array $chain, array $context, string $surface, array $controllerViews, array $imports): array
    {
        $base = $chain[0];
        $method = $base['method'];

        if ($method === 'resource') {
            return $this->expandResource($base, $chain, $context, $surface, $controllerViews, $imports);
        }

        $methods = $this->determineMethods($method, $base['args']);
        $uri = is_string($base['args'][0] ?? null) ? $base['args'][0] : '';
        $action = $this->resolveActionValue($base['args'][count($base['args']) - 1] ?? null, $imports);
        $routeName = $this->resolveRouteName($chain, $context['name']);
        $permissionData = $this->collectPermissions($base, $chain, $context['middleware']);

        return [[
            'surface' => $surface,
            'scope' => $this->determineScope($surface, $routeName, $uri, $permissionData['abilities']),
            'uri' => $this->combineUri($context['uri'], $uri),
            'name' => $routeName,
            'methods' => $methods,
            'action' => $action,
            'permissions' => $permissionData['abilities'],
            'middleware' => $permissionData['middleware'],
            'view' => $controllerViews[$action] ?? null,
        ]];
    }

    /**
     * @param  array<string, mixed>  $base
     * @param  array<int, array<string, mixed>>  $chain
     * @param  array<string, mixed>  $context
     * @param  array<string, string>  $imports
     * @return array<int, array<string, mixed>>
     */
    private function expandResource(array $base, array $chain, array $context, string $surface, array $controllerViews, array $imports): array
    {
        $uri = is_string($base['args'][0] ?? null) ? $base['args'][0] : '';
        $controller = $this->resolveControllerName($base['args'][1] ?? null, $imports);
        if ($controller === '') {
            return [];
        }

        $alias = $this->resolveResourceAlias($chain);
        if ($alias === '') {
            $alias = $this->fallbackResourceAlias($uri);
        }

        $param = $this->resolveResourceParameter($chain, $uri);
        $only = $this->resolveResourceFilter($chain, 'only');
        $except = $this->resolveResourceFilter($chain, 'except');

        $definitions = [
            ['index', 'GET', $uri, 'index'],
            ['create', 'GET', $uri.'/create', 'create'],
            ['store', 'POST', $uri, 'store'],
            ['show', 'GET', $uri.'/{'.$param.'}', 'show'],
            ['edit', 'GET', $uri.'/{'.$param.'}/edit', 'edit'],
            ['update', 'PUT|PATCH', $uri.'/{'.$param.'}', 'update'],
            ['destroy', 'DELETE', $uri.'/{'.$param.'}', 'destroy'],
        ];

        if ($only !== []) {
            $definitions = array_values(array_filter($definitions, static fn (array $row): bool => in_array($row[0], $only, true)));
        }

        if ($except !== []) {
            $definitions = array_values(array_filter($definitions, static fn (array $row): bool => ! in_array($row[0], $except, true)));
        }

        $permissionData = $this->collectPermissions($base, $chain, $context['middleware']);
        $rows = [];

        foreach ($definitions as $entry) {
            $action = $controller.'@'.$entry[3];
            $resourceRouteName = $this->combineName($context['name'], $alias.'.'.$entry[0]);
            $rows[] = [
                'surface' => $surface,
                'scope' => $this->determineScope($surface, $resourceRouteName, $uri, $permissionData['abilities']),
                'uri' => $this->combineUri($context['uri'], $entry[2]),
                'name' => $resourceRouteName,
                'methods' => $entry[1],
                'action' => $action,
                'permissions' => $permissionData['abilities'],
                'middleware' => $permissionData['middleware'],
                'view' => $controllerViews[$action] ?? null,
            ];
        }

        return $rows;
    }

    private function determineMethods(string $method, array $args): string
    {
        if ($method === 'match') {
            $methods = $args[0] ?? [];
            if (is_array($methods)) {
                $methods = array_map('strtoupper', $methods);

                return implode('|', $methods);
            }
            if (is_string($methods)) {
                return strtoupper($methods);
            }

            return 'GET|POST|PUT|PATCH|DELETE';
        }

        if ($method === 'any') {
            return 'GET|POST|PUT|PATCH|DELETE';
        }

        return strtoupper($method);
    }

    private function determineScope(string $surface, string $routeName, string $uri, array $abilities): string
    {
        if ($surface === 'api') {
            return 'api';
        }

        if (in_array($uri, ['/', '/login', '/logout'], true)) {
            return 'auth';
        }

        if ($routeName !== '' || str_starts_with($uri, '/admin')) {
            return 'admin';
        }

        if ($abilities === []) {
            return 'web';
        }

        return 'admin';
    }

    /**
     * @param  array<string, mixed>  $baseStep
     * @param  array<int, array<string, mixed>>  $chain
     * @param  array<int, string>  $baseMiddleware
     * @return array<string, mixed>
     */
    private function collectPermissions(array $baseStep, array $chain, array $baseMiddleware): array
    {
        $raw = $baseMiddleware;

        foreach ($chain as $step) {
            if ($step['method'] !== 'middleware') {
                continue;
            }

            foreach ($step['args'] as $arg) {
                if (is_string($arg)) {
                    $raw[] = $arg;

                    continue;
                }

                if (is_array($arg)) {
                    foreach ($arg as $item) {
                        if (is_string($item)) {
                            $raw[] = $item;
                        }
                    }
                }
            }
        }

        $middleware = array_values(array_unique(array_filter($raw, static fn (string $value): bool => $value !== '')));
        $abilityItems = [];
        foreach ($middleware as $middlewareItem) {
            if (str_starts_with($middlewareItem, 'can:')) {
                $abilityItems[] = substr($middlewareItem, 4);
            }
        }

        $abilities = array_values(array_unique(array_filter($abilityItems, static fn (string $item): bool => $item !== '')));

        return ['middleware' => $middleware, 'abilities' => $abilities];
    }

    private function resolveRouteName(array $chain, string $groupName): string
    {
        foreach ($chain as $step) {
            if ($step['method'] === 'name' && isset($step['args'][0]) && is_string($step['args'][0])) {
                return $step['args'][0];
            }
        }

        return $groupName;
    }

    /**
     * @param  string|mixed  $raw
     * @param  array<string, string>  $imports
     */
    private function resolveActionValue($raw, array $imports): string
    {
        if ($raw instanceof Closure) {
            return 'Closure';
        }

        if (is_array($raw) && count($raw) >= 2 && is_string($raw[0]) && is_string($raw[1])) {
            return $this->normalizeClassName($raw[0], $imports).'@'.$raw[1];
        }

        if (is_string($raw)) {
            return $this->normalizeClassName($raw, $imports).'@__invoke';
        }

        return 'Closure';
    }

    private function resolveControllerName($raw, array $imports): string
    {
        if (is_string($raw)) {
            return $this->normalizeClassName($raw, $imports);
        }

        return '';
    }

    private function normalizeClassName(string $value, array $imports): string
    {
        if ($value === '') {
            return '';
        }
        if (str_starts_with($value, '\\')) {
            return substr($value, 1);
        }
        if (str_contains($value, '\\')) {
            return $value;
        }

        return ltrim($imports[$value] ?? $value, '\\');
    }

    private function resolveResourceAlias(array $chain): string
    {
        foreach ($chain as $step) {
            if ($step['method'] === 'names' && isset($step['args'][0]) && is_string($step['args'][0])) {
                return $step['args'][0];
            }
        }

        return '';
    }

    private function resolveResourceParameter(array $chain, string $uri): string
    {
        foreach ($chain as $step) {
            if ($step['method'] === 'parameters' && isset($step['args'][0]) && is_array($step['args'][0])) {
                $map = $step['args'][0];
                if (! empty($map)) {
                    return (string) array_values($map)[0];
                }
            }
        }

        $clean = trim($uri, '/');
        if ($clean === '') {
            return 'id';
        }

        $parts = explode('/', $clean);
        $last = (string) end($parts);
        if (str_contains($last, '-')) {
            $last = (string) end(explode('-', $last));
        }

        return rtrim($last, 's');
    }

    private function resolveResourceFilter(array $chain, string $type): array
    {
        foreach ($chain as $step) {
            if ($step['method'] === $type && isset($step['args'][0]) && is_array($step['args'][0])) {
                return $step['args'][0];
            }
        }

        return [];
    }

    private function fallbackResourceAlias(string $uri): string
    {
        $uri = trim($uri, '/');
        $uri = str_replace('-', '_', $uri);

        return str_replace('/', '.', $uri);
    }

    private function combineUri(string $prefix, string $uri): string
    {
        $prefix = trim($prefix, '/');
        $uri = trim($uri, '/');

        if ($prefix === '' && $uri === '') {
            return '/';
        }
        if ($prefix === '') {
            return '/'.$uri;
        }
        if ($uri === '') {
            return '/'.$prefix;
        }

        return '/'.$prefix.'/'.$uri;
    }

    private function combineName(string $base, string $name): string
    {
        if ($base === '') {
            return $name;
        }
        if (str_ends_with($base, '.')) {
            return $base.$name;
        }

        return $base.'.'.$name;
    }

    private function routeArgValue(Arg $arg)
    {
        $node = $arg->value;
        if ($node instanceof ArrayItem) {
            $node = $node->value;
        }

        if ($node instanceof Expr\Array_) {
            $values = [];
            foreach ($node->items as $item) {
                if (! $item instanceof ArrayItem) {
                    continue;
                }

                $inner = $this->routeArgValueNode($item->value);
                if (is_array($inner)) {
                    $values = array_merge($values, $inner);

                    continue;
                }
                if ($inner !== null) {
                    $values[] = $inner;
                }
            }

            return $values;
        }

        return $this->routeArgValueNode($node);
    }

    private function routeArgValueNode($node)
    {
        if (! $node instanceof Node) {
            return null;
        }

        if ($node instanceof Node\Scalar\String_) {
            return $node->value;
        }
        if ($node instanceof Node\Scalar\LNumber) {
            return (string) $node->value;
        }
        if ($node instanceof ClassConstFetch && $node->name instanceof Node\Identifier && $node->name->toString() === 'class' && $node->class instanceof Name) {
            return $node->class->toString();
        }
        if ($node instanceof Closure) {
            return $node;
        }

        return null;
    }

    /**
     * @param  Node\Expr  $expression
     * @return array<int, array<string, mixed>>|null
     */
    private function routeChain(Node $expression): ?array
    {
        $parts = [];
        while (true) {
            if ($expression instanceof MethodCall) {
                $method = $expression->name instanceof Node\Identifier ? $expression->name->toString() : null;
                if (! is_string($method)) {
                    return null;
                }

                $parts[] = [
                    'method' => $method,
                    'args' => array_map([$this, 'routeArgValue'], $expression->args),
                ];

                $expression = $expression->var;

                continue;
            }

            if (! $expression instanceof StaticCall) {
                return null;
            }

            if (! $expression->class instanceof Name) {
                return null;
            }

            if ($expression->class->toString() !== 'Route') {
                return null;
            }

            $method = $expression->name instanceof Node\Identifier ? $expression->name->toString() : null;
            if (! is_string($method)) {
                return null;
            }

            $parts[] = [
                'method' => $method,
                'args' => array_map([$this, 'routeArgValue'], $expression->args),
            ];
            break;
        }

        return array_reverse($parts);
    }

    private function collectImports(array $statements): array
    {
        $imports = [];

        foreach ($statements as $statement) {
            if ($statement instanceof Namespace_) {
                foreach ($statement->stmts as $nested) {
                    if ($nested instanceof Use_) {
                        foreach ($nested->uses as $use) {
                            $alias = $use->alias !== null ? $use->alias->toString() : $use->name->getLast();
                            $imports[$alias] = '\\'.$use->name->toString();
                        }
                    }
                }

                continue;
            }

            if (! $statement instanceof Use_) {
                continue;
            }

            foreach ($statement->uses as $use) {
                $alias = $use->alias !== null ? $use->alias->toString() : $use->name->getLast();
                $imports[$alias] = '\\'.$use->name->toString();
            }
        }

        return $imports;
    }

    /**
     * @return array<string, string>
     */
    private function buildControllerViewMap(string $controllersPath): array
    {
        $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($controllersPath));
        $result = [];

        foreach ($iterator as $file) {
            if (! $file->isFile() || $file->getExtension() !== 'php') {
                continue;
            }

            $contents = (string) file_get_contents($file->getPathname());
            if (! preg_match('/namespace\s+([^;]+);/s', $contents, $namespaceMatch)) {
                continue;
            }

            $namespace = trim($namespaceMatch[1]);
            if (! preg_match('/class\s+(\w+)/', $contents, $classMatch)) {
                continue;
            }

            $class = $namespace.'\\'.$classMatch[1];
            $tokens = token_get_all($contents);
            $count = count($tokens);

            for ($i = 0; $i < $count; $i++) {
                $token = $tokens[$i];
                if (! is_array($token) || $token[0] !== T_FUNCTION) {
                    continue;
                }

                $j = $i + 1;
                while ($j < $count && (! is_array($tokens[$j]) || $tokens[$j][0] !== T_STRING)) {
                    $j++;
                }
                if ($j >= $count) {
                    continue;
                }
                $method = $tokens[$j][1];

                $k = $j;
                while ($k < $count && $tokens[$k] !== '{') {
                    $k++;
                }
                if ($k >= $count) {
                    continue;
                }

                $depth = 1;
                $interpolationDepth = 0;
                $k++;
                $body = '';
                while ($k < $count && $depth > 0) {
                    $current = $tokens[$k];
                    if ($current === '{') {
                        $depth++;
                    } elseif ($current === '}') {
                        if ($interpolationDepth > 0) {
                            $interpolationDepth--;
                            $k++;

                            continue;
                        }
                        $depth--;
                    } elseif (is_array($current) && in_array($current[0], [T_CURLY_OPEN, T_DOLLAR_OPEN_CURLY_BRACES], true)) {
                        $interpolationDepth++;
                        $k++;

                        continue;
                    }
                    if ($depth > 0) {
                        $body .= is_array($current) ? (string) $current[1] : $current;
                    }
                    $k++;
                }

                if (preg_match('/return\s+view\(\s*[\'"]([^\'"]+)[\'"]/', $body, $viewMatch)) {
                    $result[$class.'@'.$method] = $viewMatch[1];
                }
            }
        }

        return $result;
    }

    /**
     * @return array<string, string>
     */
    private function buildViewList(string $viewsDir): array
    {
        $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($viewsDir));
        $result = [];

        foreach ($iterator as $file) {
            if (! $file->isFile()) {
                continue;
            }
            if ($file->getExtension() !== 'php') {
                continue;
            }
            $path = $file->getPathname();
            if (! str_ends_with($path, '.blade.php')) {
                continue;
            }

            $relative = str_replace($viewsDir.'/', '', $path);
            $relative = preg_replace('/\.blade\.php$/', '', $relative);
            $result[str_replace('/', '.', $relative)] = $path;
        }

        return $result;
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    private function mapPermissionsByRoute(array $routes): array
    {
        $rows = [];
        foreach ($routes as $route) {
            $rows[] = [
                'route_name' => $route['name'] !== '' ? $route['name'] : '('.$route['uri'].')',
                'uri' => $route['uri'],
                'surface' => $route['surface'],
                'scope' => $route['scope'],
                'permissions' => $route['permissions'],
            ];
        }

        return $rows;
    }

    /**
     * @param  array<int, array<string, mixed>>  $routes
     * @return array{
     *   duplicate_names: array<string, array<int, array<string, mixed>>>,
     *   duplicate_uri_methods: array<string, array<int, array<string, mixed>>>
     * }
     */
    private function collectRouteDuplicates(array $routes): array
    {
        $byName = [];
        $byUriMethod = [];

        foreach ($routes as $route) {
            $name = $route['name'] ?? '';
            if ($name !== '') {
                $byName[$name][] = $route;
            }

            $methodKey = $route['methods'].' '.$route['uri'];
            $byUriMethod[$methodKey][] = $route;
        }

        $duplicates = ['duplicate_names' => [], 'duplicate_uri_methods' => []];

        foreach ($byName as $name => $rows) {
            if (count($rows) > 1) {
                $duplicates['duplicate_names'][$name] = $rows;
            }
        }

        foreach ($byUriMethod as $signature => $rows) {
            if (count($rows) > 1) {
                $duplicates['duplicate_uri_methods'][$signature] = $rows;
            }
        }

        return $duplicates;
    }

    /**
     * @param  array<int, array<string, mixed>>  $permissionByRoute
     * @param  array<string, array<string, mixed>>  $definedPermissions
     * @return array<string, array<string, mixed>>
     */
    private function collectPermissionDrift(array $permissionByRoute, array $definedPermissions): array
    {
        $declared = array_keys($definedPermissions);
        $declaredSet = array_fill_keys($declared, true);
        $usedPermissions = [];
        $unknown = [];
        $routeCountByPermission = [];
        $unguardedRoutes = [];

        foreach ($permissionByRoute as $entry) {
            $routePerms = $entry['permissions'];
            if (! is_array($routePerms)) {
                continue;
            }
            if ($routePerms === []) {
                $unguardedRoutes[] = sprintf('%s %s', (string) $entry['surface'], (string) $entry['route_name']);

                continue;
            }
            foreach ($routePerms as $permission) {
                $routeCountByPermission[$permission] = ($routeCountByPermission[$permission] ?? 0) + 1;
                if (! isset($declaredSet[$permission])) {
                    $unknown[] = $entry['route_name'].' ['.$entry['uri'].']';
                }
            }
        }

        $unused = array_values(array_filter(
            $declared,
            static fn (string $permission): bool => ! isset($routeCountByPermission[$permission])
        ));

        return [
            'declared_count' => count($declared),
            'used_count' => count($routeCountByPermission),
            'unguarded_routes' => $unguardedRoutes,
            'unused_permissions' => $unused,
            'unknown_permissions' => array_values(array_unique($unknown)),
            'permissions_by_routes' => $routeCountByPermission,
            'used_permissions' => array_keys($routeCountByPermission),
        ];
    }

    /**
     * @param  array<int, array<string, mixed>>  $menuItems
     * @param  array<string, bool>  $routeNames
     * @return array<string, array<int, array<string, mixed>>>
     */
    private function collectMenuHealth(array $menuItems, array $routeNames): array
    {
        $health = ['missing_routes' => [], 'dead_items' => [], 'menu_without_permissions' => []];

        foreach ($menuItems as $item) {
            if (! is_array($item)) {
                continue;
            }

            $route = (string) ($item['route'] ?? '');
            $permissions = (array) ($item['permissions'] ?? []);
            if ($route === '') {
                $health['dead_items'][] = (string) ($item['label'] ?? 'Unbenannte Navigation');

                continue;
            }

            if (! isset($routeNames[$route])) {
                $health['missing_routes'][] = $route;
            }

            if ($permissions === []) {
                $health['menu_without_permissions'][] = $route;
            }
        }

        return $health;
    }

    /**
     * @param  array<string, string>  $allViews
     * @return array{forward: array<string, array<int, string>>, reverse: array<string, int>}
     */
    private function collectViewReferenceMap(array $allViews): array
    {
        $forward = [];
        $reverse = [];

        foreach ($allViews as $view => $path) {
            $references = array_values(array_unique($this->extractBladeReferences($path)));

            foreach ($references as $reference) {
                if (! isset($allViews[$reference])) {
                    continue;
                }

                $forward[$view][] = $reference;
                $reverse[$reference] = ($reverse[$reference] ?? 0) + 1;
            }

            if (! isset($forward[$view])) {
                $forward[$view] = [];
            }
        }

        return ['forward' => $forward, 'reverse' => $reverse];
    }

    /**
     * @param  array<string, string>  $allViews
     * @param  array<string, array<string, mixed>>  $routeViews
     * @param  array<string, bool>  $reachableViews
     * @return array<string, array<int, string>>
     */
    private function collectOrphanViews(array $allViews, array $routeViews, array $reachableViews): array
    {
        $used = array_fill_keys(array_keys($routeViews), true);
        foreach ($reachableViews as $view => $_) {
            $used[$view] = true;
        }

        $orphans = [];

        foreach ($allViews as $view => $path) {
            if (isset($used[$view])) {
                continue;
            }

            $category = $this->classifyUnroutedView($path);
            $orphans[$category][] = $view;
        }

        ksort($orphans);
        foreach ($orphans as $category => $views) {
            sort($orphans[$category]);
        }

        return $orphans;
    }

    /**
     * @param  array<string, string>  $allViews
     * @param  array<string>  $seedViews
     * @param  array<string, array<int, string>>  $referenceMap
     * @return array<string, bool>
     */
    private function collectReachableViews(array $allViews, array $seedViews, array $referenceMap = []): array
    {
        $queue = array_values(array_filter(array_unique($seedViews), static fn (string $view): bool => isset($allViews[$view])));
        $reachable = array_fill_keys($queue, true);

        while ($queue !== []) {
            $current = array_shift($queue);
            if ($current === null || ! isset($allViews[$current])) {
                continue;
            }

            $references = $referenceMap[$current] ?? $this->extractBladeReferences($allViews[$current]);
            foreach ($references as $reference) {
                if (! isset($allViews[$reference]) || isset($reachable[$reference])) {
                    continue;
                }

                $reachable[$reference] = true;
                $queue[] = $reference;
            }
        }

        return $reachable;
    }

    /**
     * @param  array<string, string>  $allViews
     * @return array<string, array<int, string>>
     */
    private function collectDuplicateViewsBySignature(array $allViews): array
    {
        $signatures = [];
        foreach ($allViews as $view => $path) {
            $contents = (string) file_get_contents($path);
            $normalized = preg_replace('/\\s+/', ' ', trim($contents));
            $hash = sha1((string) $normalized);

            $signatures[$hash][] = $view;
        }

        $duplicates = [];
        foreach ($signatures as $views) {
            if (count($views) <= 1) {
                continue;
            }
            sort($views);
            $duplicates[] = $views;
        }

        usort($duplicates, static fn (array $left, array $right): int => count($right) <=> count($left));

        return $duplicates;
    }

    /**
     * @param  array<string, array<string, mixed>>  $roleCoverage
     * @param  array<int, array<string, mixed>>  $menuItems
     * @return array<string, array<int, string>>
     */
    private function collectMultiRouteViews(array $routeViews): array
    {
        $usageByView = [];
        foreach ($routeViews as $view => $route) {
            $routeName = (string) ($route['name'] ?? '');
            if ($routeName === '' || $routeName === '—') {
                continue;
            }
            $usageByView[$view][] = $routeName;
        }

        $result = [];
        foreach ($usageByView as $view => $routes) {
            if (count($routes) <= 1) {
                continue;
            }

            sort($routes);
            $result[$view] = $routes;
        }

        arsort($result);
        ksort($result);

        return $result;
    }

    /**
     * @param  array<string, array<string, mixed>>  $roleCoverage
     * @param  array<int, array<string, mixed>>  $menuItems
     * @return array<int, array<string, mixed>>
     */
    private function collectPersonaCoverage(array $roleCoverage, array $menuItems): array
    {
        $menuByRoute = [];
        foreach ($menuItems as $menuItem) {
            $route = (string) ($menuItem['route'] ?? '');
            if ($route === '') {
                continue;
            }
            $menuByRoute[$route] = (string) ($menuItem['label'] ?? $route);
        }

        $result = [];
        foreach (self::PERSONA_ROLE_MAP as $persona => $roles) {
            $roleList = [];
            $visibleRoutes = [];
            $visibleMenuItems = [];

            foreach ($roles as $role) {
                if (! isset($roleCoverage[$role])) {
                    continue;
                }
                $roleList[] = $role;
                foreach ((array) $roleCoverage[$role]['routes'] as $routeName) {
                    $visibleRoutes[$routeName] = true;
                }
            }

            foreach ($menuByRoute as $route => $label) {
                if (isset($visibleRoutes[$route])) {
                    $visibleMenuItems[] = $label;
                }
            }

            sort($visibleMenuItems);
            $routes = array_keys($visibleRoutes);
            sort($routes);

            $result[$persona] = [
                'persona' => $persona,
                'roles' => array_values(array_unique($roleList)),
                'route_count' => count($routes),
                'routes' => $routes,
                'menu_items' => $visibleMenuItems,
                'resolved' => $roleList !== [],
            ];
        }

        return $result;
    }

    /**
     * @return array<int, string>
     */
    private function extractBladeReferences(string $path): array
    {
        $contents = (string) file_get_contents($path);
        if ($contents === '') {
            return [];
        }

        $references = [];

        if (preg_match_all('/@include(?:If|Unless|When)?\s*\\(\\s*[\'"]([^\'"]+)[\'"]\\s*/', $contents, $matches)) {
            foreach ($matches[1] as $match) {
                $references[] = $match;
            }
        }

        if (preg_match_all('/@component\\s*\\(\\s*[\'"]([^\'"]+)[\'"]\\s*/', $contents, $matches)) {
            foreach ($matches[1] as $match) {
                $references[] = $match;
            }
        }

        if (preg_match_all('/@extends\\s*\\(\\s*[\'"]([^\'"]+)[\'"]\\s*/', $contents, $matches)) {
            foreach ($matches[1] as $match) {
                $references[] = $match;
            }
        }

        if (preg_match_all('/<x-([a-z0-9_.:-]+)\\b/', $contents, $matches)) {
            foreach ($matches[1] as $match) {
                $references[] = str_replace('-', '.', $match);
            }
        }

        $normalized = [];
        foreach ($references as $reference) {
            $normalizedReference = str_replace('::', '.', $reference);
            if (str_starts_with($normalizedReference, 'x-')) {
                $normalizedReference = substr($normalizedReference, 2);
            }

            if (str_contains($normalizedReference, '.blade.') || str_contains($normalizedReference, '.php')) {
                continue;
            }

            $normalized[] = ltrim(str_replace('\\', '.', $normalizedReference), '.');
        }

        return array_values(array_unique(array_filter($normalized, static fn (string $value): bool => $value !== '')));
    }

    private function classifyUnroutedView(string $path): string
    {
        $lower = strtolower($path);
        if (str_contains($lower, '/partials/')) {
            return 'Partial';
        }
        if (str_contains($lower, '/components/')) {
            return 'Komponente';
        }
        if (str_contains($lower, '/mail/')) {
            return 'Mail';
        }
        if (str_contains($lower, '/layouts/')) {
            return 'Layout';
        }
        if (str_contains($lower, '/tests/')) {
            return 'Test';
        }

        return 'Weitere';
    }

    /**
     * @param  array<int, array<string, mixed>>  $routes
     * @return array<string, int>
     */
    private function collectModuleSurfaceSummary(array $routes): array
    {
        $result = [];
        foreach ($routes as $route) {
            $uri = (string) ($route['uri'] ?? '/');
            $parts = array_values(array_filter(explode('/', trim($uri, '/'))));
            $module = '/';
            if ($parts !== []) {
                $module = $parts[0] === 'admin' && count($parts) > 1 ? $parts[1] : $parts[0];
            }

            $surface = (string) ($route['surface'] ?? 'web');
            $key = sprintf('%s:%s', $surface, $module);
            $result[$key] = ($result[$key] ?? 0) + 1;
        }

        ksort($result);

        return $result;
    }

    /**
     * @param  array<string, array<string, mixed>>  $roles
     * @param  array<int, array<string, mixed>>  $permissionByRoute
     * @return array<string, array<string, mixed>>
     */
    private function mapRoleCoverage(array $roles, array $permissionByRoute, bool $includeUnguardedRoutes): array
    {
        $result = [];

        foreach ($roles as $role => $definition) {
            $permissions = array_map('strtolower', (array) ($definition['permissions'] ?? []));
            $permissions = array_values(array_unique($permissions));
            $hasWildcard = in_array('*', $permissions, true);
            $count = 0;
            $visibleRoutes = [];

            foreach ($permissionByRoute as $entry) {
                $routePerms = $entry['permissions'];
                if ($routePerms === []) {
                    if (! $includeUnguardedRoutes) {
                        continue;
                    }
                    if (! in_array($entry['surface'], ['web', 'auth'], true)) {
                        continue;
                    }
                }

                if (! $hasWildcard && array_diff($routePerms, $permissions) !== []) {
                    continue;
                }
                $count++;
                $visibleRoutes[] = $entry['route_name'];
            }

            $result[$role] = [
                'label' => $definition['label'] ?? $role,
                'description' => $definition['description'] ?? '',
                'route_count' => $count,
                'routes' => $visibleRoutes,
                'permissions' => $permissions,
            ];
        }

        return $result;
    }

    private function collectComposerBindings(string $appServicePath): array
    {
        $contents = (string) file_get_contents($appServicePath);
        $result = [];
        if (! preg_match_all(
            '/View::composer\\(\\s*([\'"])(.*?)\\1\\s*,\\s*(.*?)\\s*::class\\)/s',
            $contents,
            $matches,
            PREG_SET_ORDER
        )) {
            return [];
        }

        foreach ($matches as $match) {
            $view = trim($match[2]);
            $composer = trim($match[3]);
            if ($composer === '') {
                continue;
            }

            $result[$view][] = $composer;
        }

        foreach ($result as $view => $classes) {
            $result[$view] = array_values(array_unique($classes));
        }

        return $result;
    }

    /**
     * @param  array{monitoring: array<int, array<string, mixed>>,verwaltung: array<int, array<string, mixed>>,logs: array<int, array<string, mixed>>}  $settingsNav
     * @return array<int, array<string, mixed>>
     */
    private function collectSettingsTabsAsMenuItems(array $settingsNav, array $routes): array
    {
        $result = [];
        $sectionLabels = [
            'monitoring' => 'Monitoring',
            'verwaltung' => 'Verwaltung',
            'logs' => 'Logs',
        ];

        $permissionsByRoute = [];
        foreach ($routes as $route) {
            if (! is_array($route)) {
                continue;
            }
            $routeName = (string) ($route['name'] ?? '');
            if ($routeName === '') {
                continue;
            }
            $permissionsByRoute[$routeName] = array_values(array_filter((array) ($route['permissions'] ?? []), 'is_string'));
        }

        foreach ($settingsNav as $section => $items) {
            if (! is_array($items)) {
                continue;
            }

            foreach ($items as $item) {
                if (! is_array($item)) {
                    continue;
                }

                if (! (bool) ($item['exists'] ?? false)) {
                    continue;
                }

                $route = (string) ($item['route'] ?? '');
                $label = trim((string) ($item['label'] ?? ''));
                if ($route === '' || $label === '') {
                    continue;
                }

                $prefix = $sectionLabels[$section] ?? '';
                $permissions = $permissionsByRoute[$route] ?? [];
                $result[] = [
                    'label' => $prefix === '' ? $label : ($prefix.' · '.$label),
                    'route' => $route,
                    'permissions' => $permissions,
                    'exists' => true,
                    'source' => 'settings',
                ];
            }
        }

        return $result;
    }

    /**
     * @param  array<int, array<string, mixed>>  $menuItems
     * @return array<int, array<string, mixed>>
     */
    private function normalizeMenuItems(array $menuItems): array
    {
        $result = [];
        $seen = [];

        foreach ($menuItems as $menuItem) {
            if (! is_array($menuItem)) {
                continue;
            }

            $route = (string) ($menuItem['route'] ?? '');
            if ($route === '') {
                $label = trim((string) ($menuItem['label'] ?? ''));
                if ($label === '') {
                    continue;
                }

                $result[] = [
                    'label' => $label,
                    'route' => '',
                    'permissions' => array_values(array_filter((array) ($menuItem['permissions'] ?? []), 'is_string')),
                    'exists' => false,
                    'source' => (string) ($menuItem['source'] ?? 'nav'),
                ];

                continue;
            }

            $label = trim((string) ($menuItem['label'] ?? ''));
            $key = strtolower($route).'|'.strtolower($label);
            if (isset($seen[$key])) {
                continue;
            }
            $seen[$key] = true;

            $result[] = [
                'label' => $label,
                'route' => $route,
                'permissions' => array_values(array_filter((array) ($menuItem['permissions'] ?? []), 'is_string')),
                'exists' => (bool) ($menuItem['exists'] ?? false),
                'source' => (string) ($menuItem['source'] ?? 'nav'),
            ];
        }

        return $result;
    }

    private function collectMenuItems(string $navigationServicePath, array $routes): array
    {
        /** @phpstan-ignore-next-line */
        $service = new \App\Support\UI\NavigationService;
        $items = $service->getDefaultItems();
        $names = [];
        foreach ($routes as $route) {
            if (is_array($route) && ($route['name'] ?? '') !== '') {
                $names[] = (string) $route['name'];
            }
        }
        $routeNames = array_fill_keys($names, true);
        $result = [];

        foreach ($items as $item) {
            if (! is_array($item)) {
                continue;
            }
            $route = (string) ($item['route'] ?? '');
            $permissions = array_values(array_filter((array) ($item['permissions'] ?? []), 'is_string'));

            $result[] = [
                'label' => (string) ($item['label'] ?? ''),
                'route' => $route,
                'permissions' => $permissions,
                'exists' => $route !== '' && isset($routeNames[$route]),
            ];
        }

        return $result;
    }

    private function collectSettingsTabs(string $settingsComposerPath, array $routes): array
    {
        $contents = (string) file_get_contents($settingsComposerPath);
        $settingsRouteNames = [];
        foreach ($routes as $route) {
            if (is_array($route) && ($route['name'] ?? '') !== '') {
                $settingsRouteNames[] = (string) $route['name'];
            }
        }
        $settingsRouteNames = array_fill_keys($settingsRouteNames, true);
        $result = ['monitoring' => [], 'verwaltung' => [], 'logs' => []];

        foreach (['monitoring' => 'monitoringLinks', 'verwaltung' => 'verwaltungLinks', 'logs' => 'logToolLinks'] as $section => $var) {
            if (! preg_match('/\\$'.$var.'\\s*=\\s*\\[(.*?)\\];/s', $contents, $match)) {
                continue;
            }
            $block = $match[1];

            if (! preg_match_all("/\\[\\s*'key'\\s*=>\\s*'([^']+)'[^\\]]*?'route'\\s*=>\\s*'([^']+)'/s", $block, $items, PREG_SET_ORDER)) {
                continue;
            }

            foreach ($items as $item) {
                $result[$section][] = [
                    'label' => $item[1],
                    'route' => $item[2],
                    'exists' => isset($settingsRouteNames[$item[2]]),
                ];
            }
        }

        return $result;
    }

    private function collectCurrentSectionUsages(string $viewsDir): array
    {
        $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($viewsDir));
        $result = [];

        foreach ($iterator as $file) {
            if (! $file->isFile()) {
                continue;
            }
            if (! str_ends_with($file->getPathname(), '.blade.php')) {
                continue;
            }

            $contents = (string) file_get_contents($file->getPathname());
            if (! preg_match('/\'currentSection\'\\s*=>\\s*\'([^\']+)\'/', $contents, $match)) {
                continue;
            }
            $relative = str_replace($viewsDir.'/', '', $file->getPathname());
            $relative = str_replace('.blade.php', '', $relative);
            $relative = str_replace('/', '.', $relative);
            $result[$match[1]][] = $relative;
        }

        ksort($result);

        return $result;
    }

    private function viewClass(string $path): string
    {
        if (str_contains($path, '/components/')) {
            return 'Komponente';
        }
        if (str_contains($path, '/mail/')) {
            return 'Mail';
        }
        if (str_contains($path, '/tests/')) {
            return 'Test';
        }
        if (str_contains($path, '/layouts/')) {
            return 'Layout';
        }

        return 'Weitere';
    }

    /**
     * @param  array<int, array<string, mixed>>  $routes
     * @param  array<string, array<int, string>>  $composerBindings
     * @param  array<int, array<string, mixed>>  $menuItems
     */
    private function renderRouteDoc(array $routes, array $composerBindings, array $menuItems, string $generatedAt): string
    {
        $out = [
            '# Route-Kartographie (automatisch generiert)',
            'Stand: '.$generatedAt,
            '',
            '## 1) Routen',
            '| Ebene | Oberfläche | Methode | URI | Route-Name | Berechtigung | View | Middleware | Composer | Action |',
            '| --- | --- | --- | --- | --- | --- | --- | --- | --- | --- |',
        ];

        foreach ($routes as $route) {
            $permissions = $route['permissions'] === [] ? '—' : implode(', ', $route['permissions']);
            $view = $route['view'] ?? '—';
            $composer = '—';
            if ($route['view'] !== null && isset($composerBindings[$route['view']])) {
                $composer = implode('<br>', $composerBindings[$route['view']]);
            }

            $out[] = sprintf(
                '| %s | %s | %s | %s | %s | %s | %s | %s | %s | %s |',
                $route['scope'],
                $route['surface'],
                $route['methods'],
                $route['uri'],
                $route['name'] ?: '—',
                $permissions,
                $view,
                implode(', ', $route['middleware']) ?: '—',
                $composer,
                $route['action']
            );
        }

        $out[] = '';
        $out[] = '## 2) Menü-Pfade';
        $out[] = '| Kontext | Label | Route | Berechtigungen | Auf Route vorhanden |';
        $out[] = '| --- | --- | --- | --- | --- |';

        foreach ($menuItems as $menu) {
            $source = (string) ($menu['source'] ?? 'nav');
            $context = $source === 'settings' ? 'Settings' : 'Hauptnavigation';
            $out[] = sprintf(
                '| %s | %s | %s | %s | %s |',
                $context,
                $menu['label'],
                $menu['route'] ?: '—',
                implode(', ', $menu['permissions']) ?: '—',
                $menu['exists'] ? 'Ja' : 'Nein'
            );
        }

        return implode(PHP_EOL, $out).PHP_EOL;
    }

    /**
     * @param  array<string, array<string, mixed>>  $routeViews
     * @param  array<string, string>  $routeViewPaths
     * @param  array<string, string>  $views
     * @param  array<string, array<int, string>>  $composerBindings
     * @param  array<string, array<int, string>>  $currentSections
     */
    private function renderViewDoc(array $routeViews, array $routeViewPaths, array $views, array $composerBindings, array $currentSections, string $generatedAt): string
    {
        $out = [
            '# View-Kartographie (Blade)',
            'Stand: '.$generatedAt,
            '',
            '## 1) Geroutete Views',
            '| View | Route | URI | Datei | currentSection | Composer |',
            '| --- | --- | --- | --- | --- | --- |',
        ];

        ksort($routeViews);
        foreach ($routeViews as $view => $route) {
            $composer = '—';
            if (isset($composerBindings[$view])) {
                $composer = implode('<br>', $composerBindings[$view]);
            }

            $sections = [];
            foreach ($currentSections as $section => $viewsForSection) {
                if (in_array($view, $viewsForSection, true)) {
                    $sections[] = $section;
                }
            }

            $out[] = sprintf(
                '| %s | %s | %s | %s | %s | %s |',
                $view,
                $route['name'] ?: '—',
                $route['uri'],
                $routeViewPaths[$view] ?? '—',
                implode(', ', $sections) ?: '—',
                $composer
            );
        }

        $out[] = '';
        $out[] = '## 2) Nicht geroutete Views';
        $out[] = '| Klasse | View | Datei | currentSection |';
        $out[] = '| --- | --- | --- | --- |';
        foreach ($views as $view => $path) {
            if (isset($routeViews[$view])) {
                continue;
            }
            $sections = [];
            foreach ($currentSections as $section => $viewsForSection) {
                if (in_array($view, $viewsForSection, true)) {
                    $sections[] = $section;
                }
            }

            $out[] = sprintf(
                '| %s | %s | %s | %s |',
                $this->viewClass($path),
                $view,
                $path,
                implode(', ', $sections) ?: '—'
            );
        }

        return implode(PHP_EOL, $out).PHP_EOL;
    }

    /**
     * @param  array<int, array<string, mixed>>  $menuItems
     * @param  array<string, array<string, mixed>>  $roleCoverage
     */
    private function renderMenuRoleMatrix(array $menuItems, array $roleCoverage): string
    {
        $out = [
            '# Menü-Rollen-Matrix (automatisch generiert)',
            'Stand: '.date('Y-m-d H:i:s'),
            '',
            '## 1) Sichtbarkeit von Navigationseinträgen je Rolle',
            '| Rolle | Label | Route | Berechtigungen | Route vorhanden | Sichtbar |',
            '| --- | --- | --- | --- | --- | --- |',
        ];

        usort($menuItems, static function (array $left, array $right): int {
            return strcmp((string) ($left['label'] ?? ''), (string) ($right['label'] ?? ''));
        });

        foreach ($menuItems as $menuItem) {
            $route = (string) ($menuItem['route'] ?? '');
            $permissions = (array) ($menuItem['permissions'] ?? []);
            $visibleRoles = [];
            foreach ($roleCoverage as $role => $coverage) {
                $rolePermissions = array_fill_keys((array) ($coverage['permissions'] ?? []), true);
                if (isset($rolePermissions['*'])) {
                    $visibleRoles[] = $role;

                    continue;
                }

                foreach ($permissions as $permission) {
                    if (isset($rolePermissions[$permission])) {
                        $visibleRoles[] = $role;
                        break;
                    }
                }
            }

            sort($visibleRoles, SORT_STRING);
            $out[] = sprintf(
                '| %s | %s | %s | %s | %s | %s |',
                implode(', ', $visibleRoles) ?: '—',
                $menuItem['label'] ?? '—',
                $route ?: '—',
                implode(', ', $permissions) ?: '—',
                ($menuItem['exists'] ?? false) ? 'Ja' : 'Nein',
                implode(', ', $visibleRoles) !== '' ? 'Ja' : 'Nein'
            );
        }

        $out[] = '';
        $out[] = '## 2) Zugriff ohne Berechtigung (Menü ohne expliziten Schutz)';
        $unprotected = array_filter($menuItems, static fn (array $item): bool => (array) ($item['permissions'] ?? []) === []);
        if ($unprotected === []) {
            $out[] = '| Status | Anzahl |';
            $out[] = '| --- | ---: |';
            $out[] = '| Keine | 0 |';

            return implode(PHP_EOL, $out).PHP_EOL;
        }

        $out[] = '| Label | Route |';
        $out[] = '| --- | --- |';
        foreach ($unprotected as $menuItem) {
            $out[] = sprintf('| %s | %s |', (string) ($menuItem['label'] ?? '—'), (string) ($menuItem['route'] ?? '—'));
        }

        return implode(PHP_EOL, $out).PHP_EOL;
    }

    /**
     * @param  array<int, array<string, mixed>>  $permissionByRoute
     * @param  array<string, array<string, mixed>>  $roleCoverage
     * @param  array<int, array<string, mixed>>  $menuItems
     */
    private function renderRouteVisibilityMatrix(array $permissionByRoute, array $roleCoverage, array $menuItems, string $generatedAt): string
    {
        $menuByRoute = [];
        foreach ($menuItems as $menuItem) {
            $route = (string) ($menuItem['route'] ?? '');
            if ($route === '') {
                continue;
            }
            $menuByRoute[] = [
                'route' => $route,
                'label' => (string) ($menuItem['label'] ?? $route),
            ];
        }

        $rows = [];
        foreach ($permissionByRoute as $entry) {
            $routeName = (string) ($entry['route_name'] ?? '—');
            $uri = (string) ($entry['uri'] ?? '—');
            $permissions = (array) ($entry['permissions'] ?? []);
            $surface = (string) ($entry['surface'] ?? '—');

            $menuLabel = '';
            $menuMatchLength = 0;
            foreach ($menuByRoute as $menuItem) {
                if (! is_array($menuItem)) {
                    continue;
                }

                $menuRoute = (string) ($menuItem['route'] ?? '');
                if ($menuRoute === '') {
                    continue;
                }

                if ($routeName === $menuRoute || str_starts_with($routeName, $menuRoute.'.')) {
                    $menuRouteLength = strlen($menuRoute);
                    if ($menuRouteLength > $menuMatchLength) {
                        $menuMatchLength = $menuRouteLength;
                        $menuLabel = (string) ($menuItem['label'] ?? '');
                    }
                }
            }

            $visibleRoles = [];
            foreach ($roleCoverage as $role => $coverage) {
                $routesForRole = array_fill_keys((array) ($coverage['routes'] ?? []), true);
                if (isset($routesForRole[$routeName])) {
                    $visibleRoles[] = $role;
                }
            }
            sort($visibleRoles, SORT_STRING);

            $rows[] = [
                'surface' => $surface,
                'uri' => $uri,
                'route' => $routeName,
                'permissions' => $permissions,
                'unguarded' => $permissions === [] ? 'Ja' : 'Nein',
                'roles' => $visibleRoles,
                'menu_label' => $menuLabel,
            ];
        }

        usort($rows, static function (array $left, array $right): int {
            return [$left['surface'], $left['uri'], $left['route']] <=> [$right['surface'], $right['uri'], $right['route']];
        });

        $out = [
            '# Routen-Sichtbarkeitsmatrix (Routen zu Rollen und Menüs)',
            'Stand: '.$generatedAt,
            '',
            '## 1) Sichtbarkeit je Route',
            '| Oberfläche | URI | Route | Berechtigungen | Ungeschützt | Rollen | Menü |',
            '| --- | --- | --- | --- | --- | --- | --- |',
        ];

        foreach ($rows as $row) {
            $out[] = sprintf(
                '| %s | %s | %s | %s | %s | %s | %s |',
                $row['surface'],
                $row['uri'],
                $row['route'],
                implode(', ', $row['permissions']) ?: '—',
                $row['unguarded'],
                implode(', ', $row['roles']) ?: '—',
                $row['menu_label'] === '' ? '—' : $row['menu_label']
            );
        }

        return implode(PHP_EOL, $out).PHP_EOL;
    }

    /**
     * @param  array<int, array<string, mixed>>  $permissionByRoute
     * @param  array<string, array<string, mixed>>  $roleCoverage
     * @param  array<string, mixed>  $identity
     * @param  array<int, array<string, mixed>>  $menuItems
     * @param  array<int, array<string, mixed>>  $routes
     */
    private function renderPermissionDoc(
        array $permissionByRoute,
        array $roleCoverage,
        array $identity,
        array $menuItems,
        array $routes,
        string $generatedAt
    ): string {
        $permissionCounts = [];
        foreach ($permissionByRoute as $entry) {
            $routePerms = $entry['permissions'];
            if ($routePerms === []) {
                continue;
            }
            foreach ($routePerms as $permission) {
                $permissionCounts[$permission] = ($permissionCounts[$permission] ?? 0) + 1;
            }
        }

        $unguardedRouteCount = count(array_filter($permissionByRoute, static fn (array $entry): bool => $entry['permissions'] === []));
        $routeNamesBySurface = $this->groupRouteNamesBySurface($permissionByRoute);

        $out = [
            '# Berechtigung & Route Coverage',
            'Stand: '.$generatedAt,
            '',
            '## 1) Berechtigungen aus Routing',
            '| Berechtigung | Label | Routen-Anzahl | Beispiele |',
            '| --- | --- | ---: | --- |',
        ];

        ksort($permissionCounts);
        foreach ($permissionCounts as $permission => $count) {
            $label = (string) ($identity['permissions'][$permission]['label'] ?? $permission);
            $examples = [];
            foreach ($permissionByRoute as $entry) {
                if (! in_array($permission, $entry['permissions'], true)) {
                    continue;
                }
                $examples[] = $entry['route_name'];
            }
            $out[] = sprintf('| %s | %s | %d | %s |', $permission, $label, $count, implode('<br>', $examples));
        }

        $out[] = '';
        $out[] = '| Typ | Gesamt-Routen | Bemerkung |';
        $out[] = '| --- | ---: | --- |';
        $out[] = sprintf('| Rollen-geschützt | %d | Nur Routen mit mindestens einem can:-Middleware Eintrag |', count($permissionByRoute) - $unguardedRouteCount);
        $out[] = sprintf('| Ungefiltert | %d | Werden in dieser Auswertung nicht als rollenabhängig bewertet |', $unguardedRouteCount);

        $out[] = '';
        $out[] = '## 2) Rollenreichweite';
        $out[] = '| Rolle | Label | Sichtbare Routen |';
        $out[] = '| --- | --- | ---: |';

        foreach ($roleCoverage as $role => $coverage) {
            $out[] = sprintf('| %s | %s | %d |', $role, $coverage['label'], $coverage['route_count']);
        }

        $out[] = '';
        $out[] = '## 3) Rollen und Menüsichtbarkeit';
        $out[] = '| Rolle | Sichtbare Menüpunkte |';
        $out[] = '| --- | --- |';
        foreach ($roleCoverage as $role => $coverage) {
            $menu = [];
            $rolePerms = array_flip($coverage['permissions']);
            foreach ($menuItems as $menuItem) {
                if ($menuItem['permissions'] === []) {
                    continue;
                }
                if (isset($rolePerms['*'])) {
                    $menu[] = $menuItem['label'];

                    continue;
                }
                foreach ($menuItem['permissions'] as $permission) {
                    if (isset($rolePerms[$permission])) {
                        $menu[] = $menuItem['label'];
                        break;
                    }
                }
            }
            $out[] = sprintf('| %s | %s |', $role, implode(', ', $menu));
        }

        $out[] = '';
        $out[] = '## 4) Oberfläche / Routenverteilung';
        $out[] = '| Oberfläche | Anzahl |';
        $out[] = '| --- | ---: |';
        foreach ($routeNamesBySurface as $surface => $count) {
            $out[] = sprintf('| %s | %d |', $surface, $count);
        }

        return implode(PHP_EOL, $out).PHP_EOL;
    }

    /**
     * @param  array<int, array<string, mixed>>  $routes
     * @param array{
     *   duplicate_names: array<string, array<int, array<string, mixed>>,
     *   duplicate_uri_methods: array<string, array<int, array<string, mixed>>
     * } $routeDuplicates
     * @param array{
     *   declared_count: int,
     *   used_count: int,
     *   unused_permissions: array<int, string>,
     *   unknown_permissions: array<int, string>,
     *   permissions_by_routes: array<string, int>,
     *   used_permissions: array<int, string>
     * } $permissionDrift
     * @param  array<string, array<int, array<string, mixed>>>  $menuHealth
     * @param  array<string, array<int, string>>  $orphanViews
     * @param  array<string, int>  $viewUsage
     * @param  array<string, array<int, string>>  $multiRouteViews
     * @param  array<int, array<int, string>>  $duplicateViewSignatures
     * @param  array<string, array<string, mixed>>  $personaCoverage
     * @param  array<string, int>  $moduleSurface
     */
    /**
     * @param  array<int, array<string, mixed>>  $permissionByRoute
     * @param  array<int, array<string, mixed>>  $routes
     * @param array{
     *   duplicate_names: array<string, array<int, array<string, mixed>>,
     *   duplicate_uri_methods: array<string, array<int, array<string, mixed>>
     * } $routeDuplicates
     * @param array{
     *   declared_count: int,
     *   used_count: int,
     *   unused_permissions: array<int, string>,
     *   unknown_permissions: array<int, string>,
     *   permissions_by_routes: array<string, int>,
     *   used_permissions: array<int, string>
     * } $permissionDrift
     * @param  array<string, array<int, array<string, mixed>>>  $menuHealth
     * @param  array<string, array<string, mixed>>  $roleCoverage
     * @param  array<int, array<string, mixed>>  $menuItems
     * @param  array<string, array<int, string>>  $orphanViews
     * @param  array<string, array<string, mixed>>  $personaCoverage
     * @param  array<string, mixed>  $identity
     */
    private function renderReorganisationRoadmap(
        array $permissionByRoute,
        array $routes,
        array $routeDuplicates,
        array $permissionDrift,
        array $menuHealth,
        array $roleCoverage,
        array $menuItems,
        array $orphanViews,
        array $personaCoverage,
        array $identity,
        string $generatedAt
    ): string {
        $identityRoles = array_map(static fn (string $role): string => strtolower($role), array_keys((array) ($identity['roles'] ?? [])));

        $totalRoutes = count($routes);
        $totalMenuItems = count($menuItems);
        $unguardedRoutes = count(array_filter($permissionByRoute, static fn (array $route): bool => $route['permissions'] === []));

        $orphanCount = 0;
        $orphanCritical = 0;
        foreach ($orphanViews as $category => $views) {
            $count = count($views);
            $orphanCount += $count;
            $isReusableCategory = str_starts_with((string) $category, 'Komponente')
                || str_starts_with((string) $category, 'Partial')
                || str_starts_with((string) $category, 'Mail');
            if (! $isReusableCategory) {
                $orphanCritical += $count;
            }
        }

        $menuHealthCounts = [
            'missing_routes' => count($menuHealth['missing_routes']),
            'menu_without_permissions' => count($menuHealth['menu_without_permissions']),
            'dead_items' => count($menuHealth['dead_items']),
        ];

        $routeDuplicateCount = count($routeDuplicates['duplicate_names']) + count($routeDuplicates['duplicate_uri_methods']);
        $gates = [
            [
                'id' => 'Gate A',
                'name' => 'Routing- und Berechtigungsintegrität',
                'status' => ($routeDuplicateCount === 0 && $permissionDrift['unused_permissions'] === [] && $permissionDrift['unknown_permissions'] === []) ? 'erfüllt' : 'offen',
                'owner' => 'Architektur + Backend',
                'risk' => $routeDuplicateCount !== 0 ? 'Route- oder Namensduplikate' : (($permissionDrift['unused_permissions'] !== [] || $permissionDrift['unknown_permissions'] !== []) ? 'Permission Drift' : 'keine'),
            ],
            [
                'id' => 'Gate B',
                'name' => 'Rollen- und Persona-Konsistenz',
                'status' => in_array('leiter', $identityRoles, true) ? 'erfüllt' : 'risikobehaftet',
                'owner' => 'Product Owner + Identity',
                'risk' => in_array('leiter', $identityRoles, true) ? 'keine' : 'Leiter wird aktuell über die `admin`-Rolle abgebildet',
            ],
            [
                'id' => 'Gate C',
                'name' => 'Navigation ohne tote Verweise',
                'status' => ($menuHealthCounts['missing_routes'] === 0 && $menuHealthCounts['dead_items'] === 0) ? 'erfüllt' : 'risikobehaftet',
                'owner' => 'Frontend',
                'risk' => ($menuHealthCounts['missing_routes'] === 0 && $menuHealthCounts['dead_items'] === 0) ? 'keine' : 'Menüeinträge ohne wirksames Route-Ziel',
            ],
            [
                'id' => 'Gate D',
                'name' => 'Komplette View-Lifecycle',
                'status' => $orphanCount === 0 ? 'erfüllt' : 'offen',
                'owner' => 'Frontend + QA',
                'risk' => $orphanCount === 0 ? 'keine' : sprintf('87+ nicht geroutete Views, %d kritisch als potenzieller Altbestand', $orphanCritical),
            ],
            [
                'id' => 'Gate E',
                'name' => 'Viewport- und Interaktionskonformität',
                'status' => 'offen',
                'owner' => 'UX + Frontend',
                'risk' => 'Automatisierte Breakpoint- und Accessibility-Prüfung noch nicht als Pipeline-Schritt vorhanden',
            ],
        ];

        $surfaceSummary = $this->collectModuleSurfaceSummary($routes);

        $out = [
            '# Systemreorganisation-Roadmap',
            'Stand: '.$generatedAt,
            '',
            '## 0) Überblick',
            '',
            sprintf('- Erfasste Routen: `%d`', $totalRoutes),
            sprintf('- Erfasste Menüeinträge: `%d`', $totalMenuItems),
            sprintf('- Ungeschützte Routen: `%d`', $unguardedRoutes),
            sprintf('- Ungeroutete Views: `%d`', $orphanCount),
            '',
            '## 1) Qualitätsgates',
            '',
            '| Gate | Name | Status | Besitzer | Risiko |',
            '| --- | --- | --- | --- | --- |',
        ];

        foreach ($gates as $gate) {
            $out[] = sprintf('| %s | %s | %s | %s | %s |', $gate['id'], $gate['name'], $gate['status'], $gate['owner'], $gate['risk']);
        }

        $out[] = '';
        $out[] = '## 2) Rollenmodell Mitarbeiter / Leiter / Admin';
        $out[] = '';
        $out[] = '| Persona | Zugeordnete Rollen | Sichtbare Routen | Status |';
        $out[] = '| --- | --- | ---: | --- |';
        foreach ($personaCoverage as $entry) {
            $status = 'offen';
            if ($entry['persona'] === 'Leiter') {
                $status = in_array('leiter', $identityRoles, true) ? 'erfüllt' : 'risikobehaftet';
            } elseif (! empty($entry['resolved'])) {
                $status = 'erfüllt';
            }

            $out[] = sprintf(
                '| %s | %s | %d | %s |',
                $entry['persona'],
                implode(', ', $entry['roles']) ?: '—',
                $entry['route_count'],
                $status
            );
        }

        $out[] = '';
        $out[] = '## 3) Modul- und Oberflächenübersicht';
        $out[] = '';
        $out[] = '| Modulfläche | Routen-Anzahl |';
        $out[] = '| --- | ---: |';
        foreach ($surfaceSummary as $module => $count) {
            $out[] = sprintf('| %s | %d |', $module, $count);
        }

        $out[] = '';
        $out[] = '## 4) Navigation und View-Lifecycle';
        $out[] = '';
        foreach ($menuHealthCounts as $key => $count) {
            $label = match ($key) {
                'missing_routes' => 'Menüpunkte ohne bestehende Route',
                'menu_without_permissions' => 'Menüpunkte ohne explizite Berechtigung',
                default => 'Menüpunkte mit ungültigem Ziel',
            };
            $out[] = sprintf('- %s: `%d`', $label, $count);
        }

        $out[] = '';
        $out[] = '| Kategorie | Anzahl | Beispiel |';
        $out[] = '| --- | ---: | --- |';
        foreach ($orphanViews as $category => $entries) {
            $out[] = sprintf('| %s | %d | %s |', $category, count($entries), $entries[0] ?? '—');
        }

        $out[] = '';
        $out[] = '## 5) Umsetzungspakete';
        $out[] = '';
        $out[] = '| Paket | Ziel | Owner | Ergebniskriterium |';
        $out[] = '| --- | --- | --- | --- |';
        $out[] = '| RP-1 | Leitungsrolle als eigene Persona-Rolle einführen oder fachlich dokumentieren | Product Owner, Identity-Verwaltung | Jede Persona hat eindeutige, dokumentierte Rechtekette im Menü |';
        $out[] = '| UI-1 | Viewport-/Keyboard/ARIA-Check einführen | Frontend + QA | 360, 768, 1280 Viewports ohne horizontales Overflow und funktionale Navigation |';
        $out[] = '| FE-1 | Nicht geroutete Views klassifizieren und bereinigen | Frontend + Architektur | Alte Views sind in den Kategorien Produktiv / Wiederverwendung / Archiv mit Ticket verifiziert |';
        $out[] = '| OP-1 | Reorganisation Audit in Release-Prozess | DevOps + Team | Jede Freigabe enthält neuen Lauf von `system-kartographie-gen.php` + diff Review |';

        $out[] = '';
        $out[] = '## 6) Sofort-Monitoring vor Deployment';
        $out[] = '';
        $out[] = '- Sichtbarkeit je Rolle in `docs/SYSTEM_PERMISSION_MATRIX.md` prüfen';
        $out[] = '- Vollständigkeit Route-Menu-Role in `docs/SYSTEM_ROUTE_VISIBILITY_MATRIX.md` prüfen';
        $out[] = '- Navigation und ungeroutete Views in `docs/SYSTEM_AUDIT_REPORT.md` prüfen und bei offenen Punkten Tickets anlegen';

        return implode(PHP_EOL, $out).PHP_EOL;
    }

    private function renderAuditReport(
        array $routes,
        array $routeDuplicates,
        array $permissionDrift,
        array $menuHealth,
        array $orphanViews,
        array $viewUsage,
        array $multiRouteViews,
        array $duplicateViewSignatures,
        array $personaCoverage,
        array $moduleSurface,
        string $generatedAt
    ): string {
        $out = [
            '# System Audit Report',
            'Stand: '.$generatedAt,
            '',
            '## 1) Qualitäts-Gate Status',
            '| Kriterium | Status |',
            '| --- | --- |',
            sprintf('| Routen insgesamt | %d |', count($routes)),
            sprintf('| Route-Duplikate (Namen) | %d |', count($routeDuplicates['duplicate_names'])),
            sprintf('| Route-Duplikate (Method+URI) | %d |', count($routeDuplicates['duplicate_uri_methods'])),
            sprintf('| Menüpunkte ohne bestehende Route | %d |', count($menuHealth['missing_routes'])),
            sprintf('| Berechtigungen insgesamt | %d |', $permissionDrift['declared_count']),
            sprintf('| Genutzte Berechtigungen | %d |', $permissionDrift['used_count']),
            sprintf('| Unbenutzte Berechtigungen | %d |', count($permissionDrift['unused_permissions'])),
            sprintf('| Unbekannte Route-Berechtigungen | %d |', count($permissionDrift['unknown_permissions'])),
            '',
            '## 2) Route-Duplikate (kritisch)',
            '| Typ | Schlüssel | Menge | Beispiele |',
            '| --- | --- | ---: | --- |',
        ];

        foreach ($routeDuplicates['duplicate_names'] as $name => $rows) {
            $samples = [];
            foreach ($rows as $row) {
                $samples[] = ($row['uri'] ?? '').' ['.($row['surface'] ?? '').' '.($row['methods'] ?? '').']';
            }
            $out[] = sprintf('| Name | `%s` | %d | %s |', $name, count($rows), implode('<br>', $samples));
        }

        foreach ($routeDuplicates['duplicate_uri_methods'] as $signature => $rows) {
            $samples = [];
            foreach ($rows as $row) {
                $samples[] = ($row['name'] ?: '—');
            }
            $out[] = sprintf('| Methode+URI | `%s` | %d | %s |', $signature, count($rows), implode('<br>', $samples));
        }

        if ($routeDuplicates['duplicate_names'] === [] && $routeDuplicates['duplicate_uri_methods'] === []) {
            $out[] = '| — | — | 0 | Keine kritischen Duplikate |';
        }

        $out[] = '';
        $out[] = '## 3) Berechtigungs-Diskrepanz';
        $out[] = '| Art | Wert |';
        $out[] = '| --- | --- |';
        $out[] = sprintf('| Unbenutzte Berechtigungen | %d |', count($permissionDrift['unused_permissions']));
        if ($permissionDrift['unused_permissions'] !== []) {
            $out[] = sprintf('| Unbenutzte Berechtigungen (Liste) | %s |', implode('<br>', $permissionDrift['unused_permissions']));
        }
        $out[] = sprintf('| Unbekannte Berechtigungen in Routen | %d |', count($permissionDrift['unknown_permissions']));
        if ($permissionDrift['unknown_permissions'] !== []) {
            $out[] = sprintf('| Unbekannte Berechtigungen (Details) | %s |', implode('<br>', $permissionDrift['unknown_permissions']));
        }

        $out[] = '';
        $out[] = '## 4) Modul- und Surface-Zuordnung';
        $out[] = '| Bereich | Routen-Anzahl |';
        $out[] = '| --- | ---: |';
        foreach ($moduleSurface as $label => $count) {
            $out[] = sprintf('| %s | %d |', $label, $count);
        }

        $out[] = '';
        $out[] = '## 5) Navigation-Integritätscheck';
        $out[] = '| Typ | Count | Einträge |';
        $out[] = '| --- | ---: | --- |';
        $out[] = sprintf('| Menüpunkte mit fehlender Route | %d | %s |', count($menuHealth['missing_routes']), implode('<br>', $menuHealth['missing_routes']));
        $out[] = sprintf('| Menüpunkte ohne explizite Berechtigung | %d | %s |', count($menuHealth['menu_without_permissions']), implode('<br>', $menuHealth['menu_without_permissions']));
        $out[] = sprintf('| Menüpunkte mit leerer Route | %d | %s |', count($menuHealth['dead_items']), implode('<br>', $menuHealth['dead_items']));

        $out[] = '';
        $out[] = '## 6) Ungeroutete Views (potenzieller Bereinigungsbereich)';
        $out[] = '| Kategorie | Anzahl | Beispiele |';
        $out[] = '| --- | ---: | --- |';
        foreach ($orphanViews as $category => $entries) {
            $out[] = sprintf('| %s | %d | %s |', $category, count($entries), implode('<br>', $entries));
        }

        if ($orphanViews === []) {
            $out[] = '| Keine | 0 | Keine ungerouteten Views gefunden |';
        }

        $out[] = '';
        $out[] = '## 7) View-Nutzungsintensität (referenzgestützt)';
        $out[] = '| View | Eingebunden durch |';
        $out[] = '| --- | ---: |';
        arsort($viewUsage);
        foreach ($viewUsage as $view => $count) {
            $out[] = sprintf('| %s | %d |', $view, $count);
        }
        if ($viewUsage === []) {
            $out[] = '| Keine | 0 |';
        }

        if ($multiRouteViews !== []) {
            $out[] = '';
            $out[] = '## 8) Mehrfachverwendung von Views';
            $out[] = '| View | Verwendete Routen |';
            $out[] = '| --- | --- |';
            foreach ($multiRouteViews as $view => $routes) {
                $out[] = sprintf('| %s | %s |', $view, implode('<br>', $routes));
            }
        } else {
            $out[] = '';
            $out[] = '## 8) Mehrfachverwendung von Views';
            $out[] = '| Ergebnis | Wert |';
            $out[] = '| --- | --- |';
            $out[] = '| keine Mehrfach-Nutzung gefunden | 0 |';
        }

        if ($duplicateViewSignatures !== []) {
            $out[] = '';
            $out[] = '## 9) Vollständig identische View-Dateien';
            $out[] = '| Anzahl | Views |';
            $out[] = '| ---: | --- |';
            foreach ($duplicateViewSignatures as $views) {
                $out[] = sprintf('| %d | %s |', count($views), implode('<br>', $views));
            }
        } else {
            $out[] = '';
            $out[] = '## 9) Vollständig identische View-Dateien';
            $out[] = '| Ergebnis | Wert |';
            $out[] = '| --- | --- |';
            $out[] = '| keine exakten Duplikate gefunden | 0 |';
        }

        $out[] = '';
        $out[] = '## 10) Rollenmodell für Mitarbeiter / Leiter / Admin';
        $out[] = '| Persona | Rollen | Sichtbare Routen | Sichtbare Menüeinträge |';
        $out[] = '| --- | --- | ---: | --- |';
        foreach ($personaCoverage as $entry) {
            $out[] = sprintf(
                '| %s | %s | %d | %s |',
                $entry['persona'],
                implode(', ', $entry['roles']) ?: 'Nicht konfiguriert',
                $entry['route_count'],
                implode(', ', $entry['menu_items']) ?: '—'
            );
        }

        return implode(PHP_EOL, $out).PHP_EOL;
    }

    /**
     * @param  array<int, array<string, mixed>>  $permissionByRoute
     * @return array<string, int>
     */
    private function groupRouteNamesBySurface(array $permissionByRoute): array
    {
        $result = [];
        foreach ($permissionByRoute as $entry) {
            $surface = (string) $entry['surface'];
            $result[$surface] = ($result[$surface] ?? 0) + 1;
        }

        return $result;
    }

    /**
     * @param  array<int, array<string, mixed>>  $routes
     * @param  array<string, array<string, mixed>>  $routeViews
     * @param  array<string, string>  $views
     */
    private function reportSummary(array $routes, array $routeViews, array $views): void
    {
        echo sprintf(
            'GENERATED: %d routes, %d geroutete views, %d total views'.PHP_EOL,
            count($routes),
            count($routeViews),
            count($views)
        );
    }
}

$options = SystemKartographieGenerator::bootstrapOptions($argv);
$generator = new SystemKartographieGenerator;
$generator->run($options);
