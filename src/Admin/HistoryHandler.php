<?php

declare(strict_types=1);

namespace Contenir\Extension\Navigation\Admin;

use Contenir\Extension\Api\Context\ExtensionContextInterface;
use Contenir\Extension\Api\Handler\HandlerInterface;
use Contenir\Extension\Api\Handler\HandlerResult;
use Contenir\Extension\Api\Handler\ParentPage;
use Contenir\Extension\Navigation\Persistence\Menus;
use Contenir\Navigation\Exception\NotFoundException;
use Contenir\Navigation\Exception\StorageException;
use Contenir\Navigation\Menu\Revision;
use LogicException;
use Override;
use Psr\Container\ContainerExceptionInterface;
use Psr\Http\Message\ServerRequestInterface;

/**
 * A menu's revisions, newest first, each with when it was published, by
 * whom, its note, and a way to restore it into the draft.
 */
final class HistoryHandler implements HandlerInterface
{
    public const string NAME = 'history';

    /**
     * What the history shows of each revision.
     *
     * @param list<Revision> $revisions
     *
     * @return list<array{id: int, published: string, by: string|null, note: string|null, live: bool}>
     */
    private static function revisions(array $revisions, ?int $live, SiteTime $time): array
    {
        $shown = [];
        foreach ($revisions as $revision) {
            $shown[] = [
                'id'        => (int) $revision->id,
                'published' => $time->show($revision->created),
                'by'        => $revision->publishedBy?->name,
                'note'      => $revision->note,
                'live'      => $revision->id === $live,
            ];
        }

        return $shown;
    }

    /**
     * @throws StorageException            When the revisions can't be read.
     * @throws ContainerExceptionInterface When the host can't make one of its services.
     * @throws LogicException              When the host offers no site database.
     */
    #[Override]
    public function handle(ServerRequestInterface $request, ExtensionContextInterface $context): HandlerResult
    {
        $menus = Menus::of($context);

        try {
            $menu = $menus->repository->navigation((int) Input::of($request)->id('menu'));
        } catch (NotFoundException) {
            return HandlerResult::redirect(Urls::menu(null))->withMessage('warning', 'That menu no longer exists.');
        }

        return HandlerResult::template(
            'navigation/history',
            "{$menu->title} history",
            [
                'menu'      => ['id' => $menu->id, 'title' => $menu->title],
                'revisions' => self::revisions(
                    $menus->repository->revisions((int) $menu->id),
                    $menu->revisionId,
                    new SiteTime($context->clock()),
                ),
            ],
            parent: new ParentPage('Navigation', MenusHandler::NAME, ['menu' => (int) $menu->id]),
        );
    }
}
