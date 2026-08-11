<?php

declare(strict_types=1);

namespace App\Checkout\ApplePay\UI\Http\Controller;

use App\Checkout\ApplePay\Application\Command\PlaceExpressOrder;
use App\Checkout\ApplePay\Application\Query\GetShippingOptions;
use App\Checkout\ApplePay\Domain\Exception\ApplePayException;
use App\Checkout\ApplePay\Domain\Exception\ApplePayNotConfigured;
use App\Checkout\ApplePay\Domain\Port\ApplePayMerchantValidator;
use App\Checkout\ApplePay\Domain\ValueObject\ShippingOption;
use App\Checkout\ApplePay\UI\Http\Exception\MalformedApplePayPayload;
use App\Checkout\ApplePay\UI\Http\Request\ApplePayPayload;
use App\Shared\Application\Bus\CommandBus;
use App\Shared\Application\Bus\QueryBus;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

final readonly class ApplePayController
{
    public function __construct(
        private CommandBus $commandBus,
        private QueryBus $queryBus,
        private ApplePayPayload $payload,
        private ApplePayMerchantValidator $merchantValidator,
        private UrlGeneratorInterface $urlGenerator,
    ) {
    }

    #[Route('/sklep/apple-pay/merchant-session', name: 'oma_apple_pay_merchant_session', methods: ['POST'])]
    public function merchantSession(Request $request): JsonResponse
    {
        try {
            $session = $this->merchantValidator->validate(
                $this->payload->validationUrl($request),
                $request->getHost(),
            );
        } catch (ApplePayNotConfigured $exception) {
            return $this->error($exception->getMessage(), Response::HTTP_SERVICE_UNAVAILABLE);
        } catch (MalformedApplePayPayload|ApplePayException $exception) {
            return $this->error($exception->getMessage(), Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        return new JsonResponse($session);
    }

    #[Route('/sklep/apple-pay/shipping-methods', name: 'oma_apple_pay_shipping_methods', methods: ['POST'])]
    public function shippingMethods(Request $request): JsonResponse
    {
        try {
            $options = $this->queryBus->ask(new GetShippingOptions($this->payload->shippingContact($request)));
        } catch (MalformedApplePayPayload|ApplePayException $exception) {
            return $this->error($exception->getMessage(), Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        return new JsonResponse(
            [
            'shippingMethods' => array_map(
                static fn (ShippingOption $option): array => [
                    'identifier' => $option->code,
                    'label' => $option->label,
                    'detail' => $option->detail ?? '',
                    'amount' => number_format($option->amount / 100, 2, '.', ''),
                ],
                $options,
            ),
            ],
        );
    }

    #[Route('/sklep/apple-pay/order', name: 'oma_apple_pay_order', methods: ['POST'])]
    public function order(Request $request): JsonResponse
    {
        try {
            $order = $this->commandBus->dispatchAndReturn(
                new PlaceExpressOrder(
                    $this->payload->contact($request),
                    $this->payload->shippingMethodCode($request),
                    $this->payload->paymentToken($request),
                ),
            );
        } catch (MalformedApplePayPayload|ApplePayException $exception) {
            return $this->error($exception->getMessage(), Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        return new JsonResponse(
            [
            'tokenValue' => $order->tokenValue,
            'total' => $order->total,
            'redirectUrl' => $this->urlGenerator->generate(
                'sylius_shop_order_pay',
                ['tokenValue' => $order->tokenValue],
            ),
            ],
        );
    }

    private function error(string $message, int $status): JsonResponse
    {
        return new JsonResponse(['error' => $message], $status);
    }
}
