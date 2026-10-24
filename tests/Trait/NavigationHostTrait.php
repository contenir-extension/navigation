<?php

declare(strict_types=1);

namespace Contenir\Extension\Navigation\Tests\Trait;

use Contenir\Extension\Api\Context\ExtensionContextInterface;
use Contenir\Extension\Api\Editor\Editor;
use Contenir\Extension\Api\Handler\HandlerInterface;
use Contenir\Extension\Api\Handler\HandlerResult;
use Contenir\Extension\Api\Record\LinkableSection;
use Contenir\Extension\Api\Record\RecordStatus;
use Contenir\Extension\Api\Record\Workflow;
use Contenir\Extension\Api\Testing\FrozenClock;
use Contenir\Extension\Api\Testing\InMemoryHost;
use Contenir\Extension\Navigation\NavigationExtension;
use Contenir\Extension\Navigation\Persistence\Menus;
use Contenir\Extension\Navigation\Persistence\SiteDatabase;
use Contenir\Navigation\ConfigProvider;
use Contenir\Navigation\Menu\Item;
use Contenir\Navigation\Menu\Navigation;
use DateTimeImmutable;
use Nyholm\Psr7\Factory\Psr17Factory;
use PDO;
use PhpDb\Adapter\Adapter;
use PhpDb\Adapter\AdapterInterface;
use PhpDb\Migration\MigrationRunner;
use PhpDb\Migration\MismatchStrategy;
use PhpDb\Sqlite;
use Psr\Http\Message\ResponseInterface;

use function count;

/**
 * The add-on loaded into an in-memory host over a fresh SQLite database with
 * the navigation tables, and a site with linkable pages and news. Each test
 * gets its own database, dropped with it. Call setUpHost() from setUp().
 */
trait NavigationHostTrait
{
    private AdapterInterface $database;

    private InMemoryHost $host;

    private Psr17Factory $http;

    /**
     * A handler's result for a request, as the host would answer it, run with
     * the add-on's context and no editor signed in.
     *
     * @param class-string<HandlerInterface> $handler
     * @param array<string, scalar>          $query
     * @param array<string, scalar>          $posted
     */
    private function answer(string $handler, array $query = [], ?array $posted = null): HandlerResult
    {
        $request = $this->http
            ->createServerRequest(null === $posted ? 'GET' : 'POST', '/admin/extension/navigation/x')
            ->withQueryParams($query);

        return (new $handler())->handle(
            null === $posted ? $request : $request->withParsedBody($posted),
            $this->context(),
        );
    }

    private function context(): ExtensionContextInterface
    {
        return $this->host->context(NavigationExtension::HANDLE);
    }

    /**
     * Adds an item to a menu's draft, returning it as saved.
     */
    private function item(int $menu, Item $item, ?int $parent = null, int $position = 0): Item
    {
        return $this->menus()->repository->saveItem($item->withPlacement($menu, $parent, $position));
    }

    /**
     * Adds a menu after the others, as Add menu does, returning its id.
     */
    private function menu(string $handle = 'header', string $title = 'Header'): int
    {
        $repository = $this->menus()->repository;

        return (int) $repository->saveNavigation(
            new Navigation($handle, $title, sequence: count($repository->navigations())),
        )->id;
    }

    private function menus(): Menus
    {
        return Menus::of($this->context());
    }

    /**
     * A request to one of the add-on's handlers, answered by the host for
     * someone holding $grants, signed in as $editor.
     *
     * @param array<string, scalar> $query
     * @param list<string>          $grants
     * @param array<string, scalar> $posted
     */
    private function request(
        string $handler,
        array $query = [],
        array $grants = [NavigationExtension::SECTION],
        ?array $posted = null,
        ?Editor $editor = null,
    ): ResponseInterface {
        $request = $this->http
            ->createServerRequest(null === $posted ? 'GET' : 'POST', "/admin/extension/navigation/{$handler}")
            ->withQueryParams($query);

        return $this->host->handle(null === $posted ? $request : $request->withParsedBody($posted), $grants, $editor);
    }

    /**
     * @param array<string, object> $services What else the host offers the add-on, by name
     */
    private function setUpHost(array $services = [], bool $records = true): void
    {
        $pdo = new PDO('sqlite::memory:');
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $driver         = new Sqlite\Pdo\Driver(connection: new Sqlite\Pdo\Connection($pdo));
        $this->database = new Adapter($driver, new Sqlite\AdapterPlatform($driver));
        $runner         = new MigrationRunner(
            $this->database,
            ConfigProvider::schemaPath(),
            ConfigProvider::SCHEMA_NAMESPACE,
            MismatchStrategy::Report,
            new Sqlite\Metadata\Source($this->database),
        );
        $runner->ensureMigrationsTable();
        $runner->runPending();

        $this->http = new Psr17Factory();
        $this->host = new InMemoryHost(
            $this->http,
            $this->http,
            new FrozenClock(new DateTimeImmutable('2026-10-23 09:00')),
        );
        if ($records) {
            $this->host->linkable(new LinkableSection(
                'page',
                'Page',
                creatable: true,
                workflows: [new Workflow('standard', 'Standard page')],
            ));
            $this->host->linkable(new LinkableSection('news', 'News article'));
            $this->host->seed('page', 1, [
                'title'  => 'About us',
                'path'   => '/about',
                'status' => RecordStatus::Published->value,
            ]);
            $this->host->seed('page', 2, [
                'title'  => 'Our team',
                'path'   => '/about/our-team',
                'status' => RecordStatus::Draft->value,
            ]);
            $this->host->seed('news', 7, [
                'title'  => 'Launch',
                'path'   => '/news/launch',
                'status' => RecordStatus::Archived->value,
            ]);
        }

        $this->host->boot(
            [NavigationExtension::HANDLE => new NavigationExtension()],
            [NavigationExtension::HANDLE],
            [SiteDatabase::SERVICE => $this->database] + $services,
        );
    }
}
