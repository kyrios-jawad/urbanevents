import React, { useState } from 'react';

export const PayFastCheckout: React.FC<PayFastCheckoutProps> = ({ orderId, amount }) => {
    const [error, setError] = useState<string | null>(null);
    const [isLoading, setIsLoading] = useState(false);

    const handlePayment = async () => {
        setIsLoading(true);
        setError(null);
        
        try {
            // 1. Get payment session from your backend
            const response = await api.post('/payment/create-session', { 
                order_id: orderId 
            });

            const { session_data, checkout_url, status } = response.data;

            // 2. Create and submit form to PayFast
            const form = document.createElement('form');
            form.method = 'POST';
            form.action = checkout_url;

            // Add all required fields from session_data
            Object.entries(session_data).forEach(([key, value]) => {
                const input = document.createElement('input');
                input.type = 'hidden';
                input.name = key;
                input.value = value as string;
                form.appendChild(input);
            });

            // 3. Submit form to redirect to PayFast
            document.body.appendChild(form);
            form.submit();
        } catch (error) {
            setError('Payment initialization failed. Please try again.');
            console.error('Payment error:', error);
        } finally {
            setIsLoading(false);
        }
    };

    return (
        <div>
            {error && <div className="text-red-500 mb-4">{error}</div>}
            <button 
                onClick={handlePayment}
                disabled={isLoading}
                className={`w-full px-4 py-2 ${isLoading ? 'opacity-50' : ''}`}
            >
                {isLoading ? 'Processing...' : `Pay ${amount} PKR`}
            </button>
        </div>
    );
}; 