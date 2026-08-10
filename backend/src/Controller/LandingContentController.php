<?php

declare(strict_types=1);

namespace App\Controller;

use App\Repository\LandingContentRepository;
use App\Repository\LandingRevisionRepository;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final readonly class LandingContentController
{
    private const ADMIN_ROLE = 'ROLE_ADMINISTRATION_ACCESS';

    private const MAX_VALUE_LENGTH = 5000;

    public function __construct(
        private LandingContentRepository $repository,
        private LandingRevisionRepository $revisionRepository,
        private Security $security,
    ) {
    }

    #[Route('/landing-content.json', name: 'oma_landing_content', methods: ['GET'])]
    public function content(): JsonResponse
    {
        return new JsonResponse(['values' => (object) $this->repository->findAllAsMap()]);
    }

    #[Route('/admin/landing-content/session', name: 'oma_landing_content_session', methods: ['GET'])]
    public function session(): JsonResponse
    {
        return new JsonResponse(['canEdit' => $this->security->isGranted(self::ADMIN_ROLE)]);
    }

    #[Route('/admin/landing-content/{key}', name: 'oma_landing_content_save', methods: ['PUT'], requirements: ['key' => '[a-z0-9._-]{1,128}'])]
    public function save(string $key, Request $request): JsonResponse
    {
        if (!$this->security->isGranted(self::ADMIN_ROLE)) {
            return new JsonResponse(['error' => 'Brak uprawnień.'], Response::HTTP_FORBIDDEN);
        }

        /**
         * @var array{value?: mixed} $payload
         */
        $payload = json_decode($request->getContent(), true) ?? [];
        $value = $payload['value'] ?? null;

        if (!is_string($value) || mb_strlen($value) > self::MAX_VALUE_LENGTH) {
            return new JsonResponse(['error' => 'Nieprawidłowa treść.'], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $content = $this->repository->upsert($key, $value);

        return new JsonResponse(['key' => $content->getKey(), 'value' => $content->getValue()]);
    }

    #[Route('/admin/landing-content', name: 'oma_landing_content_save_all', methods: ['PUT'])]
    public function saveAll(Request $request): JsonResponse
    {
        if (!$this->security->isGranted(self::ADMIN_ROLE)) {
            return new JsonResponse(['error' => 'Brak uprawnień.'], Response::HTTP_FORBIDDEN);
        }

        /**
         * @var array{values?: mixed} $payload
         */
        $payload = json_decode($request->getContent(), true) ?? [];
        $values = $payload['values'] ?? null;

        if (!is_array($values)) {
            return new JsonResponse(['error' => 'Nieprawidłowa treść.'], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        foreach ($values as $key => $value) {
            if (!is_string($key) || 1 !== preg_match('/^[a-z0-9._-]{1,128}$/', $key)) {
                return new JsonResponse(['error' => sprintf('Nieprawidłowy klucz: %s', (string) $key)], Response::HTTP_UNPROCESSABLE_ENTITY);
            }

            if (!is_string($value) || mb_strlen($value) > self::MAX_VALUE_LENGTH) {
                return new JsonResponse(['error' => sprintf('Nieprawidłowa treść dla klucza: %s', $key)], Response::HTTP_UNPROCESSABLE_ENTITY);
            }
        }

        foreach ($values as $key => $value) {
            $this->repository->upsert($key, $value);
        }

        $this->revisionRepository->record(
            $this->repository->findAllAsMap(),
            array_map(strval(...), array_keys($values)),
            $this->currentAuthor(),
            'edit',
        );

        return new JsonResponse(['saved' => count($values)]);
    }

    #[Route('/admin/site/revisions', name: 'oma_landing_revisions', methods: ['GET'])]
    public function revisions(): JsonResponse
    {
        if (!$this->security->isGranted(self::ADMIN_ROLE)) {
            return new JsonResponse(['error' => 'Brak uprawnień.'], Response::HTTP_FORBIDDEN);
        }

        $revisions = array_map(
            static fn ($revision) => [
                'id' => $revision->getId(),
                'author' => $revision->getAuthor(),
                'reason' => $revision->getReason(),
                'changedKeys' => $revision->getChangedKeys(),
                'createdAt' => $revision->getCreatedAt()->format('Y-m-d H:i'),
            ],
            $this->revisionRepository->findLatest(),
        );

        return new JsonResponse(['revisions' => $revisions]);
    }

    #[Route('/admin/site/revisions/{id}/restore', name: 'oma_landing_revision_restore', methods: ['POST'], requirements: ['id' => '\\d+'])]
    public function restore(int $id): JsonResponse
    {
        if (!$this->security->isGranted(self::ADMIN_ROLE)) {
            return new JsonResponse(['error' => 'Brak uprawnień.'], Response::HTTP_FORBIDDEN);
        }

        $revision = $this->revisionRepository->find($id);

        if (null === $revision) {
            return new JsonResponse(['error' => 'Nie znaleziono wersji.'], Response::HTTP_NOT_FOUND);
        }

        $restored = $this->repository->replaceAll($revision->getSnapshot());

        if ([] === $restored) {
            return new JsonResponse([
                'restored' => 0,
                'message' => 'Ta wersja jest identyczna z obecną treścią strony.',
            ]);
        }

        $this->revisionRepository->record(
            $revision->getSnapshot(),
            $restored,
            $this->currentAuthor(),
            'restore',
        );

        return new JsonResponse(['restored' => count($restored)]);
    }

    private function currentAuthor(): string
    {
        $user = $this->security->getUser();

        return null === $user ? 'system' : $user->getUserIdentifier();
    }
}
