<?php

declare(strict_types=1);

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\HeaderUtils;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

final class ManualController extends AbstractController
{
    private const DOWNLOAD_NAME = 'oma-instrukcja-panelu.pdf';

    public function __construct(
        #[Autowire('%kernel.project_dir%/docs/instrukcja-panelu.pdf')]
        private readonly string $manualPath,
    ) {
    }

    #[Route('/admin/manual', name: 'oma_admin_manual', methods: ['GET'])]
    #[IsGranted('ROLE_ADMINISTRATION_ACCESS')]
    public function __invoke(): Response
    {
        return $this->render('admin/manual.html.twig');
    }

    #[Route('/admin/manual/pdf', name: 'oma_admin_manual_pdf', methods: ['GET'])]
    #[IsGranted('ROLE_ADMINISTRATION_ACCESS')]
    public function pdf(): Response
    {
        if (!is_file($this->manualPath)) {
            throw new NotFoundHttpException('Instrukcja nie została jeszcze wygenerowana.');
        }

        $response = new BinaryFileResponse($this->manualPath);
        $response->setContentDisposition(HeaderUtils::DISPOSITION_INLINE, self::DOWNLOAD_NAME);

        return $response;
    }
}
