<?php

declare(strict_types=1);

namespace App\Controller;

use App\Repository\LandingContentRepository;
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
}
