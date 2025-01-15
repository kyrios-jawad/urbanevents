namespace HiEvents\Services;

use HiEvents\DomainObjects\OrderDomainObject;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Log;
use App\Models\Order;

class PaymentService
{
    private array $config;

    public function __construct()
    {
        $this->config = config('payfast');
    }

    public function createPaymentSession(OrderDomainObject $order): array
    {
        $basketId = 'ORDER-' . strtoupper(Str::random(10));
        
        $token = $this->getAccessToken([
            'basket_id' => $basketId,
            'amount' => $order->total_amount,
            'currency' => $this->config['currency']
        ]);

        return [
            'session_data' => [
                'MERCHANT_ID' => $this->config['merchant_id'],
                'TOKEN' => $token,
                'BASKET_ID' => $basketId,
                'TXNAMT' => $order->total_amount,
                'CURRENCY_CODE' => $this->config['currency'],
                'ORDER_DATE' => now()->format('Y-m-d H:i:s'),
                'SUCCESS_URL' => config('app.frontend_url') . "/payment/success/{$order->id}",
                'FAILURE_URL' => config('app.frontend_url') . "/payment/failed/{$order->id}",
                'CUSTOMER_EMAIL_ADDRESS' => $order->customer_email,
                'CUSTOMER_MOBILE_NO' => $order->customer_phone ?? '',
            ],
            'checkout_url' => $this->config['api_url'] . 'Transaction/PostTransaction',
            'status' => 'created'
        ];
    }

    private function getAccessToken(array $data): ?string
    {
        $response = Http::asForm()->post(
            $this->config['api_url'] . 'Transaction/GetAccessToken',
            [
                'MERCHANT_ID' => $this->config['merchant_id'],
                'SECURED_KEY' => $this->config['secured_key'],
                'BASKET_ID' => $data['basket_id'],
                'TXNAMT' => $data['amount'],
                'CURRENCY_CODE' => $data['currency']
            ]
        );

        $payload = $response->json();
        return $payload['ACCESS_TOKEN'] ?? null;
    }

    public function verifyPayment(string $orderId): array
    {
        $order = Order::findOrFail($orderId);
        
        if (!$order->basket_id) {
            throw new \Exception('No payment session found for this order');
        }

        try {
            $response = Http::post($this->config['api_url'] . 'Transaction/VerifyTransaction', [
                'MERCHANT_ID' => $this->config['merchant_id'],
                'SECURED_KEY' => $this->config['secured_key'],
                'BASKET_ID' => $order->basket_id,
            ]);

            if ($response->successful()) {
                $data = $response->json();
                return [
                    'verified' => $data['err_code'] === '000',
                    'message' => $data['err_msg'] ?? 'Verification completed',
                    'transaction_id' => $data['transaction_id'] ?? null
                ];
            }

            throw new \Exception('Verification request failed');
        } catch (\Exception $e) {
            Log::error('Payment verification failed', [
                'order_id' => $orderId,
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }
}