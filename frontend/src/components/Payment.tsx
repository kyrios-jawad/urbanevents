import { api } from "../api/client";

const handlePayment = async (orderId: string) => {
  try {
    const response = await api.post('/payment/create-session', {
      order: orderId
    });

    const { session_data, checkout_url } = response.data;
    
    // Create and submit form for PayFast
    const form = document.createElement('form');
    form.method = 'POST';
    form.action = checkout_url;

    // Add all required fields
    Object.entries(session_data).forEach(([key, value]) => {
      const input = document.createElement('input');
      input.type = 'hidden';
      input.name = key;
      input.value = value as string;
      form.appendChild(input);
    });

    document.body.appendChild(form);
    form.submit();
  } catch (error) {
    console.error('Payment creation failed:', error);
    // Handle error appropriately
  }
}; 