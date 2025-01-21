<?php

namespace HiEvents\Services\Handlers\Order\Payment\GoFastPay;

use Brick\Math\Exception\MathException;
use Brick\Math\Exception\NumberFormatException;
use Brick\Math\Exception\RoundingNecessaryException;
use Brick\Money\Exception\UnknownCurrencyException;
use Brick\Money\Money;
use HiEvents\DomainObjects\OrderItemDomainObject;
use HiEvents\DomainObjects\StripePaymentDomainObject;
use HiEvents\Exceptions\UnauthorizedException;
use HiEvents\Repository\Eloquent\Value\Relationship;
use HiEvents\Repository\Interfaces\AccountRepositoryInterface;
use HiEvents\Repository\Interfaces\OrderRepositoryInterface;
use HiEvents\Services\Handlers\Order\Payment\Stripe\GoFastPay\GoFastPayPaymentIntentPublicDTO;
use HiEvents\Services\Infrastructure\Session\CheckoutSessionManagementService;
use Illuminate\Config\Repository;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use InvalidArgumentException;
use Exception;

readonly class CreatePaymentIntentHandler
{
    public function __construct(
        private OrderRepositoryInterface           $orderRepository,
        private CheckoutSessionManagementService   $sessionIdentifierService,
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
     * @return GoFastPayPaymentIntentPublicDTO
     * @throws InvalidArgumentException|Exception
     */
    public function getAccessToken(
        string $merchantId,
        string $securedKey,
        string $basketId,
        string $currencyCode,
        float $transAmount
    ): GoFastPayPaymentIntentPublicDTO {
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
                Log::error('Payment Gateway Error', [
                    'status' => $response->status(),
                    'response' => $response->json(),
                    'merchant_id' => $merchantId,
                    'basket_id' => $basketId
                ]);

                throw new Exception('Failed to get access token. Status: ' . $response->status());
            }

            $data = $response->json();

            if (empty($data['ACCESS_TOKEN'])) {
                throw new Exception('Access token not found in response');
            }

            return $data;

        } catch (Exception $e) {
            Log::error('Payment Gateway Exception', [
                'message' => $e->getMessage(),
                'merchant_id' => $merchantId,
                'basket_id' => $basketId
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
    public function handle(string $orderShortId): GoFastPayPaymentIntentPublicDTO
    {
        $order = $this->orderRepository
            ->loadRelation(new Relationship(OrderItemDomainObject::class))
            ->loadRelation(new Relationship(StripePaymentDomainObject::class, name: 'stripe_payment'))
            ->findByShortId($orderShortId);

//        if (!$order || !$this->sessionIdentifierService->verifySession($order->getSessionId())) {
//            throw new UnauthorizedException(__('Sorry, we could not verify your session. Please create a new order.'));
//        }
        print_r($order);
        $amount = Money::of($order->getTotalGross(), $order->getCurrency())->getMinorAmount()->toInt();
        $gofastpay_application_fee = ($amount * (int)config('app.gofastpay_application_fee_percent')) / 100;
        $platform_fee = ($amount * (int)config('app.gofastpay_application_fee_percent')) / 100;
        $total_amount = $amount + $gofastpay_application_fee + $platform_fee;
        $paymentIntent = $this->getAccessToken(config('services.gofastpay.merchant_id'),config('services.gofastpay.secured_key'),$orderShortId,$order->getCurrency(),$total_amount);

        return $paymentIntent;
    }
}
