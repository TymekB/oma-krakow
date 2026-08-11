<?php

declare(strict_types=1);

namespace App\Notification\UI\Http\Controller;

use App\Notification\NotificationEvent;
use App\Notification\NotificationSettings;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

final class NotificationSettingsController extends AbstractController
{
    private const CSRF_TOKEN_ID = 'oma_notification_settings';

    public function __construct(private readonly NotificationSettings $settings)
    {
    }

    #[Route('/admin/zdarzenia', name: 'oma_admin_notification_settings', methods: ['GET', 'POST'])]
    #[IsGranted('ROLE_ADMINISTRATION_ACCESS')]
    public function __invoke(Request $request): Response
    {
        if ($request->isMethod('POST')) {
            if (!$this->isCsrfTokenValid(self::CSRF_TOKEN_ID, (string) $request->request->get('_token'))) {
                $this->addFlash('error', 'oma.notification.invalid_token');

                return $this->redirectToRoute('oma_admin_notification_settings');
            }

            /**
             * @var list<string> $enabled
             */
            $enabled = $request->request->all('events');
            $this->settings->save($enabled);

            $this->addFlash('success', 'oma.notification.saved');

            return $this->redirectToRoute('oma_admin_notification_settings');
        }

        return $this->render(
            'admin/notification_settings.html.twig',
            [
            'events' => NotificationEvent::cases(),
            'enabled' => $this->settings->all(),
            'csrf_token_id' => self::CSRF_TOKEN_ID,
            ],
        );
    }
}
