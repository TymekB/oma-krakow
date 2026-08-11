<?php

declare(strict_types=1);

namespace App\SiteContent\UI\Http\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

final class SiteEditPageController extends AbstractController
{
    #[Route('/admin/site/edit', name: 'oma_admin_site_edit', methods: ['GET'])]
    #[IsGranted('ROLE_ADMINISTRATION_ACCESS')]
    public function __invoke(): Response
    {
        return $this->render('admin/site_edit.html.twig');
    }
}
