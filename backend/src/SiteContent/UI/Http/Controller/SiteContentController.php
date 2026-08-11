<?php

declare(strict_types=1);

namespace App\SiteContent\UI\Http\Controller;

use App\Shared\Application\Bus\CommandBus;
use App\Shared\Application\Bus\QueryBus;
use App\SiteContent\Application\Command\ChangeContent;
use App\SiteContent\Application\Command\ChangeContentBatch;
use App\SiteContent\Application\Command\RestoreRevision;
use App\SiteContent\Application\Query\GetContentMap;
use App\SiteContent\Application\Query\GetRevisionHistory;
use App\SiteContent\Application\View\RevisionView;
use App\SiteContent\Domain\Exception\RevisionNotFound;
use App\SiteContent\Domain\Exception\SiteContentException;
use App\SiteContent\UI\Http\Exception\MalformedPayload;
use App\SiteContent\UI\Http\Request\ContentPayload;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final readonly class SiteContentController
{
    private const ADMIN_ROLE = 'ROLE_ADMINISTRATION_ACCESS';

    public function __construct(
        private CommandBus $commandBus,
        private QueryBus $queryBus,
        private ContentPayload $payload,
        private Security $security,
    ) {
    }

    #[Route('/landing-content.json', name: 'oma_landing_content', methods: ['GET'])]
    public function content(): JsonResponse
    {
        return new JsonResponse(['values' => (object) $this->queryBus->ask(new GetContentMap())]);
    }

    #[Route('/admin/landing-content/session', name: 'oma_landing_content_session', methods: ['GET'])]
    public function session(): JsonResponse
    {
        return new JsonResponse(['canEdit' => $this->security->isGranted(self::ADMIN_ROLE)]);
    }

    #[Route(
        '/admin/landing-content/{key}',
        name: 'oma_landing_content_save',
        requirements: ['key' => '[a-z0-9._-]{1,128}'],
        methods: ['PUT'],
    )]
    public function save(string $key, Request $request): JsonResponse
    {
        if (!$this->security->isGranted(self::ADMIN_ROLE)) {
            return $this->forbidden();
        }

        try {
            $value = $this->payload->singleValue($request);
            $this->commandBus->dispatch(new ChangeContent($key, $value));
        } catch (MalformedPayload|SiteContentException $exception) {
            return $this->unprocessable($exception->getMessage());
        }

        return new JsonResponse(['key' => $key, 'value' => $value]);
    }

    #[Route('/admin/landing-content', name: 'oma_landing_content_save_all', methods: ['PUT'])]
    public function saveAll(Request $request): JsonResponse
    {
        if (!$this->security->isGranted(self::ADMIN_ROLE)) {
            return $this->forbidden();
        }

        try {
            $values = $this->payload->values($request);
            $this->commandBus->dispatch(new ChangeContentBatch($values, $this->currentAuthor()));
        } catch (MalformedPayload|SiteContentException $exception) {
            return $this->unprocessable($exception->getMessage());
        }

        return new JsonResponse(['saved' => count($values)]);
    }

    #[Route('/admin/site/revisions', name: 'oma_landing_revisions', methods: ['GET'])]
    public function revisions(): JsonResponse
    {
        if (!$this->security->isGranted(self::ADMIN_ROLE)) {
            return $this->forbidden();
        }

        $revisions = array_map(
            static fn (RevisionView $view): array => [
                'id' => $view->id,
                'author' => $view->author,
                'reason' => $view->reason,
                'changedKeys' => $view->changedKeys,
                'createdAt' => $view->createdAt->format('Y-m-d H:i'),
                'isCurrent' => $view->isCurrent,
            ],
            $this->queryBus->ask(new GetRevisionHistory()),
        );

        return new JsonResponse(['revisions' => $revisions]);
    }

    #[Route(
        '/admin/site/revisions/{id}/restore',
        name: 'oma_landing_revision_restore',
        requirements: ['id' => '\d+'],
        methods: ['POST'],
    )]
    public function restore(int $id): JsonResponse
    {
        if (!$this->security->isGranted(self::ADMIN_ROLE)) {
            return $this->forbidden();
        }

        try {
            $restored = $this->commandBus->dispatchAndReturn(new RestoreRevision($id, $this->currentAuthor()));
        } catch (RevisionNotFound $exception) {
            return new JsonResponse(['error' => $exception->getMessage()], Response::HTTP_NOT_FOUND);
        } catch (SiteContentException $exception) {
            return $this->unprocessable($exception->getMessage());
        }

        if (0 === $restored) {
            return new JsonResponse(
                [
                'restored' => 0,
                'message' => 'Ta wersja jest identyczna z obecną treścią strony.',
                ]
            );
        }

        return new JsonResponse(['restored' => $restored]);
    }

    private function currentAuthor(): string
    {
        return $this->security->getUser()?->getUserIdentifier() ?? 'system';
    }

    private function forbidden(): JsonResponse
    {
        return new JsonResponse(['error' => 'Brak uprawnień.'], Response::HTTP_FORBIDDEN);
    }

    private function unprocessable(string $message): JsonResponse
    {
        return new JsonResponse(['error' => $message], Response::HTTP_UNPROCESSABLE_ENTITY);
    }
}
