<?php

namespace HiEvents\Services\Handlers\Order\Payment\GoFastPay;

use Brick\Math\Exception\MathException;
use Brick\Math\Exception\NumberFormatException;
use Brick\Math\Exception\RoundingNecessaryException;
use Brick\Money\Exception\UnknownCurrencyException;
use Brick\Money\Money;
use HiEvents\DomainObjects\Generated\StripePaymentDomainObjectAbstract;
use HiEvents\DomainObjects\OrderItemDomainObject;
use HiEvents\DomainObjects\StripePaymentDomainObject;
use HiEvents\Exceptions\Stripe\CreatePaymentIntentFailedException;
use HiEvents\Exceptions\UnauthorizedException;
use HiEvents\Repository\Eloquent\Value\Relationship;
use HiEvents\Repository\Interfaces\AccountRepositoryInterface;
use HiEvents\Repository\Interfaces\OrderRepositoryInterface;
use HiEvents\Repository\Interfaces\StripePaymentsRepositoryInterface;
use HiEvents\Services\Domain\Payment\Stripe\DTOs\CreatePaymentIntentRequestDTO;
use HiEvents\Services\Domain\Payment\Stripe\DTOs\CreatePaymentIntentResponseDTO;
use HiEvents\Services\Domain\Payment\Stripe\StripePaymentIntentCreationService;
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
        readonly private Repository                $config,
        private AccountRepositoryInterface         $accountRepository
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
     * @return string
     * @throws InvalidArgumentException|Exception
     */
    public function getAccessToken(
        string $merchantId,
        string $securedKey,
        string $basketId,
        string $currencyCode,
        float $transAmount
    ): string {
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
            print_r($data);

            if (empty($data['ACCESS_TOKEN'])) {
                throw new Exception('Access token not found in response');
            }

            return $data['ACCESS_TOKEN'];

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
     * @throws CreatePaymentIntentFailedException
     */
    public function handle(string $orderShortId): CreatePaymentIntentResponseDTO
    {
        $order = $this->orderRepository
            ->loadRelation(new Relationship(OrderItemDomainObject::class))
            ->loadRelation(new Relationship(StripePaymentDomainObject::class, name: 'stripe_payment'))
            ->findByShortId($orderShortId);

//        if (!$order || !$this->sessionIdentifierService->verifySession($order->getSessionId())) {
//            throw new UnauthorizedException(__('Sorry, we could not verify your session. Please create a new order.'));
//        }

        $account = $this->accountRepository->findByEventId($order->getEventId());

        // If we already have a Stripe session then re-fetch the client secret
//        if ($order->getStripePayment() !== null) {
//            return new CreatePaymentIntentResponseDTO(
//                paymentIntentId: $order->getStripePayment()->getPaymentIntentId(),
//                clientSecret: $this->stripePaymentService->retrievePaymentIntentClientSecret(
//                    $order->getStripePayment()->getPaymentIntentId(),
//                    $account->getStripeAccountId()
//                ),
//                accountId: $account->getStripeAccountId(),
//            );
//        }

        $amount = Money::of($order->getTotalGross(), $order->getCurrency())->getMinorAmount()->toInt();
        $gofastpay_application_fee = ($amount * (int)config('app.gofastpay_application_fee_percent')) / 100;
        $platform_fee = ($amount * (int)config('app.gofastpay_application_fee_percent')) / 100;
        $total_amount = $amount + $gofastpay_application_fee + $platform_fee;
        echo $total_amount;
        $paymentIntent = $this->getAccessToken(config('services.gofastpay.merchant_id'),config('services.gofastpay.secured_key'),$orderShortId,$order->getCurrency(),$total_amount);
        print_r($paymentIntent);

//        $paymentIntent = $this->stripePaymentService->createPaymentIntent(CreatePaymentIntentRequestDTO::fromArray([
//            'amount' => Money::of($order->getTotalGross(), $order->getCurrency())->getMinorAmount()->toInt(),
//            'currencyCode' => $order->getCurrency(),
//            'account' => $account,
//            'order' => $order,
//        ]));

//        $this->stripePaymentsRepository->create([
//            StripePaymentDomainObjectAbstract::ORDER_ID => $order->getId(),
//            StripePaymentDomainObjectAbstract::PAYMENT_INTENT_ID => $paymentIntent->paymentIntentId,
//            StripePaymentDomainObjectAbstract::CONNECTED_ACCOUNT_ID => $account->getStripeAccountId(),
//        ]);

        return $paymentIntent;
    }
}
