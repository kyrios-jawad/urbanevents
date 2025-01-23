<?php

namespace HiEvents\Services\Handlers\Order\Payment\GoFastPay;

use Brick\Math\Exception\MathException;
use Brick\Math\Exception\NumberFormatException;
use Brick\Math\Exception\RoundingNecessaryException;
use Brick\Money\Exception\UnknownCurrencyException;
use Brick\Money\Money;
use Exception;
use HiEvents\DomainObjects\OrderItemDomainObject;
use HiEvents\DomainObjects\StripePaymentDomainObject;
use HiEvents\Exceptions\UnauthorizedException;
use HiEvents\Repository\Eloquent\Value\Relationship;
use HiEvents\Repository\Interfaces\OrderRepositoryInterface;
use HiEvents\Services\Infrastructure\Session\CheckoutSessionManagementService;
use Illuminate\Support\Facades\Http;
use Psr\Log\LoggerInterface;

readonly class CreatePaymentIntentHandler
{
    public function __construct(
        private OrderRepositoryInterface           $orderRepository,
        private CheckoutSessionManagementService   $sessionIdentifierService,
        readonly private LoggerInterface           $logger
    )
    {
    }


    /**
     * Get access token from payment gateway
     *
     * @param string $merchantId
     * @param string $securedKey
     * @param string $basketId
     * @param string $currencyCode
     * @param float $transAmount
     * @return array
     * @throws Exception
     */
    public function getAccessToken(
        string $merchantId,
        string $securedKey,
        string $basketId,
        string $currencyCode,
        float $transAmount
    ): array {
        try {
            $response = Http::asForm()
                ->withUserAgent('Laravel/PaymentGateway')
                ->post('https://ipguat.apps.net.pk/Ecommerce/api' . '/Transaction/GetAccessToken', [
                    'MERCHANT_ID' => $merchantId,
                    'SECURED_KEY' => $securedKey,
                    'TXNAMT' => $transAmount,
                    'BASKET_ID' => $basketId,
                    'Amount' => $transAmount,
                    'CURRENCY_CODE' => $currencyCode,
                ]);

            if (!$response->successful()) {
                $this->logger->error("Stripe payment intent creation failed: {$basketId}", [
                    'paymentIntentDTO' => $response->json(),
                ]);

                throw new Exception('Failed to get access token. Status: ' . $response->status());
            }

            $data = $response->json();

            if (empty($data['ACCESS_TOKEN'])) {
                throw new Exception('Access token not found in response');
            }

            return $data;
        } catch (Exception $e) {
            $this->logger->error("Stripe payment intent creation failed: {$basketId}", [
                'exception' => $e->getMessage(),
            ]);
            throw $e;
        }
    }

    /**
     * @throws MathException
     * @throws NumberFormatException
     * @throws RoundingNecessaryException
     * @throws UnknownCurrencyException
     */
    public function handle(string $orderShortId): array
    {
        $order = $this->orderRepository
            ->loadRelation(new Relationship(OrderItemDomainObject::class))
            ->loadRelation(new Relationship(StripePaymentDomainObject::class, name: 'stripe_payment'))
            ->findByShortId($orderShortId);

        if (!$order || !$this->sessionIdentifierService->verifySession($order->getSessionId())) {
            throw new UnauthorizedException(__('Sorry, we could not verify your session. Please create a new order.'));
        }

        return $this->getAccessToken('14833','rPcy4T7GQkSCFsHBLdn26s',$orderShortId,$order->getCurrency(),Money::of($order->getTotalGross(), $order->getCurrency())->getMinorAmount()->toInt());
    }
}
